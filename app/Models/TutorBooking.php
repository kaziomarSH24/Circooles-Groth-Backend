<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorBooking extends Model
{
    protected $fillable = [
        'tutor_id',
        'student_id',
        'course_id',
        'schedule',
        'repeat',
        'session_quantity',
        'session_cost',
        'total_cost',
        'status',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function tutorInfo()
    {
        return $this->belongsTo(TutorInfo::class, 'tutor_id');
    }

    public function escrow()
    {
        return $this->hasOne(Escrow::class, 'booking_id');
    }
}
