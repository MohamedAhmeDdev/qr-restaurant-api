<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RegistrationController;
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
    Route::get('/user/2fa-status', [AuthController::class, 'getTwoFactorStatus']);
    Route::post('/user/toggle-2fa', [AuthController::class, 'toggleTwoFactor']);

   

     Route::get('/verify', [AuthController::class, 'verify']); 
     Route::post('/logout', [AuthController::class, 'logout']);




   // Super Admin Routes
     Route::post('/admin/invitations/send', [RegistrationController::class, 'sendInvite']);
});