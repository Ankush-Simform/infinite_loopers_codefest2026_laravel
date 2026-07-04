<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FcmService
{
    /**
     * Send a push notification to a specific FCM token.
     */
    public function sendNotification(string $token, string $title, ?string $body = null, array $data = []): bool
    {
        $credentialsPath = config('services.fcm.credentials_path') ?? env('FIREBASE_CREDENTIALS');
        $projectId = config('services.fcm.project_id') ?? env('FIREBASE_PROJECT_ID');

        if (!$credentialsPath || !file_exists($credentialsPath) || !$projectId) {
            Log::info('Mock FCM Send: Push notification triggered (Firebase credentials not configured)', [
                'token' => $token,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
            return true;
        }

        try {
            $accessToken = $this->getAccessToken($credentialsPath);
            if (!$accessToken) {
                Log::error('FCM Send Failed: Unable to fetch OAuth2 access token.');
                return false;
            }

            // FCM HTTP v1 payload format
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                ]
            ];

            if (!empty($data)) {
                // FCM custom data fields must be string-string key-values
                $payload['message']['data'] = array_map('strval', $data);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->successful()) {
                Log::info('FCM Push Notification sent successfully', ['token' => $token]);
                return true;
            }

            Log::error('FCM Push Notification failed', [
                'token' => $token,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            // If the response indicates the token is unregistered or invalid, we return false
            // so the caller can deactivate/delete the token record.
            // Common FCM errors for invalid/expired tokens are 'UNREGISTERED' or 'INVALID_ARGUMENT'.
            $errorCode = $response->json('error.status');
            if ($errorCode === 'UNREGISTERED' || $response->status() === 404 || $response->status() === 410) {
                return false; // Token is dead
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM Service Exception', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get OAuth2 Access Token using Service Account JWT.
     */
    private function getAccessToken(string $credentialsPath): ?string
    {
        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            if (!is_array($credentials)) {
                return null;
            }

            $clientEmail = $credentials['client_email'] ?? null;
            $privateKey = $credentials['private_key'] ?? null;

            if (!$clientEmail || !$privateKey) {
                return null;
            }

            $now = time();
            $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $claim = json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ]);

            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));

            $signatureInput = $base64UrlHeader . '.' . $base64UrlClaim;
            $signature = '';

            if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            $jwt = $signatureInput . '.' . $base64UrlSignature;

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('Google OAuth token request failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Exception generating Google Access Token', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
