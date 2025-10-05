<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class UpdateAppointmentStatus extends Command
{
    protected $signature = 'appointments:status';
    protected $description = 'Update appointment status to completed if end time has passed';


    /**
     * Main handle method:
     * This method checks all appointments that are currently active (status 1) 
     * and whose date is today or earlier. 
     * If the appointments end time has passed, it updates the status to completed (3). 
     * A log entry is made to confirm that the cron job executed successfully.
     */
    public function handle()
    {
        try {
            $now = Carbon::now();
            $appointments = Appointment::where('status', 1)
                ->where('date', '<=', $now->toDateString())
                ->get();

            foreach ($appointments as $appointment) {
                $appointmentEnd = Carbon::parse($appointment->date . ' ' . $appointment->appointment_end_time);

                if ($appointmentEnd->lessThanOrEqualTo($now)) {
                    $appointment->status = config('constants.status_completed');
                    $appointment->save();
                }
            }
        } catch (Exception $e) {
            Log::error('Error in appointments:status cron: ' . $e->getMessage());
        }
    }
}