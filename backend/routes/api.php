<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ReadinessController;
use App\Http\Controllers\Api\V1\ActionExecutionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BackupController;
use App\Http\Controllers\Api\V1\BackupScheduleController;
use App\Http\Controllers\Api\V1\ConsoleController;
use App\Http\Controllers\Api\V1\CoolifyIntegrationController;
use App\Http\Controllers\Api\V1\CoolifyResourceController;
use App\Http\Controllers\Api\V1\CoolifyResourceLinkController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DatabaseWorkbenchController;
use App\Http\Controllers\Api\V1\DeploymentController;
use App\Http\Controllers\Api\V1\FileRevisionController;
use App\Http\Controllers\Api\V1\FileRootController;
use App\Http\Controllers\Api\V1\FileWorkspaceController;
use App\Http\Controllers\Api\V1\GitController;
use App\Http\Controllers\Api\V1\LogController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SecurityStatusController;
use App\Http\Controllers\Api\V1\TerminalSessionController;
use App\Http\Controllers\Api\V1\TrashController;
use App\Http\Controllers\Api\V1\TwoFactorAuthenticationController;
use App\Http\Controllers\Api\V1\WebsiteActionController;
use App\Http\Controllers\Api\V1\WebsiteComponentController;
use App\Http\Controllers\Api\V1\WebsiteController;
use App\Http\Controllers\Api\V1\WebsiteDiscoveryController;
use App\Http\Controllers\Api\V1\WebsiteHealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.health');
Route::get('/ready', ReadinessController::class)->name('api.ready');
Route::post('/internal/terminal/sessions/validate', [TerminalSessionController::class, 'validateGatewayToken'])
    ->middleware('throttle:terminal-gateway')
    ->name('api.internal.terminal.sessions.validate');
