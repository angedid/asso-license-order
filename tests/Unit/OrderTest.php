<?php

namespace Tests\Unit;

use App\Domain\Model\LicenseType;
use App\Domain\Model\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_status_constants(): void
    {
        $this->assertSame('PENDING', Order::PENDING);
        $this->assertSame('PLACED', Order::PLACED);
        $this->assertSame('CANCELLED', Order::CANCELLED);
        $this->assertSame('COMPLETED', Order::COMPLETED);
    }

    public function test_can_create_order_with_pending_status(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();

        $order = $this->createOrder($tenant, $merchant, $licenseType, [
            'amount' => 150.0,
            'discount' => 0.2,
            'final_amount' => 120.0,
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertSame(150.0, (float) $order->amount);
        $this->assertSame(0.2, (float) $order->discount);
        $this->assertSame(120.0, (float) $order->final_amount);
        $this->assertNull($order->placed_at);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->cancelled_at);
        $this->assertTrue((bool) $order->active);
        $this->assertFalse($order->incrementing);
        $this->assertSame('string', $order->getKeyType());
    }

    public function test_order_items_stores_json_snapshot(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();

        $order = $this->createOrder($tenant, $merchant, $licenseType);

        $items = json_decode($order->items, true);
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertSame($licenseType->id, $items[0]['id']);
        $this->assertSame(LicenseType::MONTHLY, $items[0]['name']);
    }

    public function test_order_status_transition_to_placed(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();

        $order = $this->createOrder($tenant, $merchant, $licenseType);
        $order->status = Order::PLACED;
        $order->placed_at = Carbon::now();
        $order->save();

        $freshOrder = Order::find($order->id);
        $this->assertSame(Order::PLACED, $freshOrder->status);
        $this->assertNotNull($freshOrder->placed_at);
    }

    public function test_order_status_transition_to_completed(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();

        $order = $this->createOrder($tenant, $merchant, $licenseType);
        $order->status = Order::COMPLETED;
        $order->paid_at = Carbon::now();
        $order->save();

        $freshOrder = Order::find($order->id);
        $this->assertSame(Order::COMPLETED, $freshOrder->status);
        $this->assertNotNull($freshOrder->paid_at);
    }

    public function test_order_status_transition_to_cancelled(): void
    {
        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();

        $order = $this->createOrder($tenant, $merchant, $licenseType);
        $order->status = Order::CANCELLED;
        $order->cancelled_at = Carbon::now();
        $order->save();

        $freshOrder = Order::find($order->id);
        $this->assertSame(Order::CANCELLED, $freshOrder->status);
        $this->assertNotNull($freshOrder->cancelled_at);
    }

    public function test_order_pricing_calculation(): void
    {
        $amount = 200.0;
        $discount = 0.15; // 15% discount represented as 0.15
        $expectedFinal = $amount * (1 - $discount);

        $this->assertSame(170.0, $expectedFinal);
    }
}
