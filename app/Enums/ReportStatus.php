<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    case DRAFT = 'draft';
    case UPLOADED = 'uploaded';
    case PROCESSING = 'processing';
    case WAITING_CONFIRMATION = 'waiting_confirmation';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
