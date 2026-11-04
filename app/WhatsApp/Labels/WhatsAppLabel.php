<?php

namespace App\WhatsApp\Labels;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A label from the WhatsApp Business app ("Follow up", "Order complete"…).
 *
 * @property int $id
 * @property string $label_id
 * @property string $name
 * @property string|null $color
 * @property bool $deleted
 */
#[Fillable(['label_id', 'name', 'color', 'deleted'])]
class WhatsAppLabel extends Model
{
    protected $table = 'whatsapp_labels';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['deleted' => 'boolean'];
    }

    /**
     * WhatsApp Business colour index to a readable colour.
     */
    public function hex(): string
    {
        $palette = ['#ff9dff', '#93ceac', '#ffd429', '#6bcbef', '#ff9485', '#a5b337', '#d7a5ff', '#f0b330', '#53bdeb', '#7bc8a4', '#ff8a8c', '#fbe36c', '#c4b5fd', '#fcbf49', '#a3e635', '#f472b6', '#60a5fa', '#34d399', '#fb923c', '#e879f9'];

        return is_numeric($this->color) ? $palette[((int) $this->color) % count($palette)] : '#94a3b8';
    }
}
