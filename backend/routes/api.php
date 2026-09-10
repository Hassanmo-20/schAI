<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TaskCompletionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskStatisticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SchAI Academic Task Hub
|--------------------------------------------------------------------------
|
| Batch scoping is enforced server-side from the authenticated user; no
| endpoint accepts a batch_id or student_id from the client.
|
*/

Route::get('/health', HealthController::class)->name('api.health');

// Public: the registration form needs the valid batch/department/role choices.
Route::get('/batches', [BatchController::class, 'index'])
    ->middleware('throttle:30,1')
    ->name('api.batches.index');

Route::get('/registration-options', [BatchController::class, 'options'])
    ->middleware('throttle:30,1')
    ->name('api.registration.options');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::post('/tasks/{task}/complete', [TaskCompletionController::class, 'store']);
    Route::delete('/tasks/{task}/complete', [TaskCompletionController::class, 'destroy']);

    Route::get('/tasks/{task}/statistics', [TaskStatisticsController::class, 'show']);

    // Notifications: always the authenticated user's own rows.
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

    // AI assistant: separate, tighter limiter because each call costs money.
    Route::post('/assistant/chat', [AssistantController::class, 'chat'])
        ->middleware('throttle:assistant')
        ->name('api.assistant.chat');

    Route::get(
        '/tasks/{task}/attachments/{attachment}',
        [TaskController::class, 'downloadAttachment']
    )->name('api.tasks.attachments.download');

    Route::delete(
        '/tasks/{task}/attachments/{attachment}',
        [TaskController::class, 'destroyAttachment']
    )->name('api.tasks.attachments.destroy');
});
