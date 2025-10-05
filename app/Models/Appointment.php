<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'healthcare_professional_id',
        'date',
        'appointment_start_time',
        'appointment_end_time',
        'status',
    ];

    protected $appends = ['user_name', 'professional_name'];
    protected $hidden = ['user', 'healthcareProfessional'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function healthcareProfessional()
    {
        return $this->belongsTo(HealthcareProfessional::class);
    }

    public function getUserNameAttribute()
    {
        return $this->user->name ?? null;
    }

    public function getProfessionalNameAttribute()
    {
        return $this->healthcareProfessional->specialty->name ?? null;
    }
}
