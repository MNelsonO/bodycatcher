<?php

namespace App\Contacts\Actions;

use App\Contacts\ContactPhone;
use App\Contacts\InvalidPhoneNumber;
use App\Contacts\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Names desk contacts from a phone address-book export (vCard or CSV).
 *
 * Only contacts still showing a number (or a "WhatsApp …" placeholder) get a
 * name. A name someone typed at the desk is never replaced. Nothing is
 * created: numbers that are not already contacts are counted and skipped.
 */
class ImportContactNames
{
    public function __construct(private PhoneNormalizer $phones) {}

    /**
     * @return array{entries: int, matched: int, named: int, kept: int, unmatched: int, business: int}
     */
    public function handle(string $contents, string $extension): array
    {
        $contents = $this->toUtf8($contents);
        $entries = $extension === 'csv' || ($extension !== 'vcf' && ! str_contains($contents, 'BEGIN:VCARD'))
            ? $this->fromCsv($contents)
            : $this->fromVcard($contents);

        $result = ['entries' => count($entries), 'matched' => 0, 'named' => 0, 'kept' => 0, 'unmatched' => 0, 'business' => 0];
        $seen = [];

        DB::transaction(function () use ($entries, &$result, &$seen): void {
            foreach ($entries as $entry) {
                $contact = null;

                foreach ($entry['phones'] as $raw) {
                    try {
                        $phone = $this->phones->normalize($raw);
                    } catch (InvalidPhoneNumber) {
                        continue;
                    }

                    $match = ContactPhone::query()->where('phone', $phone)->first();

                    if ($match !== null) {
                        $contact = $match->contact->survivor();
                        break;
                    }
                }

                if ($contact === null) {
                    $result['unmatched']++;

                    continue;
                }

                if (isset($seen[$contact->id])) {
                    continue;
                }

                $seen[$contact->id] = true;
                $result['matched']++;
                $dirty = false;

                if ($this->isPlaceholder((string) $contact->full_name, (string) $contact->primary_phone)) {
                    $contact->full_name = $entry['name'];
                    $result['named']++;
                    $dirty = true;
                } else {
                    $result['kept']++;
                }

                if ($entry['business'] !== null && trim((string) $contact->business_name) === '') {
                    $contact->business_name = $entry['business'];
                    $result['business']++;
                    $dirty = true;
                }

                if ($dirty) {
                    $contact->save();
                }
            }
        });

        return $result;
    }

    public function isPlaceholder(string $name, string $primaryPhone): bool
    {
        $name = trim($name);

        return $name === ''
            || $name === $primaryPhone
            || preg_match('/^\+?[\d\s\-()]+$/', $name) === 1
            || str_starts_with($name, 'WhatsApp');
    }

    /**
     * @return list<array{name: string, phones: list<string>, business: ?string}>
     */
    private function fromVcard(string $contents): array
    {
        // Unfold: a line starting with a space or tab continues the previous
        // one; quoted-printable soft breaks end a line with "=".
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $contents = preg_replace("/\n[ \t]/", '', $contents) ?? $contents;
        $joined = [];
        $continuing = false;

        foreach (explode("\n", $contents) as $line) {
            if ($continuing) {
                $joined[array_key_last($joined)] = substr($joined[array_key_last($joined)], 0, -1).$line;
            } else {
                $joined[] = $line;
            }

            $current = $joined[array_key_last($joined)];
            $head = strtoupper((string) strstr($current, ':', true));
            $continuing = str_contains($head, 'QUOTED-PRINTABLE') && str_ends_with($current, '=');
        }

        $contents = implode("\n", $joined);

        $entries = [];

        foreach (preg_split('/BEGIN:VCARD/i', $contents) ?: [] as $card) {
            if (stripos($card, 'END:VCARD') === false) {
                continue;
            }

            $fn = null;
            $n = null;
            $org = null;
            $phones = [];

            foreach (explode("\n", $card) as $line) {
                $colon = strpos($line, ':');

                if ($colon === false) {
                    continue;
                }

                $head = strtoupper(substr($line, 0, $colon));
                $value = $this->decodeValue($head, substr($line, $colon + 1));
                $property = preg_replace('/^ITEM\d+\./', '', explode(';', $head)[0]) ?? '';

                match ($property) {
                    'FN' => $fn = $value,
                    'N' => $n = $value,
                    'ORG' => $org = $value,
                    'TEL' => $phones[] = $value,
                    default => null,
                };
            }

            $name = $this->cleanName($fn ?? '');

            if ($name === '' && $n !== null) {
                $parts = array_map('trim', explode(';', $n));
                $parts = array_pad($parts, 4, '');
                $name = $this->cleanName(trim($parts[3].' '.$parts[1].' '.$parts[2].' '.$parts[0]));
            }

            $business = $org !== null ? $this->cleanName(str_replace(';', ' ', $org)) : '';

            if ($name === '' && $business !== '') {
                $name = $business;
            }

            if ($name !== '' && $phones !== []) {
                $entries[] = ['name' => $name, 'phones' => $phones, 'business' => $business !== '' ? $business : null];
            }
        }

        return $entries;
    }

