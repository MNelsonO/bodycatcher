<?php

namespace App\Http\Controllers;

use App\Leads\Services\CommandCentre;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CommandCentre $commandCentre): Response
    {
        return Inertia::render('dashboard', [
            'metrics' => $commandCentre->metrics([
                'kind' => $request->string('kind')->toString(),
                'stage_id' => $request->string('stage_id')->toString(),
                'product_id' => $request->string('product_id')->toString(),
                'hot' => $request->string('hot')->toString(),
                'follow_up' => $request->string('follow_up')->toString(),
                'timeline' => $request->string('timeline')->toString(),
                'from' => $request->string('from')->toString() ?: null,
                'to' => $request->string('to')->toString() ?: null,
                'q' => $request->string('q')->toString(),
            ]),
        ]);
    }
}
