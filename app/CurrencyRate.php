<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const CREATED_AT = 'CreatedAt'; 
    const UPDATED_AT = 'UpdatedAt'; 
    
    protected $primaryKey = 'CurrencyRateId'; 
    protected $fillable = ['AUDUSD', 'EURUSD', 'Date', 'LeadFileInfo', 'Status']; 
    protected $hidden = ['CreatedAt', 'UpdatedAt']; 

    public function ConversionFile(){
        return $this->hasOne('App\LeadInfoFile', 'LeadFileInfo', 'LeadFileInfo');
    }
}