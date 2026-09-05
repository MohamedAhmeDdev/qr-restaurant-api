<?php

use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GuestOrderController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\ModifierGroupController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PublicMenuController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\TableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Guest Routes (No Auth)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-2fa', [AuthController::class, 'verifyTwoFactor']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/verify-invite', [RegistrationController::class, 'verifyToken']);
Route::post('/register', [RegistrationController::class, 'register']);

Route::middleware('guest.table')->group(function () {
    Route::get('/{restaurantSlug}/{tableSlug}/menu', [PublicMenuController::class, 'index']);
    Route::get('/{restaurantSlug}/{tableSlug}/menu/items/{itemId}', [PublicMenuController::class, 'show']);
    Route::get('/orders', [GuestOrderController::class, 'index']);
    Route::get('/orders/{id}', [GuestOrderController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Protected Auth Routes (Requires Valid Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/verify', [AuthController::class, 'verify']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Settings APIs
    Route::get('/user/2fa-status', [AuthController::class, 'getTwoFactorStatus']);
    Route::post('/user/toggle-2fa', [AuthController::class, 'toggleTwoFactor']);
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);
    Route::get('/roles/options', [RoleController::class, 'options']);
    /*
    |--------------------------------------------------------------------------
    | Super Admin APIs (Global Management)
    |--------------------------------------------------------------------------
    */
    Route::middleware('super_admin')->group(function () {
        // Permission Management
        Route::prefix('/permissions')->group(function () {
            Route::get('/group', [PermissionController::class, 'getGroups']);
            Route::get('/', [PermissionController::class, 'index']);
            Route::post('/', [PermissionController::class, 'store']);
                // ->middleware('permission:permission.create');
            Route::get('/{permission}', [PermissionController::class, 'show']);
            Route::put('/{permission}', [PermissionController::class, 'update']);
                // ->middleware('permission:permission.update');
            Route::delete('/{permission}', [PermissionController::class, 'destroy']);
                // ->middleware('permission:permission.delete');
        });

        // Role Management
        Route::prefix('/roles')->group(function () {
            Route::get('/', [RoleController::class, 'index']);
            Route::post('/', [RoleController::class, 'store']);
                // ->middleware('permission:role.create');
            Route::get('/{role}', [RoleController::class, 'show']);
            Route::put('/{role}', [RoleController::class, 'update']);
                // ->middleware('permission:role.update');
            Route::delete('/{role}', [RoleController::class, 'destroy']);
                // ->middleware('permission:role.delete');
            Route::post('/{role}/sync-permissions', [RoleController::class, 'syncPermissions']);
                // ->middleware('permission:permission.assign');
        });

        // Organizations & Invitations
        Route::get('/organizations', [OrganizationController::class, 'index']);
        Route::get('/organizations/{id}', [OrganizationController::class, 'show']);

        //invitation
        Route::get('/invitations', [OrganizationController::class, 'getInvitations']);
        Route::post('/invitations/send', [RegistrationController::class, 'sendInvite']);
            // ->middleware('permission:invitation.send');
        Route::post('/invitations/resend', [RegistrationController::class, 'resendInvite']);
            // ->middleware('permission:invitation.send');

        // Master Restaurant Activation Toggle
        Route::patch('/restaurants/{id}/toggle-active', [RestaurantController::class, 'toggleActive']);
    });

    /*
    |--------------------------------------------------------------------------
    | Restaurant APIs
    |--------------------------------------------------------------------------
    */
    Route::get('/restaurants', [RestaurantController::class, 'index']);
        // ->middleware('permission:restaurant.view');
    Route::post('/onboarding/complete', [RegistrationController::class, 'completeOnboarding']);
        // ->middleware('permission:restaurant.update');
    Route::post('/restaurants', [RestaurantController::class, 'store']);
        // ->middleware('permission:restaurant.create');
    Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);
        // ->middleware('permission:restaurant.view');
    Route::put('/restaurants/{id}', [RestaurantController::class, 'update']);
        // ->middleware('permission:restaurant.update');
    Route::patch('/restaurants/{id}/toggle-status', [RestaurantController::class, 'toggleStatus']);
        // ->middleware('permission:restaurant.update');
    Route::delete('/restaurants/{id}', [RestaurantController::class, 'destroy']);
        // ->middleware('permission:restaurant.delete');
    Route::post('/restaurants/{id}/restore', [RestaurantController::class, 'restore']);
        // ->middleware('permission:restaurant.restore');
    Route::delete('/restaurants/{id}/force', [RestaurantController::class, 'forceDelete']);
        // ->middleware('permission:restaurant.force_delete');

    /*
    |--------------------------------------------------------------------------
    | Active Workspace-Scoped Endpoints (Requires X-Restaurant-Slug Header)
    |--------------------------------------------------------------------------
    */
    
    Route::middleware(['restaurant.access'])->group(function () {
        // Staff Management
        Route::get('/staff', [StaffController::class, 'index']);
            // ->middleware('permission:staff.view');
        Route::post('/staff', [StaffController::class, 'store']);
            // ->middleware('permission:staff.create');
        Route::get('/staff/{id}', [StaffController::class, 'show']);
            // ->middleware('permission:staff.view');
        Route::put('/staff/{id}', [StaffController::class, 'update']);
            // ->middleware('permission:staff.update');
        Route::delete('/staff/{id}', [StaffController::class, 'destroy']);
            // ->middleware('permission:staff.delete');
        Route::post('/staff/{id}/restore', [StaffController::class, 'restore']);
            // ->middleware('permission:staff.restore');
        Route::delete('/staff/{id}/force', [StaffController::class, 'forceDelete']);
            // ->middleware('permission:staff.delete');

        // Tables Management
        Route::get('/tables', [TableController::class, 'index']);
            // ->middleware('permission:table.view');
        Route::post('/tables', [TableController::class, 'store']);
            // ->middleware('permission:table.create');
        Route::get('/tables/{id}', [TableController::class, 'show']);
            // ->middleware('permission:table.view');
        Route::put('/tables/{id}', [TableController::class, 'update']);
            // ->middleware('permission:table.update');
        Route::delete('/tables/{id}', [TableController::class, 'destroy']);
            // ->middleware('permission:table.delete');
        Route::post('/tables/{id}/regenerate-qr', [TableController::class, 'regenerateQr']);
            // ->middleware('permission:table.update');

        // Categories Management
        Route::get('/option/categories', [CategoryController::class, 'option']);
        Route::get('/categories', [CategoryController::class, 'index']);
            // ->middleware('permission:category.view');
        Route::post('/categories', [CategoryController::class, 'store']);
            // ->middleware('permission:category.create');
        Route::post('/categories/reorder', [CategoryController::class, 'reorder']);
            // ->middleware('permission:category.update');
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
            // ->middleware('permission:category.view');
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
            // ->middleware('permission:category.update');
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
            // ->middleware('permission:category.delete');

        // Modifier Groups Management
        Route::get('/option/modifier-groups', [ModifierGroupController::class, 'option']);
        Route::get('/modifier-groups', [ModifierGroupController::class, 'index']);
            // ->middleware('permission:modifier.view');
        Route::post('/modifier-groups', [ModifierGroupController::class, 'store']);
            // ->middleware('permission:modifier.create');
        Route::get('/modifier-groups/{id}', [ModifierGroupController::class, 'show']);
            // ->middleware('permission:modifier.view');
        Route::put('/modifier-groups/{id}', [ModifierGroupController::class, 'update']);
            // ->middleware('permission:modifier.update');
        Route::delete('/modifier-groups/{id}', [ModifierGroupController::class, 'destroy']);
            // ->middleware('permission:modifier.delete');

        // Menu Items Management
        Route::get('/menu-items', [MenuItemController::class, 'index']);
            // ->middleware('permission:menu.view');
        Route::post('/menu-items', [MenuItemController::class, 'store']);
            // ->middleware('permission:menu.create');
        Route::get('/menu-items/{id}', [MenuItemController::class, 'show']);
            // ->middleware('permission:menu.view');
        Route::put('/menu-items/{id}', [MenuItemController::class, 'update']);
            // ->middleware('permission:menu.update');
            Route::patch('/menu-items/{id}/toggle-active', [MenuItemController::class, 'toggleActive']);
              // ->middleware('permission:menu.update');
Route::patch('/menu-items/{id}/toggle-availability', [MenuItemController::class, 'toggleAvailability']);
  // ->middleware('permission:menu.update');
        Route::delete('/menu-items/{id}', [MenuItemController::class, 'destroy']);
            // ->middleware('permission:menu.delete');
    });

    // Orders Management
    Route::get('/orders', [OrderController::class, 'index']);
        // ->middleware('permission:order.view');
    Route::get('/orders/{id}', [OrderController::class, 'show']);
        // ->middleware('permission:order.view');
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        // ->middleware('permission:order.update');
});