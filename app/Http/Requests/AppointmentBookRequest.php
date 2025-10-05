<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\ValidationRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AppointmentBookRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'healthcare_professional_id' => ValidationRules::HEALTHCARE_PROFESSIONAL_ID,
            'date' => ValidationRules::APPOINTMENT_DATE,
            'appointment_start_time' => ValidationRules::APPOINTMENT_START_TIME,
            'appointment_end_time' => ValidationRules::APPOINTMENT_END_TIME,
        ];
    }

    public function messages()
    {
        return [
            'date.required' => 'The appointment date is required.',
            'date.date' => 'The appointment date must be a valid date.',
            'date.after_or_equal' => 'Appointment date cannot be in the past. Please select today or a future date.',
            'appointment_start_time.required' => 'Appointment start time is required.',
            'appointment_start_time.date_format' => 'Start time must be in the format HH:MM.',
            'appointment_end_time.required' => 'Appointment end time is required.',
            'appointment_end_time.date_format' => 'End time must be in the format HH:MM.',
            'appointment_end_time.after' => 'End time must be after the start time.',
            'healthcare_professional_id.required' => 'Please select a healthcare professional.',
            'healthcare_professional_id.exists' => 'Selected healthcare professional does not exist.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors()
        ], 422));
    }
}
