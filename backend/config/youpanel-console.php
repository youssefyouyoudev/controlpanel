<?php

return [
    'mock' => env('YOUPANEL_ACTION_DRIVER', 'mock') === 'mock',
    'commands' => [
        'artisan.about' => ['label' => 'php artisan about', 'description' => 'Show Laravel application information.', 'component_types' => ['laravel'], 'command' => ['php', 'artisan', 'about']],
        'artisan.routes' => ['label' => 'php artisan route:list', 'description' => 'List Laravel routes.', 'component_types' => ['laravel'], 'command' => ['php', 'artisan', 'route:list', '--except-vendor']],
        'artisan.migrate_status' => ['label' => 'php artisan migrate:status', 'description' => 'Show migration status.', 'component_types' => ['laravel'], 'command' => ['php', 'artisan', 'migrate:status']],
        'artisan.scheduler_list' => ['label' => 'php artisan schedule:list', 'description' => 'Show scheduled tasks.', 'component_types' => ['laravel'], 'command' => ['php', 'artisan', 'schedule:list']],
        'composer.validate' => ['label' => 'composer validate', 'description' => 'Validate composer.json.', 'component_types' => ['laravel', 'php'], 'command' => ['composer', 'validate', '--no-interaction', '--strict']],
        'composer.audit' => ['label' => 'composer audit', 'description' => 'Check PHP package advisories.', 'component_types' => ['laravel', 'php'], 'command' => ['composer', 'audit', '--no-interaction']],
        'npm.lint' => ['label' => 'npm run lint', 'description' => 'Run frontend lint script.', 'component_types' => ['nextjs', 'node'], 'command' => ['npm', 'run', 'lint']],
        'npm.typecheck' => ['label' => 'npm run typecheck', 'description' => 'Run TypeScript type checking.', 'component_types' => ['nextjs', 'node'], 'command' => ['npm', 'run', 'typecheck']],
        'npm.test' => ['label' => 'npm test', 'description' => 'Run JavaScript tests.', 'component_types' => ['nextjs', 'node'], 'command' => ['npm', 'test']],
        'git.status' => ['label' => 'git status', 'description' => 'Show Git working tree state.', 'component_types' => [], 'command' => ['git', 'status', '--short', '--branch']],
        'git.log' => ['label' => 'git log', 'description' => 'Show recent commits.', 'component_types' => [], 'command' => ['git', 'log', '--oneline', '--decorate', '-n', '20']],
    ],
];
