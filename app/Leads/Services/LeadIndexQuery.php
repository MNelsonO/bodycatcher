<?php

namespace App\Leads\Services;

use App\Contacts\InvalidPhoneNumber;
use App\Contacts\PhoneNormalizer;
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
            ->with(['contact.phones', 'product', 'currentStage', 'pipeline']);

        $followUp = $request->string('follow_up')->toString();

        if ($followUp !== '' && $followUp !== 'all') {
            $this->followUps->apply($query, $followUp);
        }

        if ($followUp === 'ranked' || $followUp === 'chat') {
            $query->orderByDesc('follow_up_rank')->orderBy('next_follow_up_on');
        } else {
            $query->orderByDesc('updated_at');
        }

        $productId = $request->string('product_id')->toString();

        if ($productId !== '' && $productId !== 'all') {
            $query->where('product_id', (int) $productId);
        }

        $stageId = $request->string('stage_id')->toString();

        if ($stageId !== '' && $stageId !== 'all') {
            $query->where('current_stage_id', (int) $stageId);
        }

        $kind = $request->string('kind')->toString();

        if ($kind !== '' && $kind !== 'all') {
            $query->whereHas('currentStage', fn (Builder $stage) => $stage->where('kind', $kind));
        }

        $created = $request->string('created')->toString();

        if ($created === 'today') {
            [$start, $end] = $this->clock->todayUtcRange();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $source = $request->string('source')->toString();

        if ($source !== '' && $source !== 'all') {
            $query->where('source', $source);
        }

        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $phoneNeedles = $this->phoneNeedles($search);

            $query->whereHas('contact', function (Builder $contact) use ($needle, $phoneNeedles): void {
                $contact->where(function (Builder $match) use ($needle, $phoneNeedles): void {
                    $match->whereRaw('lower(full_name) like ?', [$needle])
                        ->orWhereRaw('lower(coalesce(business_name, \'\')) like ?', [$needle])
                        ->orWhereHas('phones', function (Builder $phones) use ($needle, $phoneNeedles): void {
                            $phones->where(function (Builder $phone) use ($needle, $phoneNeedles): void {
                                $phone->whereRaw('lower(phone) like ?', [$needle]);

                                foreach ($phoneNeedles as $candidate) {
                                    $phone->orWhere('phone', 'like', '%'.$candidate.'%');
                                }
                            });
                        });
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
            'follow_up_rank' => (int) $lead->follow_up_rank,
            'follow_up_rank_reason' => $lead->follow_up_rank_reason,
            'follow_up_source' => $lead->follow_up_source,
        ];
    }

    /**
     * Local Kenyan numbers are stored as +254. A search for 07… must still match.
     *
     * @return list<string>
     */
    private function phoneNeedles(string $search): array
    {
        $digits = preg_replace('/\D+/', '', $search) ?? '';
        $needles = [];

        if ($digits !== '') {
            $needles[] = $digits;

            if (str_starts_with($digits, '0')) {
                $national = substr($digits, 1);

                if ($national !== '') {
                    $needles[] = $national;
                    $needles[] = '254'.$national;
                }
            }
        }

        try {
            $needles[] = ltrim((new PhoneNormalizer)->normalize($search), '+');
        } catch (InvalidPhoneNumber) {
            // A fragment such as 0722 is not a full number. Digit variants above cover it.
        }

        return array_values(array_unique($needles));
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
