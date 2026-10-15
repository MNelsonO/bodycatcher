<?php

namespace App\Integrations\Services;

use App\Integrations\IntegrationAccount;
use App\WhatsApp\DeskSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Prefers desk-stored (encrypted) integration credentials over .env,
 * then overlays into config so existing clients keep using config().
 */
class IntegrationConfigResolver
{
    public function applyToConfig(): void
    {
        try {
            if (! Schema::hasTable('integration_accounts')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        foreach (IntegrationAccount::query()->get() as $account) {
            $this->overlayAccount($account);
        }

        $this->overlayMessagingDriver();
    }

    public function overlayAccount(IntegrationAccount $account): void
    {
        $credentials = $account->credentials ?? [];
        $settings = $account->settings ?? [];

        match ($account->provider) {
            'flaresend' => $this->setIfFilled([
                'whatsapp.flaresend.api_key' => $credentials['api_key'] ?? null,
                'whatsapp.flaresend.webhook_secret' => $credentials['webhook_secret'] ?? null,
                'whatsapp.flaresend.base_url' => $settings['base_url'] ?? $credentials['base_url'] ?? null,
            ]),
            'meta_whatsapp' => $this->setIfFilled([
                'whatsapp.token' => $credentials['token'] ?? null,
                'whatsapp.app_secret' => $credentials['app_secret'] ?? null,
                'whatsapp.verify_token' => $credentials['verify_token'] ?? null,
                'whatsapp.phone_number_id' => $settings['phone_number_id'] ?? null,
            ]),
            'meta_business' => $this->setIfFilled([
                'integrations.meta.access_token' => $credentials['access_token'] ?? null,
                'integrations.meta.page_id' => $settings['page_id'] ?? null,
                'integrations.meta.ad_account_id' => $settings['ad_account_id'] ?? null,
            ]),
            'google_search_console' => $this->setIfFilled([
                'integrations.google_search_console.access_token' => $credentials['access_token'] ?? null,
                'integrations.google_search_console.site_url' => $settings['site_url'] ?? null,
            ]),
            'google_calendar' => $this->setIfFilled([
                'integrations.google_calendar.access_token' => $credentials['access_token'] ?? null,
                'integrations.google_calendar.calendar_id' => $settings['calendar_id'] ?? null,
            ]),
            default => null,
        };
    }

    public function overlayMessagingDriver(): void
    {
        try {
            if (! Schema::hasTable('desk_settings')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $driver = DeskSetting::query()->value('messaging_driver');

        if (is_string($driver) && in_array($driver, ['meta', 'flaresend', 'twilio'], true)) {
            config(['whatsapp.driver' => $driver]);
        }
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function setIfFilled(array $map): void
    {
        foreach ($map as $key => $value) {
            if (is_string($value) && trim($value) !== '') {
                config([$key => trim($value)]);
            }
        }
    }

    /**
     * Merge submitted secrets: blank input keeps the stored value.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @param  list<string>  $secretKeys
     * @return array<string, mixed>
     */
    public function mergeSecrets(array $existing, array $incoming, array $secretKeys): array
    {
        $merged = $existing;

        foreach ($secretKeys as $key) {
            if (! array_key_exists($key, $incoming)) {
                continue;
            }

            $value = $incoming[$key];

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $merged[$key] = trim($value);
        }

        return $merged;
    }

    /**
     * @return array<string, bool>
     */
    public function credentialFlags(IntegrationAccount $account): array
    {
        $flags = [];
        $credentials = $account->credentials ?? [];

        foreach ($credentials as $key => $value) {
            $flags[$key] = is_string($value) && trim($value) !== '';
        }

        return $flags;
    }
}
