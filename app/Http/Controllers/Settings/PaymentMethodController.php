<?php

namespace App\Http\Controllers\Settings;

use App\Billing\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethodRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function edit(): Response
    {
        abort_unless(request()->user()?->isOwner() === true || request()->user() !== null, 403);

        $methods = PaymentMethod::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentMethod $method): array => [
                'id' => $method->id,
                'name' => $method->name,
                'slug' => $method->slug,
                'is_active' => $method->is_active,
                'position' => $method->position,
            ]);

        return Inertia::render('settings/payment-methods', [
            'methods' => $methods,
        ]);
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        $name = trim((string) $request->validated('name'));
        $max = (int) PaymentMethod::query()->max('position');

        PaymentMethod::query()->create([
            'name' => $name,
            'slug' => PaymentMethod::uniqueSlug($name),
            'is_active' => $request->boolean('is_active', true),
            'position' => $request->integer('position', $max + 1),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment method added.']);

        return redirect()->route('payment-methods.edit');
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $name = trim((string) $request->validated('name'));

        $paymentMethod->fill([
            'name' => $name,
            'slug' => PaymentMethod::uniqueSlug($name, $paymentMethod->id),
            'is_active' => $request->boolean('is_active', $paymentMethod->is_active),
            'position' => $request->integer('position', $paymentMethod->position),
        ]);
        $paymentMethod->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment method updated.']);

        return redirect()->route('payment-methods.edit');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        abort_unless(request()->user()?->isOwner() === true, 403);

        $paymentMethod->is_active = false;
        $paymentMethod->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment method deactivated.']);

        return redirect()->route('payment-methods.edit');
    }
}
