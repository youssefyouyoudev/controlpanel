<?php

namespace App\Enums;

enum WebsiteComponentType: string
{
    case Laravel = 'laravel';
    case Nextjs = 'nextjs';
    case Vite = 'vite';
    case Node = 'node';
    case Static = 'static';
    case Database = 'database';
    case Worker = 'worker';
    case Custom = 'custom';
}
