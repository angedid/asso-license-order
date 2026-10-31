<?php

namespace Tests\Feature;

use App\Domain\Model\LicenseType;
use App\Domain\Model\Order;
use App\Jobs\ProcessMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Http\Middleware\CheckToken;
use Tests\TestCase;

class OrderLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_order_lifecycle_from_provisioning_to_completion(): void
    {
        Queue::fake();

        // 1. Provision Tenant & Merchant
        $tenantId = Str::uuid()->toString();
        $merchantId = Str::uuid()->toString();

        $provisionPayload = [
            'data' => [
                'tenant' => [
                    'id' => $tenantId,
                    'name' => 'Sahara Trading Hub',
                    'city' => 'Garoua',
                    'location' => 'Plateau',
                    'region' => 'North',
                    'country' => 'Cameroon',
                    'phone_number' => '+237677001122',
                    'website' => 'https://saharatrading.cm',
                    'address' => 'Route de Poli',
                    'active' => true,
                    'taxpayer_number' => 'M990011223344X',
                    'number_trade_register' => 'RC/GAR/2026/B/55',
                    'network' => json_encode(['region' => 'central-africa']),
                ],
                'user' => [
                    'id' => $merchantId,
                    'tenant_id' => $tenantId,
                    'type' => 'MERCHANT_ADMINISTRATOR',
                    'full_name' => 'Ibrahim Ahmadou',
                    'username' => 'iahmadou_hub',
                    'email' => 'ibrahim@saharatrading.cm',
                    'phone_number' => '+237677001122',
                    'region' => 'North',
                    'country' => 'Cameroon',
                    'active' => true,
                    'network' => json_encode(['level' => 1]),
                ],
            ],
        ];

        $provisionResponse = $this->withoutMiddleware(CheckToken::class)
            ->postJson('/api/tenant-provisions', $provisionPayload);

        $provisionResponse->assertStatus(200);
        $provisionResponse->assertJson(['success' => 1, 'faillure' => 0]);

        // 2. Setup License Type (BIYEARLY)
        $licenseType = $this->createLicenseType([
            'name' => LicenseType::BIYEARLY,
            'number_of_days' => 180,
            'price' => 120000.0,
            'active' => true,
        ]);

        // 3. Create Order
        $orderPayload = [
            'tenant_id' => $tenantId,
            'merchant_id' => $merchantId,
            'license_type_id' => $licenseType->id,
            'amount' => 120000.0,
            'discount' => 0.05, // 5% discount
        ];

        $createResponse = $this->postJson('/api/orders', $orderPayload);
        $createResponse->assertStatus(202);
        $createResponse->assertJson(['success' => 1, 'faillure' => 0]);

        $order = Order::where('tenant_id', $tenantId)->first();
        $this->assertNotNull($order);
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertSame(114000.0, (float) $order->final_amount); // 120000 * (1 - 0.05)

        // 4. Place Order
        $placeResponse = $this->putJson("/api/orders/{$order->id}/placed-order");
        $placeResponse->assertStatus(200);
        $placeResponse->assertJson(['success' => 1, 'faillure' => 0]);

        $order->refresh();
        $this->assertSame(Order::PLACED, $order->status);
        $this->assertNotNull($order->placed_at);

        Queue::assertPushed(ProcessMessages::class, 1);

        // 5. Pay Order (Notification from Payment service)
        $payResponse = $this->withoutMiddleware(CheckToken::class)
            ->putJson("/api/orders/{$order->id}/paid-order");
        $payResponse->assertStatus(200);
        $payResponse->assertJson(['success' => 1, 'faillure' => 0]);

        $order->refresh();
        $this->assertSame(Order::COMPLETED, $order->status);
        $this->assertNotNull($order->paid_at);

        Queue::assertPushed(ProcessMessages::class, 2);

        // 6. Attempt to cancel completed order -> must be rejected
        $cancelResponse = $this->putJson("/api/orders/{$order->id}/cancelled-order");
        $cancelResponse->assertStatus(200);
        $cancelResponse->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Completed order can not be cancelled',
        ]);

        $order->refresh();
        $this->assertSame(Order::COMPLETED, $order->status);
        $this->assertNull($order->cancelled_at);

        // No cancellation message dispatched
        Queue::assertPushed(ProcessMessages::class, 2);
    }

    public function test_order_can_be_cancelled_before_completion(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();

        // Create order
        $createResponse = $this->postJson('/api/orders', [
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'amount' => 50000.0,
            'discount' => 0.0,
        ]);
        $createResponse->assertStatus(202);

        $order = Order::where('tenant_id', $tenant->id)->first();

        // Place order
        $this->putJson("/api/orders/{$order->id}/placed-order")->assertStatus(200);

        // Cancel order
        $cancelResponse = $this->putJson("/api/orders/{$order->id}/cancelled-order");
        $cancelResponse->assertStatus(200);
        $cancelResponse->assertJson(['success' => 1, 'faillure' => 0]);

        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertNull($order->paid_at);

        Queue::assertPushed(ProcessMessages::class, 2); // 1 placed, 1 cancelled
    }
}
