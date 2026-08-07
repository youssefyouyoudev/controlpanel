<?php

namespace App\Enums;

enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Offline = 'offline';
    case Unknown = 'unknown';
    case Maintenance = 'maintenance';
}
