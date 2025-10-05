<?php

use App\Models\User;
use App\Models\Role;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Models\HealthcareProfessional;
use App\Models\Appointment;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Illuminate\Support\Carbon;

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

    // Create a healthcare professional user by diffrant role ID
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

    // Authenticate patient
    Passport::actingAs($this->patient);
});


it('lists of doctors with available slots', function () {
    $date = Carbon::today()->format('Y-m-d');

    Appointment::create([
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '10:00',
        'appointment_end_time' => '10:30',
        'status' => config('constants.status_booked'),
    ]);

    $response = $this->getJson('/api/users?type=1&page=1&page_size=10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'current_page',
            'last_page',
            'per_page',
            'total',
            'data' => [
                [
                    'id',
                    'user_id',
                    'specialty_id',
                    'name',
                    'specialty' => [
                        'id',
                        'name'
                    ],
                ]
            ],
        ]);
});


it('fails if required fields are missing', function () {
    $response = $this->postJson("/api/users/{$this->patient->id}/appointments", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'healthcare_professional_id',
            'date',
            'appointment_start_time',
            'appointment_end_time',
        ]);
});


it('fails if date format is invalid', function () {
    $response = $this->postJson("/api/users/{$this->patient->id}/appointments", [
        'healthcare_professional_id' => $this->doctor->id,
        'date' => 'invalid-date',
        'appointment_start_time' => '19:30',
        'appointment_end_time' => '20:30',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['date']);
});

it('fails if start time or end time format is invalid', function () {
    $response = $this->postJson("/api/users/{$this->patient->id}/appointments", [
        'healthcare_professional_id' => $this->doctor->id,
        'date' => Carbon::today()->format('Y-m-d'),
        'appointment_start_time' => 'invalid-time',
        'appointment_end_time' => '20:30',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['appointment_start_time']);
});

it('books an appointment successfully if slot is available and request is valid', function () {
    $date = Carbon::today()->format('Y-m-d');
    $payload = [
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '11:00',
        'appointment_end_time' => '11:30',
    ];

    $response = $this->postJson("/api/users/{$this->patient->id}/appointments", $payload);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => __('messages.appointment_success'),
        ]);

    $this->assertDatabaseHas('appointments', [
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '11:00',
        'appointment_end_time' => '11:30',
    ]);
});

it('prevents booking if requested slot overlaps with existing appointment', function () {
    $date = Carbon::today()->format('Y-m-d');

    Appointment::create([
        'user_id' => 2, // another patient ID
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '13:00',
        'appointment_end_time' => '14:00',
        'status' => config('constants.status_booked'),
    ]);

    $payload = [
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '13:30',
        'appointment_end_time' => '14:30',
    ];

    $response = $this->postJson("/api/users/{$this->patient->id}/appointments", $payload);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => __('messages.slot_unavailable'),
        ]);
});

it('prevents booking appointments longer than 2 hours', function () {
    $date = Carbon::today()->format('Y-m-d');

    $payload = [
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '15:00',
        'appointment_end_time' => '18:00', // Trying to booking for 3 hours
    ];

    $response = $this->postJson("/api/users/{$this->patient->id}/appointments", $payload);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => __('messages.max_duration_exceeded'),
        ]);
});

it('prevents booking outside allowed working hours', function () {
    $payload = [
        'healthcare_professional_id' => $this->doctor->id,
        'date' => Carbon::today()->format('Y-m-d'),
        'appointment_start_time' => '08:00', // Try to boking before 09:00
        'appointment_end_time' => '09:30',
    ];

    $response = $this->postJson("/api/users/{$this->patient->id}/appointments", $payload);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => __('messages.slot_unavailable'),
        ]);
});

it('lists all appointments for the logged in patient', function () {
    $date = Carbon::today()->format('Y-m-d');

    $status = '';      // optional, can filter by status
    $page = 1;
    $pageSize = 10;

    // Create appointments for the authenticated patient
    Appointment::create([
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '09:00',
        'appointment_end_time' => '09:30',
        'status' => config('constants.status_booked'),
    ]);

    Appointment::create([
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $date,
        'appointment_start_time' => '10:00',
        'appointment_end_time' => '10:30',
        'status' => config('constants.status_booked'),
    ]);

    $response = $this->getJson("/api/users/{$this->patient->id}/appointments?status={$status}&page={$page}&page_size={$pageSize}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'current_page',
            'last_page',
            'per_page',
            'total',
            'data' => [
                [
                    'id',
                    'user_id',
                    'healthcare_professional_id',
                    'date',
                    'appointment_start_time',
                    'appointment_end_time'
                ]
            ]
        ]);

    $appointments = $response->json('data');
    expect(count($appointments))->toBe(2);
});

it('user cannot cancel if less than 24 hours remain', function () {
    // Appointment less than 24 hours away
    $appointmentDate  = Carbon::now()->addHours(23)->format('Y-m-d');
    $appointmentStart = Carbon::now()->addHours(23)->format('H:i');
    $appointmentEnd   = Carbon::now()->addHours(24)->format('H:i');

    $appointment = Appointment::create([
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $appointmentDate,
        'appointment_start_time' => $appointmentStart,
        'appointment_end_time' => $appointmentEnd,
        'status' => config('constants.status_booked'),
    ]);

    $response = $this->postJson("/api/users/{$this->patient->id}/appointments/{$appointment->id}");

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => __('messages.do_not_cancelled'),
        ]);

    $appointment->refresh();
    expect($appointment->status)->toBe(config('constants.status_booked'));
});

it('allows cancellation if more than 24 hours remain for appointment', function () {
    // Appointment more than 24 hours away
    $appointmentDate = Carbon::now()->addDays(2)->format('Y-m-d');
    $appointmentStart = '10:00';
    $appointmentEnd   = '10:30';

    $appointment = Appointment::create([
        'user_id' => $this->patient->id,
        'healthcare_professional_id' => $this->doctor->id,
        'date' => $appointmentDate,
        'appointment_start_time' => $appointmentStart,
        'appointment_end_time' => $appointmentEnd,
        'status' => config('constants.status_booked'),
    ]);

    $response = $this->postJson("/api/users/{$this->patient->id}/appointments/{$appointment->id}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => __('messages.appointment_cancelled'),
        ]);

    // status update
    $appointment->refresh();
    expect($appointment->status)->toBe(2);
});
