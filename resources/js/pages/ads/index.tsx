import { Head, Link } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import {
    Page,
    PageHeader,
    Surface,
    SurfaceBody,
    SurfaceHeader,
} from '@/components/crm/page-frame';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Stats = {
    leads: number;
    replied: number;
    won: number;
    lost: number;
    open: number;
    from_ads: number;
    conversion: number;
};

type SystemRow = Stats & { key: string; name: string };

type AdRow = Stats & {
    id: string | null;
    title: string;
    body: string | null;
    url: string | null;
    system: string | null;
    system_label: string | null;
    last_seen: string | null;
};

type Report = {
    totals: Stats & { unknown: number };
    systems: SystemRow[];
    ads: AdRow[];
    unknown: Array<{
        lead_id: number;
        conversation_id: number | null;
        name: string;
        stage: string;
    }>;
};

const RANGES = [
    { value: '7', label: '7 days' },
    { value: '30', label: '30 days' },
    { value: '90', label: '90 days' },
    { value: 'all', label: 'All time' },
];

const TONE: Record<string, string> = {
    laundry: 'border-l-sky-500',
    hotel: 'border-l-violet-500',
    pos: 'border-l-orange-500',
    carwash: 'border-l-teal-500',
};

export default function AdsPage({
    range,
    report,
}: {
    range: string;
    report: Report;
}) {
    const tiles = [
        { label: 'Chats', value: report.totals.leads },
        { label: 'From ads', value: report.totals.from_ads },
        { label: 'Replied', value: report.totals.replied },
        { label: 'Won', value: report.totals.won },
        { label: 'Win rate', value: `${report.totals.conversion}%` },
        { label: 'System unknown', value: report.totals.unknown },
    ];

    return (
        <>
            <Head title="Ads" />
            <Page>
                <PageHeader
                    title="Ads and systems"
                    description="Which ad each chat came from and which Kemet system it is about. Chats from a Click-to-WhatsApp ad are sorted automatically; the rest are sorted from what people wrote, or by you in the Inbox."
                    actions={
                        <div className="flex gap-1 rounded-lg border p-0.5">
                            {RANGES.map((option) => (
                                <Link
                                    key={option.value}
                                    href={`/ads?range=${option.value}`}
                                    preserveScroll
                                    className={cn(
                                        'rounded-md px-2.5 py-1 text-xs font-medium text-muted-foreground hover:text-foreground',
                                        range === option.value &&
                                            'bg-primary text-primary-foreground hover:text-primary-foreground',
                                    )}
                                >
                                    {option.label}
                                </Link>
                            ))}
                        </div>
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {tiles.map((tile) => (
                        <Surface key={tile.label}>
                            <SurfaceBody className="py-3">
                                <p className="text-xs text-muted-foreground">
                                    {tile.label}
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {tile.value}
                                </p>
                            </SurfaceBody>
                        </Surface>
                    ))}
                </div>

                <Surface>
                    <SurfaceHeader
                        title="By system"
                        description="Every chat counted under the system it is about."
                    />
                    <div className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                        {report.systems.map((system) => (
                            <div
                                key={system.key}
                                className={cn(
                                    'rounded-lg border border-l-4 p-3',
                                    TONE[system.key] ?? 'border-l-primary',
                                )}
                            >
                                <div className="flex items-baseline justify-between gap-2">
                                    <p className="truncate font-medium">
                                        {system.name}
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {system.leads}
                                    </p>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {system.from_ads} from ads ·{' '}
                                    {system.replied} replied · {system.won} won
                                    · {system.lost} lost · {system.conversion}%
                                    win rate
                                </p>
                            </div>
                        ))}
                    </div>
                </Surface>

                <Surface>
                    <SurfaceHeader
                        title="By ad"
                        description="Each Click-to-WhatsApp ad that started a chat."
                    />
                    {report.ads.length === 0 ? (
                        <SurfaceBody className="flex flex-col items-center gap-2 py-10 text-center text-sm text-muted-foreground">
                            <Megaphone className="size-6 opacity-50" />
                            No chats from ads yet in this period. When someone
                            taps one of your WhatsApp ads, it shows up here.
                        </SurfaceBody>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground">
                                    <tr className="border-b">
                                        <th className="px-4 py-2 font-medium">
                                            Ad
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            System
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Chats
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Replied
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Won
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Lost
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Win rate
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {report.ads.map((ad) => (
                                        <tr key={`${ad.id}-${ad.title}`}>
                                            <td className="max-w-md px-4 py-2.5">
                                                <p className="truncate font-medium">
                                                    {ad.url ? (
                                                        <a
                                                            href={ad.url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="hover:underline"
                                                        >
                                                            {ad.title}
                                                        </a>
                                                    ) : (
                                                        ad.title
                                                    )}
                                                </p>
                                                {ad.body ? (
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {ad.body}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                {ad.system_label ? (
                                                    <Badge variant="outline">
                                                        {ad.system_label}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        Unknown
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {ad.leads}
                                            </td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {ad.replied}
                                            </td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {ad.won}
                                            </td>
                                            <td className="px-3 py-2.5 text-right tabular-nums">
                                                {ad.lost}
                                            </td>
                                            <td className="px-4 py-2.5 text-right tabular-nums">
                                                {ad.conversion}%
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Surface>

                <Surface>
                    <SurfaceHeader
                        title="System not known yet"
                        description="Ask these people which system they want (laundry, hotel/Airbnb, car wash or POS), then set it in the Inbox."
                    />
                    {report.unknown.length === 0 ? (
                        <SurfaceBody className="text-sm text-muted-foreground">
                            Every chat in this period is sorted.
                        </SurfaceBody>
                    ) : (
                        <ul className="divide-y">
                            {report.unknown.map((row) => (
                                <li
                                    key={row.lead_id}
                                    className="flex items-center gap-3 px-5 py-2.5 text-sm"
                                >
                                    <Link
                                        href={
                                            row.conversation_id
                                                ? `/inbox/${row.conversation_id}`
                                                : `/leads/${row.lead_id}`
                                        }
                                        className="min-w-0 flex-1 truncate font-medium"
                                    >
                                        {row.name}
                                    </Link>
                                    <span className="text-xs text-muted-foreground">
                                        {row.stage}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Surface>
            </Page>
        </>
    );
}
