<?php

namespace App\Ai;

enum AnalysisStatus: string
{
    case Queued = 'queued';
    case Completed = 'completed';
    case Failed = 'failed';
    case Unavailable = 'unavailable';
}
