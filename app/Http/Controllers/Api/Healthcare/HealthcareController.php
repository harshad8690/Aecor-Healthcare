<?php

namespace App\Http\Controllers\Api\Healthcare;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarkCompletedRequest;
use App\Models\Appointment;
use App\Models\HealthcareProfessional;
use App\Services\HealthcareService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthcareController extends Controller
{
    protected HealthcareService $service;
    protected ApiResponse $response;

    public function __construct(HealthcareService $service, ApiResponse $response)
    {
        $this->service = $service;
        $this->response = $response;
    }

    /** 
     * Fetch paginated list of appointments for the logged-in healthcare professional
     */
    public function listAppointments(Request $request, int $id)
    {
        try {
            $userId = Auth::id();
            if ($id != $userId) {
                return  $this->response->error(__('messages.something_went_wrong'), 500);
            }

            $status = [$request->status];
            if (!isset($request->status)) {
                $status = [config('constants.status_booked'), config('constants.status_cancelled'), config('constants.status_completed')];
            }
            $pageSize = $request->page_size ?? config('constants.paginate');
            $healthcareData = HealthcareProfessional::where('user_id', $userId)->first();
            $appointments = Appointment::with('user:id,name')->select('id', 'user_id', 'healthcare_professional_id', 'date', 'appointment_start_time', 'appointment_end_time', 'status')
                ->where('healthcare_professional_id', $healthcareData->id)
                ->where('status', $status)
                ->orderBy('date', 'desc')
                ->paginate($pageSize);

            return $this->response->success(
                [],
                __('messages.appointment_list'),
                200,
                $appointments
            );
        } catch (\Exception $e) {
            Log::error('List Appointments Error: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }

    /** 
     * Mark an appointment as completed or cancelled based on request input
     */
    public function markCompleted(MarkCompletedRequest $request, $id, int $appointmentId)
    {
        try {
            $userId = Auth::id();

            if ($id != $userId) {
                return  $this->response->error(__('messages.something_went_wrong'), 500);
            }

            $result = $this->service->markCompleted($request->validated(), $appointmentId);

            if (!$result['success']) {
                return $this->response->error($result['message'], $result['status']);
            }
            
            if($request->status == config('constants.status_cancelled')){
                $message = __('messages.appointment_cancelled');
            } else {
                $message = $message['message'] ?? __('messages.appointment_completed');
            }
            
            return $this->response->success($result['data'], $message, $result['status']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mark Completed Error: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }
}
