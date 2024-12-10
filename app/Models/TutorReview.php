<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorReview extends Model
{
    protected $fillable = ['tutor_id', 'user_id', 'rating', 'comment'];

    public function tutor()
    {
        return $this->belongsTo(TutorInfo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
