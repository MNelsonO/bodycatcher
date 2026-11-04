<?php

use App\Conversations\Actions\ImportWhatsAppChat;
use App\Conversations\Message;
use Illuminate\Http\UploadedFile;

function androidExport(): string
{
    return implode("\n", [
        '03/11/2026, 09:15 - Messages and calls are end-to-end encrypted. No one outside of this chat can read them.',
        '03/11/2026, 09:15 - Amina Wanjiku: Habari, do you have a laundry POS?',
        '03/11/2026, 09:20 - Nelson Kemet: Yes we do. It handles tickets and M-Pesa.',
        'Would you like a demo this week?',
        '03/11/2026, 09:22 - Amina Wanjiku: <Media omitted>',
        '14/11/2026, 16:05 - Amina Wanjiku: Saturday works',
    ]);
}

it('imports both sides of an Android export with original Nairobi times', function () {
    $lead = whatsappOpenLead();
    $conversation = $lead->conversation()->firstOrFail();

    $this->post(route('inbox.import', $conversation), [
        'file' => UploadedFile::fake()->createWithContent('WhatsApp Chat with Amina.txt', androidExport()),
        'owner_name' => 'Nelson Kemet',
    ])->assertRedirect(route('inbox.show', $conversation))->assertSessionHasNoErrors();

    $messages = Message::query()->where('conversation_id', $conversation->id)->orderBy('sent_at')->get();

    expect($messages)->toHaveCount(4)
        ->and($messages[0]->direction->value)->toBe('inbound')
        ->and($messages[0]->sent_at->toDateTimeString())->toBe('2026-11-03 06:15:00')
        ->and($messages[1]->direction->value)->toBe('outbound')
        ->and($messages[1]->body)->toBe("Yes we do. It handles tickets and M-Pesa.\nWould you like a demo this week?")
        ->and($messages[2]->body)->toBe('[media]')
        ->and($messages[3]->sent_at->toDateTimeString())->toBe('2026-11-14 13:05:00');

    // Importing the same file again adds nothing.
    $this->post(route('inbox.import', $conversation), [
        'file' => UploadedFile::fake()->createWithContent('again.txt', androidExport()),
        'owner_name' => 'Nelson Kemet',
    ]);

    expect(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(4);
});

it('skips client messages the desk already received from the webhook', function () {
    $lead = whatsappOpenLead();
    $conversation = $lead->conversation()->firstOrFail();
    Message::query()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'channel' => 'whatsapp',
        'origin' => 'inbound',
        'status' => 'received',
        'body' => 'Saturday works',
        'sent_at' => '2026-11-14 13:05:30',
        'provider_message_id' => 'wamid.existing',
    ]);

    $this->post(route('inbox.import', $conversation), [
        'file' => UploadedFile::fake()->createWithContent('chat.txt', androidExport()),
        'owner_name' => 'Nelson Kemet',
    ]);

    expect(Message::query()->where('conversation_id', $conversation->id)->where('body', 'Saturday works')->count())->toBe(1)
        ->and(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(4);
});

it('reads an iPhone zip export with am/pm times and suggests who the owner is', function () {
    $lead = whatsappOpenLead();
    $conversation = $lead->conversation()->firstOrFail();
    $text = implode("\r\n", [
        "\u{200E}[11/3/26, 9:15:02\u{202F}AM] Amina Wanjiku: \u{200E}Messages and calls are end-to-end encrypted.",
        '[11/3/26, 9:15:40 AM] Amina Wanjiku: Habari, do you have a laundry POS?',
        '[11/3/26, 1:20:05 PM] Nelson: Yes, want a demo?',
        "[11/3/26, 1:22:00 PM] Amina Wanjiku: \u{200E}image omitted",
    ]);

    $zipPath = tempnam(sys_get_temp_dir(), 'chat').'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('_chat.txt', $text);
    $zip->close();

    $this->postJson(route('inbox.import.preview', $conversation), [
        'file' => new UploadedFile($zipPath, 'WhatsApp Chat - Amina.zip', 'application/zip', null, true),
    ])->assertOk()
        ->assertJsonPath('senders', ['Amina Wanjiku', 'Nelson'])
        ->assertJsonPath('suggested_owner', 'Nelson')
        ->assertJsonPath('date_order_ambiguous', true);

    $this->post(route('inbox.import', $conversation), [
        'file' => new UploadedFile($zipPath, 'WhatsApp Chat - Amina.zip', 'application/zip', null, true),
        'owner_name' => 'Nelson',
        'date_order' => 'mdy',
    ])->assertRedirect();

    $mine = Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->firstOrFail();

    expect($mine->body)->toBe('Yes, want a demo?')
        ->and($mine->sent_at->toDateTimeString())->toBe('2026-11-03 10:20:05')
        ->and(Message::query()->where('conversation_id', $conversation->id)->count())->toBe(4)
        ->and($conversation->refresh()->last_read_at)->not->toBeNull();
});

it('rejects files that are not chat exports', function () {
    $lead = whatsappOpenLead();
    $conversation = $lead->conversation()->firstOrFail();

    $this->post(route('inbox.import', $conversation), [
        'file' => UploadedFile::fake()->createWithContent('notes.txt', "just some notes\nnothing here"),
        'owner_name' => 'Nelson',
    ])->assertSessionHasErrors('file');

    $this->post(route('inbox.import', $conversation), [
        'file' => UploadedFile::fake()->createWithContent('photo.jpg', 'x'),
        'owner_name' => 'Nelson',
    ])->assertSessionHasErrors('file');
});

it('works out the date order when a day above 12 proves it', function () {
    $import = app(ImportWhatsAppChat::class);

    $us = $import->parse("[11/3/26, 9:15 AM] A: hi\n[11/14/26, 9:16 AM] B: hello");
    $ke = $import->parse("03/11/2026, 09:15 - A: hi\n14/11/2026, 09:16 - B: hello");

    expect($us['order'])->toBe('mdy')->and($us['ambiguous'])->toBeFalse()
        ->and($us['rows'][0]['sent_at']->toDateString())->toBe('2026-11-03')
        ->and($ke['order'])->toBe('dmy')
        ->and($ke['rows'][0]['sent_at']->toDateString())->toBe('2026-11-03');
});
