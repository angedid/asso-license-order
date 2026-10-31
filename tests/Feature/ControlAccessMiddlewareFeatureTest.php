<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ControlAccessMiddlewareFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_allowed_when_no_bearer_token(): void
    {
        // POST /api/orders uses iam.auth.api middleware. An empty body triggers validation in controller.
        $response = $this->postJson('/api/orders', []);

        // Passes through middleware to controller, returning 202 validation error rather than auth rejection
        $response->assertStatus(202);
        $this->assertSame(0, $response->json('success'));
        $this->assertSame(1, $response->json('faillure'));
    }

    public function test_request_allowed_with_valid_active_bearer_token(): void
    {
        $jti = Str::random(40);
        $this->createAccessTokenRecord([
            'id' => $jti,
            'revoked' => false,
            'expires_at' => Carbon::now()->addHours(2),
        ]);

        $jwt = $this->createJwtToken($jti);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")
            ->postJson('/api/orders', []);

        // Reaches controller validation
        $response->assertStatus(202);
        $this->assertSame(0, $response->json('success'));
        $this->assertSame(1, $response->json('faillure'));
    }

    public function test_request_rejected_with_revoked_bearer_token(): void
    {
        $jti = Str::random(40);
        $this->createAccessTokenRecord([
            'id' => $jti,
            'revoked' => true,
            'expires_at' => Carbon::now()->addHours(2),
        ]);

        $jwt = $this->createJwtToken($jti);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")
            ->postJson('/api/orders', []);

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => "Token with jti: {$jti} not valid",
        ]);
    }

    public function test_request_rejected_with_expired_bearer_token(): void
    {
        $jti = Str::random(40);
        $this->createAccessTokenRecord([
            'id' => $jti,
            'revoked' => false,
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $jwt = $this->createJwtToken($jti);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")
            ->postJson('/api/orders', []);

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => "Token with jti: {$jti} not valid",
        ]);
    }

    public function test_request_allowed_when_token_jti_not_in_database(): void
    {
        $unknownJti = Str::random(40);
        $jwt = $this->createJwtToken($unknownJti);

        $response = $this->withHeader('Authorization', "Bearer {$jwt}")
            ->postJson('/api/orders', []);

        // Passes through to controller
        $response->assertStatus(202);
    }
}
