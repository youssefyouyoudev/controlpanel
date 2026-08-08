<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Running = 'running';
    case Stopped = 'stopped';
    case Failed = 'failed';
    case Degraded = 'degraded';
    case Unknown = 'unknown';
    case Unavailable = 'unavailable';
}
