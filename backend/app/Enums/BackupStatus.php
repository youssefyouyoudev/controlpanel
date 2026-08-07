<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Verifying = 'verifying';
    case Verified = 'verified';
    case Restoring = 'restoring';
    case Restored = 'restored';
}
