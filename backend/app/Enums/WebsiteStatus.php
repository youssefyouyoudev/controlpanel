<?php

namespace App\Enums;

enum WebsiteStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Offline = 'offline';
    case Unknown = 'unknown';
}
