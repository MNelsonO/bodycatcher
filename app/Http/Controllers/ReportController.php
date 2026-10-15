<?php

namespace App\Http\Controllers;

use App\Reports\Services\DeskReports;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, DeskReports $reports): Response
    {
        $payload = $reports->build(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        return Inertia::render('reports/index', [
            'report' => $payload,
        ]);
    }
}
