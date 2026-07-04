<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    case UPLOADED = 'uploaded';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
