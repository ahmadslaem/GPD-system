<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;


Route::get('/', function () {
    return redirect('/frontend/dashboard/index.html');
});

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Login
// Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

// Route::middleware('auth:sanctum')->group(function () {

//     // Get logged user profile
//     Route::get('/profile', [AuthController::class, 'profile']);

//     // Logout and delete token
//     Route::post('/logout', [AuthController::class, 'logout']);

// });