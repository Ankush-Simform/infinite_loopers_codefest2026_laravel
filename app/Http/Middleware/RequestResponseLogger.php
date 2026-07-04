<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ActivityType;
use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class RequestResponseLogger
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Sanitize sensitive request parameters
        $sanitizedPayload = $this->sanitizePayload($request->all());

        Log::channel('request_response')->info('Incoming Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => $sanitizedPayload,
        ]);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        // Capture response content (limit size to avoid huge logs)
        $responseContent = $response->getContent();
        $decodedResponse = json_decode($responseContent ?: '', true);
        if ($decodedResponse === null) {
            $decodedResponse = $responseContent ? substr($responseContent, 0, 1000) : null;
        } else {
            $decodedResponse = $this->sanitizePayload($decodedResponse);
        }

        Log::channel('request_response')->info('Outgoing Response', [
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'response' => $decodedResponse,
        ]);

        // Save activity log to the database
        try {
            ActivityLog::create([
                'user_id' => $request->user()?->id,
                'method' => $request->method(),
                'activity_type' => ActivityType::API,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'properties' => [
                    'url' => $request->fullUrl(),
                    'duration_ms' => $durationMs,
                    'status_code' => $response->getStatusCode(),
                ],
                'payload' => $sanitizedPayload,
            ]);
        } catch (\Throwable $e) {
            // Prevent database logging errors from failing the actual response
            Log::error('Failed to save activity log in database', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    /**
     * Sanitize sensitive inputs from logging payloads.
     */
    private function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'id_token',
            'api_key',
            'api_secret',
            'credential',
            'master_key_hash',
            'secret',
        ];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            } elseif (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $payload[$key] = '[REDACTED]';
            }
        }

        return $payload;
    }
}
