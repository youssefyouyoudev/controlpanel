<?php

namespace App\Enums;

enum DeploymentProvider: string
{
    case Coolify = 'coolify';
    case Local = 'local';
    case Manual = 'manual';
}
