<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallets extends Model
{
    protected $fillable = ['user_id', 'balance', 'currency'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
