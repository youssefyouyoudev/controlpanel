<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreBackupScheduleRequest;
use App\Http\Resources\BackupScheduleResource;
use App\Models\BackupSchedule;
use App\Models\Website;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupScheduleController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        return ApiResponse::success(BackupScheduleResource::collection($website->backupSchedules()->latest()->get())->resolve($request));
    }

    public function store(StoreBackupScheduleRequest $request, Website $website): JsonResponse
    {
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure backup schedules.');
        $schedule = $website->backupSchedules()->create($this->payload($request->validated()));
        $this->auditLogger->record('backup_schedule.created', $request->user(), $website, ['target_type' => 'backup_schedule', 'target_identifier' => (string) $schedule->id]);

        return ApiResponse::success(['schedule' => new BackupScheduleResource($schedule)], 'Backup schedule created.', 201);
    }

    public function update(StoreBackupScheduleRequest $request, Website $website, BackupSchedule $schedule): JsonResponse
    {
        $this->assertBelongs($website, $schedule);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure backup schedules.');
        $schedule->update($this->payload($request->validated()));
        $this->auditLogger->record('backup_schedule.updated', $request->user(), $website, ['target_type' => 'backup_schedule', 'target_identifier' => (string) $schedule->id]);

        return ApiResponse::success(['schedule' => new BackupScheduleResource($schedule->refresh())], 'Backup schedule updated.');
    }

    public function destroy(Request $request, Website $website, BackupSchedule $schedule): JsonResponse
    {
        $this->assertBelongs($website, $schedule);
        abort_unless($request->user()?->isOwner(), 403, 'Only owners may configure backup schedules.');
        $schedule->delete();

        return ApiResponse::success(null, 'Backup schedule deleted.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $cron = $data['schedule'] === 'weekly' ? '0 2 * * 0' : '0 2 * * *';
        unset($data['schedule']);

        return [...$data, 'cron_expression' => $cron];
    }

    private function assertBelongs(Website $website, BackupSchedule $schedule): void
    {
        abort_unless($schedule->website_id === $website->id, 404);
    }
}
