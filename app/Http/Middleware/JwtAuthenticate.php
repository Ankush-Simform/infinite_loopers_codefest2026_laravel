<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class JwtAuthenticate
{
    public function __construct(
        protected JwtService $jwtService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = null;
        $authorization = $request->header('Authorization');

        if ($authorization && str_starts_with($authorization, 'Bearer ')) {
            $token = substr($authorization, 7);
        } elseif ($request->has('token')) {
            $token = $request->query('token');
        }

        if (! $token) {
            return ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->jwtService->validateToken($token);

        if (! $payload || ! isset($payload['sub'])) {
            return ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $user = User::find($payload['sub']);
        if (! $user) {
            return ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        // Set the authenticated user for the Request and Laravel's Auth guard
        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
    
}
