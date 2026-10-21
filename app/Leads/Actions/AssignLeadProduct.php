<?php

namespace App\Leads\Actions;

use App\Leads\ActivityEvent;
use App\Leads\Lead;
use App\Models\User;
use App\Settings\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignLeadProduct
{
    public function __construct(private RecordActivity $activity) {}

    public function handle(Lead $lead, int $productId, User $actor): Lead
    {
        if ($lead->product_id === $productId) {
            return $lead;
        }

        $product = Product::query()->findOrFail($productId);

        if (! $product->is_active || $product->archived_at !== null) {
            throw ValidationException::withMessages([
                'product_id' => 'Choose an active product.',
            ]);
        }

        $conflict = Lead::query()
            ->where('contact_id', $lead->contact_id)
            ->where('product_id', $product->id)
            ->whereNull('closed_at')
            ->whereKeyNot($lead->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'product_id' => 'This contact already has an open lead for that product.',
            ]);
        }

        return DB::transaction(function () use ($lead, $product, $actor): Lead {
            $from = $lead->product?->name ?? 'Unknown';

            $lead->product_id = $product->id;
            $lead->save();

            $conversation = $lead->conversation;

            if ($conversation !== null) {
                $conversation->focused_product_id = $product->id;
                $conversation->save();
            }

            $this->activity->handle(ActivityEvent::ProductChanged, $lead, $lead->contact, $actor, [
                'from_product' => $from,
                'to_product' => $product->name,
            ]);

            return $lead->refresh();
        });
    }
}
