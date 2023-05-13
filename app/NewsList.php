<?php 
namespace App;

use Illuminate\Database\Eloquent\Model;

class NewsList extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $table = "news_affiliate_lists";
    protected $primaryKey = 'NewsAffiliateId';
    protected $fillable = [
    	'NewsId', 'UserId', 'CreatedBy', 'UpdatedBy', 'IsRead'
    ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    
    public function NewsAffiliateList(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }
}