<?php

namespace App\Services\Discovery;

use Illuminate\Support\Str;

class StackDetector
{
    /**
     * @return array<string, mixed>
     */
    public function detect(?string $root, ?string $proxyPass = null): array
    {
        if ($root === null || ! is_dir($root) || ! is_readable($root)) {
            return [
                'architecture' => $proxyPass ? 'reverse-proxy' : 'unknown',
                'summary' => $proxyPass ? 'Reverse proxy' : 'Unknown',
                'primary_type' => $proxyPass ? 'reverse_proxy' : 'unknown',
                'primary_runtime' => null,
                'frameworks' => [],
                'runtimes' => $proxyPass ? ['nginx'] : [],
                'components' => [],
                'evidence' => $proxyPass ? ['proxy_pass '.$proxyPass] : [],
            ];
        }

        $components = [];
        foreach ($this->candidateDirectories($root) as $directory) {
            $component = $this->detectComponent($root, $directory);
            if ($component !== null) {
                $components[] = $component;
            }
        }

        if ($components === []) {
            $components[] = [
                'name' => basename($root),
                'role' => 'app',
                'type' => $proxyPass ? 'reverse_proxy' : 'unknown',
                'framework' => $proxyPass ? 'Reverse proxy' : 'Unknown',
                'runtime' => null,
                'path' => $root,
                'relative_path' => '',
                'scripts' => new \stdClass,
                'evidence' => $proxyPass ? ['proxy_pass '.$proxyPass] : [],
            ];
        }

        $frameworks = array_values(array_unique(array_filter(array_map(fn (array $component): ?string => $component['framework'] ?? null, $components))));
        $runtimes = array_values(array_unique(array_filter([
            'nginx',
            ...array_map(fn (array $component): ?string => $component['runtime'] ?? null, $components),
        ])));
        $roles = array_unique(array_map(fn (array $component): string => (string) $component['role'], $components));
        $architecture = in_array('backend', $roles, true) && in_array('frontend', $roles, true)
            ? 'full-stack'
            : ($components[0]['role'] ?? 'app');

        return [
            'architecture' => $architecture,
            'summary' => implode(' + ', $frameworks) ?: 'Unknown',
            'primary_type' => (string) ($components[0]['type'] ?? 'unknown'),
            'primary_runtime' => $components[0]['runtime'] ?? null,
            'frameworks' => $frameworks,
            'runtimes' => $runtimes,
            'components' => $components,
            'evidence' => array_values(array_unique(array_merge(...array_map(fn (array $component): array => $component['evidence'] ?? [], $components)))),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function candidateDirectories(string $root): array
    {
        $directories = [$root];

        foreach (['backend', 'frontend', 'api', 'client', 'server'] as $name) {
            $path = $root.DIRECTORY_SEPARATOR.$name;
            if (is_dir($path)) {
                $directories[] = $path;
            }
        }

        foreach (['apps', 'packages'] as $group) {
            $base = $root.DIRECTORY_SEPARATOR.$group;
            if (! is_dir($base) || ! is_readable($base)) {
                continue;
            }

            foreach (array_slice(scandir($base) ?: [], 0, 40) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $base.DIRECTORY_SEPARATOR.$item;
                if (is_dir($path)) {
                    $directories[] = $path;
                }
            }
        }

        return array_values(array_unique($directories));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detectComponent(string $projectRoot, string $directory): ?array
    {
        $composer = $this->jsonFile($directory.DIRECTORY_SEPARATOR.'composer.json');
        $package = $this->jsonFile($directory.DIRECTORY_SEPARATOR.'package.json');
        $has = fn (string $file): bool => file_exists($directory.DIRECTORY_SEPARATOR.$file);
        $deps = strtolower(json_encode([$composer['require'] ?? [], $package['dependencies'] ?? [], $package['devDependencies'] ?? []]) ?: '');
        $evidence = [];
        $type = null;
        $framework = null;
        $runtime = null;

        if ($has('artisan') || str_contains($deps, 'laravel/framework')) {
            $type = 'laravel';
            $framework = 'Laravel';
            $runtime = 'PHP';
            $evidence[] = 'Laravel';
        } elseif ($has('wp-config.php')) {
            $type = 'wordpress';
            $framework = 'WordPress';
            $runtime = 'PHP';
            $evidence[] = 'wp-config.php';
        } elseif (str_contains($deps, 'symfony/')) {
            $type = 'symfony';
            $framework = 'Symfony';
            $runtime = 'PHP';
            $evidence[] = 'Symfony dependencies';
        } elseif ($composer !== [] || $has('public/index.php') || $has('index.php')) {
            $type = 'php';
            $framework = 'PHP';
            $runtime = 'PHP';
            $evidence[] = 'PHP application';
        }

        if ($has('next.config.js') || $has('next.config.mjs') || $has('next.config.ts') || str_contains($deps, '"next"')) {
            [$type, $framework, $runtime] = ['nextjs', 'Next.js', 'Node'];
            $evidence[] = 'Next.js';
        } elseif ($has('nuxt.config.js') || $has('nuxt.config.ts') || str_contains($deps, '"nuxt"')) {
            [$type, $framework, $runtime] = ['nuxt', 'Nuxt', 'Node'];
            $evidence[] = 'Nuxt';
        } elseif ($has('vite.config.js') || $has('vite.config.ts') || str_contains($deps, '"vite"')) {
            $type = $this->viteType($deps);
            $framework = $type === 'vue' ? 'Vue / Vite' : ($type === 'react' ? 'React / Vite' : 'Vite');
            $runtime = 'Node';
            $evidence[] = 'Vite';
        } elseif (str_contains($deps, '"@nestjs/core"')) {
            [$type, $framework, $runtime] = ['nestjs', 'NestJS', 'Node'];
            $evidence[] = 'NestJS';
        } elseif (str_contains($deps, '"express"')) {
            [$type, $framework, $runtime] = ['express', 'Express', 'Node'];
            $evidence[] = 'Express';
        } elseif ($package !== []) {
            [$type, $framework, $runtime] = ['node', 'Node.js', 'Node'];
            $evidence[] = 'package.json';
        } elseif (($type === null) && ($has('index.html') || $has('public/index.html'))) {
            [$type, $framework, $runtime] = ['static', 'Static HTML', null];
            $evidence[] = 'static index';
        }

        if ($type === null) {
            return null;
        }

        return [
            'name' => basename($directory),
            'role' => $this->roleFor($projectRoot, $directory, $type),
            'type' => $type,
            'framework' => $framework,
            'runtime' => $runtime,
            'path' => $directory,
            'relative_path' => $this->relativePath($projectRoot, $directory),
            'scripts' => $this->scripts($package),
            'evidence' => array_values(array_unique($evidence)),
        ];
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, string>|\stdClass
     */
    private function scripts(array $package): array|\stdClass
    {
        if (! isset($package['scripts']) || ! is_array($package['scripts']) || $package['scripts'] === []) {
            return new \stdClass;
        }

        return collect($package['scripts'])
            ->filter(fn (mixed $script, mixed $name): bool => is_string($name) && is_string($script))
            ->all() ?: new \stdClass;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 512 * 1024) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) ? $payload : [];
    }

    private function viteType(string $dependencies): string
    {
        return match (true) {
            str_contains($dependencies, '"vue"') => 'vue',
            str_contains($dependencies, '"react"') => 'react',
            default => 'vite',
        };
    }

    private function roleFor(string $projectRoot, string $directory, string $type): string
    {
        $relative = $this->relativePath($projectRoot, $directory);
        if (str_contains($relative, 'frontend') || in_array($type, ['nextjs', 'nuxt', 'react', 'vue', 'vite', 'static'], true)) {
            return 'frontend';
        }

        if (str_contains($relative, 'backend') || in_array($type, ['laravel', 'symfony', 'php', 'nestjs', 'express'], true)) {
            return 'backend';
        }

        return 'app';
    }

    private function relativePath(string $projectRoot, string $directory): string
    {
        $relative = Str::after(str_replace('\\', '/', $directory), rtrim(str_replace('\\', '/', $projectRoot), '/').'/');

        return $relative === str_replace('\\', '/', $directory) ? '' : $relative;
    }
}
