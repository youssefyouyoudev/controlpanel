<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'website_id' => Website::factory(),
            'action' => fake()->randomElement(['auth.login', 'profile.updated', 'website.member_assigned']),
            'target_type' => 'user',
            'target_identifier' => (string) fake()->numberBetween(1, 100),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'request_id' => fake()->uuid(),
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}
