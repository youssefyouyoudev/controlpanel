<?php

namespace App\Enums;

enum DeploymentTrigger: string
{
    case Manual = 'manual';
    case GitPush = 'git_push';
    case Webhook = 'webhook';
    case Rollback = 'rollback';
    case Redeploy = 'redeploy';
    case Unknown = 'unknown';
}
