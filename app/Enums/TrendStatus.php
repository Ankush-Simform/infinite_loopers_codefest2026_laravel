<?php

declare(strict_types=1);

namespace App\Enums;

enum TrendStatus: string
{
    case NORMAL = 'normal';
    case HIGH = 'high';
    case LOW = 'low';
    case INCREASED = 'increased';
    case DECREASED = 'decreased';
    case STABLE = 'stable';
}
