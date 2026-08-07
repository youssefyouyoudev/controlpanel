<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Running = 'running';
    case Stopped = 'stopped';
    case Degraded = 'degraded';
    case Unknown = 'unknown';
    case Unavailable = 'unavailable';
}
