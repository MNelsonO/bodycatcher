<?php

namespace App\Providers;

use App\WhatsApp\CloudWhatsAppClient;
use App\WhatsApp\FakeWhatsAppClient;
use App\WhatsApp\LaravelWebhookDispatcher;
use App\WhatsApp\WebhookDispatcher;
use App\WhatsApp\WhatsAppClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FakeWhatsAppClient::class);
        $this->app->bind(WhatsAppClient::class, function (Application $app): WhatsAppClient {
            if ($app->environment('testing')) {
                return $app->make(FakeWhatsAppClient::class);
            }

            return $app->make(CloudWhatsAppClient::class);
        });
        $this->app->bind(WebhookDispatcher::class, LaravelWebhookDispatcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
