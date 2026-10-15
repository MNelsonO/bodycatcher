<?php

namespace App\SalesPipelines;

/**
 * Canonical Kemet Tech sales funnel.
 *
 * Keep stage names short and sequential so agents know what to do next.
 * Dependent labels (command centre, reports, WhatsApp inbound) read from here.
 */
final class DefaultKemetStages
{
    public const PIPELINE_NAME = 'Kemet Tech sales';

    public const INBOUND_STAGE = 'New';

    /**
     * @return list<array{name: string, kind: StageKind, delay: int|null, purpose: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'New',
                'kind' => StageKind::Active,
                'delay' => 24,
                'purpose' => 'Just arrived. Make first contact today.',
            ],
            [
                'name' => 'Reached',
                'kind' => StageKind::Active,
                'delay' => 48,
                'purpose' => 'First contact done. Keep the conversation going.',
            ],
            [
                'name' => 'Qualified',
                'kind' => StageKind::Active,
                'delay' => 48,
                'purpose' => 'Real need and product fit confirmed. Book a demo.',
            ],
            [
                'name' => 'Demo',
                'kind' => StageKind::Active,
                'delay' => 24,
                'purpose' => 'Demo booked or running. Show up and land it.',
            ],
            [
                'name' => 'Evaluating',
                'kind' => StageKind::Active,
                'delay' => 48,
                'purpose' => 'Demo done. They are weighing the product.',
            ],
            [
                'name' => 'Proposal',
                'kind' => StageKind::Active,
                'delay' => 72,
                'purpose' => 'Price or proposal sent. Chase a decision.',
            ],
            [
                'name' => 'Closing',
                'kind' => StageKind::Active,
                'delay' => 48,
                'purpose' => 'Negotiating terms. Push to win.',
            ],
            [
                'name' => 'Won',
                'kind' => StageKind::Won,
                'delay' => null,
                'purpose' => 'Deal closed. They bought.',
            ],
            [
                'name' => 'Lost',
                'kind' => StageKind::Lost,
                'delay' => null,
                'purpose' => 'Not buying — wrong fit, chose competitor, or opted out.',
            ],
            [
                'name' => 'Nurture',
                'kind' => StageKind::ArchivedNurture,
                'delay' => 336,
                'purpose' => 'Not now. Stay warm and check in later.',
            ],
        ];
    }

    /**
     * Old stage names → new stage names (for reshaping existing desks).
     *
     * @return array<string, string>
     */
    public static function renameMap(): array
    {
        return [
            'New Lead' => 'New',
            'Contacted' => 'Reached',
            'Qualified' => 'Qualified',
            'Interested' => 'Qualified',
            'Demo Requested' => 'Demo',
            'Demo Scheduled' => 'Demo',
            'Demo Completed' => 'Evaluating',
            'Proposal / Price Sent' => 'Proposal',
            'Negotiation' => 'Closing',
            'Won' => 'Won',
            'Lost' => 'Lost',
            'Nurture / Future Opportunity' => 'Nurture',
            'Disqualified' => 'Lost',
        ];
    }

    /** @return list<string> */
    public static function followUpBucket(): array
    {
        return ['New', 'Reached', 'Qualified'];
    }

    /** @return list<string> */
    public static function almostBuyingBucket(): array
    {
        return ['Evaluating', 'Proposal', 'Closing'];
    }

    /** @return list<string> */
    public static function demoBucket(): array
    {
        return ['Demo', 'Evaluating'];
    }

    /** @return list<string> */
    public static function nurtureBucket(): array
    {
        return ['Nurture'];
    }

    /** @return list<string> */
    public static function hotStages(): array
    {
        return ['Qualified', 'Demo', 'Evaluating', 'Proposal', 'Closing'];
    }

    /** @return list<string> */
    public static function highInterestStages(): array
    {
        return ['Demo', 'Evaluating', 'Proposal', 'Closing'];
    }
}
