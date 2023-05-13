<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ResetPassword extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'ResetPasswordId';
    protected $fillable = ['UserId', 'PasswordResetToken', 'EmailId'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}