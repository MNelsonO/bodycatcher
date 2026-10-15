<?php

namespace App\Console\Commands;

use App\FollowUps\Services\FollowUpEngine;
use Illuminate\Console\Command;

class RunFollowUps extends Command
{
    protected $signature = 'follow-ups:run';

    protected $description = 'Schedule follow-up tasks and send the ones that are due';

    public function handle(FollowUpEngine $engine): int
    {
        $engine->run();

        return self::SUCCESS;
    }
}
