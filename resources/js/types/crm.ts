export type Option = {
    value: string;
    label: string;
};

export type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type ContactSummary = {
    id: number;
    full_name: string;
    primary_phone: string;
    additional_phones: string[];
    business_name: string | null;
    business_type: string | null;
    location: string | null;
    email: string | null;
    preferred_channel: string;
    consent_status: string;
    opted_out_at_label: string | null;
    lead_source: string | null;
    created_at_label: string | null;
};

export type ContactFormOptions = {
    channels: Option[];
    consent: Option[];
};

export type LeadRow = {
    id: number;
    contact_name: string;
    business_name: string | null;
    phone: string;
    product_name: string;
    pipeline_name: string;
    stage_name: string;
    stage_kind: string;
    outcome: string;
    source: string | null;
    next_action: string | null;
    next_follow_up_on: string | null;
    next_follow_up_label: string | null;
    follow_up_state: string;
};

export type StageOption = {
    id: number;
    name: string;
    kind: string;
};

export type LeadDetail = {
    id: number;
    outcome: string;
    source: string | null;
    business_problem: string | null;
    current_solution: string | null;
    branch_count: number | null;
    budget_note: string | null;
    decision_maker_status: string;
    purchase_timeline_note: string | null;
    next_action: string | null;
    next_follow_up_on: string | null;
    next_follow_up_label: string | null;
    lost_reason: string | null;
    created_at_label: string | null;
    contact: ContactSummary;
    product: { id: number; name: string };
    pipeline: { id: number; name: string };
    stage: {
        id: number;
        name: string;
        kind: string;
        default_follow_up_delay_hours: number | null;
    };
    permitted_stages: StageOption[];
    history: Array<{
        id: number;
        from_stage: string | null;
        to_stage: string;
        reason: string | null;
        actor: string;
        at: string | null;
    }>;
    messages: Array<{
        id: number;
        direction: string;
        channel: string;
        origin: string;
        status: string;
        body: string;
        sent_at_label: string | null;
        logged_by: string | null;
        refusal_reason: string | null;
        provider_message_id: string | null;
        attachments: Array<{
            id: number;
            provider_media_id: string;
            mime_type: string | null;
            caption: string | null;
        }>;
    }>;
    whatsapp: {
        configured: boolean;
        window_open: boolean;
        last_inbound_label: string | null;
        window_closes_label: string | null;
        automation_paused: boolean;
        taken_over: boolean;
        needs_personal_attention: boolean;
        automatic_sends_paused: boolean;
        opted_out: boolean;
    };
    intelligence: {
        configured: boolean;
        latest: null | {
            id: number;
            status: string;
            summary: string | null;
            score: number | null;
            score_reasons: string[];
            extraction: Array<{ key: string; label: string; value: string }>;
            suggested_stage: { id: number; name: string } | null;
            stage_reason: string | null;
            stage_rejected_reason: string | null;
            knowledge: Array<{ id: number; title: string }>;
            error: string | null;
            completed_at_label: string | null;
            draft: null | {
                id: number;
                original_body: string;
                body: string;
                status: string;
            };
        };
    };
    follow_ups: Array<{
        id: number;
        rule: string | null;
        action: string;
        body: string;
        status: string;
        cancel_on_reply: boolean;
        due_label: string | null;
        held_reason: string | null;
        failure_reason: string | null;
    }>;
    notes: Array<{
        id: number;
        body: string;
        author: string;
        at: string | null;
    }>;
    activities: Array<{
        id: number;
        event: string;
        label: string;
        actor: string | null;
        at: string | null;
    }>;
};

export type FollowUpRow = {
    id: number;
    contact_name: string;
    business_name: string | null;
    product_name: string;
    stage_name: string;
    next_action: string | null;
    follow_up_on: string | null;
    follow_up_label: string | null;
    status: string;
    reason: string;
};

export type DashboardMetrics = {
    total_leads: number;
    new_leads_today: number;
    follow_ups_due_today: number;
    overdue_follow_ups: number;
    won_count: number;
    lost_count: number;
    leads_by_stage: Array<{
        stage_id: number;
        stage_name: string;
        pipeline_name: string;
        kind: string;
        total: number;
    }>;
    follow_up_rows: FollowUpRow[];
    today: string;
    timezone: string;
};
