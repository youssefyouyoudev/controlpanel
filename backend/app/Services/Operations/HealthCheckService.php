<?php

namespace App\Services\Operations;

use App\Enums\HealthStatus;
use App\Models\WebsiteHealthCheck;
use App\Models\WebsiteHealthCheckResult;
use App\Notifications\YouPanelNotification;
use App\Services\AuditLogger;
use App\Services\Security\SafeUrlService;
use Illuminate\Support\Facades\Notification;

class HealthCheckService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly SafeUrlService $urls) {}

    public function assertSafeUrl(string $url, bool $allowInternal = false): void
    {
        $this->urls->assertSafeHttpUrl($url, $allowInternal);
    }

    public function run(WebsiteHealthCheck $check): WebsiteHealthCheckResult
    {
        $this->assertSafeUrl($check->url, $check->allow_internal);
        $started = microtime(true);

        if ((bool) config('youpanel-actions.mock')) {
            $status = str_contains($check->url, 'offline') ? HealthStatus::Offline : HealthStatus::Healthy;
            $httpStatus = $status === HealthStatus::Healthy ? $check->expected_status : 503;
        } else {
            $response = $this->urls->get($check->url, $check->allow_internal, min($check->timeout_seconds, (int) config('youpanel.health.timeout_seconds')), 2);
            $httpStatus = $response->status();
            $body = substr($response->body(), 0, (int) config('youpanel.health.max_response_bytes'));
            $status = $httpStatus === $check->expected_status && (! $check->expected_text || str_contains($body, $check->expected_text))
                ? HealthStatus::Healthy
                : HealthStatus::Degraded;
        }

        $responseTime = (int) ((microtime(true) - $started) * 1000);
        $failureReason = $status === HealthStatus::Healthy ? null : 'The response did not match the configured health check.';
        $failures = $status === HealthStatus::Healthy ? 0 : $check->consecutive_failures + 1;

        $check->update([
            'status' => $failures >= (int) config('youpanel.health.failure_threshold') ? $status : ($status === HealthStatus::Healthy ? HealthStatus::Healthy : HealthStatus::Degraded),
            'consecutive_failures' => $failures,
            'last_checked_at' => now(),
            'failure_reason' => $failureReason,
        ]);

        $result = WebsiteHealthCheckResult::query()->create([
            'website_id' => $check->website_id,
            'website_health_check_id' => $check->id,
            'status' => $check->status,
            'http_status' => $httpStatus,
            'response_time_ms' => $responseTime,
            'failure_reason' => $failureReason,
            'metadata' => ['url' => parse_url($check->url, PHP_URL_HOST)],
            'checked_at' => now(),
        ]);

        if ($check->status !== HealthStatus::Healthy) {
            Notification::send($check->website->members, new YouPanelNotification('Website health warning', $failureReason ?? 'Website check failed.', 'warning', '/websites/'.$check->website_id.'/overview'));
        }

        $this->auditLogger->record('website.health_checked', null, $check->website, ['target_type' => 'health_check', 'target_identifier' => (string) $check->id, 'status' => $check->status->value]);

        return $result;
    }

}
