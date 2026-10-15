<?php

namespace App\Http\Controllers;

use App\Billing\BusinessProfile;
use App\Http\Requests\BusinessProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessProfileController extends Controller
{
    public function edit(): Response
    {
        abort_unless(auth()->user()?->isOwner() === true, 403);

        $profile = BusinessProfile::current();

        return Inertia::render('billing/business-profile', [
            'profile' => [
                'legal_name' => $profile->legal_name,
                'trading_name' => $profile->trading_name,
                'address_line1' => $profile->address_line1,
                'address_line2' => $profile->address_line2,
                'city' => $profile->city,
                'country' => $profile->country,
                'phone' => $profile->phone,
                'email' => $profile->email,
                'tax_id' => $profile->tax_id,
                'bank_details' => $profile->bank_details,
                'default_terms' => $profile->default_terms,
                'default_tax_rate' => (float) $profile->default_tax_rate,
                'currency' => $profile->currency,
                'logo_url' => $profile->logoUrl(),
            ],
        ]);
    }

    public function update(BusinessProfileRequest $request): RedirectResponse
    {
        $profile = BusinessProfile::current();
        $data = $request->safe()->except(['logo']);

        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $profile->fill($data);
        $profile->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Business profile saved.']);

        return redirect()->route('billing.profile.edit');
    }
}

