<?php

namespace App\Model;

use App\BaseAuthenticatableModel;
use App\Constants\Columns;
use App\Constants\Enums;
use App\Constants\Tables;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends BaseAuthenticatableModel
{
    use Notifiable, HasApiTokens, SoftDeletes, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        Columns::name,
        Columns::email,
        Columns::phone,
        Columns::image_url,
        Columns::password,
        Columns::fcm_token,
        Columns::is_email_verify,
        Columns::is_phone_verify,
        Columns::email_verified_at,
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        Columns::password,
        Columns::remember_token,
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        Columns::is_email_verify,
        Columns::is_phone_verify,
        Columns::email_verified_at,
    ];

    /**
     * Rules to be use for validation while insert data in "users" Table
     */
    public static $rules = [
        Columns::name => "required|string",
        Columns::email => "required|email|unique:" . Tables::USERS,
        Columns::image_url => 'string',
        Columns::password => "required|string|min:6",
        Columns::confirm_password => "required|string|same:" . Columns::password . "|min:6",
    ];

}
