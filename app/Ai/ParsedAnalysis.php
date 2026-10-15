<?php

namespace App\Ai;

final class ParsedAnalysis
{
    /**
     * @param  array<string, mixed>  $extraction
     * @param  list<string>  $scoreReasons
     */
    public function __construct(
        public string $summary,
        public array $extraction,
        public ?int $score,
        public array $scoreReasons,
        public ?int $suggestedStageId,
        public ?string $stageReason,
        public ?string $stageRejectedReason,
        public ?string $replyDraft,
    ) {}
}
