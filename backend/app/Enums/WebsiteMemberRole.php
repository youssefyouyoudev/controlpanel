<?php

namespace App\Enums;

enum WebsiteMemberRole: string
{
    case Developer = 'developer';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
