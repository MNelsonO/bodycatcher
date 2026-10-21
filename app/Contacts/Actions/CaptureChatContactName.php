<?php

namespace App\Contacts\Actions;

use App\Contacts\Contact;
use App\Leads\Lead;

/**
 * Pulls a real name from chat ("my name is Jane") or a CONTACT_NAME: line
 * and fills the contact when the current name is empty, a phone, or a placeholder.
 */
class CaptureChatContactName
{
    public function handle(Lead $lead, string $text): bool
    {
        $name = $this->extract($text);

        if ($name === null) {
            return false;
        }

        $lead->loadMissing('contact');
        $contact = $lead->contact;

        if (! $this->shouldReplace($contact, $name)) {
            return false;
        }

        $contact->full_name = $name;
        $contact->save();

        return true;
    }

    /**
     * Apply an already-parsed name (e.g. CONTACT_NAME: from the live assistant).
     */
    public function applyName(Lead $lead, string $rawName): bool
    {
        $name = $this->cleanName($rawName);

        if ($name === null) {
            return false;
        }

        $lead->loadMissing('contact');
        $contact = $lead->contact;

        if (! $this->shouldReplace($contact, $name)) {
            return false;
        }

        $contact->full_name = $name;
        $contact->save();

        return true;
    }

    public function extract(string $text): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if (preg_match('/^\s*CONTACT_NAME:\s*(.+)$/imu', $text, $contactLine) === 1) {
            $fromLine = $this->cleanName($contactLine[1] ?? '');

            if ($fromLine !== null) {
                return $fromLine;
            }
        }

        if (preg_match('/\b(?:my name is|i am|i\'m|this is)\s+([A-Za-z][A-Za-z\'\-]+(?:\s+[A-Za-z][A-Za-z\'\-]+)?)\b/iu', $text, $match) !== 1) {
            return null;
        }

        return $this->cleanName($match[1] ?? '');
    }

    private function shouldReplace(Contact $contact, string $incoming): bool
    {
        if ($this->isPlaceholderName($incoming) || $incoming === $contact->primary_phone) {
            return false;
        }

        if ($contact->full_name === $incoming) {
            return false;
        }

        $current = trim((string) $contact->full_name);

        // Keep a desk-saved real name; only fill when empty, phone, or placeholder.
        if (
            $current !== ''
            && $current !== $contact->primary_phone
            && ! $this->isPlaceholderName($current)
        ) {
            return false;
        }

        return true;
    }

    private function cleanName(string $value): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($name === '') {
            return null;
        }

        // Strip trailing punctuation from a CONTACT_NAME line fragment.
        $name = rtrim($name, " \t.,;:!?\"'");

        if ($name === '' || $this->isPlaceholderName($name)) {
            return null;
        }

        if (str_contains($name, '@') || preg_match('/^\+?\d{8,15}$/', $name) === 1) {
            return null;
        }

        return mb_substr($name, 0, 120);
    }

    private function isPlaceholderName(string $name): bool
    {
        $lower = mb_strtolower(trim($name));

        if ($lower === '') {
            return true;
        }

        return in_array($lower, [
            'whatsapp contact',
            'whatsapp lead',
            'unknown',
            'null',
            'undefined',
            'contact',
        ], true)
            || str_starts_with($lower, 'whatsapp +')
            || str_starts_with($lower, 'whatsapp ');
    }
}
