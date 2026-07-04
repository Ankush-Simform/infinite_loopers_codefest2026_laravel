<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case MALE = 'Male';
    case FEMALE = 'Female';
    case OTHER = 'Other';
}
