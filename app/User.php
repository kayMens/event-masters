<?php

namespace App;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected static $ROLE_ADMIN = 'admin';
    protected static $ROLE_USER = 'user';
    protected static $ROLE_VENDOR = 'vendor'; //for everyone|anyone
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'phone_verified_at' => 'datetime',
    ];

    public function isAdmin() {
        return $this->type == static::$ROLE_ADMIN;
    }
    
    public function isUser() {
        return $this->type == static::$ROLE_USER;
    }

    public function isVendor() {
        return $this->type == static::$ROLE_VENDOR;
    }

    public function event() {
        return $this->hasMany('App\Event');
    }
}
