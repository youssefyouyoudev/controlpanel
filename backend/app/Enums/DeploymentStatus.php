<?php

namespace App\Enums;

enum DeploymentStatus: string
{
    case Queued = 'queued';
    case AwaitingApproval = 'awaiting_approval';
    case Preparing = 'preparing';
    case Building = 'building';
    case Deploying = 'deploying';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
    case Unknown = 'unknown';
}
