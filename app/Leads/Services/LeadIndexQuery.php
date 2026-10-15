<?php

namespace App\Leads\Services;

use App\Leads\Lead;
use App\Settings\DisplayClock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LeadIndexQuery
{
    public function __construct(
        private FollowUpQuery $followUps,
        private DisplayClock $clock,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $search = trim($request->string('q')->toString());

        $query = Lead::query()
            ->with(['contact.phones', 'product', 'currentStage', 'pipeline'])
            ->orderByDesc('updated_at');

        $followUp = $request->string('follow_up')->toString();

        if ($followUp !== '' && $followUp !== 'all') {
            $this->followUps->apply($query, $followUp);
        }

        $productId = $request->string('product_id')->toString();

        if ($productId !== '' && $productId !== 'all') {
            $query->where('product_id', (int) $productId);
        }

        $stageId = $request->string('stage_id')->toString();

        if ($stageId !== '' && $stageId !== 'all') {
            $query->where('current_stage_id', (int) $stageId);
        }

        $source = $request->string('source')->toString();

        if ($source !== '' && $source !== 'all') {
            $query->where('source', $source);
        }

        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $digits = preg_replace('/\D+/', '', $search) ?? '';

            $query->whereHas('contact', function (Builder $contact) use ($needle, $digits): void {
                $contact->whereRaw('lower(full_name) like ?', [$needle])
                    ->orWhereRaw('lower(coalesce(business_name, \'\')) like ?', [$needle])
                    ->orWhereHas('phones', function (Builder $phones) use ($needle, $digits): void {
                        $phones->whereRaw('lower(phone) like ?', [$needle]);

                        if ($digits !== '') {
                            $phones->orWhere('phone', 'like', '%'.$digits.'%');
                        }
                    });
            });
        }

        return $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Lead $lead): array => $this->row($lead));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'contact_name' => $lead->contact->full_name,
            'business_name' => $lead->contact->business_name,
            'phone' => $lead->contact->primary_phone,
            'product_name' => $lead->product->name,
            'pipeline_name' => $lead->pipeline->name,
            'stage_name' => $lead->currentStage->name,
            'stage_kind' => $lead->currentStage->kind->value,
            'outcome' => $lead->outcome(),
            'source' => $lead->source,
            'next_action' => $lead->next_action,
            'next_follow_up_on' => $lead->next_follow_up_on?->toDateString(),
            'next_follow_up_label' => $this->clock->formatDate($lead->next_follow_up_on),
            'follow_up_state' => $this->state($lead),
        ];
    }

    private function state(Lead $lead): string
    {
        $date = $lead->next_follow_up_on?->toDateString();

        if ($date === null) {
            return 'none';
        }

        $today = $this->clock->today();

        if ($date === $today) {
            return 'due_today';
        }

        return $date < $today ? 'overdue' : 'upcoming';
    }
}
