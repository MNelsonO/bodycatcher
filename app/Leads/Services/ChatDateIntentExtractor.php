<?php

namespace App\Leads\Services;

use App\Leads\ChatScheduleHint;
use App\Settings\DisplayClock;
use Illuminate\Support\Carbon;

/**
 * Pulls clear follow-up / talk dates from inbound WhatsApp text without an LLM.
 * Tuned for short Kenyan business chats (English).
 */
class ChatDateIntentExtractor
{
    public function __construct(private DisplayClock $clock) {}

    public function extract(string $body, ?Carbon $relativeTo = null): ?ChatScheduleHint
    {
        $text = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        if ($text === '' || mb_strlen($text) > 2000) {
            return null;
        }

        $lower = mb_strtolower($text);
        $now = ($relativeTo ?? $this->clock->now())->copy()->timezone($this->clock->timezone());

        if (! $this->hasIntent($lower)) {
            return null;
        }

        $date = $this->parseDate($lower, $now);

        if ($date === null) {
            return null;
        }

        $time = $this->parseTime($lower);
        $conversationAt = null;

        if ($time !== null) {
            $conversationAt = $date->copy()->setTime($time['hour'], $time['minute'], 0);

            if ($conversationAt->lt($now) && $date->isSameDay($now)) {
                return null;
            }
        }

        if ($date->lt($now->copy()->startOfDay())) {
            return null;
        }

        $action = $this->actionLine($lower, $date, $conversationAt);
        $confidence = $time !== null ? 90 : 75;

        if (preg_match('/\b(tomorrow|today|tonight)\b/u', $lower) === 1) {
            $confidence = min(100, $confidence + 5);
        }

        return new ChatScheduleHint(
            followUpOn: $date->copy()->startOfDay(),
            conversationAt: $conversationAt,
            action: $action,
            matchedSnippet: mb_substr($text, 0, 180),
            confidence: $confidence,
        );
    }

    private function hasIntent(string $lower): bool
    {
        return preg_match(
            '/\b(follow\s*up|follow-up|call\s+me|call\s+you|call\s+back|talk|meet|meeting|demo|available|free\s+(?:on|at|tomorrow|today|this|next)|remind|check\s*in|schedule|book|see\s+you|ping\s+me|get\s+back|reach\s+(?:me|out)|come\s+(?:by|through)|let(?:\'s| us)\s+(?:talk|meet|call)|i(?:\'ll| will)\s+(?:call|be\s+free|be\s+available)|can\s+(?:we|you)\s+(?:talk|call|meet)|ring\s+me)\b/u',
            $lower,
        ) === 1;
    }

    private function parseDate(string $lower, Carbon $now): ?Carbon
    {
        if (preg_match('/\btonight\b/u', $lower) === 1 || preg_match('/\btoday\b/u', $lower) === 1) {
            return $now->copy()->startOfDay();
        }

        if (preg_match('/\btomorrow\b/u', $lower) === 1) {
            return $now->copy()->addDay()->startOfDay();
        }

        if (preg_match('/\bin\s+(\d{1,2})\s+days?\b/u', $lower, $match) === 1) {
            $days = (int) $match[1];

            if ($days >= 1 && $days <= 60) {
                return $now->copy()->addDays($days)->startOfDay();
            }
        }

        if (preg_match('/\bnext\s+week\b/u', $lower) === 1) {
            return $now->copy()->addWeek()->startOfWeek(Carbon::MONDAY)->startOfDay();
        }

        $weekdays = [
            'monday' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
            'sunday' => Carbon::SUNDAY,
        ];

        foreach ($weekdays as $name => $iso) {
            if (preg_match('/\b(?:next\s+)?'.$name.'\b/u', $lower) !== 1) {
                continue;
            }

            $target = $now->copy()->next($iso)->startOfDay();

            if (str_contains($lower, 'next '.$name)) {
                // already next()
            } elseif ($now->dayOfWeek === $iso && ! preg_match('/\b(tonight|today|this\s+'.$name.')\b/u', $lower)) {
                // "call Monday" on Monday → later today unless they said next
                $target = $now->copy()->startOfDay();
            }

            return $target;
        }

        if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u', $lower, $match) === 1) {
            return $this->safeDate((int) $match[1], (int) $match[2], (int) $match[3], $now);
        }

        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/u', $lower, $match) === 1) {
            $day = (int) $match[1];
            $month = (int) $match[2];
            $year = isset($match[3]) && $match[3] !== ''
                ? ((int) $match[3] < 100 ? 2000 + (int) $match[3] : (int) $match[3])
                : $now->year;

