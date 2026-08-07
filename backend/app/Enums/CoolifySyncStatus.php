<?php

namespace App\Enums;

enum CoolifySyncStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Partial = 'partial';
    case Failed = 'failed';
    case Locked = 'locked';
}
