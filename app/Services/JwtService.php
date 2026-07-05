<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

final class JwtService
{
    public const PURPOSE_AUTH = 'auth';

    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';

    private string $secret;

    private int $ttl; // in seconds

    private int $emailVerificationTtl;

    public function __construct()
    {
        // Read configuration settings from config/auth.php
        $this->secret = config('auth.jwt.secret') ?: 'base64:fallbacksecretkeyshouldbechanged';
        $this->ttl = (int) config('auth.jwt.ttl', 18000);
        $this->emailVerificationTtl = (int) config('auth.email_verification.ttl', 3600);
    }

    /**
     * Generate a new JWT token for a user.
     */
    public function generateToken(User $user): string
    {
        return $this->generateUserToken($user, self::PURPOSE_AUTH, $this->ttl);
    }

    public function generateEmailVerificationToken(User $user): string
    {
        return $this->generateUserToken($user, self::PURPOSE_EMAIL_VERIFICATION, $this->emailVerificationTtl, [
            'email_hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function generateUserToken(User $user, string $purpose, int $ttl, array $claims = []): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = json_encode(array_merge([
            'sub' => $user->id,
            'email' => $user->email,
            'purpose' => $purpose,
            'iat' => time(),
            'exp' => time() + $ttl,
        ], $claims));

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader.'.'.$base64UrlPayload, $this->secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader.'.'.$base64UrlPayload.'.'.$base64UrlSignature;
    }

    /**
     * Validate the given JWT token and return its payload if valid.
     */
    public function validateToken(string $token, ?string $purpose = null): ?array
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

        if ($purpose !== null && ($payload['purpose'] ?? self::PURPOSE_AUTH) !== $purpose) {
            Log::warning('JWT token purpose mismatch', [
                'user_id' => $payload['sub'] ?? null,
                'expected_purpose' => $purpose,
                'actual_purpose' => $payload['purpose'] ?? null,
            ]);

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
