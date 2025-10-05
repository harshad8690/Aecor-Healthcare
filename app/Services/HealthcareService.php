<?php

namespace App\Services;

use App\Helpers\ApiResponse;
use App\Models\Appointment;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthcareService
{
    protected ApiResponse $response;

    public function __construct(ApiResponse $response)
    {
        $this->response = $response;
    }

    /** 
     * Update appointment status to completed or cancelled for the logged in healthcare professional
     */
    public function markCompleted(array $data, int $id): array
    {
        try {
            DB::beginTransaction();
            $userId = Auth::user()->healthcareProfessional->id;
            $appointment = Appointment::where('id', $id)
                            ->where('healthcare_professional_id', $userId)
                            ->first();
            
            if (isset($appointment) && $appointment->status == 3) {
                return [
                    'success' => false,
                    'status'  => 400,
                    'message' => __('messages.already_mark_as_completed'),
                    'data'    => [],
                ];
            }


            if (!$appointment) {
                return [
                    'success' => false,
                    'status'  => 404,
                    'message' => __('messages.data_not_found'),
                    'data'    => [],
                ];
            }

            $appointment->status = $data['status'] ?? $appointment->status;
            $appointment->save();

            DB::commit();

            return [
                'success' => true,
                'status'  => 200,
                'message' => __('messages.appointment_completed'),
                'data'    => [],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Mark Completed Error: ' . $e->getMessage());

            return [
                'success' => false,
                'status'  => 500,
                'message' => __('messages.something_went_wrong'),
                'data'    => [],
            ];
        }
    }
}
