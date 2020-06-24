<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $guarded = [];

    
    public function getServiceAttribute($value)
    {
        return json_decode($value);
    }

    public function setServiceAttribute($value)
    {
        $this->attributes['service'] = json_encode($value);
    }
}
