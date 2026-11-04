<?php

namespace App\Http\Controllers;

use App\Contacts\Contact;
use App\Conversations\Actions\ImportWhatsAppChat;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function rename(Request $request, Conversation $conversation): RedirectResponse
    {
        $contact = $conversation->contact;
        $this->authorize('update', $conversation->lead);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        $contact->full_name = trim($data['full_name']);
        $contact->business_name = isset($data['business_name']) && trim($data['business_name']) !== ''
            ? trim($data['business_name'])
            : null;
        $contact->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved. WhatsApp will not overwrite this name.']);

        return redirect()->route('inbox.show', $conversation);
    }

    public function importPreview(Request $request, Conversation $conversation, ImportWhatsAppChat $import): JsonResponse
    {
        $this->authorize('update', $conversation->lead);
        $text = $this->chatText($request, $import);
        $order = $request->string('date_order')->toString() ?: null;
        $parsed = $import->parse($text, $order);
        $senders = $import->senders($text);
        $contact = $conversation->contact;

        $matchesContact = function (string $sender) use ($contact): bool {
            $name = mb_strtolower(trim($sender));
            $digits = preg_replace('/\D+/', '', $sender) ?? '';
            $phone = preg_replace('/\D+/', '', (string) $contact->primary_phone) ?? '';

            return $name === mb_strtolower(trim((string) $contact->full_name))
                || ($digits !== '' && strlen($digits) >= 9 && str_ends_with($phone, substr($digits, -9)));
        };

        $theirs = array_values(array_filter($senders, $matchesContact));
        $suggested = count($senders) === 2 && count($theirs) === 1
            ? array_values(array_diff($senders, $theirs))[0]
            : null;

        $clock = app(DisplayClock::class);
        $first = $parsed['rows'][0]['sent_at'] ?? null;
        $last = $parsed['rows'] === [] ? null : $parsed['rows'][array_key_last($parsed['rows'])]['sent_at'];

        return response()->json([
            'senders' => $senders,
            'suggested_owner' => $suggested,
            'messages' => count($parsed['rows']),
            'date_order' => $parsed['order'],
            'date_order_ambiguous' => $parsed['ambiguous'],
            'first_label' => $clock->formatDateTime($first),
            'last_label' => $clock->formatDateTime($last),
        ]);
    }

    public function import(Request $request, Conversation $conversation, ImportWhatsAppChat $import): RedirectResponse
    {
        $this->authorize('update', $conversation->lead);
        $request->validate([
            'owner_name' => ['required', 'string', 'max:120'],
            'date_order' => ['nullable', 'in:dmy,mdy'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $result = $import->handle($conversation, $this->chatText($request, $import), $request->string('owner_name')->toString(), $actor, $request->string('date_order')->toString() ?: null);

        Inertia::flash('toast', [
            'type' => $result['imported'] > 0 ? 'success' : 'info',
            'message' => $result['imported'] > 0
                ? sprintf('Imported %d messages (%d yours, %d theirs). %d were already here.', $result['imported'], $result['mine'], $result['theirs'], $result['skipped'])
                : 'Every message in that file is already in this chat.',
        ]);

        return redirect()->route('inbox.show', $conversation);
    }

    private function chatText(Request $request, ImportWhatsAppChat $import): string
    {
        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $file = $request->file('file');
        $extension = mb_strtolower((string) $file?->getClientOriginalExtension());

        if (! in_array($extension, ['txt', 'zip'], true)) {
            throw ValidationException::withMessages(['file' => 'Upload the .txt (Android) or .zip (iPhone) file from WhatsApp "Export chat".']);
        }

        return $import->readFile((string) $file?->getRealPath(), $extension);
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
