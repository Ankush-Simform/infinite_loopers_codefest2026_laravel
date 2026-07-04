<?php

namespace App\Enums;

enum ActivityType: string
{
    // database, api, external-api and admin
    case DATABASE = 'database';
    case API = 'api';
    case EXTERNAL_API = 'external-api';
    case ADMIN = 'admin';
}
