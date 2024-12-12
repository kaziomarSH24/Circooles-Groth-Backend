<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'seller_id',
        'buyer_id',
        'course_id',
        'amount',
        'reference',
        'status',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }


    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
