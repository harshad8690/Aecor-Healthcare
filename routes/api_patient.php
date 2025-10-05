<?php

use App\Http\Controllers\Api\Patient\PatientController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->middleware(['auth:api', 'role.patient'])->group(function () {
    Route::get('/', [PatientController::class, 'doctorSlots']);
    Route::get('{user_id}/appointments', [PatientController::class, 'list']);
    Route::post('{user_id}/appointments', [PatientController::class, 'appointmentBook']);
    Route::post('{user_id}/appointments/{appointment_id}', [PatientController::class, 'cancelAppointment']);
});