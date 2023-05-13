<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Storage;

class Ad extends Model
{ 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
    protected $table = 'ads';
    protected $primaryKey = 'AdId';
    protected $fillable = [
        'Title', 'BannerImage', 'LandingPageURL', 'AdBrandId', 'AdTypeId', 'AdSizeId', 'LanguageId', 'IsAllCountrySelected', 'CountryList', 'IsPublic', 'IsActive', 'CreatedBy', 'UpdatedBy'
    ];

    public function Brand(){
        return $this->hasOne('App\AdBrandMaster', 'AdBrandId', 'AdBrandId');
    }
 
    public function Type(){
        return $this->hasOne('App\AdTypeMaster', 'AdTypeId', 'AdTypeId');
    }

    public function Size(){
        return $this->hasOne('App\AdSizeMaster', 'AdSizeId', 'AdSizeId');
    }

    public function Language(){
        return $this->hasOne('App\LanguageMaster', 'LanguageId', 'LanguageId');
    }

    public function Country(){
        return $this->hasOne('App\CountryMaster', 'CountryId', 'CountryId');
    }

    public function AffiliatAds(){
        return $this->hasMany('App\AdAffiliateList', 'AdId', 'AdId');
    }
    
    public function UserAdBrand(){
        return $this->hasMany('App\UserAdBrand', 'AdBrandId', 'AdBrandId');
    }

    public function UserAdType(){
        return $this->hasMany('App\UserAdType', 'AdTypeId', 'AdTypeId');
    }

    public function CampaignAd(){
        return $this->hasOne('App\CampaignAdList', 'AdId', 'AdId');
    }

    public function Campaign(){
        return $this->hasOne('App\Campaign', 'AdId', 'AdId');
    }

}
