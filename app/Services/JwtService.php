<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

final class JwtService
{
    private string $secret;

    private int $ttl; // in seconds

    public function __construct()
    {
        // Read configuration settings from config/auth.php
        $this->secret = config('auth.jwt.secret') ?: 'base64:fallbacksecretkeyshouldbechanged';
        $this->ttl = (int) config('auth.jwt.ttl', 18000);
    }

    /**
     * Generate a new JWT token for a user.
     */
    public function generateToken(User $user): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);

        $payload = json_encode([
            'sub' => $user->id,
            'email' => $user->email,
            'iat' => time(),
            'exp' => time() + $this->ttl,
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader.'.'.$base64UrlPayload, $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader.'.'.$base64UrlPayload.'.'.$base64UrlSignature;
    }

    /**
     * Validate the given JWT token and return its payload if valid.
     */
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;

        $signature = $this->base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader.'.'.$base64UrlPayload, $this->secret, true);

        if (! hash_equals($signature, $expectedSignature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($base64UrlPayload), true);
        if ($payload === null) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            Log::warning('JWT token expired', ['user_id' => $payload['sub'] ?? null]);

            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
