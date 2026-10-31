<?php

namespace Tests;

use App\Domain\Model\AccessToken;
use App\Domain\Model\LicenseType;
use App\Domain\Model\Merchant;
use App\Domain\Model\Order;
use App\Domain\Model\Tenant;
use DateTime;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a mock JWT token string with the given claims.
     */
    protected function createJwtToken(
        string $jti,
        ?string $userId = null,
        ?string $clientId = null,
        ?int $exp = null,
        array $scopes = ['*']
    ): string {
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'RS256']));
        $payload = base64_encode(json_encode([
            'sub' => $userId ?? Str::uuid()->toString(),
            'aud' => $clientId ?? Str::uuid()->toString(),
            'jti' => $jti,
            'scopes' => $scopes,
            'exp' => $exp ?? Carbon::now()->addHour()->timestamp,
        ]));
        $signature = base64_encode('fake-signature');

        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * Helper to create a persisted Tenant.
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        return Tenant::create(array_merge([
            'id' => Str::uuid()->toString(),
            'name' => 'Acme Corporation',
            'city' => 'Douala',
            'location' => 'Bonanjo',
            'region' => 'Littoral',
            'country' => 'Cameroon',
            'phone_number' => '+237600000000',
            'website' => 'https://acme.example.com',
            'address' => '123 Boulevard de la Liberte',
            'active' => true,
            'taxpayer_number' => 'M123456789012A',
            'number_trade_register' => 'RC/DLA/2026/B/001',
            'owner' => Str::uuid()->toString(),
            'network' => json_encode(['cluster' => 'primary', 'tier' => 'gold']),
        ], $attributes));
    }

    /**
     * Helper to create a persisted Merchant.
     */
    protected function createMerchant(Tenant $tenant, array $attributes = []): Merchant
    {
        return Merchant::create(array_merge([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'type' => 'MERCHANT_ADMINISTRATOR',
            'account_id' => Str::uuid()->toString(),
            'full_name' => 'John Doe',
            'username' => 'johndoe_'.Str::random(6),
            'email' => 'john.doe@example.com',
            'phone_numbers' => json_encode(['+237600000000']),
            'active' => true,
        ], $attributes));
    }

    /**
     * Helper to create a persisted LicenseType.
     */
    protected function createLicenseType(array $attributes = []): LicenseType
    {
        return LicenseType::create(array_merge([
            'id' => Str::uuid()->toString(),
            'name' => LicenseType::MONTHLY,
            'number_of_days' => 30,
            'price' => 49.99,
            'active' => true,
        ], $attributes));
    }

    /**
     * Helper to create a persisted AccessToken.
     */
    protected function createAccessTokenRecord(array $attributes = []): AccessToken
    {
        $expiresIn = $attributes['expires_in'] ?? 3600;
        $expiresAt = $attributes['expires_at'] ?? Carbon::now()->addSeconds($expiresIn);

        return AccessToken::create(array_merge([
            'id' => Str::random(40),
            'user_id' => Str::uuid()->toString(),
            'client_id' => Str::uuid()->toString(),
            'name' => 'Test Token',
            'scopes' => json_encode(['*']),
            'revoked' => false,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt instanceof DateTime ? $expiresAt : new DateTime($expiresAt),
        ], $attributes));
    }

    /**
     * Helper to create a persisted Order.
     */
    protected function createOrder(
        Tenant $tenant,
        Merchant $merchant,
        LicenseType $licenseType,
        array $attributes = []
    ): Order {
        $amount = $attributes['amount'] ?? 100.0;
        $discount = $attributes['discount'] ?? 0.1;
        $finalAmount = $attributes['final_amount'] ?? ($amount * (1 - $discount));

        return Order::create(array_merge([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'merchant_id' => $merchant->id,
            'license_type_id' => $licenseType->id,
            'amount' => $amount,
            'discount' => $discount,
            'final_amount' => $finalAmount,
            'placed_at' => null,
            'status' => Order::PENDING,
            'paid_at' => null,
            'cancelled_at' => null,
            'active' => true,
            'items' => json_encode([$licenseType]),
        ], $attributes));
    }
}
