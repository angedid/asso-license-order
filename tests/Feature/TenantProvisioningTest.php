<?php

namespace Tests\Feature;

use App\Domain\Model\Merchant;
use App\Domain\Model\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_successfully_provisions_tenant_and_merchant(): void
    {
        $tenantId = Str::uuid()->toString();
        $userId = Str::uuid()->toString();

        $payload = [
            'data' => [
                'tenant' => [
                    'id' => $tenantId,
                    'name' => 'Atlantic Wholesalers',
                    'city' => 'Douala',
                    'location' => 'Akwa',
                    'region' => 'Littoral',
                    'country' => 'Cameroon',
                    'phone_number' => '+237699999999',
                    'website' => 'https://atlantic.example.com',
                    'address' => '45 Rue de la Joie',
                    'active' => true,
                    'taxpayer_number' => 'M010203040506P',
                    'number_trade_register' => 'RC/DLA/2026/A/1234',
                    'network' => json_encode(['cluster' => 'west-africa']),
                ],
                'user' => [
                    'id' => $userId,
                    'tenant_id' => $tenantId,
                    'type' => 'MERCHANT_ADMINISTRATOR',
                    'full_name' => 'Alice Admin',
                    'username' => 'alice_admin',
                    'email' => 'alice@example.com',
                    'phone_number' => '+237699999999',
                    'region' => 'Littoral',
                    'country' => 'Cameroon',
                    'active' => true,
                    'network' => json_encode(['role' => 'owner']),
                ],
            ],
        ];

        $response = $this->withoutMiddleware(\Laravel\Passport\Http\Middleware\CheckToken::class)
            ->postJson('/api/tenant-provisions', $payload);

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Tenant Provision saved successfully',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'name' => 'Atlantic Wholesalers',
            'owner' => $userId,
            'city' => 'Douala',
        ]);

        $this->assertDatabaseHas('merchants', [
            'id' => $userId,
            'tenant_id' => $tenantId,
            'type' => 'MERCHANT_ADMINISTRATOR',
            'full_name' => 'Alice Admin',
            'username' => 'alice_admin',
            'email' => 'alice@example.com',
        ]);
    }

    public function test_provisioning_failure_rolls_back_database(): void
    {
        $tenantId = Str::uuid()->toString();
        $userId = Str::uuid()->toString();

        // Deliberately missing required fields or causing an exception (e.g. invalid network json nullability)
        $payload = [
            'data' => [
                'tenant' => [
                    'id' => $tenantId,
                    'name' => null, // name is not nullable in tenants table
                    'city' => 'Douala',
                    'location' => 'Akwa',
                    'region' => 'Littoral',
                    'country' => 'Cameroon',
                    'phone_number' => '+237699999999',
                    'website' => 'https://atlantic.example.com',
                    'address' => '45 Rue de la Joie',
                    'active' => true,
                    'taxpayer_number' => 'M010203040506P',
                    'number_trade_register' => 'RC/DLA/2026/A/1234',
                    'network' => null,
                ],
                'user' => [
                    'id' => $userId,
                    'tenant_id' => $tenantId,
                    'type' => 'MERCHANT_ADMINISTRATOR',
                    'full_name' => 'Alice Admin',
                    'username' => 'alice_admin',
                    'email' => 'alice@example.com',
                    'phone_number' => '+237699999999',
                    'region' => 'Littoral',
                    'country' => 'Cameroon',
                    'active' => true,
                    'network' => null,
                ],
            ],
        ];

        $response = $this->withoutMiddleware(\Laravel\Passport\Http\Middleware\CheckToken::class)
            ->postJson('/api/tenant-provisions', $payload);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertSame(0, $data['success']);
        $this->assertSame(1, $data['faillure']);
        $this->assertStringContainsString('Faild to save Tenant Provision', $data['raison']);

        // Verify rollback: neither tenant nor merchant exists
        $this->assertDatabaseMissing('tenants', ['id' => $tenantId]);
        $this->assertDatabaseMissing('merchants', ['id' => $userId]);
    }

    public function test_unauthenticated_request_is_rejected_by_rabbitmq_middleware(): void
    {
        $response = $this->postJson('/api/tenant-provisions', [
            'data' => [],
        ]);

        // Passport's CheckToken middleware returns 401 when unauthenticated
        $response->assertStatus(401);
    }
}
