<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorVerification extends Model
{
    protected $table = 'tutor_verifications';
    protected $fillable = [
        'tutor_id',
        'academic_certificates',
        'id_card',
        'tsc',
        'verification_fee',
        'status',
    ];
    protected $casts = [
        'academic_certificates' => 'json',
        'id_card' => 'json',
        'tsl' => 'json',
    ];
    public function tutor()
    {
        return $this->belongsTo(TutorInfo::class);
    }
}
