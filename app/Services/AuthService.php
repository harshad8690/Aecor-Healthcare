<?php

namespace App\Services;

use App\Helpers\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\HealthcareProfessional;
use Exception;
use Illuminate\Support\Facades\Log;

class AuthService
{
    protected ApiResponse $response;

    public function __construct(ApiResponse $response)
    {
        $this->response = $response;
    }

    /** 
     * Create a new user and, if role is healthcare professional, also create related profile
     */
    public function registerUser(array $data)
    {
        try{
            $data['password'] = Hash::make($data['password']);
            $user = User::create($data);
            if (isset($data['role_id']) && $data['role_id'] == 2) {
                HealthcareProfessional::create([
                    'name' => $data['name'],
                    'specialty_id' => $data['specialty_id'],
                    'user_id' => $user->id, 
                ]);
            }
            return $user;
        } catch (Exception $e) {
            Log::error('Cancel Appointment Error: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }
}
