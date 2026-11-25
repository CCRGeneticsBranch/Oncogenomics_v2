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
        $this->app->scoped(\App\Services\ChatbotQueryTelemetry::class);
        $this->app->scoped(\App\Services\ChatbotIntent::class);
        $this->app->scoped(\App\Services\ChatbotConversationStore::class);
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

        $events = $this->app->make(Dispatcher::class);
        foreach ([\Laravel\Ai\Events\PromptingAgent::class, \Laravel\Ai\Events\StreamingAgent::class] as $eventClass) {
            $events->listen($eventClass, function ($event) {
                $provider = $event->prompt->provider->name();
                $model = $event->prompt->model;
                app(\App\Services\ChatbotQueryTelemetry::class)->llm($event->invocationId.':'.$provider.':'.$model, $provider, $model, []);
            });
        }
        foreach ([\Laravel\Ai\Events\AgentPrompted::class, \Laravel\Ai\Events\AgentStreamed::class] as $eventClass) {
            $events->listen($eventClass, function ($event) {
                $provider = $event->response->meta->provider;
                $model = $event->response->meta->model;
                app(\App\Services\ChatbotQueryTelemetry::class)->llm($event->invocationId.':'.$provider.':'.$model,
                    $provider, $model, $event->response->usage->toArray());
            });
        }
        $events->listen(\Laravel\Ai\Events\InvokingTool::class, function ($event) {
            app(\App\Services\ChatbotQueryTelemetry::class)->tool(method_exists($event->tool, 'name') ? $event->tool->name() : \Illuminate\Support\Str::snake(class_basename($event->tool)));
        });

        $ai->extend('openai', function ($app, array $config): \Laravel\Ai\Providers\OpenAiProvider {
            $events = $app->make(Dispatcher::class);
            return new \Laravel\Ai\Providers\OpenAiProvider(
                new \App\Ai\Gateways\OpenAiGateway($events), $config, $events,
            );
        });

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
