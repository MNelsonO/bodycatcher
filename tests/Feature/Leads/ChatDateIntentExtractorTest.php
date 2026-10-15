<?php

use App\Leads\Services\ChatDateIntentExtractor;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['app.display_timezone' => 'Africa/Nairobi']);
});

it('extracts tomorrow call with time', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Africa/Nairobi'));

    $hint = app(ChatDateIntentExtractor::class)->extract('Please call me tomorrow at 3pm');

    expect($hint)->not->toBeNull()
        ->and($hint->followUpOn->toDateString())->toBe('2026-10-17')
        ->and($hint->conversationAt?->format('Y-m-d H:i'))->toBe('2026-10-17 15:00')
        ->and($hint->action)->toContain('Call')
        ->and($hint->confidence)->toBeGreaterThanOrEqual(70);

    Carbon::setTestNow();
});

it('extracts friday demo without inventing a past date', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'Africa/Nairobi')); // Wednesday

    $hint = app(ChatDateIntentExtractor::class)->extract('Can we book a demo on Friday?');

    expect($hint)->not->toBeNull()
        ->and($hint->followUpOn->toDateString())->toBe('2026-10-16')
        ->and($hint->action)->toContain('Demo');

    Carbon::setTestNow();
});

it('ignores messages with dates but no follow-up intent', function () {
    $hint = app(ChatDateIntentExtractor::class)->extract('Our shop opened on 12/01/2020 in Nairobi');

    expect($hint)->toBeNull();
});
