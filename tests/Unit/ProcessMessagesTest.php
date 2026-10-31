<?php

namespace Tests\Unit;

use App\Jobs\ProcessMessages;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\TestCase;

class ProcessMessagesTest extends TestCase
{
    public function test_job_implements_should_queue(): void
    {
        $job = new ProcessMessages('test_exchange', 'fanout', 'test_message');
        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    public function test_job_properties_are_initialized(): void
    {
        $exchangeName = 'ORDER_PLACED_EXCHANGE';
        $exchangeType = 'fanout';
        $payload = json_encode(['order_id' => '12345', 'status' => 'PLACED']);

        $job = new ProcessMessages($exchangeName, $exchangeType, $payload);

        $reflection = new \ReflectionClass($job);

        $exchangeProperty = $reflection->getProperty('exchangeName');
        $this->assertSame($exchangeName, $exchangeProperty->getValue($job));

        $typeProperty = $reflection->getProperty('exchangetype');
        $this->assertSame($exchangeType, $typeProperty->getValue($job));

        $msgProperty = $reflection->getProperty('message');
        $this->assertSame($payload, $msgProperty->getValue($job));
    }
}