    /**
     * Google Contacts, Outlook and iPhone-tool CSVs, or any sheet with a name
     * column and a phone column.
     *
     * @return list<array{name: string, phones: list<string>, business: ?string}>
     */
    private function fromCsv(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split("/\r\n|\n|\r/", trim($contents)) ?: [];

        if ($lines === []) {
            return [];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $rows = array_map(fn (string $line) => str_getcsv($line, $delimiter, '"', ''), $lines);
        $header = array_map(fn ($cell) => mb_strtolower(trim((string) $cell)), array_shift($rows));

        $find = function (array $needles, array $avoid = []) use ($header): array {
            $hits = [];

            foreach ($header as $index => $label) {
                foreach ($avoid as $skip) {
                    if (str_contains($label, $skip)) {
                        continue 2;
                    }
                }

                foreach ($needles as $needle) {
                    if ($label === $needle || str_contains($label, $needle)) {
                        $hits[] = $index;
                        break;
                    }
                }
            }

            return $hits;
        };

        $full = $find(['display name', 'full name', 'name'], ['first', 'given', 'middle', 'last', 'family', 'nick', 'organization', 'company', 'file as', 'phonetic', 'yomi', 'label', 'type']);
        $first = $find(['first name', 'given name']);
        $middle = $find(['middle name', 'additional name']);
        $last = $find(['last name', 'family name', 'surname']);
        $phones = $find(['phone', 'mobile', 'tel', 'number', 'whatsapp'], ['type', 'label']);
        $business = $find(['organization name', 'organization 1 - name', 'company', 'business'], ['phone', 'title', 'type', 'label']);

        if ($phones === []) {
            return [];
        }

        $entries = [];

        foreach ($rows as $row) {
            $cell = fn (array $indexes): string => trim(implode(' ', array_filter(array_map(fn ($i) => trim((string) ($row[$i] ?? '')), $indexes))));

            $name = $this->cleanName($full !== [] ? $cell([$full[0]]) : '');

            if ($name === '') {
                $name = $this->cleanName(trim($cell($first).' '.$cell($middle).' '.$cell($last)));
            }

            $company = $business !== [] ? $this->cleanName($cell([$business[0]])) : '';

            if ($name === '' && $company !== '') {
                $name = $company;
            }

            $numbers = [];

            foreach ($phones as $index) {
                foreach (preg_split('/\s*:::\s*|\s*[,\/;]\s*/', (string) ($row[$index] ?? '')) ?: [] as $value) {
                    if (preg_match('/\d{6,}/', preg_replace('/\D+/', '', $value) ?? '') === 1) {
                        $numbers[] = trim($value);
                    }
                }
            }

            if ($name !== '' && $numbers !== []) {
                $entries[] = ['name' => $name, 'phones' => $numbers, 'business' => $company !== '' ? $company : null];
            }
        }

        return $entries;
    }

    private function decodeValue(string $head, string $value): string
    {
        if (str_contains($head, 'QUOTED-PRINTABLE')) {
            $value = quoted_printable_decode($value);
        }

        if (str_contains($head, 'ENCODING=B') || str_contains($head, 'BASE64')) {
            return '';
        }

        return trim(str_replace(['\\,', '\\;', '\\n', '\\N'], [',', ';', ' ', ' '], $value));
    }

    private function cleanName(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '' || preg_match('/^\+?[\d\s\-()]+$/', $value) === 1) {
            return '';
        }

        return mb_substr($value, 0, 120);
    }

    private function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, "\xFF\xFE") || str_starts_with($contents, "\xFE\xFF")) {
            return mb_convert_encoding($contents, 'UTF-8', 'UTF-16');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            return mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        return $contents;
    }
}
