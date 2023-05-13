<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class NewsBrandList extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $table = "news_brand_lists";
    protected $primaryKey = 'NewsBrandId';
    protected $fillable = [
    	'NewsId', 'AdBrandId', 'CreatedBy', 'UpdatedBy'
    ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];


    public function UserAdBrand()
    {
        return $this->hasMany('App\UserAdBrand', 'AdBrandId', 'AdBrandId');
    }
}