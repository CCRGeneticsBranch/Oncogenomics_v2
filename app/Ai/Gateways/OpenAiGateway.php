<?php

namespace App\Ai\Gateways;

use App\Ai\Exceptions\ProviderStreamTimeout;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway as LaravelOpenAiGateway;
use Laravel\Ai\Providers\Provider;
use Throwable;

class OpenAiGateway extends LaravelOpenAiGateway
{
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        // Guzzle's streamed response body needs its own socket read timeout.
        // Keep it below the web proxy's idle timeout so failover can be emitted.
        return parent::client($provider, $timeout)->withOptions([
            'read_timeout' => 20,
        ]);
    }

    protected function readLine($streamBody): string
    {
        try {
            $line = parent::readLine($streamBody);
        } catch (Throwable $exception) {
            if ($streamBody->getMetadata('timed_out')) {
                throw new ProviderStreamTimeout('OpenAI response stream timed out; retry with the next provider.', 0, $exception);
            }
            throw $exception;
        }

        if ($streamBody->getMetadata('timed_out')) {
            throw new ProviderStreamTimeout('OpenAI response stream timed out; retry with the next provider.');
        }

        return $line;
    }
}
