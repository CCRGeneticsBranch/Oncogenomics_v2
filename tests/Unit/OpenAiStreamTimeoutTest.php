<?php

namespace Tests\Unit;

use App\Ai\Exceptions\ProviderStreamTimeout;
use App\Ai\Gateways\OpenAiGateway;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Exceptions\FailoverableException;
use Mockery;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

class OpenAiStreamTimeoutTest extends TestCase
{
    private function gateway(): OpenAiGateway
    {
        return new class(app(Dispatcher::class)) extends OpenAiGateway {
            public function line($body): string { return $this->readLine($body); }
            public function request($provider) { return $this->client($provider, 180); }
        };
    }

    public function test_read_timeout_is_bounded_and_provider_is_registered(): void
    {
        $provider = app(\Laravel\Ai\AiManager::class)->textProvider('openai');
        $this->assertSame(20, $this->gateway()->request($provider)->getOptions()['read_timeout']);
    }

    public function test_valid_stream_lines_remain_unchanged(): void
    {
        $this->assertSame("data: {}\n", $this->gateway()->line(Utils::streamFor("data: {}\n")));
    }

    public function test_read_failure_due_to_timeout_can_fail_over(): void
    {
        $body = Mockery::mock(StreamInterface::class);
        $body->shouldReceive('eof')->andReturn(false);
        $body->shouldReceive('read')->with(1)->andThrow(new \RuntimeException('Unable to read from stream'));
        $body->shouldReceive('getMetadata')->with('timed_out')->andReturn(true);
        try { $this->gateway()->line($body); $this->fail('Expected timeout'); }
        catch (ProviderStreamTimeout $e) { $this->assertInstanceOf(FailoverableException::class, $e); }
    }

    public function test_empty_timed_out_read_does_not_loop_forever(): void
    {
        $body = Mockery::mock(StreamInterface::class);
        $body->shouldReceive('eof')->andReturn(false);
        $body->shouldReceive('read')->with(1)->andReturn('');
        $body->shouldReceive('getMetadata')->with('timed_out')->andReturn(true);
        $this->expectException(ProviderStreamTimeout::class);
        $this->gateway()->line($body);
    }

    public function test_unrelated_read_errors_are_not_reclassified(): void
    {
        $body = Mockery::mock(StreamInterface::class);
        $body->shouldReceive('eof')->andReturn(false);
        $body->shouldReceive('read')->with(1)->andThrow(new \LogicException('Detached'));
        $body->shouldReceive('getMetadata')->with('timed_out')->andReturn(false);
        $this->expectException(\LogicException::class);
        $this->gateway()->line($body);
    }
}
