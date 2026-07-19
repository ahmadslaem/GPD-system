<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Camp extends Model
{
     protected $fillable = [

        'name',
        'location',
        'capacity',
        'current_population',
        'is_active'

    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function families()
    {
        return $this->hasMany(Family::class);
    }
public function transferRequests()
{
    return $this->hasMany(
        TransferRequest::class
    );
}
}
