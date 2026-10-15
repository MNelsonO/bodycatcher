<?php

namespace App\Http\Controllers;

use App\Ai\LlmConfiguration;
use App\Http\Requests\WhatsAppSettingsRequest;
use App\Models\User;
use App\Settings\Product;
use App\WhatsApp\DeskSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppSettingsController extends Controller
{
    public function index(Request $request, LlmConfiguration $llm): Response
    {
        $this->authorize('view', DeskSetting::class);

        $settings = DeskSetting::current();
        $user = $request->user();
        $driver = (string) config('whatsapp.driver', 'meta');

        return Inertia::render('whatsapp/index', [
            'driver' => $driver,
            'variables' => $this->variables($driver),
            'webhook_paths' => [
                'meta' => '/webhooks/whatsapp',
                'flaresend' => '/webhooks/flaresend',
                'twilio' => '/webhooks/twilio',
            ],
            'automatic_sends_paused' => $settings->automatic_sends_paused,
            'live_assistant_enabled' => $settings->live_assistant_enabled,
            'llm_ready' => $llm->ready(),
            'llm_provider' => $llm->provider(),
            'inbound_product_id' => $settings->inbound_product_id,
            'products' => Product::query()
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name']),
            'opt_out_keywords' => config('whatsapp.opt_out_keywords'),
            'can_manage' => $user instanceof User && $user->isOwner(),
        ]);
    }

    public function update(WhatsAppSettingsRequest $request): RedirectResponse
    {
        $settings = DeskSetting::current();
        $settings->automatic_sends_paused = $request->boolean('automatic_sends_paused');
        $settings->live_assistant_enabled = $request->boolean('live_assistant_enabled');
        $productId = $request->input('inbound_product_id');
        $settings->inbound_product_id = is_numeric($productId) ? (int) $productId : null;
        $settings->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'WhatsApp desk settings saved.']);

        return redirect()->route('whatsapp.settings');
    }

    /**
     * @return list<array{key: string, configured: bool}>
     */
    private function variables(string $driver): array
    {
        $values = match ($driver) {
            'flaresend' => [
                'MESSAGING_DRIVER' => $driver,
                'FLARESEND_API_KEY' => config('whatsapp.flaresend.api_key'),
                'FLARESEND_WEBHOOK_SECRET' => config('whatsapp.flaresend.webhook_secret'),
                'FLARESEND_BASE_URL' => config('whatsapp.flaresend.base_url'),
            ],
            'twilio' => [
                'MESSAGING_DRIVER' => $driver,
                'TWILIO_ACCOUNT_SID' => config('whatsapp.twilio.account_sid'),
                'TWILIO_AUTH_TOKEN' => config('whatsapp.twilio.auth_token'),
                'TWILIO_WHATSAPP_FROM' => config('whatsapp.twilio.from'),
            ],
            default => [
                'MESSAGING_DRIVER' => $driver,
                'WHATSAPP_TOKEN' => config('whatsapp.token'),
                'WHATSAPP_PHONE_NUMBER_ID' => config('whatsapp.phone_number_id'),
                'WHATSAPP_APP_SECRET' => config('whatsapp.app_secret'),
                'WHATSAPP_VERIFY_TOKEN' => config('whatsapp.verify_token'),
            ],
        };

        $rows = [];

        foreach ($values as $key => $value) {
            $rows[] = [
                'key' => $key,
                'configured' => is_string($value) && $value !== '',
            ];
        }

        return $rows;
    }
}
