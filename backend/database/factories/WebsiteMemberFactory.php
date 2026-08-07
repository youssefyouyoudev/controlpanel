<?php

namespace Database\Factories;

use App\Enums\WebsiteMemberRole;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteMember>
 */
class WebsiteMemberFactory extends Factory
{
    protected $model = WebsiteMember::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(WebsiteMemberRole::cases()),
        ];
    }
}
