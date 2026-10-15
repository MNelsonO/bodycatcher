<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; }
        .muted { color: #64748b; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .logo { max-height: 64px; max-width: 180px; }
        h1 { font-size: 22px; margin: 0 0 4px; color: #1d4ed8; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.items th, table.items td { border-bottom: 1px solid #cbd5e1; padding: 8px; text-align: left; }
        table.items th { background: #eff6ff; }
        .right { text-align: right; }
        .totals { width: 280px; margin-left: auto; margin-top: 16px; }
        .totals td { padding: 4px 0; }
        .badge { display: inline-block; padding: 2px 8px; border: 1px solid #1d4ed8; color: #1d4ed8; border-radius: 999px; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if (!empty($logoPath) && file_exists($logoPath))
                    <img class="logo" src="{{ $logoPath }}" alt="Logo">
                @endif
                <h1>{{ $business->trading_name ?: $business->legal_name }}</h1>
                @foreach ($business->addressLines() as $line)
                    <div class="muted">{{ $line }}</div>
                @endforeach
                @if ($business->phone)<div class="muted">{{ $business->phone }}</div>@endif
                @if ($business->email)<div class="muted">{{ $business->email }}</div>@endif
                @if ($business->tax_id)<div class="muted">Tax ID: {{ $business->tax_id }}</div>@endif
            </td>
            <td class="right">
                <div style="font-size: 18px; font-weight: bold;">INVOICE</div>
                <div>{{ $invoice->number }}</div>
                <div class="badge">{{ $invoice->status->value }}</div>
                <div class="muted" style="margin-top: 8px;">Issued {{ $invoice->issue_date?->toDateString() }}</div>
                <div class="muted">Due {{ $invoice->due_date?->toDateString() }}</div>
            </td>
        </tr>
    </table>

    <div>
        <strong>Bill to</strong>
        <div>{{ $invoice->contact->full_name }}</div>
        @if ($invoice->contact->business_name)<div>{{ $invoice->contact->business_name }}</div>@endif
        @if ($invoice->customer_address)<div class="muted">{{ $invoice->customer_address }}</div>@endif
        @if ($invoice->contact->email)<div class="muted">{{ $invoice->contact->email }}</div>@endif
        <div class="muted">{{ $invoice->contact->primary_phone }}</div>
    </div>

    <table class="items">
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

    <table class="totals">
        <tr>
            <td class="muted">Subtotal</td>
            <td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="muted">Tax</td>
            <td class="right">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Total</strong></td>
            <td class="right"><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</strong></td>
        </tr>
    </table>

    @if ($invoice->notes)
        <p style="margin-top: 24px;"><strong>Notes</strong><br>{!! nl2br(e($invoice->notes)) !!}</p>
    @endif

    @if ($invoice->terms_and_conditions)
        <p style="margin-top: 16px;"><strong>Terms and conditions</strong><br>{!! nl2br(e($invoice->terms_and_conditions)) !!}</p>
    @endif

    @if ($business->bank_details)
        <p style="margin-top: 16px;"><strong>Payment details</strong><br>{!! nl2br(e($business->bank_details)) !!}</p>
    @endif
</body>
</html>
