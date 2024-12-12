<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Escrow extends Model
{
    protected $fillable = [
        'booking_id',
        'hold_amount',
        'status',
        'release_date',
        'refund_date',
        'reference_id',
    ];


    public function booking()
    {
        return $this->belongsTo(TutorBooking::class, 'booking_id');
    }

}
