<?php

namespace App\Http\Controllers;

use App\Billing\BusinessProfile;
use App\Billing\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SharedInvoiceController extends Controller
{
    public function show(string $token): View
    {
        $invoice = Invoice::query()
            ->with(['contact', 'items'])
            ->where('share_token', $token)
            ->firstOrFail();

        $invoice->refreshOpenStatus();

        return view('billing.invoice-share', [
            'invoice' => $invoice,
            'business' => BusinessProfile::current(),
        ]);
    }

    public function pdf(string $token): Response
    {
        $invoice = Invoice::query()
            ->with(['contact', 'items'])
            ->where('share_token', $token)
            ->firstOrFail();

        $profile = BusinessProfile::current();

        return Pdf::loadView('billing.invoice-pdf', [
            'invoice' => $invoice,
            'business' => $profile,
            'logoPath' => $profile->logo_path
                ? storage_path('app/public/'.$profile->logo_path)
                : null,
        ])->download($invoice->number.'.pdf');
    }
}
