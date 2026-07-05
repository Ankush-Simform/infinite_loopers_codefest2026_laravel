<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\GoogleAuthRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\JwtService;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends Controller
{
    public function __construct(
        protected JwtService $jwtService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
            ]);

            $user->sendEmailVerificationNotification();

            $token = $this->jwtService->generateToken($user);

            Log::info('User registration successful', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return ApiResponse::success(
                [
                    'token' => $token,
                    'user' => UserResource::make($user->load('profile')),
                ],
                'Registration successful. Please verify your email address.',
                Response::HTTP_CREATED
            );
        } catch (\Throwable $e) {
            Log::error('User registration failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Registration failed. Please try again.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                Log::warning('Login failed: Invalid credentials', ['email' => $request->email]);

                return ApiResponse::error('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
            }

            if (! $user->hasVerifiedEmail()) {
                Log::warning('Login failed: Email not verified', ['user_id' => $user->id, 'email' => $user->email]);

                return ApiResponse::error('Email is not verified. Please verify your email before logging in.', Response::HTTP_FORBIDDEN);
            }

            $token = $this->jwtService->generateToken($user);

            Log::info('User login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return ApiResponse::success([
                'token' => $token,
                'user' => UserResource::make($user->load('profile')),
            ], 'Login successful.');
        } catch (\Throwable $e) {
            Log::error('User login failed with exception', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Login failed. Please try again.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function google(GoogleAuthRequest $request): JsonResponse
    {
        try {
            $payload = $this->validateGoogleToken($request->id_token);

            $clientId = config('services.google.client_id');
            if (! $payload || ($clientId && $payload['aud'] !== $clientId)) {
                Log::warning('Google login failed: ID token validation failed', [
                    'payload_aud' => $payload['aud'] ?? null,
                    'config_client_id' => $clientId,
                ]);

                return ApiResponse::error('Unable to verify Google login.', Response::HTTP_UNAUTHORIZED);
            }

            if (empty($payload['email'])) {
                Log::warning('Google login failed: Payload missing email address');

                return ApiResponse::error('Google account does not contain an email address.', Response::HTTP_UNAUTHORIZED);
            }

            $user = User::where('google_id', $payload['sub'])
                ->orWhere('email', $payload['email'])
                ->first();

            if ($user === null) {
                $user = User::create([
                    'name' => $payload['name'] ?? $payload['email'],
                    'email' => $payload['email'],
                    'phone' => null,
                    'password' => null,
                    'google_id' => $payload['sub'],
                    'email_verified_at' => now(),
                ]);
                Log::info('New user registered via Google', ['user_id' => $user->id, 'email' => $user->email]);
            } elseif ($user->google_id === null) {
                $user->update(['google_id' => $payload['sub'], 'email_verified_at' => $user->email_verified_at ?? now()]);
                Log::info('Existing user linked to Google account', ['user_id' => $user->id, 'email' => $user->email]);
            }

            $token = $this->jwtService->generateToken($user);

            Log::info('Google authentication successful', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return ApiResponse::success([
                'token' => $token,
                'user' => UserResource::make($user->load('profile')),
            ], 'Google login successful.');
        } catch (\Throwable $e) {
            Log::error('Google authentication failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Google authentication failed.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            Log::info('Authenticated user details retrieved', ['user_id' => $user->id]);

            return ApiResponse::success(
                UserResource::make($user->load('profile')),
                'Authenticated user retrieved.'
            );
        } catch (\Throwable $e) {
            Log::error('Me endpoint failed', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve user details.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            // Stateless JWT logout - handled on client side by clearing token

            Log::info('User logged out successfully', ['user_id' => $user->id]);

            return ApiResponse::success(message: 'Successfully logged out.');
        } catch (\Throwable $e) {
            Log::error('Logout failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Logout failed.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resendVerification(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->hasVerifiedEmail()) {
                Log::warning('Resend verification failed: Email already verified', ['user_id' => $user->id]);

                return ApiResponse::error('Email is already verified.', Response::HTTP_BAD_REQUEST);
            }

            $user->sendEmailVerificationNotification();

            Log::info('Verification email resent successfully', ['user_id' => $user->id]);

            return ApiResponse::success(message: 'Verification email resent.');
        } catch (\Throwable $e) {
            Log::error('Resending verification email failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to resend verification email.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
                Log::warning('Email verification failed: Invalid hash', ['user_id' => $id]);

                return ApiResponse::error('Invalid verification link.', Response::HTTP_FORBIDDEN);
            }

            if ($user->hasVerifiedEmail()) {
                Log::info('Email verification skipped: Already verified', ['user_id' => $id]);

                return ApiResponse::success(message: 'Email already verified.');
            }

            $user->markEmailAsVerified();
            event(new Verified($user));

            Log::info('Email verified successfully', ['user_id' => $id]);

            return ApiResponse::success(message: 'Email verified successfully.');
        } catch (\Throwable $e) {
            Log::error('Email verification failed with exception', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Email verification failed.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateGoogleToken(string $idToken): ?array
    {
        if (app()->environment('local', 'testing') && str_starts_with($idToken, 'mock_token_')) {
            $userKey = substr($idToken, 11) ?: 'user';

            return [
                'aud' => config('services.google.client_id') ?: 'mock-client-id',
                'sub' => 'mock_google_id_'.$userKey,
                'email' => $userKey.'@example.com',
                'name' => 'Mock Google User '.ucfirst($userKey),
            ];
        }

        try {
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (! $response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Throwable $exception) {
            Log::warning('Google token validation failed.', ['message' => $exception->getMessage()]);

            return null;
        }
    }
}
