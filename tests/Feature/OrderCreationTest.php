<?php

namespace Tests\Feature;

use App\Domain\Model\LicenseType;
use App\Domain\Model\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successfully_creates_pending_order(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType([
            'name' => LicenseType::YEARLY,
            'number_of_days' => 365,
            'price' => 500.0,
        ]);

        $payload = [
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'amount' => 500.0,
            'discount' => 0.1, // 10% discount
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response->assertStatus(202);
        $response->assertExactJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Order created successfully',
        ]);

        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'amount' => 500.0,
            'discount' => 0.1,
            'final_amount' => 450.0, // 500 * (1 - 0.1)
            'status' => Order::PENDING,
            'active' => true,
        ]);

        $order = Order::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($order);
        $this->assertNull($order->placed_at);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->cancelled_at);

        $items = json_decode($order->items, true);
        $this->assertIsArray($items);
        $this->assertSame($licenseType->id, $items[0]['id']);
        $this->assertSame(LicenseType::YEARLY, $items[0]['name']);
    }

    public function test_fails_when_tenant_id_missing(): void
    {
        $merchant = $this->createMerchant($this->createTenant());
        $licenseType = $this->createLicenseType();

        $response = $this->postJson('/api/orders', [
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'amount' => 100,
            'discount' => 0,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Le tenant est obligatoire',
        ]);
    }

    public function test_fails_when_tenant_id_does_not_exist(): void
    {
        $merchant = $this->createMerchant($this->createTenant());
        $licenseType = $this->createLicenseType();

        $response = $this->postJson('/api/orders', [
            'tenant_id' => Str::uuid()->toString(),
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'amount' => 100,
            'discount' => 0,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Tenant inconnu',
        ]);
    }

    public function test_fails_when_merchant_id_missing(): void
    {
        $tenant = $this->createTenant();
        $licenseType = $this->createLicenseType();

        $response = $this->postJson('/api/orders', [
            'tenant_id' => $tenant->id,
            'license_type_id' => $licenseType->id,
            'amount' => 100,
            'discount' => 0,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Le marchand est obligatoire',
        ]);
    }

    public function test_fails_when_merchant_id_does_not_exist(): void
    {
        $tenant = $this->createTenant();
        $licenseType = $this->createLicenseType();

        $response = $this->postJson('/api/orders', [
            'tenant_id' => $tenant->id,
            'merchant_id' => Str::uuid()->toString(),
            'license_type_id' => $licenseType->id,
            'amount' => 100,
            'discount' => 0,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Merchant inconnu',
        ]);
    }

    public function test_fails_when_license_type_id_missing(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);

        $response = $this->postJson('/api/orders', [
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'amount' => 100,
            'discount' => 0,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Le type de licence est obligatoire',
        ]);
    }

    public function test_fails_when_license_type_id_does_not_exist(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);

        $response = $this->postJson('/api/orders', [
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'license_type_id' => Str::uuid()->toString(),
            'amount' => 100,
            'discount' => 0,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Type de licence inconnu',
        ]);
    }

    public function test_fails_when_amount_missing_or_non_numeric(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();

        $responseMissing = $this->postJson('/api/orders', [
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'discount' => 0,
        ]);
        $responseMissing->assertStatus(202);
        $responseMissing->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Le montant est obligatoire',
        ]);

        $responseNonNumeric = $this->postJson('/api/orders', [
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'amount' => 'invalid-amount',
            'discount' => 0,
        ]);
        $responseNonNumeric->assertStatus(202);
        $responseNonNumeric->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Le montant est un nombre',
        ]);
    }

    public function test_fails_when_discount_missing_or_non_numeric(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();

        // Missing discount
        $responseMissing = $this->postJson('/api/orders', [
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'amount' => 100,
        ]);
        $responseMissing->assertStatus(202);
        $responseMissing->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'La reduction est obligatoire',
        ]);

        // Non-numeric discount
        $responseNonNumeric = $this->postJson('/api/orders', [
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'amount' => 100,
            'discount' => 'invalid-discount',
        ]);
        $responseNonNumeric->assertStatus(202);
        $responseNonNumeric->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'La reduction est un nombre',
        ]);
    }
}
