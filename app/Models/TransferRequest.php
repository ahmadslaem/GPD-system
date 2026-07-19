<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class TransferRequest extends Model
{

protected $fillable=[

'family_id',
'from_camp_id',
'to_camp_id',
'requested_by',
'reason',
'status',
'manager_note',
'reviewed_by'

];



public function family()
{
    return $this->belongsTo(Family::class);
}



public function fromCamp()
{
    return $this->belongsTo(
        Camp::class,
        'from_camp_id'
    );
}



public function toCamp()
{
    return $this->belongsTo(
        Camp::class,
        'to_camp_id'
    );
}



public function requester()
{
    return $this->belongsTo(
        User::class,
        'requested_by'
    );
}


public function reviewer()
{
    return $this->belongsTo(
        User::class,
        'reviewed_by'
    );
}


}