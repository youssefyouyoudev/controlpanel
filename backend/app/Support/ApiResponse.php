<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => $data,
            'meta' => self::meta($meta),
            'errors' => null,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function error(string $message, int $status, array $errors = [], array $meta = []): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'data' => null,
            'meta' => self::meta($meta),
            'errors' => (object) $errors,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function meta(array $meta = []): array
    {
        /** @var Request|null $request */
        $request = request();
        $requestId = $request?->attributes->get('request_id');

        return array_filter([
            'request_id' => $requestId,
            ...$meta,
        ], fn (mixed $value): bool => $value !== null);
    }
}
