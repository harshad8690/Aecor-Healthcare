<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Healthcare\HealthcareController;

Route::middleware(['auth:api', 'role.healthcare'])
    ->prefix('healthcare')
    ->group(function () {

        Route::get('{user_id}/appointments', [HealthcareController::class, 'listAppointments']);
        Route::post('{user_id}/appointments/{id}', [HealthcareController::class, 'markCompleted']);
});
