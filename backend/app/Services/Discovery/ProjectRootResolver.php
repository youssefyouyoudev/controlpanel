<?php

namespace App\Services\Discovery;

class ProjectRootResolver
{
    /**
     * @return array{root_path: string|null, evidence: array<int, string>, candidates: array<int, array<string, mixed>>}
     */
    public function resolve(?string $documentRoot, ?string $proxyPass = null): array
    {
        if ($documentRoot === null) {
            return ['root_path' => null, 'evidence' => $proxyPass ? ['nginx reverse proxy without document root'] : [], 'candidates' => []];
        }

        $configured = rtrim($documentRoot, DIRECTORY_SEPARATOR);
        if (! is_dir($configured)) {
            return ['root_path' => $documentRoot, 'evidence' => ['document root path is not readable'], 'candidates' => []];
        }

        $start = basename(str_replace('\\', '/', $configured)) === 'public' ? dirname($configured) : $configured;
        $boundary = $this->allowedBoundaryFor($start) ?? $start;
        $candidates = [];
        $current = $start;

        while ($this->isWithin($current, $boundary)) {
            $score = $this->score($current);
            $candidates[] = [
                'path' => $current,
                'score' => $score['score'],
                'evidence' => $score['evidence'],
            ];

            if ($current === $boundary || dirname($current) === $current) {
                break;
            }

            $current = dirname($current);
        }

        usort($candidates, fn (array $left, array $right): int => [$right['score'], strlen((string) $right['path'])] <=> [$left['score'], strlen((string) $left['path'])]);
        $best = $candidates[0] ?? ['path' => $start, 'evidence' => []];

        return [
            'root_path' => (string) $best['path'],
            'evidence' => array_values($best['evidence'] ?? []),
            'candidates' => $candidates,
        ];
    }

    /**
     * @return array{score: int, evidence: array<int, string>}
     */
    private function score(string $path): array
    {
        $score = 0;
        $evidence = [];

        if (is_dir($path.DIRECTORY_SEPARATOR.'.git')) {
            $score += 8;
            $evidence[] = '.git repository';
        }

        if ($this->hasBackendFrontend($path)) {
            $score += 12;
            $evidence[] = 'backend/frontend project layout';
        }

        if (is_file($path.DIRECTORY_SEPARATOR.'artisan')) {
            $score += 5;
            $evidence[] = 'Laravel artisan';
        }

        if (is_file($path.DIRECTORY_SEPARATOR.'composer.json')) {
            $score += 3;
            $evidence[] = 'composer.json';
        }

        if (is_file($path.DIRECTORY_SEPARATOR.'package.json')) {
            $score += 3;
            $evidence[] = 'package.json';
        }

        if (is_file($path.DIRECTORY_SEPARATOR.'docker-compose.yml') || is_file($path.DIRECTORY_SEPARATOR.'docker-compose.yaml')) {
            $score += 4;
            $evidence[] = 'docker compose';
        }

        if (is_dir($path.DIRECTORY_SEPARATOR.'public')) {
            $score += 1;
        }

        return ['score' => $score, 'evidence' => $evidence];
    }

    private function hasBackendFrontend(string $path): bool
    {
        return is_dir($path.DIRECTORY_SEPARATOR.'backend')
            && is_dir($path.DIRECTORY_SEPARATOR.'frontend')
            && ($this->looksLikeApp($path.DIRECTORY_SEPARATOR.'backend') || $this->looksLikeApp($path.DIRECTORY_SEPARATOR.'frontend'));
    }

    private function looksLikeApp(string $path): bool
    {
        return is_file($path.DIRECTORY_SEPARATOR.'artisan')
            || is_file($path.DIRECTORY_SEPARATOR.'composer.json')
            || is_file($path.DIRECTORY_SEPARATOR.'package.json')
            || is_file($path.DIRECTORY_SEPARATOR.'vite.config.js')
            || is_file($path.DIRECTORY_SEPARATOR.'vite.config.ts')
            || is_file($path.DIRECTORY_SEPARATOR.'next.config.js')
            || is_file($path.DIRECTORY_SEPARATOR.'next.config.mjs');
    }

    private function allowedBoundaryFor(string $path): ?string
    {
        $path = $this->normalize($path);

        foreach ((array) config('youpanel.discovery.allowed_roots', []) as $root) {
            $root = $this->normalize((string) $root);
            if ($root !== '' && $this->isWithin($path, $root)) {
                return $root;
            }
        }

        return null;
    }

    private function isWithin(string $path, string $root): bool
    {
        $path = $this->normalize($path);
        $root = rtrim($this->normalize($root), '/');

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', rtrim(realpath($path) ?: $path, DIRECTORY_SEPARATOR));
    }
}
