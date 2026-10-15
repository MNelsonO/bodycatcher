<?php

namespace App\Http\Controllers;

use App\Billing\BusinessProfile;
use App\Http\Requests\MoneyAccountRequest;
use App\Http\Requests\MoneyDebtRequest;
use App\Http\Requests\MoneyRecurringRequest;
use App\Money\MoneyAccount;
use App\Money\MoneyDebt;
use App\Money\MoneyRecurring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyController extends Controller
{
    public function index(): Response
    {
        $accounts = MoneyAccount::query()->orderByDesc('is_active')->orderBy('name')->get();
        $debts = MoneyDebt::query()->with('account:id,name')->orderByRaw("case status when 'open' then 0 when 'partial' then 1 else 2 end")->orderBy('due_on')->get();
        $recurrings = MoneyRecurring::query()->with('account:id,name')->orderBy('next_due_on')->get();

        $cash = (float) $accounts->where('is_active', true)->sum('balance');
        $iOwe = (float) $debts->where('direction', 'payable')->where('status', '!=', 'settled')->sum(fn (MoneyDebt $d) => $d->remaining());
        $owedToMe = (float) $debts->where('direction', 'receivable')->where('status', '!=', 'settled')->sum(fn (MoneyDebt $d) => $d->remaining());
        $monthlyOut = (float) $recurrings->where('status', 'active')->where('direction', 'outflow')->where('cadence', 'monthly')->sum('amount');
        $monthlyIn = (float) $recurrings->where('status', 'active')->where('direction', 'inflow')->where('cadence', 'monthly')->sum('amount');

        $currency = BusinessProfile::current()->currency ?: 'KES';

        return Inertia::render('money/index', [
            'summary' => [
                'cash' => $cash,
                'i_owe' => $iOwe,
                'owed_to_me' => $owedToMe,
                'net' => round($cash + $owedToMe - $iOwe, 2),
                'monthly_out' => $monthlyOut,
                'monthly_in' => $monthlyIn,
                'currency' => $currency,
            ],
            'accounts' => $accounts->map(fn (MoneyAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'institution' => $account->institution,
                'account_type' => $account->account_type,
                'account_number' => $account->account_number,
                'currency' => $account->currency,
                'balance' => (float) $account->balance,
                'is_active' => $account->is_active,
                'notes' => $account->notes,
            ]),
            'debts' => $debts->map(fn (MoneyDebt $debt): array => [
                'id' => $debt->id,
                'direction' => $debt->direction,
                'party_name' => $debt->party_name,
                'title' => $debt->title,
                'amount' => (float) $debt->amount,
                'amount_paid' => (float) $debt->amount_paid,
                'remaining' => $debt->remaining(),
                'currency' => $debt->currency,
                'due_on' => $debt->due_on?->toDateString(),
                'status' => $debt->status,
                'money_account_id' => $debt->money_account_id,
                'account_name' => $debt->account?->name,
                'notes' => $debt->notes,
            ]),
            'recurrings' => $recurrings->map(fn (MoneyRecurring $item): array => [
                'id' => $item->id,
                'direction' => $item->direction,
                'title' => $item->title,
                'party_name' => $item->party_name,
                'amount' => (float) $item->amount,
                'currency' => $item->currency,
                'cadence' => $item->cadence,
                'day_of_month' => $item->day_of_month,
                'next_due_on' => $item->next_due_on?->toDateString(),
                'status' => $item->status,
                'money_account_id' => $item->money_account_id,
                'account_name' => $item->account?->name,
                'notes' => $item->notes,
                'is_overdue' => $item->isOverdue(),
                'is_due_soon' => $item->isDueSoon(),
            ]),
            'defaults' => [
                'currency' => $currency,
                'next_due_on' => now()->toDateString(),
            ],
        ]);
    }

    public function storeAccount(MoneyAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        MoneyAccount::query()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account saved.']);

        return redirect()->route('money.index');
    }

    public function updateAccount(MoneyAccountRequest $request, MoneyAccount $account): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $account->is_active);
        $account->fill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account updated.']);

        return redirect()->route('money.index');
    }

    public function destroyAccount(MoneyAccount $account): RedirectResponse
    {
        $account->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account removed.']);

        return redirect()->route('money.index');
    }

    public function storeDebt(MoneyDebtRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['amount_paid'] = $data['amount_paid'] ?? 0;

        $debt = MoneyDebt::query()->create($data);
        $debt->refreshStatus();
        $debt->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Debt saved.']);

        return redirect()->route('money.index');
    }

    public function updateDebt(MoneyDebtRequest $request, MoneyDebt $debt): RedirectResponse
    {
        $debt->fill($request->validated());
        $debt->refreshStatus();
        $debt->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Debt updated.']);

        return redirect()->route('money.index');
    }

    public function settleDebt(Request $request, MoneyDebt $debt): RedirectResponse
    {
        $data = $request->validate([
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $paid = array_key_exists('amount_paid', $data) && $data['amount_paid'] !== null
            ? (float) $data['amount_paid']
            : (float) $debt->amount;

        $debt->amount_paid = min($paid, (float) $debt->amount);
        $debt->refreshStatus();
        $debt->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Debt payment recorded.']);

        return redirect()->route('money.index');
    }

    public function destroyDebt(MoneyDebt $debt): RedirectResponse
    {
        $debt->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Debt removed.']);

        return redirect()->route('money.index');
    }

    public function storeRecurring(MoneyRecurringRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'active';

        MoneyRecurring::query()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurring item saved.']);

        return redirect()->route('money.index');
    }

    public function updateRecurring(MoneyRecurringRequest $request, MoneyRecurring $recurring): RedirectResponse
    {
        $recurring->fill($request->validated())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurring item updated.']);

        return redirect()->route('money.index');
    }

    public function markRecurringPaid(MoneyRecurring $recurring): RedirectResponse
    {
        abort_unless($recurring->status === 'active', 422);

        $recurring->advanceNextDue();
        $recurring->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marked paid — next due moved forward.']);

        return redirect()->route('money.index');
    }

    public function destroyRecurring(MoneyRecurring $recurring): RedirectResponse
    {
        $recurring->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurring item removed.']);

        return redirect()->route('money.index');
    }
}
