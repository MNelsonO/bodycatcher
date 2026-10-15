<?php

namespace App\Http\Controllers;

use App\Billing\TaxLedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $system = $request->string('system')->toString();

        $query = TaxLedgerEntry::query()
            ->with('contact:id,full_name,business_name')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id');

        if ($system !== '' && $system !== 'all') {
            $query->where('system_name', $system);
        }

        $entries = $query->paginate(30)->withQueryString();

        $totals = TaxLedgerEntry::query()
            ->when($system !== '' && $system !== 'all', fn ($q) => $q->where('system_name', $system))
            ->selectRaw('coalesce(sum(net_amount),0) as net_amount, coalesce(sum(tax_amount),0) as tax_amount, coalesce(sum(gross_amount),0) as gross_amount')
            ->first();

        $systems = TaxLedgerEntry::query()
            ->distinct()
            ->orderBy('system_name')
            ->pluck('system_name');

        return Inertia::render('billing/tax-log', [
            'entries' => $entries->through(fn (TaxLedgerEntry $entry): array => [
                'id' => $entry->id,
                'source' => $entry->source,
                'source_id' => $entry->source_id,
                'system_name' => $entry->system_name,
                'description' => $entry->description,
                'contact_name' => $entry->contact?->full_name,
                'net_amount' => (float) $entry->net_amount,
                'tax_amount' => (float) $entry->tax_amount,
                'gross_amount' => (float) $entry->gross_amount,
                'occurred_on' => $entry->occurred_on?->toDateString(),
            ]),
            'totals' => [
                'net_amount' => (float) ($totals->net_amount ?? 0),
                'tax_amount' => (float) ($totals->tax_amount ?? 0),
                'gross_amount' => (float) ($totals->gross_amount ?? 0),
            ],
            'systems' => $systems,
            'filters' => ['system' => $system],
        ]);
    }
}
