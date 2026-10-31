<?php

namespace Tests\Feature;

use App\Domain\Model\Order;
use App\Jobs\ProcessMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Http\Middleware\CheckToken;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_successfully_places_pending_order_and_dispatches_message(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();
        $order = $this->createOrder($tenant, $merchant, $licenseType, [
            'status' => Order::PENDING,
        ]);

        $response = $this->putJson("/api/orders/{$order->id}/placed-order");

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Order placed successfully',
        ]);

        $freshOrder = Order::find($order->id);
        $this->assertSame(Order::PLACED, $freshOrder->status);
        $this->assertNotNull($freshOrder->placed_at);

        Queue::assertPushed(ProcessMessages::class, function (ProcessMessages $job) use ($order) {
            $reflection = new \ReflectionClass($job);
            $exchangeProperty = $reflection->getProperty('exchangeName');
            $msgProperty = $reflection->getProperty('message');

            $expectedExchange = env('LICENSE_ORDER_PLACED_EXCHANGE', 'LICENSE_ORDER_PLACED_EXCHANGE');
            $this->assertSame($expectedExchange, $exchangeProperty->getValue($job));

            $payload = json_decode($msgProperty->getValue($job), true);
            $this->assertSame($order->id, $payload['id']);
            $this->assertSame(Order::PLACED, $payload['status']);

            return true;
        });
    }

    public function test_placing_non_existent_order_returns_failure(): void
    {
        $nonExistentId = Str::uuid()->toString();
        $response = $this->putJson("/api/orders/{$nonExistentId}/placed-order");

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Order not found',
        ]);
    }

    public function test_successfully_pays_order_and_dispatches_message(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();
        $order = $this->createOrder($tenant, $merchant, $licenseType, [
            'status' => Order::PLACED,
        ]);

        $response = $this->withoutMiddleware(CheckToken::class)
            ->putJson("/api/orders/{$order->id}/paid-order");

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Order placed successfully', // as implemented in ApiController
        ]);

        $freshOrder = Order::find($order->id);
        $this->assertSame(Order::COMPLETED, $freshOrder->status);
        $this->assertNotNull($freshOrder->paid_at);

        Queue::assertPushed(ProcessMessages::class, function (ProcessMessages $job) use ($order) {
            $reflection = new \ReflectionClass($job);
            $exchangeProperty = $reflection->getProperty('exchangeName');
            $msgProperty = $reflection->getProperty('message');

            $expectedExchange = env('LICENSE_ORDER_COMPLETED_EXCHANGE', 'LICENSE_ORDER_COMPLETED_EXCHANGE');
            $this->assertSame($expectedExchange, $exchangeProperty->getValue($job));

            $payload = json_decode($msgProperty->getValue($job), true);
            $this->assertSame($order->id, $payload['id']);
            $this->assertSame(Order::COMPLETED, $payload['status']);

            return true;
        });
    }

    public function test_paying_non_existent_order_returns_failure(): void
    {
        $nonExistentId = Str::uuid()->toString();
        $response = $this->withoutMiddleware(CheckToken::class)
            ->putJson("/api/orders/{$nonExistentId}/paid-order");

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Order not found',
        ]);
    }

    public function test_unauthenticated_paying_order_rejected_by_rabbitmq_middleware(): void
    {
        $orderId = Str::uuid()->toString();
        $response = $this->putJson("/api/orders/{$orderId}/paid-order");

        $response->assertStatus(401);
    }

    public function test_successfully_cancels_pending_order_and_dispatches_message(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();
        $order = $this->createOrder($tenant, $merchant, $licenseType, [
            'status' => Order::PENDING,
        ]);

        $response = $this->putJson("/api/orders/{$order->id}/cancelled-order");

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Order cancelled successfully',
        ]);

        $freshOrder = Order::find($order->id);
        $this->assertSame(Order::CANCELLED, $freshOrder->status);
        $this->assertNotNull($freshOrder->cancelled_at);

        Queue::assertPushed(ProcessMessages::class, function (ProcessMessages $job) use ($order) {
            $reflection = new \ReflectionClass($job);
            $exchangeProperty = $reflection->getProperty('exchangeName');
            $msgProperty = $reflection->getProperty('message');

            $expectedExchange = env('LICENSE_ORDER_CANCELLED_EXCHANGE', 'LICENSE_ORDER_CANCELLED_EXCHANGE');
            $this->assertSame($expectedExchange, $exchangeProperty->getValue($job));

            $payload = json_decode($msgProperty->getValue($job), true);
            $this->assertSame($order->id, $payload['id']);
            $this->assertSame(Order::CANCELLED, $payload['status']);

            return true;
        });
    }

    public function test_successfully_cancels_placed_order(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();
        $order = $this->createOrder($tenant, $merchant, $licenseType, [
            'status' => Order::PLACED,
        ]);

        $response = $this->putJson("/api/orders/{$order->id}/cancelled-order");

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Order cancelled successfully',
        ]);

        $freshOrder = Order::find($order->id);
        $this->assertSame(Order::CANCELLED, $freshOrder->status);
        $this->assertNotNull($freshOrder->cancelled_at);
    }

    public function test_cannot_cancel_completed_order(): void
    {
        Queue::fake();

        $tenant = $this->createTenant();
        $merchant = $this->createMerchant($tenant);
        $licenseType = $this->createLicenseType();
        $order = $this->createOrder($tenant, $merchant, $licenseType, [
            'status' => Order::COMPLETED,
        ]);

        $response = $this->putJson("/api/orders/{$order->id}/cancelled-order");

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Completed order can not be cancelled',
        ]);

        $freshOrder = Order::find($order->id);
        $this->assertSame(Order::COMPLETED, $freshOrder->status);
        $this->assertNull($freshOrder->cancelled_at);

        Queue::assertNotPushed(ProcessMessages::class);
    }

    public function test_cancelling_non_existent_order_returns_failure(): void
    {
        $nonExistentId = Str::uuid()->toString();
        $response = $this->putJson("/api/orders/{$nonExistentId}/cancelled-order");

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Order not found',
        ]);
    }
}
