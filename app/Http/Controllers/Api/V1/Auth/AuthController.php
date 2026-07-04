<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\GoogleAuthRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $user->sendEmailVerificationNotification();

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success(
            [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => UserResource::make($user->load('profile')),
            ],
            'Registration successful. Please verify your email address.',
            Response::HTTP_CREATED
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return ApiResponse::error('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->hasVerifiedEmail()) {
            return ApiResponse::error('Email is not verified. Please verify your email before logging in.', Response::HTTP_FORBIDDEN);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user->load('profile')),
        ], 'Login successful.');
    }

    public function google(GoogleAuthRequest $request): JsonResponse
    {
        $payload = $this->validateGoogleToken($request->id_token);

        if (! $payload || $payload['aud'] !== config('services.google.client_id')) {
            return ApiResponse::error('Unable to verify Google login.', Response::HTTP_UNAUTHORIZED);
        }

        if (empty($payload['email'])) {
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
                'password' => Hash::make(Str::random(40)),
                'google_id' => $payload['sub'],
                'email_verified_at' => now(),
            ]);
        } elseif ($user->google_id === null) {
            $user->update(['google_id' => $payload['sub'], 'email_verified_at' => $user->email_verified_at ?? now()]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user->load('profile')),
        ], 'Google login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            UserResource::make($request->user()->load('profile')),
            'Authenticated user retrieved.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(message: 'Successfully logged out.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::error('Email is already verified.', Response::HTTP_BAD_REQUEST);
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::success(message: 'Verification email resent.');
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return ApiResponse::error('Invalid verification link.', Response::HTTP_FORBIDDEN);
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(message: 'Email already verified.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return ApiResponse::success(message: 'Email verified successfully.');
    }

    private function validateGoogleToken(string $idToken): ?array
    {
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
