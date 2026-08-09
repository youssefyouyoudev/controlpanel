<?php

namespace App\Console\Commands;

use App\Services\Security\SecurityConfigurationInspector;
use Illuminate\Console\Command;

class SecurityCheckCommand extends Command
{
    protected $signature = 'youpanel:security-check {--json : Output machine-readable JSON}';

    protected $description = 'Run safe YouPanel security posture checks without printing secrets.';

    public function handle(SecurityConfigurationInspector $inspector): int
    {
        $result = $inspector->inspect();

        if ($this->option('json')) {
            $this->line(json_encode([...$result, 'checked_at' => now()->toISOString()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach ($result['checks'] as $check) {
                $method = match ($check['status']) {
                    'pass' => 'info',
                    'warning' => 'warn',
                    default => 'error',
                };
                $label = match ($check['status']) {
                    'pass' => 'PASS',
                    'warning' => 'WARN',
                    default => 'FAIL',
                };

                $this->{$method}(sprintf('[%s] %s: %s', $label, $check['name'], $check['message']));
            }
        }

        return $result['score']['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
