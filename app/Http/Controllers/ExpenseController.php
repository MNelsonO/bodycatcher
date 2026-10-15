<?php

namespace App\Http\Controllers;

use App\Billing\BusinessProfile;
use App\Billing\TaxLedgerEntry;
use App\Expenses\Expense;
use App\Http\Requests\ExpenseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $category = $request->string('category')->toString();
        $month = $request->string('month')->toString();

        $query = Expense::query()->orderByDesc('spent_on')->orderByDesc('id');

        if ($category !== '' && $category !== 'all') {
            $query->where('category', $category);
        }

        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $query->whereBetween('spent_on', [
                $month.'-01',
                date('Y-m-t', strtotime($month.'-01')),
            ]);
        }

        $stats = Expense::query()
            ->when($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1, function ($q) use ($month): void {
                $q->whereBetween('spent_on', [
                    $month.'-01',
                    date('Y-m-t', strtotime($month.'-01')),
                ]);
            })
            ->selectRaw('coalesce(sum(amount),0) as amount, coalesce(sum(tax_amount),0) as tax_amount')
            ->selectRaw('count(*) as total_rows')
            ->first();

        $byCategory = Expense::query()
            ->when($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1, function ($q) use ($month): void {
                $q->whereBetween('spent_on', [
                    $month.'-01',
                    date('Y-m-t', strtotime($month.'-01')),
                ]);
            })
            ->select('category', DB::raw('coalesce(sum(amount + tax_amount),0) as gross'), DB::raw('count(*) as rows'))
            ->groupBy('category')
            ->orderByDesc('gross')
            ->get();

        return Inertia::render('expenses/index', [
            'expenses' => $query->paginate(30)->withQueryString()->through(fn (Expense $expense): array => [
                'id' => $expense->id,
                'title' => $expense->title,
                'category' => $expense->category,
                'vendor' => $expense->vendor,
                'amount' => (float) $expense->amount,
                'tax_amount' => (float) $expense->tax_amount,
                'gross' => $expense->gross(),
                'currency' => $expense->currency,
                'spent_on' => $expense->spent_on?->toDateString(),
                'system_name' => $expense->system_name,
                'notes' => $expense->notes,
                'receipt_url' => $expense->receiptUrl(),
            ]),
            'stats' => [
                'amount' => (float) ($stats->amount ?? 0),
                'tax_amount' => (float) ($stats->tax_amount ?? 0),
                'gross' => (float) ($stats->amount ?? 0) + (float) ($stats->tax_amount ?? 0),
                'total_rows' => (int) ($stats->total_rows ?? 0),
            ],
            'by_category' => $byCategory->map(fn ($row): array => [
                'category' => $row->category,
                'gross' => (float) $row->gross,
                'rows' => (int) $row->rows,
            ]),
            'filters' => [
                'category' => $category,
                'month' => $month !== '' ? $month : now()->format('Y-m'),
            ],
            'defaults' => [
                'currency' => BusinessProfile::current()->currency,
                'spent_on' => now()->toDateString(),
            ],
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['receipt']);
        $data['created_by_user_id'] = $request->user()->id;
        $data['tax_amount'] = $data['tax_amount'] ?? 0;
        $data['system_name'] = $data['system_name'] ?? 'expenses';

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts', 'public');
        }

        $expense = Expense::query()->create($data);

        if ((float) $expense->tax_amount > 0) {
            TaxLedgerEntry::query()->create([
                'source' => 'expense',
                'source_id' => $expense->id,
                'contact_id' => null,
                'description' => 'Expense tax · '.$expense->title,
                'net_amount' => $expense->amount,
                'tax_amount' => $expense->tax_amount,
                'gross_amount' => $expense->gross(),
                'occurred_on' => $expense->spent_on?->toDateString() ?? now()->toDateString(),
                'system_name' => $expense->system_name ?: 'expenses',
                'meta' => [
                    'category' => $expense->category,
                    'vendor' => $expense->vendor,
                ],
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense saved.']);

        return redirect()->route('expenses.index');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }

        TaxLedgerEntry::query()
            ->where('source', 'expense')
            ->where('source_id', $expense->id)
            ->delete();

        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Expense removed.']);

        return redirect()->route('expenses.index');
    }
}
