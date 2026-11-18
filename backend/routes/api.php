<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInfoController;
use Illuminate\Support\Facades\Route;

/*
 * Permissions are checked with "can:<permission>" (see App\Support\Permissions
 * and the Gate::before in AppServiceProvider).
 */

// ---------- Public ----------
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('branding', [SettingsController::class, 'branding']);
Route::get('branding/logo', [SettingsController::class, 'logo']);

// ---------- Authenticated ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Profile / password / signature / two-factor
    Route::put('profile', [ProfileController::class, 'update']);
    Route::delete('profile', [ProfileController::class, 'destroy']);
    Route::put('profile/password', [ProfileController::class, 'password']);
    Route::post('profile/signature', [ProfileController::class, 'signature']);
    Route::delete('profile/signature', [ProfileController::class, 'deleteSignature']);
    Route::post('profile/photo', [ProfileController::class, 'photo']);
    Route::delete('profile/photo', [ProfileController::class, 'deletePhoto']);
    Route::post('profile/two-factor/setup', [ProfileController::class, 'twoFactorSetup']);
    Route::post('profile/two-factor/confirm', [ProfileController::class, 'twoFactorConfirm']);
    Route::post('profile/two-factor/disable', [ProfileController::class, 'twoFactorDisable']);
    Route::get('users/{user}/signature', [UserController::class, 'signature']);
    Route::get('users/{user}/photo', [UserController::class, 'photo']);

    Route::get('lookups', LookupController::class);
    Route::get('dashboard', [DocumentController::class, 'dashboard']);

    // Alerts (bell)
    Route::get('alerts/mine', [AlertController::class, 'mine']);
    Route::post('alerts/read-all', [AlertController::class, 'readAll']);
    Route::post('alerts/{alert}/read', [AlertController::class, 'read']);

    // ---------- Documents ----------
    Route::get('documents', [DocumentController::class, 'index'])->middleware('can:document_access');
    Route::post('documents', [DocumentController::class, 'store'])->middleware('can:document_create');
    Route::post('documents/bulk-delete', [DocumentController::class, 'bulkDelete'])->middleware('can:document_delete_all');
    Route::get('documents/{document}', [DocumentController::class, 'show']);
    // POST + _method=PUT so multipart uploads work
    Route::match(['put', 'post'], 'documents/{document}/update', [DocumentController::class, 'update'])->middleware('can:document_edit');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->middleware('can:document_delete');
    Route::post('documents/{document}/close', [DocumentController::class, 'close'])->middleware('can:document_complete_edit');

    // Flow actions by the person whose turn it is
    Route::post('documents/{document}/submit', [CommentController::class, 'submit']);
    Route::post('documents/{document}/return', [CommentController::class, 'returnTo']);
    Route::post('documents/{document}/attachments', [AttachmentController::class, 'store']);
    Route::get('attachments/{attachment}', [AttachmentController::class, 'download']);
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);

    // ---------- Comments ----------
    Route::get('comments', [CommentController::class, 'index'])->middleware('can:comment_access');
    Route::post('comments', [CommentController::class, 'store'])->middleware('can:comment_grand_edit');
    Route::post('comments/bulk-delete', [CommentController::class, 'bulkDelete']);
    Route::get('comments/{comment}', [CommentController::class, 'show'])->middleware('can:comment_show');
    Route::put('comments/{comment}', [CommentController::class, 'update']);
    Route::post('comments/{comment}/reset', [CommentController::class, 'reset']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

    // ---------- User management ----------
    $crud = function (string $uri, string $controller, string $perm) {
        Route::get($uri, [$controller, 'index'])->middleware("can:{$perm}_access");
        Route::post($uri, [$controller, 'store'])->middleware("can:{$perm}_create");
        Route::post("{$uri}/bulk-delete", [$controller, 'bulkDelete'])->middleware("can:{$perm}_delete");
        $param = '{'.str_replace('-', '_', \Illuminate\Support\Str::singular($uri)).'}';
        Route::get("{$uri}/{$param}", [$controller, 'show'])->middleware("can:{$perm}_show");
        Route::put("{$uri}/{$param}", [$controller, 'update'])->middleware("can:{$perm}_edit");
        Route::delete("{$uri}/{$param}", [$controller, 'destroy'])->middleware("can:{$perm}_delete");
    };
    $crud('permissions', PermissionController::class, 'permission');
    $crud('roles', RoleController::class, 'role');
    $crud('users', UserController::class, 'user');
    $crud('user-infos', UserInfoController::class, 'user_info');

    Route::get('audit-logs', [LogController::class, 'auditIndex'])->middleware('can:audit_log_access');
    Route::get('audit-logs/{auditLog}', [LogController::class, 'auditShow'])->middleware('can:audit_log_show');
    Route::get('user-logs', [LogController::class, 'userLogs'])->middleware('can:user_log_access');
    Route::get('user-logs/{userLog}', [LogController::class, 'userLogShow'])->middleware('can:user_log_access');
    Route::post('user-logs/purge', [LogController::class, 'purgeUserLogs'])->middleware('can:user_log_delete');

    Route::get('user-alerts', [AlertController::class, 'index'])->middleware('can:user_alert_access');
    Route::post('user-alerts', [AlertController::class, 'store'])->middleware('can:user_alert_create');
    Route::post('user-alerts/bulk-delete', [AlertController::class, 'bulkDelete'])->middleware('can:user_alert_delete');
    Route::delete('user-alerts/{alert}', [AlertController::class, 'destroy'])->middleware('can:user_alert_delete');

    // ---------- Settings (each resource checks <resource>_access/create/edit/show/delete) ----------
    Route::middleware('can:setting_access')->group(function () {
        Route::get('settings/{resource}', [SettingsController::class, 'index']);
        Route::post('settings/{resource}', [SettingsController::class, 'store']);
        Route::post('settings/{resource}/bulk-delete', [SettingsController::class, 'bulkDelete']);
        Route::get('settings/{resource}/{id}', [SettingsController::class, 'show'])->whereNumber('id');
        Route::match(['put', 'post'], 'settings/{resource}/{id}', [SettingsController::class, 'update'])->whereNumber('id');
        Route::delete('settings/{resource}/{id}', [SettingsController::class, 'destroy'])->whereNumber('id');
    });
});
