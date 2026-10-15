<?php

namespace App\Http\Middleware;

use App\Billing\BusinessProfile;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'displayTimezone' => config('app.display_timezone'),
            'brand' => $this->brand(),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{name: string, tagline: string, logo_url: string}
     */
    private function brand(): array
    {
        $fallback = [
            'name' => (string) config('app.name', 'Kemet Tech'),
            'tagline' => 'Laundry sales desk',
            'logo_url' => '/brand/kemet-mark.svg',
        ];

        try {
            $profile = BusinessProfile::current();
            $name = $profile->trading_name ?: $profile->legal_name ?: $fallback['name'];

            return [
                'name' => $name,
                'tagline' => 'Laundry sales desk',
                'logo_url' => $profile->logoUrl() ?: $fallback['logo_url'],
            ];
        } catch (Throwable) {
            return $fallback;
        }
    }
}
