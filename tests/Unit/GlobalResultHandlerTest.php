<?php

namespace Tests\Unit;

use App\Http\Controllers\GlobalResultHandler;
use PHPUnit\Framework\TestCase;

class GlobalResultHandlerTest extends TestCase
{
    public function test_build_success_response_array(): void
    {
        $message = 'Operation completed successfully';
        $result = GlobalResultHandler::buildSuccesResponseArray($message);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('faillure', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertSame(1, $result['success']);
        $this->assertSame(0, $result['faillure']);
        $this->assertSame($message, $result['response']);
    }

    public function test_build_failure_reason_array(): void
    {
        $reason = 'Validation error: tenant_id required';
        $result = GlobalResultHandler::buildFaillureReasonArray($reason);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('faillure', $result);
        $this->assertArrayHasKey('raison', $result);
        $this->assertSame(0, $result['success']);
        $this->assertSame(1, $result['faillure']);
        $this->assertSame($reason, $result['raison']);
    }
}
