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
    public function tutor()
    {
        return $this->belongsTo(User::class, 'user_id');
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

     public function getSubjectsAttribute()
    {
        $ids = $this->subjects_id;
        if (is_string($ids)) {
            $ids = json_decode($ids, true);
        }
        if (!is_array($ids)) {
            $ids = [];
        }
        // dd();
        return Subject::whereIn('id', $ids)->get();
    }
    /**
     * Mutator to ensure subjects_id is always stored as an array of integers.
     */
    // public function setSubjectsIdAttribute($value)
    // {
    //     if (is_string($value)) {
    //         $value = json_decode($value, true);
    //     }
    //     $this->attributes['subjects_id'] = json_encode(array_map('intval', (array) $value));
    // }
}