            // Prefer day/month (Kenya) when both <= 12 and ambiguous — first number is day.
            if ($day > 31 || $month > 12) {
                // maybe month/day slipped in
                if ($month <= 31 && $day <= 12) {
                    [$day, $month] = [$month, $day];
                } else {
                    return null;
                }
            }

            $date = $this->safeDate($year, $month, $day, $now);

            if ($date !== null && $date->lt($now->copy()->startOfDay()) && ! isset($match[3])) {
                $date = $this->safeDate($year + 1, $month, $day, $now);
            }

            return $date;
        }

        $months = [
            'january' => 1, 'jan' => 1,
            'february' => 2, 'feb' => 2,
            'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'may' => 5,
            'june' => 6, 'jun' => 6,
            'july' => 7, 'jul' => 7,
            'august' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9, 'sept' => 9,
            'october' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'december' => 12, 'dec' => 12,
        ];

        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?([a-z]{3,9})\b/u', $lower, $match) === 1) {
            $day = (int) $match[1];
            $monthName = $match[2];
            $month = $months[$monthName] ?? null;

            if ($month === null || $day < 1 || $day > 31) {
                return null;
            }

            $date = $this->safeDate($now->year, $month, $day, $now);

            if ($date !== null && $date->lt($now->copy()->startOfDay())) {
                $date = $this->safeDate($now->year + 1, $month, $day, $now);
            }

            return $date;
        }

        return null;
    }

    /**
     * @return array{hour: int, minute: int}|null
     */
    private function parseTime(string $lower): ?array
    {
        if (preg_match('/\b(?:at\s+)?(\d{1,2})(?::(\d{2}))\s*(am|pm)?\b/u', $lower, $match) === 1
            || preg_match('/\b(?:at\s+)?(\d{1,2})\s*(am|pm)\b/u', $lower, $match) === 1) {
            $hour = (int) $match[1];
            $minute = isset($match[2]) && is_numeric($match[2]) ? (int) $match[2] : 0;
            $meridiem = null;

            if (isset($match[3]) && is_string($match[3])) {
                $meridiem = $match[3];
            } elseif (isset($match[2]) && in_array($match[2], ['am', 'pm'], true)) {
                $meridiem = $match[2];
                $minute = 0;
            }

            if ($meridiem === 'pm' && $hour < 12) {
                $hour += 12;
            }

            if ($meridiem === 'am' && $hour === 12) {
                $hour = 0;
            }

            if ($hour > 23 || $minute > 59) {
                return null;
            }

            return ['hour' => $hour, 'minute' => $minute];
        }

        if (preg_match('/\btonight\b/u', $lower) === 1) {
            return ['hour' => 19, 'minute' => 0];
        }

        return null;
    }

    private function safeDate(int $year, int $month, int $day, Carbon $now): ?Carbon
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day, 0, 0, 0, $now->timezone);
    }

    private function actionLine(string $lower, Carbon $date, ?Carbon $conversationAt): string
    {
        $verb = 'Follow up';

        if (preg_match('/\bdemo\b/u', $lower) === 1) {
            $verb = 'Demo';
        } elseif (preg_match('/\bmeet(?:ing)?\b/u', $lower) === 1) {
            $verb = 'Meeting';
        } elseif (preg_match('/\b(call|ring)\b/u', $lower) === 1) {
            $verb = 'Call';
        } elseif (preg_match('/\btalk\b/u', $lower) === 1) {
            $verb = 'Talk';
        }

        $when = $date->format('d M Y');

        if ($conversationAt !== null) {
            $when .= ' '.$conversationAt->format('H:i');
        }

        return 'Chat: '.$verb.' on '.$when;
    }
}
