<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyMember extends Model
{
    protected $fillable=[
'name',
'national_id',
'birth_date',
'gender',
'has_disability',
'family_id'
];


public function family()
{
    return $this->belongsTo(
        Family::class
    );
}


}
