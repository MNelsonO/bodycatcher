<?php

namespace App\Http\Controllers;

use App\Leads\Interest\AdReport;
use App\Leads\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdReportController extends Controller
{
    public function index(Request $request, AdReport $report): Response
    {
        $this->authorize('viewAny', Lead::class);
        $range = $request->string('range')->toString();
        $days = match ($range) {
            '7' => 7,
            '30' => 30,
            '90' => 90,
            default => null,
        };

        return Inertia::render('ads/index', [
            'range' => $days === null ? 'all' : (string) $days,
            'report' => $report->build($days),
        ]);
    }
}
