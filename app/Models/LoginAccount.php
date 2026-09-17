<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'account_type',
    'User_ID',
    'Email',
    'OTP_CODE',
    'Password',
    'User_First_Name',
    'User_Middle_Name',
    'User_Last_Name',
    'Gender',
    'profile_picture_mime',
    'profile_picture',
])]
#[Hidden([
    'Password',
    'OTP_CODE',
    'profile_picture',
    'profile_picture_mime',
])]
class LoginAccount extends Authenticatable
{
    use Notifiable;

    protected $connection = 'system';

    protected $table = 'logins';

    protected $primaryKey = 'login_ID';

    public $incrementing = true;

    protected $keyType = 'int';

    public function getAuthPasswordName(): string
    {
        return 'Password';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->Password;
    }
}
