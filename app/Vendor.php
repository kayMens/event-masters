<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $guarded = [];

    protected $cast = [
        'complete' => 'bool',
        'service' => 'json',
    ];

    public function user() {
        return $this->belongsTo('\App\User', 'user_id');
    }

    public function getServiceAttribute($value)
    {
        return json_decode($value);
    }

    // public function setServiceAttribute($value)
    // {
        // $this->attributes['service'] = json_encode($value);
    // }
}
