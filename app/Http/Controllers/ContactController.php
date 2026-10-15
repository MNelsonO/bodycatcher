<?php

namespace App\Http\Controllers;

use App\Contacts\Actions\CreateOrUpdateContact;
use App\Contacts\Actions\MergeContacts;
use App\Contacts\ConsentStatus;
use App\Contacts\Contact;
use App\Contacts\PreferredChannel;
use App\Http\Requests\ContactRequest;
use App\Http\Requests\MergeContactRequest;
use App\Models\User;
use App\Settings\DisplayClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request, DisplayClock $clock): Response
    {
        $this->authorize('viewAny', Contact::class);

        $search = trim($request->string('q')->toString());

        $contacts = Contact::query()
            ->whereNull('merged_into_contact_id')
            ->with('phones')
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';
                $digits = preg_replace('/\D+/', '', $search) ?? '';
                $query->where(function ($inner) use ($needle, $digits): void {
                    $inner->whereRaw('lower(full_name) like ?', [$needle])
                        ->orWhereRaw('lower(coalesce(business_name, \'\')) like ?', [$needle])
                        ->orWhereHas('phones', function ($phones) use ($needle, $digits): void {
                            $phones->whereRaw('lower(phone) like ?', [$needle]);
                            if ($digits !== '') {
                                $phones->orWhere('phone', 'like', '%'.$digits.'%');
                            }
                        });
                });
            })
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Contact $contact): array => $this->summary($contact, $clock));

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Contact::class);

        return Inertia::render('contacts/create', [
            'contact' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(ContactRequest $request, CreateOrUpdateContact $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $result = $action->handle($request->validated(), $actor);

        $message = $result->matchedExisting
            ? 'That phone already belonged to a contact. The existing record was updated.'
            : 'Contact saved.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('contacts.show', $result->contact);
    }

    public function show(Contact $contact, DisplayClock $clock): Response|RedirectResponse
    {
        $this->authorize('view', $contact);

        if ($contact->merged_into_contact_id !== null) {
            return redirect()->route('contacts.show', $contact->merged_into_contact_id);
        }

        $contact->load(['phones', 'leads.product', 'leads.currentStage']);

        $others = Contact::query()
            ->whereNull('merged_into_contact_id')
            ->whereKeyNot($contact->id)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'business_name', 'primary_phone']);

        return Inertia::render('contacts/show', [
            'contact' => $this->summary($contact, $clock),
            'leads' => $contact->leads->map(fn ($lead): array => [
                'id' => $lead->id,
                'product_name' => $lead->product->name,
                'stage_name' => $lead->currentStage->name,
                'outcome' => $lead->outcome(),
            ])->all(),
            'merge_candidates' => $others->map(fn (Contact $candidate): array => [
                'id' => $candidate->id,
                'label' => $candidate->full_name.' · '.$candidate->primary_phone.($candidate->business_name ? ' · '.$candidate->business_name : ''),
            ])->all(),
        ]);
    }

    public function edit(Contact $contact): Response|RedirectResponse
    {
        $this->authorize('update', $contact);

        if ($contact->merged_into_contact_id !== null) {
            return redirect()->route('contacts.show', $contact->merged_into_contact_id);
        }

        $contact->load('phones');

        return Inertia::render('contacts/edit', [
            'contact' => [
                'id' => $contact->id,
                'full_name' => $contact->full_name,
                'primary_phone' => $contact->primary_phone,
                'additional_phones' => $contact->phones->where('is_primary', false)->pluck('phone')->implode("\n"),
                'business_name' => $contact->business_name,
                'business_type' => $contact->business_type,
                'location' => $contact->location,
                'email' => $contact->email,
                'preferred_channel' => $contact->preferred_channel->value,
                'consent_status' => $contact->consent_status->value,
                'lead_source' => $contact->lead_source,
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(ContactRequest $request, Contact $contact, CreateOrUpdateContact $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $result = $action->handle($request->validated(), $actor, $contact);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contact updated.']);

        return redirect()->route('contacts.show', $result->contact);
    }

    public function merge(MergeContactRequest $request, Contact $contact, MergeContacts $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $merged = Contact::query()->findOrFail($request->integer('merged_contact_id'));
        $survivor = $action->handle($contact, $merged, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contacts merged. Message history stayed with the surviving contact.']);

        return redirect()->route('contacts.show', $survivor);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Contact $contact, DisplayClock $clock): array
    {
        return [
            'id' => $contact->id,
            'full_name' => $contact->full_name,
            'primary_phone' => $contact->primary_phone,
            'additional_phones' => $contact->relationLoaded('phones')
                ? $contact->phones->where('is_primary', false)->pluck('phone')->values()->all()
                : [],
            'business_name' => $contact->business_name,
            'business_type' => $contact->business_type,
            'location' => $contact->location,
            'email' => $contact->email,
            'preferred_channel' => $contact->preferred_channel->value,
            'consent_status' => $contact->consent_status->value,
            'opted_out_at_label' => $clock->formatDateTime($contact->opted_out_at),
            'lead_source' => $contact->lead_source,
            'created_at_label' => $clock->formatDateTime($contact->created_at),
        ];
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function options(): array
    {
        return [
            'channels' => collect(PreferredChannel::cases())->map(fn (PreferredChannel $channel): array => [
                'value' => $channel->value,
                'label' => match ($channel) {
                    PreferredChannel::Whatsapp => 'WhatsApp',
                    PreferredChannel::Phone => 'Phone',
                    PreferredChannel::Email => 'Email',
                },
            ])->all(),
            'consent' => collect(ConsentStatus::cases())->map(fn (ConsentStatus $status): array => [
                'value' => $status->value,
                'label' => match ($status) {
                    ConsentStatus::Unknown => 'Unknown',
                    ConsentStatus::OptedIn => 'Opted in',
                    ConsentStatus::OptedOut => 'Opted out',
                },
            ])->all(),
        ];
    }
}
