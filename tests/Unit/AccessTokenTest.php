<?php

namespace Tests\Unit;

use App\Domain\Model\AccessToken;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_access_token(): void
    {
        $jti = Str::random(40);
        $userId = Str::uuid()->toString();
        $clientId = Str::uuid()->toString();
        $expiresAt = Carbon::now()->addHours(2);

        $token = AccessToken::create([
            'id' => $jti,
            'user_id' => $userId,
            'client_id' => $clientId,
            'name' => 'Mobile App Token',
            'scopes' => json_encode(['orders:create', 'orders:read']),
            'revoked' => false,
            'token_type' => 'Bearer',
            'expires_in' => 7200,
            'expires_at' => new DateTime("@{$expiresAt->timestamp}"),
        ]);

        $this->assertInstanceOf(AccessToken::class, $token);
        $this->assertSame($jti, $token->id);
        $this->assertSame($userId, $token->user_id);
        $this->assertSame($clientId, $token->client_id);
        $this->assertFalse((bool) $token->revoked);
        $this->assertSame('Bearer', $token->token_type);
        $this->assertSame(7200, (int) $token->expires_in);
        $this->assertFalse($token->incrementing);
        $this->assertSame('string', $token->getKeyType());

        $this->assertDatabaseHas('access_tokens', [
            'id' => $jti,
            'revoked' => false,
        ]);
    }

    public function test_can_revoke_access_token(): void
    {
        $token = $this->createAccessTokenRecord(['revoked' => false]);
        $this->assertFalse((bool) $token->revoked);

        $token->revoked = true;
        $token->save();

        $freshToken = AccessToken::find($token->id);
        $this->assertTrue((bool) $freshToken->revoked);
        $this->assertDatabaseHas('access_tokens', [
            'id' => $token->id,
            'revoked' => true,
        ]);
    }
}
