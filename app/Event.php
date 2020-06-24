<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $guarded = [];

    protected $cast = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
