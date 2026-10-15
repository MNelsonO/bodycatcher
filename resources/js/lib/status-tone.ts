/**
 * Laundry desk status → badge variant.
 * Blue/white brand, green win/paid, red overdue/lost, lead slate for neutrals.
 */
export type StatusTone =
    | 'default'
    | 'secondary'
    | 'destructive'
    | 'success'
    | 'lead'
    | 'laundry'
    | 'outline';

export function invoiceStatusTone(status: string): StatusTone {
    switch (status) {
        case 'paid':
            return 'success';
        case 'overdue':
            return 'destructive';
        case 'unpaid':
        case 'sent':
            return 'laundry';
        case 'void':
            return 'lead';
        default:
            return 'outline';
    }
}

export function leadOutcomeTone(outcome: string): StatusTone {
    switch (outcome) {
        case 'won':
            return 'success';
        case 'lost':
            return 'destructive';
        case 'open':
            return 'laundry';
        default:
            return 'lead';
    }
}

export function followUpTone(state: string): StatusTone {
    switch (state) {
        case 'overdue':
            return 'destructive';
        case 'due_today':
            return 'laundry';
        case 'upcoming':
            return 'success';
        case 'none':
            return 'lead';
        default:
            return 'outline';
    }
}
