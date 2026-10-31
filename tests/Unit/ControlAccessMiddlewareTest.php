<?php

namespace Tests\Unit;

use App\Http\Middleware\ControlAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ControlAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private ControlAccess $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ControlAccess;
    }

    public function test_valid_token_returns_true(): void
    {
        $token = $this->createAccessTokenRecord([
            'revoked' => false,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $isValid = $this->middleware->checkAccessTokenValidity($token);
        $this->assertTrue($isValid);
    }

    public function test_expired_token_returns_false(): void
    {
        $token = $this->createAccessTokenRecord([
            'revoked' => false,
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $isValid = $this->middleware->checkAccessTokenValidity($token);
        $this->assertFalse($isValid);
    }

    public function test_revoked_token_returns_false(): void
    {
        $token = $this->createAccessTokenRecord([
            'revoked' => true,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $isValid = $this->middleware->checkAccessTokenValidity($token);
        $this->assertFalse($isValid);
    }

    public function test_revoked_and_expired_token_returns_false(): void
    {
        $token = $this->createAccessTokenRecord([
            'revoked' => true,
            'expires_at' => Carbon::now()->subHours(2),
        ]);

        $isValid = $this->middleware->checkAccessTokenValidity($token);
        $this->assertFalse($isValid);
    }
}
