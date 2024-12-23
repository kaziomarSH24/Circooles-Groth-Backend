<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorInfo extends Model
{
    protected $table = 'tutor_infos';
    protected $fillable = [
        'user_id',
        'address',
        'description',
        'subjects_id',
        'designation',
        'organization',
        'teaching_experience',
        'expertise_area',
        'language',
        'degree',
        'institute',
        'graduation_year',
        'time_zone',
        'online',
        'offline',
        'session_charge',
    ];
    protected $casts = [
        'online' => 'json',
        'offline' => 'json',
        'subjects_id' => 'array',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function tutorVerification()
    {
        return $this->hasOne(TutorVerification::class, 'tutor_id');
    }

    public function tutorReviews()
    {
        return $this->hasMany(TutorReview::class, 'tutor_id');
    }

    public function accountDetails()
    {
        return $this->hasOne(AccountDetails::class, 'tutor_id');
    }

    public function tutorBookings()
    {
        return $this->hasMany(TutorBooking::class, 'tutor_id');
    }
}
