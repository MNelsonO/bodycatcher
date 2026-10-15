<?php

namespace App\Leads;

enum ActivityEvent: string
{
    case ContactCreated = 'contact_created';
    case LeadCreated = 'lead_created';
    case StageChanged = 'stage_changed';
    case MessageLogged = 'message_logged';
    case NoteAdded = 'note_added';
    case ContactNoteAdded = 'contact_note_added';
    case InteractionRecorded = 'interaction_recorded';
    case ConversationScheduled = 'conversation_scheduled';
    case ContactMerged = 'contact_merged';
    case FollowUpChanged = 'follow_up_changed';
    case MessageRefused = 'message_refused';
    case MessagingFlagChanged = 'messaging_flag_changed';
    case ContactOptedOut = 'contact_opted_out';
    case AnalysisCompleted = 'analysis_completed';
    case AnalysisFailed = 'analysis_failed';
    case ExtractionApplied = 'extraction_applied';
    case DraftEdited = 'draft_edited';
    case DraftSent = 'draft_sent';
    case FollowUpScheduled = 'follow_up_scheduled';
    case ChatFollowUpCaptured = 'chat_follow_up_captured';
    case EscalatedToOperator = 'escalated_to_operator';
    case OperatorCorrectionApplied = 'operator_correction_applied';
    case KnowledgeGapAnswered = 'knowledge_gap_answered';
    case FollowUpCancelled = 'follow_up_cancelled';
    case FollowUpHeld = 'follow_up_held';
    case FollowUpSent = 'follow_up_sent';
    case FollowUpRefused = 'follow_up_refused';
    case FollowUpCompleted = 'follow_up_completed';
}
