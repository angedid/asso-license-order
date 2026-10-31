<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Passport\Http\Middleware\CheckToken;
use Tests\TestCase;

class AccessTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_successfully_saves_access_token(): void
    {
        $jti = Str::random(40);
        $userId = Str::uuid()->toString();
        $clientId = Str::uuid()->toString();
        $exp = Carbon::now()->addHour()->timestamp;

        $jwt = $this->createJwtToken($jti, $userId, $clientId, $exp, ['orders:create']);

        $tokenPayload = [
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => $jwt,
        ];

        // ApiController expects double json encoded body: json_decode(json_decode($request->getContent(), true), true)
        $rawBody = json_encode(json_encode($tokenPayload));

        $response = $this->withoutMiddleware(CheckToken::class)
            ->call(
                'POST',
                '/api/access-tokens',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                $rawBody
            );

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Access Token saved successfully',
        ]);

        $this->assertDatabaseHas('access_tokens', [
            'id' => $jti,
            'user_id' => $userId,
            'client_id' => $clientId,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'revoked' => false,
        ]);
    }

    public function test_successfully_revokes_token_on_logout(): void
    {
        $jti = Str::random(40);
        $token = $this->createAccessTokenRecord([
            'id' => $jti,
            'revoked' => false,
        ]);

        $response = $this->withoutMiddleware(CheckToken::class)
            ->postJson('/api/logout', ['jti' => $jti]);

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Access Token revoked successfully',
        ]);

        $this->assertDatabaseHas('access_tokens', [
            'id' => $jti,
            'revoked' => true,
        ]);
    }

    public function test_logout_fails_when_token_not_found(): void
    {
        $nonExistentJti = 'non-existent-jti-12345';

        $response = $this->withoutMiddleware(CheckToken::class)
            ->postJson('/api/logout', ['jti' => $nonExistentJti]);

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => "Token with jti: {$nonExistentJti} not found",
        ]);
    }

    public function test_unauthenticated_requests_rejected_by_rabbitmq_middleware(): void
    {
        $responseSave = $this->postJson('/api/access-tokens', []);
        $responseSave->assertStatus(401);

        $responseLogout = $this->postJson('/api/logout', ['jti' => 'abc']);
        $responseLogout->assertStatus(401);
    }
}
