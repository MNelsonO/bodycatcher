<?php

namespace App\Ai\Services;

use App\Ai\InvalidAnalysis;
use App\Ai\ParsedAnalysis;
use App\Leads\DecisionMakerStatus;
use DateTimeImmutable;
use JsonException;

class AnalysisParser
{
    /**
     * @param  list<int>  $permittedStageIds
     */
    public function parse(string $raw, array $permittedStageIds): ParsedAnalysis
    {
        $decoded = $this->decode($raw);

        if (! is_array($decoded)) {
            throw new InvalidAnalysis('The model did not return a JSON object.');
        }

        $summary = $decoded['summary'] ?? null;

        if (! is_string($summary) || trim($summary) === '') {
            throw new InvalidAnalysis('The model did not return a summary.');
        }

        [$stageId, $stageReason, $rejected] = $this->stage($decoded, $permittedStageIds);

        return new ParsedAnalysis(
            summary: mb_substr(trim($summary), 0, 2000),
            extraction: $this->extraction(is_array($decoded['extraction'] ?? null) ? $decoded['extraction'] : []),
            score: $this->score($decoded),
            scoreReasons: $this->score($decoded) === null ? [] : $this->reasons($decoded['score_reasons'] ?? null),
            suggestedStageId: $stageId,
            stageReason: $stageId === null ? null : $stageReason,
            stageRejectedReason: $rejected,
            replyDraft: $this->draft($decoded['reply_draft'] ?? null),
        );
    }

    private function decode(string $raw): mixed
    {
        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidAnalysis('The model did not return JSON.');
        }

        return $decoded;
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<string, mixed>
     */
    private function extraction(array $decoded): array
    {
        $clean = [];

        foreach (['business_problem', 'current_solution', 'budget_note', 'purchase_timeline_note', 'next_action'] as $key) {
            $value = $this->text($decoded[$key] ?? null, 2000);

            if ($value !== null) {
                $clean[$key] = $value;
            }
        }

        $branches = $decoded['branch_count'] ?? null;

        if (is_int($branches) || (is_string($branches) && ctype_digit($branches))) {
            $count = (int) $branches;

            if ($count >= 0 && $count <= 10000) {
                $clean['branch_count'] = $count;
            }
        }

        $maker = $decoded['decision_maker_status'] ?? null;

        if (is_string($maker)) {
            $status = DecisionMakerStatus::tryFrom(strtolower(trim($maker)));

            if ($status !== null) {
                $clean['decision_maker_status'] = $status->value;
            }
        }

        $date = $this->date($decoded['next_follow_up_on'] ?? null);

        if ($date !== null) {
            $clean['next_follow_up_on'] = $date;
        }

        return $clean;
    }

    /**
     * @param  array<mixed>  $decoded
     * @param  list<int>  $permittedStageIds
     * @return array{0: int|null, 1: string|null, 2: string|null}
     */
    private function stage(array $decoded, array $permittedStageIds): array
    {
        $suggested = $decoded['suggested_stage_id'] ?? null;

        if ($suggested === null || $suggested === '') {
            return [null, null, null];
        }

        if (! is_int($suggested) && ! (is_string($suggested) && ctype_digit($suggested))) {
            return [null, null, 'The suggested stage was not a stage id.'];
        }

        $candidate = (int) $suggested;

        if (! in_array($candidate, $permittedStageIds, true)) {
            return [null, null, 'The suggested stage is not a permitted move from the current stage.'];
        }

        return [$candidate, $this->text($decoded['stage_reason'] ?? null, 500), null];
    }

    /**
     * @param  array<mixed>  $decoded
     */
    private function score(array $decoded): ?int
    {
        $raw = $decoded['score'] ?? null;

        if (! is_int($raw) && ! (is_string($raw) && ctype_digit($raw))) {
            return null;
        }

        $score = (int) $raw;

        return $score >= 0 && $score <= 100 ? $score : null;
    }

    /**
     * @return list<string>
     */
    private function reasons(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $reasons = [];

        foreach ($raw as $reason) {
            $text = $this->text($reason, 300);

            if ($text === null) {
                continue;
            }

            $reasons[] = $text;

            if (count($reasons) === 8) {
                break;
            }
        }

        return $reasons;
    }

    private function draft(mixed $raw): ?string
    {
        return $this->text($raw, 4000);
    }

    private function text(mixed $raw, int $limit): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $text = trim($raw);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $limit);
    }

    private function date(mixed $raw): ?string
    {
        if (! is_string($raw) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        if ($parsed === false || $parsed->format('Y-m-d') !== $raw) {
            return null;
        }

        return $raw;
    }
}
