<?php

namespace App\Console\Commands;

use App\WhatsApp\Labels\LabelSync;
use Illuminate\Console\Command;

/**
 * Loads chat→label links exported from the gateway ("jid|[\"7\",\"8\"]" per
 * line). Links for known contacts attach now; the rest wait until a live
 * message reveals who the chat is.
 */
class ImportWhatsAppLabelLinks extends Command
{
    protected $signature = 'whatsapp:labels-import {file}';

    protected $description = 'Import chat label links exported from the WhatsApp gateway.';

    public function handle(LabelSync $labels): int
    {
        $argument = $this->argument('file');
        $path = is_string($argument) ? $argument : '';

        if (! is_readable($path)) {
            $this->error('File not readable.');

            return self::FAILURE;
        }

        $result = ['linked' => 0, 'waiting' => 0, 'ignored' => 0];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$jid, $json] = array_pad(explode('|', $line, 2), 2, '[]');
            $ids = json_decode($json, true);

            foreach (is_array($ids) ? $ids : [] as $labelId) {
                $outcome = $labels->associate(trim($jid), (string) $labelId, true);
                $key = $outcome === 'linked' ? 'linked' : ($outcome === 'unknown-chat' ? 'waiting' : 'ignored');
                $result[$key]++;
            }
        }

        $this->info("Linked {$result['linked']}, waiting {$result['waiting']}, ignored {$result['ignored']}.");

        return self::SUCCESS;
    }
}
