<?php

namespace App\Http\Controllers;

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
    public function index(Request $request): Response
    {
        $this->authorize('view', DeskSetting::class);

        $settings = DeskSetting::current();
        $user = $request->user();

        return Inertia::render('whatsapp/index', [
            'variables' => $this->variables(),
            'webhook_path' => '/webhooks/whatsapp',
            'automatic_sends_paused' => $settings->automatic_sends_paused,
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
        $productId = $request->input('inbound_product_id');
        $settings->inbound_product_id = is_numeric($productId) ? (int) $productId : null;
        $settings->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'WhatsApp desk settings saved.']);

        return redirect()->route('whatsapp.settings');
    }

    /**
     * @return list<array{key: string, configured: bool}>
     */
    private function variables(): array
    {
        $values = [
            'WHATSAPP_TOKEN' => config('whatsapp.token'),
            'WHATSAPP_PHONE_NUMBER_ID' => config('whatsapp.phone_number_id'),
            'WHATSAPP_APP_SECRET' => config('whatsapp.app_secret'),
            'WHATSAPP_VERIFY_TOKEN' => config('whatsapp.verify_token'),
        ];

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
