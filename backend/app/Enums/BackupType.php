<?php

namespace App\Enums;

enum BackupType: string
{
    case Files = 'files';
    case Database = 'database';
    case Configuration = 'configuration';
    case Full = 'full';
    case PreDeployment = 'pre_deployment';
    case PreMigration = 'pre_migration';
    case Manual = 'manual';
}
