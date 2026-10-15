<?php

namespace App\Http\Controllers;

use App\Leads\Services\CommandCentre;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(CommandCentre $commandCentre): Response
    {
        return Inertia::render('dashboard', [
            'metrics' => $commandCentre->metrics(),
        ]);
    }
}
