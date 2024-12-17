<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountDetails extends Model
{

    use SoftDeletes;

    protected $fillable = [
        'tutor_id',
        'account_number',
        'name',
        'email',
        'bank_name',
        'bank_code',
        'account_type',
        'currency',
        'recipient_code',
    ];




    public function tutor()
    {
        return $this->belongsTo(TutorInfo::class);
    }
}
