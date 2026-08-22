<?php

use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Auth Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-2fa', [AuthController::class, 'verifyTwoFactor']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/verify-invite', [RegistrationController::class, 'verifyToken']);
Route::post('/register', [RegistrationController::class, 'register']);


/*
|--------------------------------------------------------------------------
| Protected Auth Routes (Requires Valid Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    |//Settings APIs
    |--------------------------------------------------------------------------
    */
    Route::get('/user/2fa-status', [AuthController::class, 'getTwoFactorStatus']);
    Route::post('/user/toggle-2fa', [AuthController::class, 'toggleTwoFactor']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);


    Route::get('/verify', [AuthController::class, 'verify']);
    Route::post('/logout', [AuthController::class, 'logout']);





    /*
    |--------------------------------------------------------------------------
        Super Admin APIs
    |--------------------------------------------------------------------------
    */
    // Permission Management Routes (Super Admin Only)
    Route::prefix('admin/permissions')->middleware('super_admin')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::get('/{permission}', [PermissionController::class, 'show']);
        Route::put('/{permission}', [PermissionController::class, 'update']);
        Route::delete('/{permission}', [PermissionController::class, 'destroy']);
    });

    // Role Management Routes (Super Admin Only)
    Route::prefix('admin/roles')->middleware('super_admin')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/{role}', [RoleController::class, 'show']);
        Route::put('/{role}', [RoleController::class, 'update']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
        Route::post('/{role}/sync-permissions', [RoleController::class, 'syncPermissions'])
            ->middleware('permission:permissions.assign');;
    });

    // Get all organizations + owners + restaurant counts
    Route::get('/organizations', [OrganizationController::class, 'index']);

    // Get specific organization + list of all its restaurants
    Route::get('/organizations/{id}', [OrganizationController::class, 'show']);


    // Get invitations list
    Route::get('/invitations', [OrganizationController::class, 'getInvitations']);
    Route::post('/invitations/send', [RegistrationController::class, 'sendInvite']);
    Route::post('/invitations/resend', [RegistrationController::class, 'resendInvite']);
});
