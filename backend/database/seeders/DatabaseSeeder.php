<?php

namespace Database\Seeders;

use App\Enums\ServerStatus;
use App\Enums\UserRole;
use App\Enums\WebsiteMemberRole;
use App\Enums\WebsiteStatus;
use App\Models\AllowedPath;
use App\Models\Server;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteComponent;
use App\Models\WebsiteHealthCheck;
use App\Models\WebsiteLogSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! (bool) config('youpanel.demo_enabled')) {
            return;
        }

        $owner = User::query()
            ->where('role', UserRole::Owner)
            ->orderBy('id')
            ->first()
            ?? User::query()->firstOrCreate(
                ['email' => 'owner@youpanel.test'],
                [
                    'name' => 'Youssef Demo Owner',
                    'password' => Hash::make('ChangeMe!DemoOnly123'),
                    'role' => UserRole::Owner,
                    'is_active' => true,
                    'timezone' => 'Africa/Casablanca',
                ]
            );

        $developer = User::query()->firstOrCreate(
            ['email' => 'developer@youpanel.test'],
            [
                'name' => 'Trusted Developer',
                'password' => Hash::make('ChangeMe!DemoOnly123'),
                'role' => UserRole::Developer,
                'is_active' => true,
            ]
        );

        $server = Server::query()->firstOrCreate(
            ['slug' => 'youssef-ubuntu'],
            [
                'name' => 'Youssef Ubuntu Server',
                'hostname' => 'panel.youssefyouyou.com',
                'description' => 'Primary private Ubuntu server.',
                'operating_system' => 'Ubuntu',
                'is_local' => true,
                'status' => ServerStatus::Healthy,
                'last_seen_at' => now(),
            ]
        );

        $portfolio = Website::query()->firstOrCreate(
            ['slug' => 'youssef-portfolio'],
            [
                'server_id' => $server->id,
                'name' => 'Youssef Portfolio',
                'domain' => 'youssefyouyou.com',
                'framework' => 'Laravel + Next.js',
                'root_path' => '/var/www/youssefyouyou.com',
                'repository_branch' => 'main',
                'status' => WebsiteStatus::Healthy,
            ]
        );

        $rifitv = Website::query()->firstOrCreate(
            ['slug' => 'rifitv'],
            [
                'server_id' => $server->id,
                'name' => 'RiFiTV',
                'domain' => 'rifitv.com',
                'framework' => 'Laravel + Blade',
                'root_path' => '/var/www/live.rifimedia.com',
                'repository_branch' => 'main',
                'status' => WebsiteStatus::Degraded,
            ]
        );

        $portfolio->members()->syncWithoutDetaching([$developer->id => ['role' => WebsiteMemberRole::Developer->value]]);
        $rifitv->members()->syncWithoutDetaching([$developer->id => ['role' => WebsiteMemberRole::Viewer->value]]);
        $this->seedDemoWorkspace($portfolio, 'portfolio', [
            'README.md' => "# Youssef Portfolio\n\nDemo workspace for Phase 2 file editing.\n",
            'frontend/app/page.tsx' => "export default function Page() {\n  return <main>Youssef Portfolio</main>;\n}\n",
            'frontend/app/globals.css' => ":root {\n  --brand: #2f7df4;\n}\n",
            'backend/routes/api.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/health', fn () => ['ok' => true]);\n",
            'config/site.json' => "{\n  \"name\": \"Youssef Portfolio\",\n  \"framework\": \"Laravel + Next.js\"\n}\n",
            'public/logo.svg' => "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 120 120\"><rect width=\"120\" height=\"120\" rx=\"18\" fill=\"#111827\"/><text x=\"60\" y=\"70\" text-anchor=\"middle\" font-size=\"36\" fill=\"#ffffff\">YP</text></svg>\n",
        ]);
        $portfolioBackend = WebsiteComponent::query()->firstOrCreate(
            ['website_id' => $portfolio->id, 'slug' => 'portfolio-backend'],
            [
                'name' => 'Portfolio Backend',
                'type' => 'laravel',
                'relative_working_directory' => 'backend',
                'runtime' => 'php-8.3',
                'status' => 'healthy',
                'is_active' => true,
            ]
        );
        WebsiteComponent::query()->firstOrCreate(
            ['website_id' => $portfolio->id, 'slug' => 'portfolio-frontend'],
            [
                'name' => 'Portfolio Frontend',
                'type' => 'nextjs',
                'relative_working_directory' => 'frontend',
                'runtime' => 'node',
                'process_manager' => 'pm2',
                'process_name' => 'youssef-frontend',
                'build_command_key' => 'npm.build',
                'status' => 'healthy',
                'is_active' => true,
            ]
        );
        WebsiteLogSource::query()->firstOrCreate(
            ['website_id' => $portfolio->id, 'slug' => 'demo-app'],
            [
                'website_component_id' => $portfolioBackend->id,
                'name' => 'Demo Laravel log',
                'type' => 'laravel',
                'absolute_path' => storage_path('logs/laravel.log'),
                'is_active' => true,
            ]
        );
        WebsiteHealthCheck::query()->firstOrCreate(
            ['website_id' => $portfolio->id, 'url' => 'https://youssefyouyou.com'],
            ['expected_status' => 200, 'timeout_seconds' => 5, 'status' => 'unknown', 'is_active' => true]
        );
        $this->seedDemoWorkspace($rifitv, 'rifitv', [
            'README.md' => "# RiFiTV\n\nDemo Laravel Blade workspace for safe browsing.\n",
            'resources/views/home.blade.php' => "<x-layout>\n    <h1>RiFiTV</h1>\n</x-layout>\n",
            'routes/web.php' => "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::view('/', 'home');\n",
            'public/css/app.css' => "body {\n  font-family: Inter, sans-serif;\n}\n",
            'storage/app/content.json' => "{\n  \"channel\": \"RiFiTV\",\n  \"mode\": \"demo\"\n}\n",
        ]);
        WebsiteComponent::query()->firstOrCreate(
            ['website_id' => $rifitv->id, 'slug' => 'rifitv-app'],
            [
                'name' => 'RiFiTV App',
                'type' => 'laravel',
                'relative_working_directory' => '',
                'runtime' => 'php-8.3',
                'status' => 'degraded',
                'is_active' => true,
            ]
        );

        $owner->auditLogs()->create(['action' => 'demo.seeded', 'target_type' => 'database', 'target_identifier' => 'phase-3']);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function seedDemoWorkspace(Website $website, string $slug, array $files): void
    {
        $root = storage_path('app/youpanel-demo/'.$slug);
        File::ensureDirectoryExists($root);

        foreach ($files as $relativePath => $content) {
            $absolutePath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            File::ensureDirectoryExists(dirname($absolutePath));
            if (! File::exists($absolutePath)) {
                File::put($absolutePath, $content);
            }
        }

        AllowedPath::query()->firstOrCreate(
            ['website_id' => $website->id, 'name' => 'Demo workspace'],
            [
                'relative_label' => 'Demo workspace',
                'absolute_path' => $root,
                'is_primary' => true,
                'can_read' => true,
                'can_write' => true,
                'can_upload' => true,
                'can_create' => true,
                'can_rename' => true,
                'can_move' => true,
                'can_copy' => true,
                'can_delete' => true,
                'can_archive' => true,
                'can_extract' => true,
                'is_active' => true,
                'allowed_extensions' => ['php', 'ts', 'tsx', 'css', 'json', 'md', 'svg', 'blade.php'],
                'blocked_patterns' => ['.env', '.env.*', '*.key', '*.pem'],
                'metadata' => ['diagnostics' => ['status' => 'writable', 'readable' => true, 'writable' => true]],
            ]
        );
    }
}
