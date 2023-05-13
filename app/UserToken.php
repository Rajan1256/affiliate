<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use DB;
use App\User;

class UserToken extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'TokenId';
    protected $fillable = ['UserId', 'Token'];

    function validToken($request)
    {
        // $user = UserToken::where('Token', $request)->first();
        $user = User::whereHas('UserTokens', function ($qr) use ($request) {
            $qr->where('Token', $request);
        })->where('IsDeleted', 1)->where('IsEnabled', 1)->where('AdminVerified', 1)->first();
        if ($user) {
            return $user->UserId;
        } else {
            return false;
        }
    }

    function validTokenAdmin($request)
    {
        $myuser = User::whereHas('UserTokens', function ($qr) use ($request) {
            $qr->where('Token', $request);
        })->count();

        if ($myuser == 1) {
            $log_user = User::whereHas('UserTokens', function ($qr) use ($request) {
                $qr->where('Token', $request);
            })->where('IsDeleted', 1)->where('IsEnabled', 1)->where('AdminVerified', 1)->first();
        } else {
            $log_user = UserToken::where('Token', $request)->first();
        }

        if ($log_user) {
            return $log_user;
        } else {
            return false;
        }
    }
}
