<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserBonus extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $table = "user_bonus";
    protected $primaryKey = 'UserBonusId';
    protected $fillable = [ 'RevenueModelId', 'UserId', 'CurrencyId', 'USDAmount', 'AUDAmount', 'EURAmount', 'TotalAccTradeVol', 'TotalIntroducedAcc', 'Type', 'NextBonusDate', 'Comment', 'CreatedAt', 'UpdatedAt' ];

    public function User(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

    public function RevenueModel(){
        return $this->hasOne('App\RevenueModel', 'RevenueModelId', 'RevenueModelId');
    }

}