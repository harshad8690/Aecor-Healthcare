<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class HealthcareProfessional extends Model
{
    use Notifiable;

    protected $fillable = [
        'user_id',
        'name',
        'specialty_id'
    ];

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
