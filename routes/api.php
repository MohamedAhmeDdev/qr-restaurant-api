<?php

use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\TableController;
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

    Route::get('/roles/options', [RoleController::class, 'options']);

    /*
    |--------------------------------------------------------------------------
        Super Admin APIs
    |--------------------------------------------------------------------------
    */
    // Permission Management Routes (Super Admin Only)
    Route::prefix('/permissions')->middleware('super_admin')->group(function () {
        Route::get('/group', [PermissionController::class, 'getGroups']);
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/', [PermissionController::class, 'store'])
            ->middleware('permission:permission.create');
        Route::get('/{permission}', [PermissionController::class, 'show']);
        Route::put('/{permission}', [PermissionController::class, 'update'])
            ->middleware('permission:permission.update');
        Route::delete('/{permission}', [PermissionController::class, 'destroy'])
            ->middleware('permission:permission.delete');
    });

    // Role Management Routes (Super Admin Only)
    Route::prefix('/roles')->middleware('super_admin')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store'])
            ->middleware('permission:role.create');
        Route::get('/{role}', [RoleController::class, 'show']);
        Route::put('/{role}', [RoleController::class, 'update'])
            ->middleware('permission:role.update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:role.delete');
        Route::post('/{role}/sync-permissions', [RoleController::class, 'syncPermissions'])
            ->middleware('permission:permission.assign');
    });

    // Get all organizations + owners + restaurant counts
    Route::get('/organizations', [OrganizationController::class, 'index']);

    // Get specific organization + list of all its restaurants
    Route::get('/organizations/{id}', [OrganizationController::class, 'show']);


    // Get invitations list
    Route::get('/invitations', [OrganizationController::class, 'getInvitations']);
    Route::post('/invitations/send', [RegistrationController::class, 'sendInvite'])
        ->middleware('permission:invitation.send');
    Route::post('/invitations/resend', [RegistrationController::class, 'resendInvite'])
        ->middleware('permission:invitation.send');




    Route::get('/restaurants', [RestaurantController::class, 'index'])
        ->middleware('permission:restaurant.view');
    Route::post('/restaurants', [RestaurantController::class, 'store'])
        ->middleware('permission:restaurant.create');
    Route::get('/restaurants/{id}', [RestaurantController::class, 'show'])
        ->middleware('permission:restaurant.view');
    Route::post('/restaurants/{id}', [RestaurantController::class, 'update'])
        ->middleware('permission:restaurant.update');
    Route::delete('/restaurants/{id}', [RestaurantController::class, 'destroy'])
        ->middleware('permission:restaurant.delete');
    Route::post('/restaurants/{id}/restore', [RestaurantController::class, 'restore'])
        ->middleware('permission:restaurant.restore');
    Route::delete('/restaurants/{id}/force', [RestaurantController::class, 'forceDelete'])
        ->middleware('permission:restaurant.force_delete');









    /*
--------------------------------------------------------------------------
    | Active Workspace-Scoped Endpoints (Requires X-Restaurant-Slug Header)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['restaurant.access'])->group(function () {

        Route::get('/tables', [TableController::class, 'index'])
            ->middleware('permission:table.view');
        Route::post('/tables', [TableController::class, 'store'])
            ->middleware('permission:table.create');
        Route::get('/tables/{id}', [TableController::class, 'show'])
            ->middleware('permission:table.view');
        Route::put('/tables/{id}', [TableController::class, 'update'])
            ->middleware('permission:table.update');
        Route::delete('/tables/{id}', [TableController::class, 'destroy'])
            ->middleware('permission:table.delete');

        Route::post('/{id}/regenerate-qr', [TableController::class, 'regenerateQr'])
            ->middleware('permission:table.update');


        // Staff Management Endpoints
        Route::get('/staff', [StaffController::class, 'index'])
            ->middleware('permission:staff.view');
        Route::post('/staff', [StaffController::class, 'store'])
            ->middleware('permission:staff.create');
        Route::get('/staff/{id}', [StaffController::class, 'show'])
            ->middleware('permission:staff.view');
        Route::put('/staff/{id}', [StaffController::class, 'update'])
            ->middleware('permission:staff.update');
        Route::delete('/staff/{id}', [StaffController::class, 'destroy'])
            ->middleware('permission:staff.delete');
        Route::post('/staff/{id}/restore', [StaffController::class, 'restore'])
            ->middleware('permission:staff.restore');
        Route::delete('/staff/{id}/force', [StaffController::class, 'forceDelete'])
            ->middleware('permission:staff.delete');
    });
});
