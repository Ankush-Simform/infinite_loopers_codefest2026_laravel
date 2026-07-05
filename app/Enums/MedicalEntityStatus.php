<?php

declare(strict_types=1);

namespace App\Enums;

enum MedicalEntityStatus: string
{
    case NORMAL = 'Normal';
    case HIGH = 'High';
    case LOW = 'Low';
    case CRITICAL = 'Critical';
    case POSITIVE = 'Positive';
    case NEGATIVE = 'Negative';
}
