<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'subtitle',
        'slug',
        'price',
        'category_id',
        'sub_category_id',
        'topic',
        'language',
        'c_level',
        'duration',
        'thumbnail',
        'trailer_video',
        'description',
        'teach_course',
        'targer_audience',
        'requirements',
        'total_enrollment',
    ];

    protected $casts = [
        'teach_course' => 'array',
        'targer_audience' => 'array',
        'requirements' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function curriculum()
    {
        return $this->hasMany(Curriculum::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function checkouts()
    {
        return $this->hasMany(Checkout::class);
    }

    public function courseProgress()
    {
        return $this->hasMany(CourseProgress::class);
    }

    //get attribute for course thumbnail
    public function getThumbnailAttribute($value)
    {
        return $value ? asset('uploads/admin/course/thumbnail/' . $value) : null;
    }

    //get attribute for course trail_video
    public function getTrailerVideoAttribute($value)
    {
        return $value ? asset('uploads/admin/course/video/' . $value) : null;
    }

}
