<?php

namespace App\Enums;

enum ProfileRelation: string
{
    case SELF = 'self';
    case WIFE = 'wife';
    case SON = 'son';
    case DAUGHTER = 'daughter';
    case FATHER = 'father';
    case MOTHER = 'mother';
    case OTHER = 'other';
    case FAMILY = 'family';
}
