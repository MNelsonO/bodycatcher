<?php

namespace App\Conversations\Actions;

use App\Conversations\Conversation;
use App\Conversations\Message;
use App\Conversations\MessageChannel;
use App\Conversations\MessageDirection;
use App\Conversations\MessageOrigin;
use App\Conversations\MessageStatus;
use App\Leads\Actions\RecordActivity;
use App\Leads\ActivityEvent;
use App\Models\User;
use App\Settings\DisplayClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Imports a WhatsApp "Export chat" file (Android .txt or iPhone .zip/.txt)
 * into one desk conversation. Lines written by the owner become outbound,
 * everything else inbound. Messages already in the desk are skipped, and
 * nothing is sent or triggered: no auto-replies, follow-ups or opt-outs.
 */
class ImportWhatsAppChat
{
    // Android: "04/11/2026, 18:31 - Name: text"   iPhone: "[04/11/2026, 18:31:22] Name: text"
    private const LINE = '/^\[?(\d{1,4})[\/.\-](\d{1,2})[\/.\-](\d{1,4}),?\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([ap]\.?\s?m\.?)?\]?\s*(?:-\s+)?(.*)$/iu';

    public function __construct(
        private DisplayClock $clock,
        private RecordActivity $activity,
    ) {}

    /**
     * Reads the uploaded file into chat text. iPhone exports arrive as a zip
     * holding _chat.txt.
     */
    public function readFile(string $path, string $extension): string
    {
        if ($extension === 'zip') {
            $zip = new ZipArchive;

            if ($zip->open($path) !== true) {
                throw ValidationException::withMessages(['file' => 'That zip file could not be opened.']);
            }

            $text = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if (str_ends_with(mb_strtolower($name), '.txt')) {
                    $text = $zip->getFromIndex($i);
                    break;
                }
            }

            $zip->close();

            if (! is_string($text)) {
                throw ValidationException::withMessages(['file' => 'No chat text was found in that zip.']);
            }

            return $text;
        }

