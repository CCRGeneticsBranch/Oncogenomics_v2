<?php

namespace App\Providers;

use App\Ai\Gateways\GeminiGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;
use Laravel\Ai\Providers\GeminiProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(AiManager $ai)
    {
        if (! $this->app->environment('testing')) {
            URL::forceScheme('https');
        }

        $ai->extend('gemini', function ($app, array $config): GeminiProvider {
            $events = $app->make(Dispatcher::class);

            return new GeminiProvider(
                new GeminiGateway($events),
                $config,
                $events,
            );
        });
    }
}
