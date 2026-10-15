<?php

namespace App\Providers;

use App\Ai\AnalysisDispatcher;
use App\Ai\FakeLlmClient;
use App\Ai\LaravelAnalysisDispatcher;
use App\Ai\LlmClient;
use App\Ai\OpenAiCompatibleClient;
use App\Billing\BusinessProfile;
use App\WhatsApp\CloudWhatsAppClient;
use App\WhatsApp\FakeWhatsAppClient;
use App\WhatsApp\FlareSendWhatsAppClient;
use App\WhatsApp\LaravelWebhookDispatcher;
use App\WhatsApp\TwilioWhatsAppClient;
use App\WhatsApp\WebhookDispatcher;
use App\WhatsApp\WhatsAppClient;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Notifications\Messages\MailMessage;
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

            return match ((string) config('whatsapp.driver', 'meta')) {
                'flaresend' => $app->make(FlareSendWhatsAppClient::class),
                'twilio' => $app->make(TwilioWhatsAppClient::class),
                default => $app->make(CloudWhatsAppClient::class),
            };
        });
        $this->app->bind(WebhookDispatcher::class, LaravelWebhookDispatcher::class);
        $this->app->singleton(FakeLlmClient::class);
        $this->app->bind(LlmClient::class, function (Application $app): LlmClient {
            if ($app->environment('testing')) {
                return $app->make(FakeLlmClient::class);
            }

            return $app->make(OpenAiCompatibleClient::class);
        });
        $this->app->bind(AnalysisDispatcher::class, LaravelAnalysisDispatcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthMail();
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

    protected function configureAuthMail(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $email = method_exists($notifiable, 'getEmailForPasswordReset')
                ? $notifiable->getEmailForPasswordReset()
                : (string) ($notifiable->email ?? '');

            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $email,
            ], false));

            $brand = config('app.name', 'Kemet Tech');

            try {
                $profile = BusinessProfile::current();
                $brand = $profile->trading_name ?: $profile->legal_name ?: $brand;
            } catch (\Throwable) {
                // Keep app name fallback when billing tables are not ready.
            }

            $expire = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject('Reset your '.$brand.' desk password')
                ->markdown('mail.auth.reset-password', [
                    'url' => $url,
                    'email' => $email,
                    'name' => (string) ($notifiable->name ?? 'there'),
                    'brand' => $brand,
                    'expire' => $expire,
                ]);
        });
    }
}
