<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->number }} · {{ $business->trading_name ?: $business->legal_name }}</title>
    <style>
        :root { --blue: #1d4ed8; --soft: #eff6ff; --ink: #0f172a; --muted: #64748b; }
        body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: linear-gradient(#dbeafe, #fff 40%); color: var(--ink); }
        .wrap { max-width: 860px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border: 1px solid #bfdbfe; border-radius: 16px; padding: 24px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        a.btn, button.btn { background: var(--blue); color: #fff; text-decoration: none; border: 0; border-radius: 999px; padding: 10px 16px; font-size: 14px; cursor: pointer; }
        a.btn.secondary { background: #fff; color: var(--blue); border: 1px solid var(--blue); }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 14px; }
        th { background: var(--soft); }
        .right { text-align: right; }
        .muted { color: var(--muted); }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; border: 1px solid var(--blue); color: var(--blue); font-size: 12px; text-transform: uppercase; }
        @media print { .actions { display: none; } body { background: #fff; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="actions">
        <a class="btn" href="{{ url('/share/invoices/'.$invoice->share_token.'/pdf') }}">Download PDF</a>
        <button class="btn secondary" type="button" onclick="window.print()">Print / Share</button>
    </div>
    <div class="card">
        <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div>
                @if ($business->logoUrl())
                    <img src="{{ $business->logoUrl() }}" alt="Logo" style="max-height:64px;max-width:180px;">
                @endif
                <h1 style="margin:8px 0 4px;color:var(--blue);font-size:24px;">{{ $business->trading_name ?: $business->legal_name }}</h1>
                @foreach ($business->addressLines() as $line)
                    <div class="muted">{{ $line }}</div>
                @endforeach
            </div>
            <div style="text-align:right;">
                <div style="font-size:20px;font-weight:700;">INVOICE</div>
                <div>{{ $invoice->number }}</div>
                <div class="badge">{{ $invoice->status->value }}</div>
                <div class="muted" style="margin-top:8px;">Issued {{ $invoice->issue_date?->toDateString() }}</div>
                <div class="muted">Due {{ $invoice->due_date?->toDateString() }}</div>
            </div>
        </div>

        <div style="margin-top:20px;">
            <strong>Bill to</strong>
            <div>{{ $invoice->contact->full_name }}</div>
            @if ($invoice->contact->business_name)<div>{{ $invoice->contact->business_name }}</div>@endif
            @if ($invoice->customer_address)<div class="muted">{{ $invoice->customer_address }}</div>@endif
        </div>

        <table>
            <thead>
            <tr>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Unit</th>
                <th class="right">Tax %</th>
                <th class="right">Total</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->tax_rate, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div style="margin-top:16px;text-align:right;">
            <div class="muted">Subtotal: {{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</div>
            <div class="muted">Tax: {{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</div>
            <div style="font-size:18px;font-weight:700;margin-top:4px;">Total: {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</div>
        </div>

        @if ($invoice->notes)
            <p style="margin-top:20px;"><strong>Notes</strong><br>{!! nl2br(e($invoice->notes)) !!}</p>
        @endif
        @if ($invoice->terms_and_conditions)
            <p style="margin-top:12px;"><strong>Terms and conditions</strong><br>{!! nl2br(e($invoice->terms_and_conditions)) !!}</p>
        @endif
        @if ($business->bank_details)
            <p style="margin-top:12px;"><strong>Payment details</strong><br>{!! nl2br(e($business->bank_details)) !!}</p>
        @endif
    </div>
</div>
</body>
</html>
