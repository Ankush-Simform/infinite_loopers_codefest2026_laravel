<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Chat\ChatController;
use App\Http\Controllers\Api\V1\Devices\DeviceController;
use App\Http\Controllers\Api\V1\Home\HomeController;
use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\Profile\ReportProfileController;
use App\Http\Controllers\Api\V1\Reports\ReportCategoryController;
use App\Http\Controllers\Api\V1\Reports\ReportController;
use App\Http\Controllers\Api\V1\Reports\ReportUploadController;
use App\Http\Controllers\Api\V1\Reports\WebhookController;
use App\Http\Controllers\Api\V1\System\StatusController;
use App\Http\Controllers\Api\V1\Timeline\TimelineController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

Route::get('status', StatusController::class)->name('api.status');

Route::post('webhooks/report-processing-complete', [WebhookController::class, 'handle'])
    ->name('api.webhooks.report_processing_complete');

Route::get('login', function () {
    return ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
})->name('login');

Route::get('v1/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('status', StatusController::class)->name('status');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('google', [AuthController::class, 'google'])->name('google');
        Route::post('email/verify', [AuthController::class, 'verifyEmailToken'])->name('verification.verify_token');
        Route::post('email/resend', [AuthController::class, 'resendVerifica
        tion'])->name('verification.resend');

        Route::middleware('auth.jwt')->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::put('me', [AuthController::class, 'updateMe'])->name('me.update');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::middleware('auth.jwt')->group(function (): void {
        // Home Dashboard API
        Route::get('home', HomeController::class)->name('home');

        // User Profile API
        Route::get('user', [UserController::class, 'show'])->name('user.show');
        Route::patch('user', [UserController::class, 'update'])->name('user.update');
        Route::post('user', [UserController::class, 'update'])->name('user.post_update');
        Route::put('user/password', [UserController::class, 'updatePassword'])->name('user.password.update');
        Route::put('profile/password', [UserController::class, 'updatePassword'])->name('profile.password.update');

        // Report Profiles API
        Route::get('report-profiles/enums', [ReportProfileController::class, 'getEnums'])->name('report-profiles.enums');
        Route::apiResource('report-profiles', ReportProfileController::class);

        // Reports API
        Route::get('categories', ReportCategoryController::class)->name('categories.index');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('reports', [ReportController::class, 'store'])->name('reports.store');
        Route::get('reports/{id}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{id}/file', [ReportController::class, 'showFile'])->name('reports.file');
        Route::put('reports/{id}', [ReportController::class, 'update'])->name('reports.update');
        Route::delete('reports/{id}', [ReportController::class, 'destroy'])->name('reports.destroy');

        // Staged Uploads API (Review flow)
        Route::post('reports/upload', [ReportUploadController::class, 'upload'])->name('reports.upload');
        Route::get('reports/upload/{upload_id}/status', [ReportUploadController::class, 'status'])->name('reports.upload.status');
        Route::get('reports/upload/{upload_id}/review', [ReportUploadController::class, 'review'])->name('reports.upload.review');
        Route::get('reports/upload/{upload_id}/file', [ReportUploadController::class, 'showFile'])->name('reports.upload.file');
        Route::post('reports/upload/{upload_id}/save', [ReportUploadController::class, 'save'])->name('reports.upload.save');

        // Timelines API
        Route::get('timelines', [TimelineController::class, 'index'])->name('timelines.index');
        Route::post('timelines', [TimelineController::class, 'store'])->name('timelines.store');
        Route::get('timelines/{id}', [TimelineController::class, 'show'])->name('timelines.show');
        Route::put('timelines/{id}', [TimelineController::class, 'update'])->name('timelines.update');
        Route::delete('timelines/{id}', [TimelineController::class, 'destroy'])->name('timelines.destroy');

        // Chat API
        Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
        Route::post('chats', [ChatController::class, 'store'])->name('chats.store');
        Route::put('chats/{id}', [ChatController::class, 'update'])->name('chats.update');
        Route::get('chats/{id}/messages', [ChatController::class, 'messages'])->name('chats.messages');
        Route::post('chats/{id}/messages', [ChatController::class, 'sendMessage'])->name('chats.send_message');
        Route::delete('chats/{id}', [ChatController::class, 'destroy'])->name('chats.destroy');
        Route::get('chats/attachments/{id}', [ChatController::class, 'showAttachment'])->name('chats.attachments.show');

        // Notifications API
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read_all');

        // Device Management API
        Route::post('devices', [DeviceController::class, 'register'])->name('devices.register');
        Route::delete('devices', [DeviceController::class, 'deregister'])->name('devices.deregister');
    });
});
