<?php

namespace App\Enums;

enum ConsoleExecutionStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
    case Rejected = 'rejected';
}
