<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('auth/register', [AuthController::class, 'register'])->name('register');
Route::post('auth/login', [AuthController::class, 'login'])->name('login');
Route::get('category', [AuthController::class, 'category']);

Route::middleware('auth:api')->group(function () {
    Route::post('users/logout', [AuthController::class, 'logout'])->name('logout');
});
require base_path('routes/api_patient.php');
require base_path('routes/api_healthcare.php');