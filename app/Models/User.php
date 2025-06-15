<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'google_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'otp_expiry_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Implement JWTSubject methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function tutorInfo()
    {
        return $this->hasOne(TutorInfo::class, 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'buyer_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    //checkout relationship
    public function checkouts()
    {
        return $this->hasMany(Checkout::class);
    }

    public function tutorBookings()
    {
        return $this->hasMany(TutorBooking::class, 'student_id');
    }

    public function tutorReviews()
    {
        return $this->hasMany(TutorReview::class, 'student_id');
    }

    public function courseProgress()
    {
        return $this->hasMany(CourseProgress::class);
    }

    public function getAvatarAttribute($value)
    {
        if ($value == null) {

            return "https://ui-avatars.com/api/?background=random&name={$this->name}&bold=true";
        }
        return asset('avatars/' . $value);
    }

}
