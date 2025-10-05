<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentBookRequest;
use App\Http\Requests\DoctorSlotsRequest;
use App\Models\Appointment;
use App\Services\PatientService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{
    protected PatientService $service;
    protected ApiResponse $response;

    public function __construct(PatientService $service, ApiResponse $response)
    {
        $this->service = $service;
        $this->response = $response;
    }

    /** 
     * List all appointments for the logged in patient
     */
    public function list(Request $request, $id)
    {
        try {
            $authId = Auth::id();
            if ($id != $authId) {
                return  $this->response->error(__('messages.something_went_wrong'), 500);
            }

            $pageSize = $request->page_size ?? config('constants.paginate');
            $status = [$request->status];
            if(!isset($request->status)){
                $status = [config('constants.status_booked'), config('constants.status_cancelled'), config('constants.status_completed')];
            }   
            
            $appointments = Appointment::select('id' ,'user_id', 'healthcare_professional_id', 'date', 'appointment_start_time', 'appointment_end_time', 'status')
                ->where('user_id', Auth::id())
                ->whereIn('status', $status)
                ->orderBy('date', 'desc')
                ->paginate($pageSize);

            return $this->response->success(
                [],
                __('messages.appointment_list'),
                200,
                $appointments
            );
        } catch (\Exception $e) {
            Log::error('Appointment List Error: ' . $e->getMessage());
            return  $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }

    /** 
     * Book a new appointment for the patient
     */
    public function appointmentBook(AppointmentBookRequest $request, int $id)
    {
        try {
            DB::beginTransaction();
            $user = $request->user();
            $authId = Auth::id();
            if($id != $authId){
                return  $this->response->error(__('messages.something_went_wrong'), 500);
            }
            
            $result = $this->service->bookAppointment($request->validated(), $user->id);

            // If slot unavailable
            if (!empty($result['error'])) {
                DB::rollBack();
                return $this->response->error(
                    $result['error'],
                    400,
                    [
                        'date'             => $result['date'] ?? null,
                        'booked_slots_all' => $result['booked_slots_all'] ?? null,
                        'available_slots'  => $result['available_slots'] ?? null,
                    ]
                );
            }
            
            DB::commit();

            return $this->response->success(
                $result['appointment'],
                __('messages.appointment_success'),
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Appointment Error: ' . $e->getMessage());
            return  $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }

    /** 
     * Get available doctors with their appointment slots
     */
    public function doctorSlots(Request $request)
    {
        try {
            $userId  = Auth::id();
            $result  = $this->service->getDoctors($request, $userId);

            if (isset($result['error'])) {
                return $this->response->error($result['error'], 500);
            }

            $message = !empty($result['data']) ? __('messages.doctor_details') : __('messages.data_not_found');
            return $this->response->success(
                [],
                $message,
                200,
                $result
            );
        } catch (\Exception $e) {
            Log::error('Doctor List Error: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }

    /** 
     * Cancel a specific appointment by ID.
     * If less than 24 hours remain for the appointment, it cannot be cancelled.
     */
    public function cancelAppointment(int $id, int $appointmentId)
    {
        try {
            return $this->service->cancelAppointment($appointmentId);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cancel Appointment Error: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }
}