Route::post('/internal/terminal/sessions/{terminalSession}/events', [TerminalSessionController::class, 'gatewayEvent'])
    ->middleware('throttle:terminal-gateway')
    ->name('api.internal.terminal.sessions.events');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('/auth/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:login')->name('auth.two-factor-challenge');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:passwords')->name('auth.forgot-password');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:passwords')->name('auth.reset-password');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/user', [AuthController::class, 'user'])->name('auth.user');
        Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile');
        Route::put('/auth/password', [AuthController::class, 'updatePassword'])->name('auth.password');
        Route::get('/auth/two-factor', [TwoFactorAuthenticationController::class, 'show'])->middleware('throttle:operations-read')->name('auth.two-factor.show');
        Route::post('/auth/two-factor', [TwoFactorAuthenticationController::class, 'store'])->middleware('throttle:operations-sensitive')->name('auth.two-factor.store');
        Route::post('/auth/two-factor/confirm', [TwoFactorAuthenticationController::class, 'confirm'])->middleware('throttle:operations-sensitive')->name('auth.two-factor.confirm');
        Route::post('/auth/two-factor/recovery-codes', [TwoFactorAuthenticationController::class, 'recoveryCodes'])->middleware('throttle:operations-sensitive')->name('auth.two-factor.recovery-codes');
        Route::delete('/auth/two-factor', [TwoFactorAuthenticationController::class, 'destroy'])->middleware('throttle:operations-sensitive')->name('auth.two-factor.destroy');

        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');
        Route::get('/dashboard/services', [DashboardController::class, 'services'])->name('dashboard.services');
        Route::get('/dashboard/websites', [DashboardController::class, 'websites'])->name('dashboard.websites');
        Route::get('/dashboard/activity', [DashboardController::class, 'activity'])->name('dashboard.activity');
        Route::get('/system/readiness', [ReadinessController::class, 'detailed'])->middleware('throttle:operations-read')->name('system.readiness');
        Route::get('/security/status', SecurityStatusController::class)->middleware('throttle:operations-read')->name('security.status');
        Route::post('/terminal/sessions', [TerminalSessionController::class, 'store'])->middleware('throttle:operations-sensitive')->name('terminal.sessions.store');
        Route::get('/terminal/sessions/{terminalSession}', [TerminalSessionController::class, 'show'])->middleware('throttle:operations-read')->name('terminal.sessions.show');
        Route::delete('/terminal/sessions/{terminalSession}', [TerminalSessionController::class, 'destroy'])->middleware('throttle:operations-sensitive')->name('terminal.sessions.destroy');

        Route::get('/databases/overview', [DatabaseWorkbenchController::class, 'overview'])->middleware('throttle:operations-read')->name('databases.overview');
        Route::get('/databases', [DatabaseWorkbenchController::class, 'index'])->middleware('throttle:operations-read')->name('databases.index');
        Route::get('/databases/{database}', [DatabaseWorkbenchController::class, 'show'])->middleware('throttle:operations-read')->name('databases.show');
        Route::get('/databases/{database}/tables', [DatabaseWorkbenchController::class, 'tables'])->middleware('throttle:operations-read')->name('databases.tables');
        Route::get('/databases/{database}/tables/{table}', [DatabaseWorkbenchController::class, 'table'])->middleware('throttle:operations-read')->name('databases.tables.show');
        Route::get('/databases/{database}/tables/{table}/rows', [DatabaseWorkbenchController::class, 'rows'])->middleware('throttle:operations-read')->name('databases.tables.rows');
        Route::post('/databases/{database}/query', [DatabaseWorkbenchController::class, 'query'])->middleware('throttle:operations-sensitive')->name('databases.query');

        Route::get('/websites', [WebsiteController::class, 'index'])->name('websites.index');
        Route::post('/websites/discovery/scan', [WebsiteDiscoveryController::class, 'scan'])->middleware('throttle:operations-sensitive')->name('websites.discovery.scan');
        Route::post('/websites/discovery/sync', [WebsiteDiscoveryController::class, 'sync'])->middleware('throttle:operations-sensitive')->name('websites.discovery.sync');
        Route::get('/websites/{website}', [WebsiteController::class, 'show'])->name('websites.show');

        Route::get('/notifications', [NotificationController::class, 'index'])->middleware('throttle:operations-read');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('throttle:operations-write');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->middleware('throttle:operations-write');

        Route::get('/integrations/coolify/status', [CoolifyIntegrationController::class, 'status'])->middleware('throttle:coolify-read');
        Route::post('/integrations/coolify/test', [CoolifyIntegrationController::class, 'test'])->middleware('throttle:coolify-write');
        Route::get('/integrations/coolify/capabilities', [CoolifyIntegrationController::class, 'capabilities'])->middleware('throttle:coolify-read');
        Route::post('/integrations/coolify/synchronize', [CoolifyIntegrationController::class, 'synchronize'])->middleware('throttle:coolify-write');
        Route::get('/integrations/coolify/resources', [CoolifyIntegrationController::class, 'resources'])->middleware('throttle:coolify-read');

        Route::get('/deployments', [DeploymentController::class, 'index'])->middleware('throttle:coolify-read');
        Route::get('/deployments/{deployment}', [DeploymentController::class, 'show'])->middleware('throttle:coolify-read');
        Route::post('/deployments/{deployment}/approve', [DeploymentController::class, 'approve'])->middleware('throttle:deployments-write');
        Route::post('/deployments/{deployment}/reject', [DeploymentController::class, 'reject'])->middleware('throttle:deployments-write');
        Route::post('/deployments/{deployment}/cancel', [DeploymentController::class, 'cancel'])->middleware('throttle:deployments-write');
        Route::post('/deployments/{deployment}/redeploy', [DeploymentController::class, 'redeploy'])->middleware('throttle:deployments-write');
        Route::get('/deployments/{deployment}/logs', [DeploymentController::class, 'logs'])->middleware('throttle:logs-read');
        Route::get('/deployments/{deployment}/stream', [DeploymentController::class, 'stream'])->middleware('throttle:logs-read');

        Route::get('/console-executions/{execution}', [ConsoleController::class, 'show'])->middleware('throttle:operations-read');
        Route::get('/console-executions/{execution}/stream', [ConsoleController::class, 'stream'])->middleware('throttle:logs-read');
        Route::post('/console-executions/{execution}/cancel', [ConsoleController::class, 'cancel'])->middleware('throttle:console-run');

        Route::get('/action-executions', [ActionExecutionController::class, 'index'])->middleware('throttle:operations-read');
        Route::get('/action-executions/{execution}', [ActionExecutionController::class, 'show'])->middleware('throttle:operations-read');
        Route::post('/action-executions/{execution}/cancel', [ActionExecutionController::class, 'cancel'])->middleware('throttle:operations-sensitive');
        Route::post('/action-executions/{execution}/retry', [ActionExecutionController::class, 'retry'])->middleware('throttle:operations-sensitive');
        Route::get('/action-executions/{execution}/output', [ActionExecutionController::class, 'output'])->middleware('throttle:operations-read');
        Route::get('/action-executions/{execution}/stream', [ActionExecutionController::class, 'stream'])->middleware('throttle:operations-read');

        Route::get('/websites/{website}/file-roots', [FileRootController::class, 'index'])->middleware('throttle:files-browse');
        Route::post('/websites/{website}/file-roots', [FileRootController::class, 'store'])->middleware('throttle:files-recursive');
        Route::get('/websites/{website}/file-roots/{allowedPath}', [FileRootController::class, 'show'])->middleware('throttle:files-browse');
        Route::put('/websites/{website}/file-roots/{allowedPath}', [FileRootController::class, 'update'])->middleware('throttle:files-recursive');
        Route::delete('/websites/{website}/file-roots/{allowedPath}', [FileRootController::class, 'destroy'])->middleware('throttle:files-recursive');
        Route::post('/websites/{website}/file-roots/{allowedPath}/validate', [FileRootController::class, 'validateRoot'])->middleware('throttle:files-recursive');

        Route::get('/websites/{website}/files', [FileWorkspaceController::class, 'index'])->middleware('throttle:files-browse');
        Route::get('/websites/{website}/files/metadata', [FileWorkspaceController::class, 'metadata'])->middleware('throttle:files-browse');
        Route::get('/websites/{website}/files/search', [FileWorkspaceController::class, 'search'])->middleware('throttle:files-search');
        Route::get('/websites/{website}/files/content', [FileWorkspaceController::class, 'content'])->middleware('throttle:files-read');
        Route::put('/websites/{website}/files/content', [FileWorkspaceController::class, 'save'])->middleware('throttle:files-write');
        Route::post('/websites/{website}/files/create', [FileWorkspaceController::class, 'create'])->middleware('throttle:files-write');
        Route::post('/websites/{website}/directories', [FileWorkspaceController::class, 'directory'])->middleware('throttle:files-write');
        Route::post('/websites/{website}/files/upload', [FileWorkspaceController::class, 'upload'])->middleware('throttle:files-upload');
        Route::get('/websites/{website}/files/download', [FileWorkspaceController::class, 'download'])->middleware('throttle:files-download');
        Route::post('/websites/{website}/files/download-archive', [FileWorkspaceController::class, 'archive'])->middleware('throttle:files-download');
        Route::post('/websites/{website}/files/rename', [FileWorkspaceController::class, 'rename'])->middleware('throttle:files-write');
        Route::post('/websites/{website}/files/move', [FileWorkspaceController::class, 'move'])->middleware('throttle:files-recursive');
        Route::post('/websites/{website}/files/copy', [FileWorkspaceController::class, 'copy'])->middleware('throttle:files-recursive');
        Route::post('/websites/{website}/files/archive', [FileWorkspaceController::class, 'archive'])->middleware('throttle:files-recursive');
        Route::post('/websites/{website}/files/extract', [FileWorkspaceController::class, 'extract'])->middleware('throttle:files-recursive');
        Route::delete('/websites/{website}/files', [FileWorkspaceController::class, 'delete'])->middleware('throttle:files-recursive');

        Route::get('/websites/{website}/trash', [TrashController::class, 'index'])->middleware('throttle:files-browse');
        Route::post('/websites/{website}/trash/empty-expired', [TrashController::class, 'emptyExpired'])->middleware('throttle:files-permanent-delete');
        Route::post('/websites/{website}/trash/{trashEntry}/restore', [TrashController::class, 'restore'])->middleware('throttle:files-restore');
        Route::delete('/websites/{website}/trash/{trashEntry}', [TrashController::class, 'destroy'])->middleware('throttle:files-permanent-delete');

        Route::get('/websites/{website}/files/revisions', [FileRevisionController::class, 'index'])->middleware('throttle:files-browse');
        Route::get('/websites/{website}/files/revisions/{revision}', [FileRevisionController::class, 'show'])->middleware('throttle:files-read');
        Route::post('/websites/{website}/files/revisions/{revision}/restore', [FileRevisionController::class, 'restore'])->middleware('throttle:files-restore');

        Route::get('/websites/{website}/components', [WebsiteComponentController::class, 'index'])->middleware('throttle:operations-read');
        Route::post('/websites/{website}/components', [WebsiteComponentController::class, 'store'])->middleware('throttle:operations-sensitive');
        Route::get('/websites/{website}/components/{component}', [WebsiteComponentController::class, 'show'])->middleware('throttle:operations-read');
        Route::put('/websites/{website}/components/{component}', [WebsiteComponentController::class, 'update'])->middleware('throttle:operations-sensitive');
        Route::delete('/websites/{website}/components/{component}', [WebsiteComponentController::class, 'destroy'])->middleware('throttle:operations-sensitive');
        Route::post('/websites/{website}/components/{component}/validate', [WebsiteComponentController::class, 'validateComponent'])->middleware('throttle:operations-read');

        Route::get('/websites/{website}/actions', [WebsiteActionController::class, 'index'])->middleware('throttle:operations-read');
        Route::post('/websites/{website}/actions/{actionKey}/execute', [WebsiteActionController::class, 'execute'])->where('actionKey', '[A-Za-z0-9_.-]+')->middleware('throttle:operations-sensitive');

        Route::get('/websites/{website}/coolify-links', [CoolifyResourceLinkController::class, 'index'])->middleware('throttle:coolify-read');
        Route::post('/websites/{website}/coolify-links', [CoolifyResourceLinkController::class, 'store'])->middleware('throttle:coolify-write');
        Route::put('/websites/{website}/coolify-links/{link}', [CoolifyResourceLinkController::class, 'update'])->middleware('throttle:coolify-write');
        Route::delete('/websites/{website}/coolify-links/{link}', [CoolifyResourceLinkController::class, 'destroy'])->middleware('throttle:coolify-write');
        Route::post('/websites/{website}/coolify-links/{link}/verify', [CoolifyResourceLinkController::class, 'verify'])->middleware('throttle:coolify-write');

        Route::post('/websites/{website}/deployments', [DeploymentController::class, 'store'])->middleware('throttle:deployments-write');
        Route::post('/websites/{website}/terminal/sessions', [TerminalSessionController::class, 'storeForWebsite'])->middleware('throttle:operations-sensitive')->name('websites.terminal.sessions.store');

        Route::get('/websites/{website}/resources', [CoolifyResourceController::class, 'index'])->middleware('throttle:coolify-read');
        Route::get('/websites/{website}/resources/{link}', [CoolifyResourceController::class, 'show'])->middleware('throttle:coolify-read');
        Route::post('/websites/{website}/resources/{link}/start', [CoolifyResourceController::class, 'start'])->middleware('throttle:deployments-write');
        Route::post('/websites/{website}/resources/{link}/stop', [CoolifyResourceController::class, 'stop'])->middleware('throttle:deployments-write');
        Route::post('/websites/{website}/resources/{link}/restart', [CoolifyResourceController::class, 'restart'])->middleware('throttle:deployments-write');

        Route::get('/websites/{website}/console/commands', [ConsoleController::class, 'commands'])->middleware('throttle:operations-read');
        Route::post('/websites/{website}/console/execute', [ConsoleController::class, 'execute'])->middleware('throttle:console-run');

        Route::get('/websites/{website}/git/status', [GitController::class, 'status'])->middleware('throttle:operations-read');
        Route::get('/websites/{website}/git/commits', [GitController::class, 'commits'])->middleware('throttle:operations-read');
        Route::get('/websites/{website}/git/branches', [GitController::class, 'branches'])->middleware('throttle:operations-read');
        Route::post('/websites/{website}/git/fetch', [GitController::class, 'fetch'])->middleware('throttle:operations-sensitive');
        Route::post('/websites/{website}/git/pull', [GitController::class, 'pull'])->middleware('throttle:operations-sensitive');

        Route::get('/websites/{website}/logs/sources', [LogController::class, 'sources'])->middleware('throttle:operations-read');
        Route::post('/websites/{website}/logs/sources', [LogController::class, 'storeSource'])->middleware('throttle:operations-sensitive');
        Route::get('/websites/{website}/logs/{source}', [LogController::class, 'show'])->middleware('throttle:logs-read');
        Route::get('/websites/{website}/logs/{source}/stream', [LogController::class, 'stream'])->middleware('throttle:logs-read');

        Route::get('/websites/{website}/backups', [BackupController::class, 'index'])->middleware('throttle:operations-read');
        Route::post('/websites/{website}/backups', [BackupController::class, 'store'])->middleware('throttle:operations-sensitive');
        Route::get('/websites/{website}/backups/{backup}', [BackupController::class, 'show'])->middleware('throttle:operations-read');
        Route::get('/websites/{website}/backups/{backup}/download', [BackupController::class, 'download'])->middleware('throttle:operations-sensitive');
        Route::post('/websites/{website}/backups/{backup}/verify', [BackupController::class, 'verify'])->middleware('throttle:operations-read');
        Route::post('/websites/{website}/backups/{backup}/restore', [BackupController::class, 'restore'])->middleware('throttle:operations-sensitive');
        Route::delete('/websites/{website}/backups/{backup}', [BackupController::class, 'destroy'])->middleware('throttle:operations-sensitive');

        Route::get('/websites/{website}/backup-schedules', [BackupScheduleController::class, 'index'])->middleware('throttle:operations-read');
        Route::post('/websites/{website}/backup-schedules', [BackupScheduleController::class, 'store'])->middleware('throttle:operations-sensitive');
        Route::put('/websites/{website}/backup-schedules/{schedule}', [BackupScheduleController::class, 'update'])->middleware('throttle:operations-sensitive');
        Route::delete('/websites/{website}/backup-schedules/{schedule}', [BackupScheduleController::class, 'destroy'])->middleware('throttle:operations-sensitive');

        Route::get('/websites/{website}/health', [WebsiteHealthController::class, 'show'])->middleware('throttle:operations-read');
        Route::post('/websites/{website}/health', [WebsiteHealthController::class, 'store'])->middleware('throttle:operations-sensitive');
        Route::post('/websites/{website}/health/check', [WebsiteHealthController::class, 'check'])->middleware('throttle:operations-write');
        Route::get('/websites/{website}/health/history', [WebsiteHealthController::class, 'history'])->middleware('throttle:operations-read');
    });
});
