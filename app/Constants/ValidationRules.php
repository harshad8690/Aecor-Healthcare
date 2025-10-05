<?php

namespace App\Constants;

class ValidationRules
{
    const NAME = 'required|string|max:255';
    const EMAIL = 'required|email|unique:users,email';
    const PASSWORD = 'required|string|min:8';
    const ROLE = 'required|in:1,2';
    const SPECIALITY= 'required_if:role_id,2|exists:specialties,id';

    public const LOGIN_EMAIL = 'required|email';
    public const LOGIN_PASSWORD = 'required|string';

    public const APPOINTMENT_DATE = 'required|date|date_format:Y-m-d|after_or_equal:today';
    public const APPOINTMENT_START_TIME = 'required|date_format:H:i';
    public const APPOINTMENT_END_TIME = 'required|date_format:H:i|after:appointment_start_time';
    public const HEALTHCARE_PROFESSIONAL_ID = 'required|exists:healthcare_professionals,id';

    public const DOCTOR_SLOTS_PROFESSIONAL_ID = 'required|exists:healthcare_professionals,id';
    public const STATUS_UPDATE = 'required|in:2,3';
}
