<?php

namespace App\Contacts;

use App\Conversations\Message;
use App\Leads\Lead;
use App\Settings\DisplayClock;
use App\Settings\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ContactListQuery
{
    public function __construct(private DisplayClock $clock) {}

    /**
     * @param  array{q?: string, country?: string, product_id?: string|int|null, spoke?: string, scheduled?: string}  $filters
     * @return LengthAwarePaginator<int, Contact>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $country = strtoupper(trim((string) ($filters['country'] ?? '')));
        if ($country === '' || $country === '__ALL__') {
            $country = '';
        }

        $productId = isset($filters['product_id']) && is_numeric($filters['product_id'])
            ? (int) $filters['product_id']
            : 0;

        $spoke = trim((string) ($filters['spoke'] ?? ''));
        $scheduled = trim((string) ($filters['scheduled'] ?? ''));

        $query = Contact::query()
            ->whereNull('merged_into_contact_id')
            ->with([
                'phones',
                'leads' => fn ($leads) => $leads
                    ->whereNull('closed_at')
                    ->with(['product:id,name', 'currentStage:id,name'])
                    ->orderByDesc('updated_at'),
            ])
            ->addSelect([
                'last_spoke_at' => Message::query()
                    ->select('messages.sent_at')
                    ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
                    ->join('leads', 'leads.id', '=', 'conversations.lead_id')
                    ->whereColumn('leads.contact_id', 'contacts.id')
                    ->orderByDesc('messages.sent_at')
                    ->limit(1),
                'next_talk_at' => Lead::query()
                    ->select('next_conversation_at')
                    ->whereColumn('leads.contact_id', 'contacts.id')
                    ->whereNull('closed_at')
                    ->whereNotNull('next_conversation_at')
                    ->orderBy('next_conversation_at')
                    ->limit(1),
            ])
            ->when($search !== '', function (Builder $inner) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';
                $digits = preg_replace('/\D+/', '', $search) ?? '';
                $inner->where(function (Builder $match) use ($needle, $digits): void {
                    $match->whereRaw('lower(full_name) like ?', [$needle])
                        ->orWhereRaw('lower(coalesce(business_name, \'\')) like ?', [$needle])
                        ->orWhereRaw('lower(coalesce(country, \'\')) like ?', [$needle])
                        ->orWhereRaw('lower(coalesce(conversation_area, \'\')) like ?', [$needle])
                        ->orWhereHas('phones', function (Builder $phones) use ($needle, $digits): void {
                            $phones->whereRaw('lower(phone) like ?', [$needle]);
                            if ($digits !== '') {
                                $phones->orWhere('phone', 'like', '%'.$digits.'%');
                            }
                        })
                        ->orWhereHas('leads', function (Builder $leads) use ($needle): void {
                            $leads->whereNull('closed_at')
                                ->where(function (Builder $leadMatch) use ($needle): void {
                                    $leadMatch->whereRaw('lower(coalesce(business_problem, \'\')) like ?', [$needle])
                                        ->orWhereHas('product', fn (Builder $product) => $product->whereRaw('lower(name) like ?', [$needle]))
                                        ->orWhereHas('currentStage', fn (Builder $stage) => $stage->whereRaw('lower(name) like ?', [$needle]));
                                });
                        });
                });
            })
            ->when($country !== '', fn (Builder $inner) => $inner->where('country_code', $country))
            ->when($productId > 0, function (Builder $inner) use ($productId): void {
                $inner->whereHas('leads', function (Builder $leads) use ($productId): void {
                    $leads->whereNull('closed_at')->where('product_id', $productId);
                });
            })
            ->when($this->clock->periodUtcRange($spoke) !== null, function (Builder $inner) use ($spoke): void {
                [$start, $end] = $this->clock->periodUtcRange($spoke);
                $inner->whereExists(function ($exists) use ($start, $end): void {
                    $exists->selectRaw('1')
                        ->from('messages')
                        ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
                        ->join('leads', 'leads.id', '=', 'conversations.lead_id')
                        ->whereColumn('leads.contact_id', 'contacts.id')
                        ->whereBetween('messages.sent_at', [$start, $end]);
                });
            })
            ->when($this->clock->periodUtcRange($scheduled) !== null, function (Builder $inner) use ($scheduled): void {
                [$start, $end] = $this->clock->periodUtcRange($scheduled);
                $inner->whereHas('leads', function (Builder $leads) use ($start, $end): void {
                    $leads->whereNull('closed_at')
                        ->whereNotNull('next_conversation_at')
                        ->whereBetween('next_conversation_at', [$start, $end]);
                });
            })
            ->orderBy('full_name');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function productOptions(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $product): array => [
                'value' => (string) $product->id,
                'label' => $product->name,
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function spokeOptions(): array
    {
        return [
            ['value' => '__all__', 'label' => 'Any last talk'],
            ['value' => 'today', 'label' => 'Spoke today'],
            ['value' => 'this_week', 'label' => 'Spoke this week'],
            ['value' => 'this_month', 'label' => 'Spoke this month'],
            ['value' => 'last_7_days', 'label' => 'Spoke last 7 days'],
            ['value' => 'last_30_days', 'label' => 'Spoke last 30 days'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function scheduledOptions(): array
    {
        return [
            ['value' => '__all__', 'label' => 'Any scheduled talk'],
            ['value' => 'today', 'label' => 'Talk today'],
            ['value' => 'this_week', 'label' => 'Talk this week'],
            ['value' => 'next_week', 'label' => 'Talk next week'],
            ['value' => 'this_month', 'label' => 'Talk this month'],
        ];
    }
}
