<?php

namespace App\Enums;

enum ActionExecutionStatus: string
{
    case Queued = 'queued';
    case Preparing = 'preparing';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
    case Blocked = 'blocked';
}
