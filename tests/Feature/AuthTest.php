<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;

uses(DatabaseMigrations::class);

beforeEach(function () {
    foreach (
        [
            ['id' => 1, 'name' => 'User'],
            ['id' => 2, 'name' => 'Healthcare Professional']
        ] as $role
    ) {
        Role::updateOrCreate(
            ['id' => $role['id']],
            ['name' => $role['name']]
        );
    }
});

it('registers a user with role 1 successfully', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'role1@example.com',
        'password' => 'password123',
        'role_id' => 1,
    ];

    $response = $this->postJson('/api/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'role1@example.com',
        'role_id' => 1,
    ]);
});

it('registers a user with role 2 (healthcare professional) successfully', function () {
    $specialty = Specialty::factory()->create();

    $payload = [
        'name' => 'Dr. Jane',
        'email' => 'role2@example.com',
        'password' => 'password123',
        'role_id' => 2,
        'specialty_id' => $specialty->id,
    ];

    $response = $this->postJson('/api/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'role2@example.com',
        'role_id' => 2,
    ]);

    $this->assertDatabaseHas('healthcare_professionals', [
        'user_id' => User::where('email', 'role2@example.com')->first()->id,
        'specialty_id' => $specialty->id,
    ]);
});

it('allows a user to login with valid credentials', function () {
    $this->user = User::create([
        'name' => 'John Doe',
        'email' => 'role1@example.com',
        'password' => Hash::make('password123'),
        'role_id' => 1,
    ]);
    
    $payload = [
        'email' => 'role1@example.com',
        'password' => 'password123',
    ];

    $response = $this->postJson('/api/auth/login', $payload);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'token',
            ],
        ]);
});

it('prevents login with invalid credentials', function () {
    $this->user = User::create([
        'name' => 'John Doe',
        'email' => 'role1@example.com',
        'password' => Hash::make('password123'),
        'role_id' => 1,
    ]);
    $payload = [
        'email' => 'role1@example.com',
        'password' => 'wrongpassword',
    ];

    $response = $this->postJson('/api/auth/login', $payload);

    $response->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => __('messages.credentials_not_match'),
        ]);
});

it('allows a logged-in user to logout successfully', function () {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'role1@example.com',
        'password' => Hash::make('password123'),
        'role_id' => 1,
    ]);

    Passport::actingAs($user);

    $response = $this->postJson('/api/users/logout');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => __('messages.logout_success'), 
            'data' => [],
        ]);
});

it('prevents logout if user is not authenticated', function () {
    $response = $this->postJson('/api/users/logout');

    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Unauthenticated.',
        ]);
});
