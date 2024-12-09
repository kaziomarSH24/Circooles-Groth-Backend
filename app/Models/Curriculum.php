<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculum extends Model
{
    protected $fillable = [
        'course_id',
        'section_name',
        
    ];

    public function course() : BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
    public function lectures() : HasMany
    {
        return $this->hasMany(Lecture::class);
    }
    
}
