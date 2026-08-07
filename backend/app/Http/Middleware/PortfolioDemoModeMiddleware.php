<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortfolioDemoModeMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('youpanel.portfolio_demo')) {
            return $next($request);
        }

        $allowedMutations = [
            'api/v1/auth/login',
            'api/v1/auth/logout',
            'api/v1/auth/two-factor-challenge',
            'api/v1/auth/forgot-password',
            'api/v1/auth/reset-password',
        ];

        $isAllowedMutation = collect($allowedMutations)->contains(fn (string $pattern): bool => $request->is($pattern));

        if (! $request->isMethodSafe() && ! $isAllowedMutation) {
            return ApiResponse::error('Portfolio demo mode is read-only. Mutating actions are disabled.', 423);
        }

        return $next($request);
    }
}
