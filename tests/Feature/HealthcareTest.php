<?php

use App\Models\User;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\HealthcareProfessional;
use App\Models\Appointment;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function () {
    // Create roles
    Role::updateOrCreate(['id' => 1], ['name' => 'User']);
    Role::updateOrCreate(['id' => 2], ['name' => 'Healthcare Professional']);

    // Create a specialty
    $this->specialty = Specialty::create(['name' => 'Cardiology']);

    // Create a patient user
    $this->patient = User::create([
        'name' => 'Patient One',
        'email' => 'patient@example.com',
        'password' => Hash::make('password123'),
        'role_id' => 1,
    ]);

    // Create a healthcare professional user
    $this->doctorUser = User::create([
        'name' => 'Dr. Smith',
        'email' => 'doctor@example.com',
        'password' => Hash::make('password123'),
        'role_id' => 2,
    ]);

    // Create healthcare professional record
    $this->doctor = HealthcareProfessional::create([
        'user_id' => $this->doctorUser->id,
        'specialty_id' => $this->specialty->id,
    ]);

    // Authenticate healthcare professional
    Passport::actingAs($this->doctorUser);
});

it('fetches the list of appointments for healthcare professional', function () {
    $date = Carbon::today()->format('Y-m-d');

    // first appointment
    Appointment::create([
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '09:00',
        'appointment_end_time' => '09:30',
        'status' => config('constants.status_booked'),
    ]);

    // Create second appointment
    Appointment::create([
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '10:00',
        'appointment_end_time' => '10:30',
        'status' => config('constants.status_booked'),
    ]);

    $response = $this->getJson("api/healthcare/{$this->doctorUser->id}/appointments?page=1&page_size=10&status=1");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'current_page',
            'last_page',
            'per_page',
            'total',
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'healthcare_professional_id',
                    'date',
                    'appointment_start_time',
                    'appointment_end_time',
                    'status',
                    'user_name',
                    'professional_name',
                ]
            ]
        ]);

    $appointments = $response->json('data') ?? [];
    expect(count($appointments))->toBe(2);
});

it('returns empty list if healthcare professional has no appointments', function () {
    $response = $this->getJson("api/healthcare/{$this->doctorUser->id}/appointments?page=1&page_size=10&status=1");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => __('messages.appointment_list'),
        ]);

    $appointments = $response->json('data') ?? [];
    expect(count($appointments))->toBe(0);
});

it('fails when request is invalid', function () {
    $appointmentId = 20000;
    $response = $this->postJson("api/healthcare/{$this->doctorUser->id}/appointments/9999", [
        'status' => 'invalid_status', // not allowed value
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('returns error if appointment not found', function () {
    $response = $this->postJson("api/healthcare/{$this->doctorUser->id}/appointments/9999", [
        'status' => config('constants.status_completed'),
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'message' => __('messages.data_not_found'),
        ]);
});

it('marks an appointment as completed if valid', function () {
    $date = Carbon::today()->format('Y-m-d');

    // Create an appointment
    $appointment = Appointment::create([
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '09:00',
        'appointment_end_time' => '09:30',
        'status' => config('constants.status_booked'),
    ]);

    $response = $this->postJson("api/healthcare/{$this->doctorUser->id}/appointments/{$appointment->id}", [
        'status' => config('constants.status_completed'),
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => __('messages.appointment_completed'),
        ]);

    $appointment->refresh();
    expect($appointment->status)->toBe(config('constants.status_completed'));
});

it('marks an appointment as cancelled if valid', function () {
    $date = Carbon::today()->format('Y-m-d');

    // Create an appointment
    $appointment = Appointment::create([
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '10:00',
        'appointment_end_time' => '10:30',
        'status' => config('constants.status_booked'),
    ]);

    $response = $this->postJson("api/healthcare/{$this->doctorUser->id}/appointments/{$appointment->id}", [
        'status' => config('constants.status_cancelled'),
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => __('messages.appointment_cancelled'),
        ]);

    $appointment->refresh();
    expect($appointment->status)->toBe(config('constants.status_cancelled'));
});