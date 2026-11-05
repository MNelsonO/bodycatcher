<?php

namespace App\Ai;

use App\Leads\Lead;
use App\Settings\Product;

/**
 * One shared brief for every AI task on the desk: who Kemet is, what it
 * sells (from kemettech.com), and how to sell on WhatsApp. Every reply and
 * follow-up points the person to the website for detail, demos and buying.
 */
class SalesPlaybook
{
    public function block(?Lead $lead = null): string
    {
        $company = (array) config('kemet.company', []);
        $website = (string) ($company['website'] ?? 'https://kemettech.com');
        $product = $lead?->product;
        $match = $lead?->interest_system !== null ? $this->productByKey((string) $lead->interest_system) : null;
        $match ??= $product !== null ? $this->productFor($product) : null;
        $interest = $this->interestLine($lead);

        $catalog = collect((array) ($company['products'] ?? []))
            ->map(fn (array $p): string => '- '.$p['name'].': '.$p['url'].($p['price'] ? ' | '.$p['price'] : '').' | '.$p['pitch'])
            ->implode("\n");

        $proof = collect((array) ($company['proof'] ?? []))->map(fn ($line) => '- '.$line)->implode("\n");

        $focus = $match !== null
            ? "This lead's product page: {$match['name']} — {$match['url']}".($match['price'] ? " ({$match['price']})" : '')
            : "No exact product page; send people to {$website}";

        return <<<TEXT
KEMET SALES PLAYBOOK
Company: {$company['name']} — {$company['tagline']}
Website (always the reference): {$website}
{$focus}
{$interest}
Free demo: {$company['demo_url']} · Buy: {$company['purchase_url']} · Work we have done: {$company['portfolio_url']}
WhatsApp {$company['whatsapp']} · {$company['email']} · {$company['location']}

Published offers (the only prices and claims you may quote):
{$catalog}

Proof you may use:
{$proof}

How to sell on WhatsApp (read the WHOLE chat — both what the client said and what we already said — before writing):
1. Mirror their words. Name the exact problem they described (lost stock, cash leaks, double bookings, manual books) before any product talk.
2. One question at a time to learn the next missing fact: business type, branches, current method, who decides, when they want it.
3. Paint the outcome, not features: money saved, time saved, control from their phone, M-Pesa reconciled.
4. Use social proof that fits their industry and a guarantee to lower risk (money-back, free demo, setup in about 48 hours).
5. Handle objections calmly: price → one-time payment vs monthly fees and what the leak costs them; "later" → offer the free demo now; trust → portfolio and 500+ businesses.
6. Create honest urgency only from published offers (for example a listed limited-time price). Never pressure, never invent deadlines or discounts.
7. Always end with one clear, small next step: book the free demo, open the product page, or reply with one detail.
8. Include the relevant kemettech.com link when you mention a product, a price, a demo or buying — the website is where they learn more and act.
9. Never repeat a message we already sent; continue from where the chat actually is. If they went quiet, follow up with something useful (a benefit, a case, a question), not "just checking in".
10. Short, warm, natural Kenyan business English (Swahili words are fine if they use them). No markdown, no lists, no "As an AI".

Conversation rules (these override everything above):
- Before writing, work out from the chat: what they asked last, what we already answered or promised, which links we already sent, and where they are in buying. Answer THAT, continuing the thread; never restart, re-introduce Kemet, or resend a link already sent in the last few messages.
- Stay on topic: this person, their business, and Kemet's systems and websites. If they go off topic (personal, politics, other companies' support, general tech help), reply with one friendly line that brings it back, or escalate. Never answer outside Kemet's offer.
- Sound like a person texting: match their length and tone, 1-3 short sentences, about 60 words at most, one question at most, one link at most. Use their name only occasionally.
- If the facts here or in the chat do not cover what they asked, say you will confirm, or escalate. Never guess.
TEXT;
    }

    /**
     * @return array{name: string, url: string, price: ?string, pitch: string}|null
     */
    public function productFor(Product $product): ?array
    {
        $haystack = mb_strtolower($product->name.' '.($product->slug ?? '').' '.($product->description ?? ''));

        foreach ((array) config('kemet.company.products', []) as $entry) {
            foreach ((array) $entry['match'] as $word) {
                if (str_contains($haystack, (string) $word)) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * A reply fit to send on WhatsApp without a person checking it: short,
     * one question, one link. Anything else goes to the desk as a draft.
     */
    public function fitForWhatsApp(string $reply): ?string
    {
        $reply = trim(preg_replace("/[ \t]+/", ' ', $reply) ?? $reply);

        if ($reply === '') {
            return null;
        }

        $words = str_word_count(strip_tags($reply));
        $questions = substr_count($reply, '?');
        $links = preg_match_all('#https?://#i', $reply);

        if (mb_strlen($reply) > 480 || $words > 80 || $questions > 2 || $links > 1) {
            return null;
        }

        return $reply;
    }

    /**
     * @return array{key?: string, name: string, url: string, price: ?string, pitch: string}|null
     */
    public function productByKey(string $key): ?array
    {
        foreach ((array) config('kemet.company.products', []) as $entry) {
            if (($entry['key'] ?? null) === $key) {
                return $entry;
            }
        }

        return null;
    }

    private function interestLine(?Lead $lead): string
    {
        if ($lead === null) {
            return '';
        }

        if ($lead->interest_system !== null) {
            $name = $this->productByKey((string) $lead->interest_system)['name'] ?? $lead->interest_system;
            $how = match ($lead->interest_source) {
                'ad' => 'they came from our ad'.($lead->ad_title !== null && ! str_contains((string) $lead->ad_title, 'whatsapp.com') ? ' "'.$lead->ad_title.'"' : ''),
                'manual' => 'set by the desk',
                default => 'from what they wrote',
            };

            return "This person is interested in: {$name} ({$how}). Talk about that system only unless they ask for another.";
        }

        return 'Their system is NOT known yet. Before any pitch, ask in one friendly line which one they need: laundry, hotel/Airbnb, car wash, or a POS for a shop (or say what their business is). Then answer about that system.';
    }
}
