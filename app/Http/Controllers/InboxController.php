<?php

namespace App\Http\Controllers;

use App\Contacts\Contact;
use App\Conversations\Actions\LogMessage;
use App\Conversations\Actions\ScheduleMessage;
use App\Conversations\Actions\StartConversation;
use App\Conversations\Conversation;
use App\Conversations\MessageDirection;
use App\Conversations\MessageOrigin;
use App\Conversations\Services\InboxData;
use App\Leads\Lead;
use App\Models\User;
use App\Settings\DisplayClock;
use App\Settings\Product;
use App\WhatsApp\SendOutcomeToast;
use App\WhatsApp\Services\SendWhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(Request $request, InboxData $inbox): Response
    {
        $this->authorize('viewAny', Lead::class);

        return $this->page($request, $inbox, null);
    }

    public function show(Request $request, Conversation $conversation, InboxData $inbox): Response
    {
        $this->authorize('view', $conversation->lead);

        $conversation->markRead();

        return $this->page($request, $inbox, $conversation);
    }

    public function store(Request $request, StartConversation $action): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $data = $request->validate([
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required_without:contact_id', 'nullable', 'string', 'max:30'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $conversation = $action->handle([
            'contact_id' => isset($data['contact_id']) ? (int) $data['contact_id'] : null,
            'full_name' => $data['full_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'product_id' => isset($data['product_id']) ? (int) $data['product_id'] : null,
        ], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Chat ready. Write the first message below.']);

        return redirect()->route('inbox.show', $conversation);
    }

    public function message(Request $request, Conversation $conversation, SendWhatsAppMessage $sender, LogMessage $logger): RedirectResponse
    {
        $lead = $conversation->lead;
        $this->authorize('update', $lead);

        $data = $request->validate([
            'mode' => ['required', Rule::in(['whatsapp', 'log_outbound', 'log_inbound'])],
            'body' => ['required', 'string', 'max:4096'],
            'idempotency_key' => ['required_if:mode,whatsapp', 'nullable', 'string', 'max:100'],
            'sent_at' => ['nullable', 'date'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        if ($data['mode'] === 'whatsapp') {
            $outcome = $sender->send($lead, $data['body'], $actor, MessageOrigin::Manual, (string) $data['idempotency_key']);
            SendOutcomeToast::flash($outcome);
        } else {
            $sentAt = isset($data['sent_at']) && is_string($data['sent_at']) && $data['sent_at'] !== ''
                ? Carbon::parse($data['sent_at'], app(DisplayClock::class)->timezone())->utc()->toDateTimeString()
                : null;

            $logger->handle(
                $lead,
                $data['mode'] === 'log_inbound' ? MessageDirection::Inbound : MessageDirection::Outbound,
                $data['body'],
                $sentAt,
                $actor,
            );

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Message added to the history. Nothing was sent.']);
        }

        $conversation->refresh()->markRead();

        return redirect()->route('inbox.show', $conversation);
    }

    public function schedule(Request $request, Conversation $conversation, ScheduleMessage $action): RedirectResponse
    {
        $lead = $conversation->lead;
        $this->authorize('update', $lead);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
            'send_at' => ['required', 'date'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $task = $action->handle($lead, $data['body'], $data['send_at'], $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Scheduled. It sends when follow-ups:run next passes after '.app(DisplayClock::class)->formatDateTime($task->due_at).'.',
        ]);

        return redirect()->route('inbox.show', $conversation);
    }

    private function page(Request $request, InboxData $inbox, ?Conversation $conversation): Response
    {
        return Inertia::render('inbox/index', [
            'conversations' => fn () => $inbox->conversations($request),
            'thread' => fn () => $conversation === null ? null : $inbox->thread($conversation),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'filter' => $request->string('filter')->toString() ?: 'all',
            ],
            'contacts' => Inertia::optional(fn () => Contact::query()
                ->whereNull('merged_into_contact_id')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'business_name', 'primary_phone'])),
            'products' => fn () => Product::query()
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
