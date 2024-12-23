<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'tutor_booking_id',
        'start_time',
        'end_time',
        'type',
        'status',
        'reschedule_at',
        'reschedule_by',
        'zoom_link',
    ];

    public function tutorBooking()
    {
        return $this->belongsTo(TutorBooking::class);
    }

}
