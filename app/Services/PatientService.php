<?php

namespace App\Services;

use App\Helpers\ApiResponse;
use App\Models\Appointment;
use App\Models\HealthcareProfessional;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientService
{
    protected $response;

    public function __construct(ApiResponse $response)
    {
        $this->response = $response;
    }

    /** 
     * Book a new appointment for a patient
     * Checks if user already has a booking, slot availability, and max duration (2 hour max duration)
     */
    public function bookAppointment(array $data, $userId)
    {
        try{
            $requestedStart = Carbon::parse($data['appointment_start_time']);
            $requestedEnd   = Carbon::parse($data['appointment_end_time']);

            $workingHours = config('constants.time_slot');
            $startWorking = Carbon::parse($workingHours['start']);
            $endWorking   = Carbon::parse($workingHours['end']);

            if ($requestedStart < $startWorking || $requestedEnd > $endWorking) {
                return ['error' => __('messages.slot_unavailable')];
            }

            $baseQuery = Appointment::where('healthcare_professional_id', $data['healthcare_professional_id'])
                ->where('date', $data['date'])
                ->where('status', config('constants.status_booked'));

            // Limit set for appointment duration to 2 hours
            $duration = ($requestedEnd->getTimestamp() - $requestedStart->getTimestamp()) / 3600;
            if ($duration > 2) {
                return [
                    'error' => __('messages.max_duration_exceeded'),
                ];
            }

            $appointments = (clone $baseQuery)
                ->orderBy('appointment_start_time')
                ->get();

            foreach ($appointments as $appt) {
                $apptStart = Carbon::parse($appt->appointment_start_time);
                $apptEnd   = Carbon::parse($appt->appointment_end_time);

                // If requested slot overlaps with existing slot - return error with data
                if ($requestedStart < $apptEnd && $requestedEnd > $apptStart) {
                    $availableSlots = $this->getAvailableSlots(
                        $data['healthcare_professional_id'],
                        $data['date']
                    );
                    
                    $bookedSlots = $appointments->map(function ($a) {
                        return [
                            'start_time' => Carbon::parse($a->appointment_start_time)->format('H:i'),
                            'end_time'   => Carbon::parse($a->appointment_end_time)->format('H:i'),
                        ];
                    });

                    return [
                        'error'            => __('messages.slot_unavailable'),
                        'date'             => $data['date'],
                        'booked_slots_all' => $bookedSlots,
                        'available_slots'  => $availableSlots,
                    ];
                }
            }

            // Create appointment if slot is available
            $appointment = Appointment::create([
                'user_id' => $userId,
                'healthcare_professional_id' => $data['healthcare_professional_id'],
                'date' => $data['date'],
                'appointment_start_time' => $requestedStart->format('H:i'),
                'appointment_end_time' => $requestedEnd->format('H:i'),
                'status' => config('constants.status_booked') ?? 1,
            ]);

            return [
                'exists' => false,
                'appointment' => [
                    'id' => $appointment->id
                ],
            ];
        } catch (Exception $e) {
            Log::error('Exception: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }

    /** 
     * Get all available time slots for a healthcare professional on a given date
     */
    public function getAvailableSlots(int $professionalId, string $date, int $slotMinutes = 30)
    {
        try{
            $workingHoursStart = config('constants.time_slot')['start'];
            $workingHoursEnd   = config('constants.time_slot')['end'];

            $appointments = Appointment::where('healthcare_professional_id', $professionalId)
                ->where('date', $date)
                ->where('status', config('constants.status_booked'))
                ->orderBy('appointment_start_time')
                ->get(['appointment_start_time', 'appointment_end_time']);

            $slots       = [];
            $currentTime = Carbon::parse($workingHoursStart);
            $endTime     = Carbon::parse($workingHoursEnd);

            while ($currentTime < $endTime) {
                $slotStart = $currentTime;
                $slotEnd = Carbon::parse($slotStart->format('H:i'))->addMinutes($slotMinutes);

                // Check if slot conflicts with existing appointments
                $isAvailable = true;
                foreach ($appointments as $appt) {
                    $apptStart = Carbon::parse($appt->appointment_start_time);
                    $apptEnd   = Carbon::parse($appt->appointment_end_time);

                    if ($slotStart < $apptEnd && $slotEnd > $apptStart) {
                        $isAvailable = false;
                        break;
                    }
                }

                if ($isAvailable) {
                    $slots[] = [
                        'start_time' => $slotStart->format('H:i'),
                        'end_time'   => $slotEnd->format('H:i'),
                    ];
                }

                $currentTime->addMinutes($slotMinutes);
            }

            return $slots;
        } catch (Exception $e) {
            Log::error('Exception: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }

    /** 
     * Get list of doctors with available slots and patient booking info
     */
    public function getDoctors($request)
    {
        try {
            if($request->type == config('constants.user')){
                $pageSize = $request->page_size ?? 10;
                $doctors = HealthcareProfessional::with([
                    'specialty:id,name',
                ])
                ->select('id', 'user_id', 'specialty_id', 'name')
                ->orderBy('id', 'desc')
                ->paginate($pageSize);
                
                return $doctors;
            }
            return [];
        } catch (\Exception $e) {
            Log::error('Exception: ' . $e->getMessage());
            return [
                'error' => __('messages.something_went_wrong'),
                'data'  => []
            ];
        }
    }

    /** 
     * Cancel a patient's appointment if more than 24 hours remain
     */
    public function cancelAppointment(int $appointmentId)
    {
        try {
            DB::beginTransaction();

            $userId = Auth::id();

            $appointment = Appointment::where('id', $appointmentId)
                ->whereIn('status', [config('constants.status_booked'), config('constants.status_cancelled')])
                ->where('user_id', $userId)
                ->first();
            
            if (isset($appointment) && $appointment->status == config('constants.status_cancelled')) {
                return $this->response->error(__('messages.appointment_already_cancelled'), 404);
            }

            if (!$appointment) {
                return $this->response->error(__('messages.data_not_found'), 404);
            }
            
            // Check 24-hour cutoff before appointment
            $appointmentStart = Carbon::parse($appointment->date . ' ' . $appointment->appointment_start_time, 'Asia/Kolkata');
            $cutoffTimeStr    = $appointmentStart->copy()->subHours(24)->toDateTimeString();
            $nowStr           = Carbon::now('Asia/Kolkata')->toDateTimeString();

            if ($nowStr >= $cutoffTimeStr) {
                return $this->response->error(__('messages.do_not_cancelled'), 400);
            }


            $appointment->status = config('constants.status_cancelled');
            $appointment->save();

            DB::commit();

            return $this->response->success([], __('messages.appointment_cancelled'), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cancel Appointment Error: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }
}
