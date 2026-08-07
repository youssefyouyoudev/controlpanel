<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Offline = 'offline';
    case Unknown = 'unknown';
}
