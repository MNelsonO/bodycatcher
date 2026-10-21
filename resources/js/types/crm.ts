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
    country_code: string | null;
    country: string | null;
    conversation_area: string | null;
    email: string | null;
    preferred_channel: string;
    consent_status: string;
    opted_out_at_label: string | null;
    lead_source: string | null;
    created_at_label: string | null;
    interested_system?: string | null;
    interested_systems?: string[];
    intent?: string | null;
    stage_name?: string | null;
    open_lead_id?: number | null;
    last_spoke_at?: string | null;
    next_talk_at?: string | null;
};

export type ContactFormOptions = {
    channels: Option[];
    consent: Option[];
    countries: Option[];
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
    message_count: number;
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
    next_conversation_at: string | null;
    next_conversation_label: string | null;
    conversations_stopped: boolean;
    marked_on: string | null;
    marked_on_label: string | null;
    marked_label: string | null;
    conversation_topic: string | null;
    conversation_topic_summary: string | null;
    intent: string | null;
    lost_reason: string | null;
    created_at_label: string | null;
    contact_is_new: boolean;
    conversation_is_new: boolean;
    message_count: number;
    pipeline_stages: StageOption[];
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
        driver: string;
        window_open: boolean;
        enforces_service_window: boolean;
        template_ready: boolean;
        template_name: string;
        can_send: boolean;
        can_send_free_form: boolean;
        last_inbound_label: string | null;
        window_closes_label: string | null;
        automation_paused: boolean;
        taken_over: boolean;
        needs_personal_attention: boolean;
        conversations_stopped: boolean;
        automatic_sends_paused: boolean;
        opted_out: boolean;
    };
    escalation: null | {
        id: number;
        reason: string;
        inbound_snippet: string | null;
        suggested_reply: string | null;
        source: string;
        notified_at_label: string | null;
        contact_name?: string;
        lead_status?: string;
        product_name?: string;
        stage_name?: string;
    };
    interactions: Array<{
        id: number;
        type: string;
        type_label: string;
        direction: string;
        body: string;
        occurred_at_label: string | null;
        duration_minutes: number | null;
        outcome: string | null;
        author: string;
    }>;
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
            suggested_product: {
                id: number;
                name: string;
                reason: string | null;
            } | null;
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
    next_talk_label?: string | null;
    status: string;
    reason: string;
    rank?: number;
    source?: string | null;
};

export type ChartDay = {
    date: string;
    label: string;
    opened?: number;
    won?: number;
    inbound?: number;
    outbound?: number;
    count?: number;
    revenue?: number;
};

export type DashboardMetrics = {
    total_leads: number;
    new_leads_today: number;
    follow_ups_due_today: number;
    overdue_follow_ups: number;
    won_count: number;
    lost_count: number;
    open_leads: number;
    leads_by_stage: Array<{
        stage_id: number;
        stage_name: string;
        pipeline_name: string;
        kind: string;
        total: number;
    }>;
    follow_up_rows: FollowUpRow[];
    whatsapp: {
        conversations: number;
        messages_today: number;
        inbound_today: number;
        outbound_today: number;
        messages_7d: number;
        active_7d: number;
    };
    demos: {
        requested: number;
        scheduled: number;
        completed: number;
        in_flight: number;
        total: number;
    };
    stage_buckets: {
        follow_up: { total: number; stages: string[] };
        almost_buying: { total: number; stages: string[] };
        meetings: {
            total: number;
            requested: number;
            scheduled: number;
            completed: number;
            stages: string[];
        };
        nurture: { total: number; stages: string[] };
    };
    interactions: {
        today: number;
        phone_calls_today: number;
        meetings_today: number;
        notes_today: number;
    };
    scheduled_conversations: number;
    stopped_conversations: number;
    orders: {
        bought_count: number;
        bought_revenue: number;
        unpaid_count: number;
        overdue_count: number;
        draft_count: number;
        currency: string;
    };
    spend: {
        month_total: number;
    };
    charts: {
        leads_7d: ChartDay[];
        whatsapp_7d: ChartDay[];
        bought_7d: ChartDay[];
        days?: number;
        funnel: Array<{
            stage_id: number;
            stage_name: string;
            pipeline_name: string;
            kind: string;
            total: number;
        }>;
    };
    board: {
        filters: {
            kind: string;
            stage_id: string;
            product_id: string;
            hot: boolean;
            follow_up: string;
            timeline: string;
            from: string;
            to: string;
            q: string;
        };
        options: {
            kinds: Array<{ value: string; label: string }>;
            stages: Array<{ value: string; label: string }>;
            products: Array<{ value: string; label: string }>;
            follow_ups: Array<{ value: string; label: string }>;
            timelines: Array<{ value: string; label: string }>;
        };
        range: {
            from: string | null;
            to: string | null;
            label: string;
        };
        summary: {
            matched: number;
            open: number;
            hot: number;
            won: number;
            lost: number;
        };
        leads: Array<{
            id: number;
            contact_name: string;
            business_name: string | null;
            product_name: string;
            stage_name: string;
            stage_kind: string;
            source: string | null;
            intent: string | null;
            next_action: string | null;
            follow_up_label: string | null;
            next_talk_label: string | null;
            follow_up_rank?: number;
            follow_up_rank_reason?: string | null;
            follow_up_source?: string | null;
            ai_score: number | null;
            hot: boolean;
            updated_label: string | null;
        }>;
    };
    today: string;
    timezone: string;
};
