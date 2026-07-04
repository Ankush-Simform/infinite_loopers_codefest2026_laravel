<?php

namespace App\Enums;

enum ProfileRelation: string
{
    // SELF , FAMILY, OTHER
    case SELF = 'self';
    case FAMILY = 'family';
    case OTHER = 'other';
}
