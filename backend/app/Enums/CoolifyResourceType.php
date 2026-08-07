<?php

namespace App\Enums;

enum CoolifyResourceType: string
{
    case Application = 'application';
    case Service = 'service';
    case Database = 'database';
    case Server = 'server';
    case Project = 'project';
    case Environment = 'environment';
}
