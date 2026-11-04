<?php

namespace App\Console\Commands;

use App\WhatsApp\Labels\LabelSync;
use Illuminate\Console\Command;

class ReapplyWhatsAppLabels extends Command
{
    protected $signature = 'whatsapp:labels-reapply';

    protected $description = 'Apply WhatsApp label rules (follow-up, won, lost) to every labelled contact.';

    public function handle(LabelSync $labels): int
    {
        $this->info('Applied label rules to '.$labels->reapply().' leads.');

        return self::SUCCESS;
    }
}
