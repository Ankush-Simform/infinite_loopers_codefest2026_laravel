<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatMessageRole: string
{
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case SYSTEM = 'system';
}