        return (string) file_get_contents($path);
    }

    /**
     * @return list<string>
     */
    public function senders(string $text): array
    {
        $senders = [];

        foreach ($this->parse($text)['rows'] as $row) {
            $senders[$row['sender']] = true;
        }

        return array_keys($senders);
    }

    /**
     * @return array{total: int, imported: int, skipped: int, mine: int, theirs: int}
     */
    public function handle(Conversation $conversation, string $text, string $ownerName, User $actor, ?string $order = null): array
    {
        $rows = $this->parse($text, $order)['rows'];

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'No WhatsApp messages were found. Export the chat from WhatsApp as "Without media" and upload that file.']);
        }

        $owner = $this->normaliseName($ownerName);
        $result = ['total' => count($rows), 'imported' => 0, 'skipped' => 0, 'mine' => 0, 'theirs' => 0];
        $newest = null;

        DB::transaction(function () use ($conversation, $rows, $owner, &$result, &$newest): void {
            foreach ($rows as $row) {
                $outbound = $this->normaliseName($row['sender']) === $owner;
                $direction = $outbound ? MessageDirection::Outbound : MessageDirection::Inbound;
                $sentAt = $row['sent_at'];

                $exists = Message::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('direction', $direction->value)
                    ->where('body', $row['body'])
                    ->whereBetween('sent_at', [$sentAt->subMinutes(2), $sentAt->addMinutes(2)])
                    ->exists();

                if ($exists) {
                    $result['skipped']++;

                    continue;
                }

                Message::query()->create([
                    'conversation_id' => $conversation->id,
                    'direction' => $direction,
                    'channel' => MessageChannel::WhatsApp,
                    'origin' => $outbound ? MessageOrigin::Manual : MessageOrigin::Inbound,
                    'status' => $outbound ? MessageStatus::Sent : MessageStatus::Received,
                    'body' => $row['body'],
                    'sent_at' => $sentAt,
                    'logged_by_user_id' => null,
                    'retryable' => false,
                    'send_attempts' => 0,
                ]);

                $result['imported']++;
                $result[$outbound ? 'mine' : 'theirs']++;

                if ($newest === null || $sentAt->greaterThan($newest)) {
                    $newest = $sentAt;
                }
            }
        });

        // Imported history is history: it should not light up as unread.
        $conversation->refresh();

        if ($newest !== null && ($conversation->last_read_at === null || $conversation->last_read_at->lessThan($newest))) {
            $conversation->last_read_at = $newest;
            $conversation->saveQuietly();
        }

        if ($result['imported'] > 0) {
            $lead = $conversation->lead;
            $this->activity->handle(ActivityEvent::ChatImported, $lead, $lead->contact, $actor, [
                'imported' => $result['imported'],
                'mine' => $result['mine'],
                'theirs' => $result['theirs'],
            ]);
        }

        return $result;
    }

    /**
     * @return array{rows: list<array{sent_at: CarbonImmutable, sender: string, body: string}>, order: string, ambiguous: bool}
     */
    public function parse(string $text, ?string $order = null): array
    {
        $text = $this->toUtf8($text);
        // Strip direction marks and odd spaces WhatsApp inserts.
        $text = str_replace(["\u{200E}", "\u{200F}", "\u{202A}", "\u{202C}", "\u{FEFF}"], '', $text);
        $text = str_replace(["\u{202F}", "\u{00A0}"], ' ', $text);
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];

        $raw = [];

        foreach ($lines as $line) {
            if (preg_match(self::LINE, $line, $m) === 1) {
                $rest = $m[8];
                $colon = mb_strpos($rest, ': ');

                // "Messages and calls are end-to-end encrypted", "X joined", ...
                if ($colon === false) {
                    $raw[] = null;

                    continue;
                }

                $raw[] = [
                    'a' => (int) $m[1],
                    'b' => (int) $m[2],
                    'c' => (int) $m[3],
                    'h' => (int) $m[4],
                    'i' => (int) $m[5],
                    's' => $m[6] !== '' ? (int) $m[6] : 0,
                    'ampm' => mb_strtolower(str_replace(['.', ' '], '', $m[7])),
                    'sender' => trim(mb_substr($rest, 0, $colon)),
                    'body' => mb_substr($rest, $colon + 2),
                ];

                continue;
            }

            // A continuation of a multi-line message.
            $last = array_key_last($raw);

            if ($last !== null && $raw[$last] !== null) {
                $raw[$last]['body'] .= "\n".$line;
            }
        }

        $entries = array_values(array_filter($raw));
        [$detected, $ambiguous] = $this->dateOrder($entries);
        $order = in_array($order, ['dmy', 'mdy'], true) ? $order : $detected;
        $dayFirst = $order === 'dmy';
        $rows = [];

        foreach ($entries as $entry) {
            $body = trim($entry['body']);
            $body = preg_replace('/^<?(Media omitted|image omitted|video omitted|audio omitted|sticker omitted|document omitted|GIF omitted)>?$/i', '[media]', $body) ?? $body;

            if ($body === '' || $body === 'This message was deleted' || $body === 'You deleted this message') {
                continue;
            }

            $sentAt = $this->moment($entry, $dayFirst);

            if ($sentAt === null) {
                continue;
            }

            $rows[] = ['sent_at' => $sentAt, 'sender' => $entry['sender'], 'body' => mb_substr($body, 0, 4096)];
        }

        return ['rows' => $rows, 'order' => $order, 'ambiguous' => $ambiguous];
    }

    /**
     * Exports use the phone's date order, which the file does not state.
     * A day above 12 settles it. Otherwise pick the order under which the
     * messages run forwards in time and none lands in the future; Kenya
     * writes the day first, so that wins a tie.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array{0: string, 1: bool}
     */
    private function dateOrder(array $entries): array
    {
        foreach ($entries as $entry) {
            if ($entry['a'] > 31) {
                return ['dmy', false]; // year first; handled in moment()
            }

            if ($entry['a'] > 12) {
                return ['dmy', false];
            }

            if ($entry['b'] > 12) {
                return ['mdy', false];
            }
        }

        $score = function (bool $dayFirst) use ($entries): int {
            $penalty = 0;
            $previous = null;
            $tomorrow = CarbonImmutable::now()->addDay();

            foreach ($entries as $entry) {
                $moment = $this->moment($entry, $dayFirst);

                if ($moment === null) {
                    $penalty += 5;

                    continue;
                }

                if ($moment->greaterThan($tomorrow)) {
                    $penalty += 3;
                }

                if ($previous !== null && $moment->lessThan($previous)) {
                    $penalty += 2;
                }

                $previous = $moment;
            }

            return $penalty;
        };

        $dmy = $score(true);
        $mdy = $score(false);

        if ($dmy === $mdy) {
            return ['dmy', true];
        }

        return [$dmy < $mdy ? 'dmy' : 'mdy', true];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function moment(array $entry, bool $dayFirst): ?CarbonImmutable
    {
        if ($entry['a'] > 31) {
            [$year, $month, $day] = [$entry['a'], $entry['b'], $entry['c']];
        } elseif ($dayFirst) {
            [$day, $month, $year] = [$entry['a'], $entry['b'], $entry['c']];
        } else {
            [$month, $day, $year] = [$entry['a'], $entry['b'], $entry['c']];
        }

        if ($year < 100) {
            $year += 2000;
        }

        $hour = $entry['h'];

        if ($entry['ampm'] === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($entry['ampm'] === 'am' && $hour === 12) {
            $hour = 0;
        }

        if (! checkdate($month, $day, $year) || $hour > 23 || $entry['i'] > 59) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, $hour, $entry['i'], $entry['s'], $this->clock->timezone())?->utc();
    }

    private function normaliseName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }

    private function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, "\xFF\xFE") || str_starts_with($contents, "\xFE\xFF")) {
            return mb_convert_encoding($contents, 'UTF-8', 'UTF-16');
        }

        return mb_check_encoding($contents, 'UTF-8') ? $contents : mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }
}
