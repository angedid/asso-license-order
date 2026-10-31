<?php

namespace Tests\Unit;

use App\Domain\Model\Merchant;
use App\Domain\Model\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantAndMerchantTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_tenant_with_valid_attributes(): void
    {
        $tenantId = Str::uuid()->toString();
        $ownerId = Str::uuid()->toString();

        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'STP Wholesaler Ltd',
            'city' => 'Yaounde',
            'location' => 'Centre Ville',
            'region' => 'Centre',
            'country' => 'Cameroon',
            'phone_number' => '+237612345678',
            'website' => 'https://wholesaler.stp.cm',
            'address' => 'Avenue Kennedy',
            'active' => true,
            'taxpayer_number' => 'M998877665544',
            'number_trade_register' => 'RC/YAO/2026/A/100',
            'owner' => $ownerId,
            'network' => json_encode(['tier' => 'platinum']),
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertSame($tenantId, $tenant->id);
        $this->assertSame('STP Wholesaler Ltd', $tenant->name);
        $this->assertTrue((bool) $tenant->active);
        $this->assertFalse($tenant->incrementing);
        $this->assertSame('string', $tenant->getKeyType());

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'name' => 'STP Wholesaler Ltd',
            'owner' => $ownerId,
        ]);
    }

    public function test_can_create_merchant_linked_to_tenant(): void
    {
        $tenant = $this->createTenant();

        $merchantId = Str::uuid()->toString();
        $merchant = Merchant::create([
            'id' => $merchantId,
            'tenant_id' => $tenant->id,
            'type' => 'MERCHANT_ADMINISTRATOR',
            'full_name' => 'Jane Doe',
            'username' => 'janedoe_admin',
            'email' => 'jane@example.com',
            'active' => true,
        ]);

        $this->assertInstanceOf(Merchant::class, $merchant);
        $this->assertSame($merchantId, $merchant->id);
        $this->assertSame($tenant->id, $merchant->tenant_id);
        $this->assertSame('MERCHANT_ADMINISTRATOR', $merchant->type);
        $this->assertSame('janedoe_admin', $merchant->username);
        $this->assertFalse($merchant->incrementing);
        $this->assertSame('string', $merchant->getKeyType());

        $this->assertDatabaseHas('merchants', [
            'id' => $merchantId,
            'tenant_id' => $tenant->id,
            'username' => 'janedoe_admin',
        ]);
    }

    public function test_merchant_type_user(): void
    {
        $tenant = $this->createTenant();

        $merchant = Merchant::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'type' => 'MERCHANT_USER',
            'full_name' => 'Staff Member',
            'username' => 'staff_user_1',
            'email' => 'staff@example.com',
            'active' => true,
        ]);

        $this->assertSame('MERCHANT_USER', $merchant->type);
    }
}
