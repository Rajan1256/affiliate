<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class CurrencyConvert extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt'; 
    
    protected $primaryKey = 'CurrencyConvertId';
    protected $fillable = [ 'CurrencyRateId', 'USDAUD', 'USDEUR', 'AUDUSD', 'AUDEUR', 'EURUSD', 'EURAUD' ]; 
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    
    public function CurrencyRate(){
        return $this->hasOne('App\CurrencyRate', 'CurrencyRateId', 'CurrencyRateId');
    }
}