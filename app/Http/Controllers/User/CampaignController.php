<?php

namespace App\Http\Controllers\User;
use Illuminate\Http\Request;
use Validator; 
use App\User;
use App\UserToken;
use App\Campaign;
use App\CampaignAdList;
use App\CampaignTypeMaster;
use App\UserRevenueType;
use App\RevenueModel;
use App\RevenueModelLog;
use App\UserAdBrand;
use App\UserAdType;
use App\Ad;
use App\Lead;
use App\UserRevenuePayment;
use App\CampaignAdImpression; 
use App\CampaignAdClick;
use Laravel\Lumen\Routing\Controller as BaseController;

class CampaignController extends BaseController
{

    private $request;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {  
        $this->request = $request;
        $this->storage_path = getenv('STORAGE_URL');
    }

    public function CampaignAddUpdate(Request $request)
    { 
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token'));
        if($UserId){
            $validator = Validator::make($request->all(), [
                'CampaignName' => 'required|max:255',
                'CampaignTypeId' => 'required',
                'RevenueModelId' => 'required',
                // 'CampaignAds' => 'required'
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Something went wrong.',
                    "TotalCount" => count($validator->errors()),
                    "Data" => array('Error' => $validator->errors())
                ], 200);
            }
            if(isset($request->CampaignId) && $request->CampaignId != '')
            {
                $CampaignAdList = CampaignAdList::where('UserId', $UserId)->where('CampaignId', '!=', $request->CampaignId)->whereHas('Ad', function($AdEnable){
                            $AdEnable->where('IsActive', 1);
                    })->whereHas('Campaign', function($qr){
                        $qr->where('IsDeleted', 1);
                    })->where('IsDeleted', 0)->get();
                $AssignedAdId = []; 
                foreach ($CampaignAdList as $value) { 
                    array_push($AssignedAdId, $value['AdId']);
                }
                $CampaignAds = $request->CampaignAds;
                /*$adsSelected = '';
                foreach ($CampaignAds as $value) {
                    if(in_array($value, $AssignedAdId)){
                        $AdName = Ad::find($value);
                        $adsSelected .= $AdName->Title.', ';
                    }
                }
                if($adsSelected != ''){
                    $adsSelected = rtrim($adsSelected, " ,");
                    return response()->json([
                        "IsSuccess" => false,
                        "Message" => "Selected ads are already assigned to another campaign. Please select other ads to create a campaign.",
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200);     
                }*/

                $Campaign = Campaign::where(['CampaignId' => $request->CampaignId, 'UserId' => $UserId])->first(); 
                if($Campaign){
                    $Campaign->UserId = $UserId;
                    $Campaign->CampaignName = $request->CampaignName;
                    $Campaign->CampaignTypeId = $request->CampaignTypeId;
                    $Campaign->RevenueModelId = $request->RevenueModelId; 
                    $Campaign->save();
                    
                    // $CampaignAdOld = CampaignAdList::where('CampaignId',$request->CampaignId)->delete();
                    $CampaignAdListOld = CampaignAdList::where('CampaignId',$request->CampaignId)->where('IsDeleted',0)->pluck('AdId');
                    $oldArray = [];
                    foreach ($CampaignAdListOld as $oldId) {
                      array_push($oldArray, $oldId);
                    }
                    $CampaignAdListNew = $request->CampaignAds;

                    foreach ($CampaignAdListOld as $OldData) {
                      if(!in_array($OldData, $CampaignAdListNew)){
                        $CampaignAd = CampaignAdList::where('AdId', $OldData)->where('CampaignId', $request->CampaignId)->where('IsDeleted',0)->first();
                        CampaignAdList::where('CampaignAddId',$CampaignAd->CampaignAddId)->update(['IsDeleted'=>1]); 
                      }
                    }
                    foreach ($CampaignAdListNew as $NewAdId) {
                      if(!in_array($NewAdId, $oldArray)){ 
                        CampaignAdList::create([
                          'CampaignId' => $Campaign->CampaignId,
                          'AdId' => $NewAdId,
                          'UserId' => $UserId
                        ]);
                      }
                    }

                    /*$CampaignAdOld = CampaignAdList::where('CampaignId',$request->CampaignId)->delete();
                    $CampaignAds = $request->CampaignAds;
                    foreach ($CampaignAds as $value) {
                        CampaignAdList::create([
                            'CampaignId' => $Campaign->CampaignId,
                            'AdId' => $value,
                            'UserId' => $UserId
                        ]);
                    }*/
                    return response()->json([
                        "IsSuccess" => true,
                        "Message" => "Campaign updated successfully.",
                        "TotalCount" => 1,
                        "Data" => array("Campaign" => $Campaign)
                    ], 200);
                }
                else { 
                    return response()->json([
                        'IsSuccess' => true,
                        'Message' => 'Campaign not found.',
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200); 
                }
            }else{

                $CampaignAdList = CampaignAdList::where('UserId', $UserId)->whereHas('Ad', function($AdEnable){
                            $AdEnable->where('IsActive', 1);
                    })->whereHas('Campaign', function($qr){
                        $qr->where('IsDeleted', 1);
                    })->get();
                $AssignedAdId = [];
                foreach ($CampaignAdList as $value) { 
                    array_push($AssignedAdId, $value['AdId']);
                }
                $CampaignAds = $request->CampaignAds;
                /*$adsSelected = '';
                foreach ($CampaignAds as $value) {
                    if(in_array($value, $AssignedAdId)){
                        $AdName = Ad::find($value);
                        $adsSelected .= $AdName->Title.', ';
                    }
                }
                if($adsSelected != ''){
                    $adsSelected = rtrim($adsSelected, " ,");
                    return response()->json([
                        "IsSuccess" => false,
                        "Message" => "Selected ads are already assigned to another campaign. Please select other ads to create a campaign.",
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200);     
                }*/

                $Campaign = new Campaign(); 
                $Campaign->UserId = $UserId;
                $Campaign->CampaignName = $request->CampaignName;
                $Campaign->CampaignTypeId = $request->CampaignTypeId;
                $Campaign->RevenueModelId = $request->RevenueModelId;
                $Campaign->IsDeleted = 1;
                $Campaign->save(); 

                $CampaignAds = $request->CampaignAds;
                foreach ($CampaignAds as $value) {
                    CampaignAdList::create([
                        'CampaignId' => $Campaign->CampaignId,
                        'AdId' => $value,
                        'UserId' => $UserId
                    ]);
                }
                return response()->json([
                    "IsSuccess" => true,
                    "Message" => "Campaign created successfully.",
                    "TotalCount" => 0,
                    "Data" => array("Campaign" => $Campaign)
                ], 200);                
            }
        }
        else
        {
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Token not found.',
                "TotalCount" => 0,
                'Data' => []
            ], 200);
        }
    }

    public function CampaignAddUpdate2(Request $request)
    {
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token'));
        if($UserId){
            $validator = Validator::make($request->all(), [
                'CampaignName' => 'required|max:255',
                'CampaignTypeId' => 'required',
                'RevenueModelId' => 'required',
                'CampaignAds' => 'required'
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Something went wrong.',
                    "TotalCount" => count($validator->errors()),
                    "Data" => array('Error' => $validator->errors())
                ], 200);
            }
            if(isset($request->CampaignId) && $request->CampaignId != '')
            {
                $CampaignAdList = CampaignAdList::where('UserId', $UserId)->where('CampaignId', '!=', $request->CampaignId)->whereHas('Campaign', function($qr){
                        $qr->where('IsDeleted', 1);
                    })->get();
                $AssignedAdId = [];
                foreach ($CampaignAdList as $value) { 
                    array_push($AssignedAdId, $value['AdId']);
                }
                $CampaignAds = $request->CampaignAds;
                /*$adsSelected = '';
                foreach ($CampaignAds as $value) {
                    if(in_array($value, $AssignedAdId)){
                        $AdName = Ad::find($value);
                        $adsSelected .= $AdName->Title.', ';
                    }
                }
                if($adsSelected != ''){
                    $adsSelected = rtrim($adsSelected, " ,");
                    return response()->json([
                        "IsSuccess" => false,
                        "Message" => "Selected ads are already assigned to another campaign. Please select other ads to create a campaign.",
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200);     
                }*/

                $Campaign = Campaign::where(['CampaignId' => $request->CampaignId, 'UserId' => $UserId])->first();
                if($Campaign){
                    $Campaign->UserId = $UserId;
                    $Campaign->CampaignName = $request->CampaignName;
                    $Campaign->CampaignTypeId = $request->CampaignTypeId;
                    $Campaign->RevenueModelId = $request->RevenueModelId; 
                    $Campaign->save();

                    $CampaignAdOld = CampaignAdList::where('CampaignId',$request->CampaignId)->delete();
                    $CampaignAds = $request->CampaignAds;
                    foreach ($CampaignAds as $value) {
                        CampaignAdList::create([
                            'CampaignId' => $Campaign->CampaignId,
                            'AdId' => $value,
                            'UserId' => $UserId
                        ]);
                    }
                    return response()->json([
                        "IsSuccess" => true,
                        "Message" => "Campaign updated successfully.",
                        "TotalCount" => 1,
                        "Data" => array("Campaign" => $Campaign)
                    ], 200);
                }
                else { 
                    return response()->json([
                        'IsSuccess' => true,
                        'Message' => 'Campaign not found.',
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200); 
                }
            }else{

                $CampaignAdList = CampaignAdList::where('UserId', $UserId)->whereHas('Campaign', function($qr){
                        $qr->where('IsDeleted', 1);
                    })->get();
                $AssignedAdId = [];
                foreach ($CampaignAdList as $value) { 
                    array_push($AssignedAdId, $value['AdId']);
                }
                $CampaignAds = $request->CampaignAds;
                /*$adsSelected = '';
                foreach ($CampaignAds as $value) {
                    if(in_array($value, $AssignedAdId)){
                        $AdName = Ad::find($value);
                        $adsSelected .= $AdName->Title.', ';
                    }
                }
                if($adsSelected != ''){
                    $adsSelected = rtrim($adsSelected, " ,");
                    return response()->json([
                        "IsSuccess" => false,
                        "Message" => "Selected ads are already assigned to another campaign. Please select other ads to create a campaign.",
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200);     
                }*/

                $Campaign = new Campaign(); 
                $Campaign->UserId = $UserId;
                $Campaign->CampaignName = $request->CampaignName;
                $Campaign->CampaignTypeId = $request->CampaignTypeId;
                $Campaign->RevenueModelId = $request->RevenueModelId;
                $Campaign->IsDeleted = 1;
                $Campaign->save(); 

                $CampaignAds = $request->CampaignAds;
                foreach ($CampaignAds as $value) {
                    CampaignAdList::create([
                        'CampaignId' => $Campaign->CampaignId,
                        'AdId' => $value,
                        'UserId' => $UserId
                    ]);
                }
                return response()->json([
                    "IsSuccess" => true,
                    "Message" => "Campaign created successfully.",
                    "TotalCount" => 0,
                    "Data" => array("Campaign" => $Campaign)
                ], 200);                
            }
        }
        else
        {
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Token not found.',
                "TotalCount" => 0,
                'Data' => []
            ], 200);
        }
    }

    public function CreateCampaignType(Request $request)
    { 
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token'));
        if($UserId){
            $validator = Validator::make($request->all(), [
                'Type'  => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Campaign type is required.',
                    "TotalCount" => 1,
                    "Data" => []
                ], 200);
            }

            $Campaign = new CampaignTypeMaster();
            $Campaign->Type = $request->Type;
            $Campaign->Description = $request->Description;
            $Campaign->CreatedBy = $UserId;
            $Campaign->save();

            $CampaignList = CampaignTypeMaster::where('CreatedBy', $UserId)->where('IsActive',1)->orderBy('Type')->get();
            return response()->json([
                "IsSuccess" => true,
                "Message" => "Campaign created successfully.",
                "TotalCount" => 0,
                "Data" => array("CampaignTypeList" => $CampaignList)
            ], 200);
        }
        else
        {
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Token not found.',
                "TotalCount" => 0,
                'Data' => []
            ], 200);
        }
    }

    public function CampaignView(Request $request)
    { 
      try{
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token')); 
        if($UserId){
            $Campaign = Campaign::where(['CampaignId' => $request->CampaignId, 'UserId' => $UserId, 'IsDeleted' => 1])->first();
            if($Campaign){
                $Campaign['CampaignIdEncrypted'] = encrypt($Campaign->CampaignId);
                $Campaign['UserIdEncrypted'] = encrypt($Campaign->UserId);
                /*$CampaignAdList = CampaignAdList::with('ad.Brand', 'ad.Type', 'ad.Size', 'ad.Language', 'ad.Country')->whereHas('Ad', function($AdEnable){
                        $AdEnable->where('IsActive', 1);
                    })->where('CampaignId',$Campaign->CampaignId)->where('IsDeleted', 0)->orderBy('CampaignAddId', 'desc')->get();*/
                $CampaignAdList = CampaignAdList::with('ad.Brand', 'ad.Type', 'ad.Size', 'ad.Language', 'ad.Country')->where('CampaignId',$Campaign->CampaignId)->where('IsDeleted', 0)->orderBy('CampaignAddId', 'desc')->get();

                $adsSelected = [];
                foreach ($CampaignAdList as  $value) {
                    $var = [
                        'AdId' => $value['ad']['AdId'],
                        'Title' => $value['ad']['Title'],
                        'BannerImage' => env('STORAGE_URL').'app/adds/'.$value['ad']['BannerImage'],
                        'LandingPageURL' => $value['ad']['LandingPageURL'],
                        'Brand' => $value['ad']['brand']['Title'],
                        'Type' => $value['ad']['type']['Title'],
                        'Size' => $value['ad']['size']['Width'].'*'.$value['ad']['size']['Height'],
                        'Width' => $value['ad']['size']['Width'],
                        'Height' => $value['ad']['size']['Height'],
                        'Language' => $value['ad']['language']['LanguageName'],
                        'IsAllCountrySelected' => $value['ad']['IsAllCountrySelected'],
                        'CountryList' => $value['ad']['CountryList'],
                        'AdTrackingId' => $value['ad']['AdTrackingId'],
                        'IsActive' => $value['ad']['IsActive'],
                        'AdTrackingIdEncrypted' => encrypt($value['ad']['AdTrackingId']),
                    ];
                    array_push($adsSelected, $var);
                } 
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Campaign view.',
                    "TotalCount" => $CampaignAdList->count(),
                    "Data" => array('Campaign' => $Campaign, 'CampaignAdLists' => $adsSelected )
                ], 200); 
            }
            else { 
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Campaign not found.',
                    "TotalCount" => 0,
                    "Data" => []
                ], 200);
            }
        }
        else
        {
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Token not found.',
                "TotalCount" => 0,
                'Data' => []
            ], 200);
        }
      }
      catch(exception $e)
      {
          $res = [
              'IsSuccess'=>false,
              'Message'=>$e,
              'TotalCount' => 0,
              'Data' => []
          ];
      }
      return response()->json($res);
    }

    public function CampaignEdit(Request $request)
    { 
      try{
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token'));
        if($UserId){ 
          $Campaign = Campaign::where(['CampaignId' => $request->CampaignId, 'UserId' => $UserId])->first();
          if($Campaign)
          {
            $user_avaliable = UserRevenueType::where(['UserId' => $UserId])->count();
            $revenumodeldata = [];
            if($user_avaliable >= 1)
            {
              $Revune = UserRevenueType::where(['UserId' => $UserId])->select('RevenueModelId')->distinct()->get();
              if($Revune->count() >= 1)
              {
                $revenuemodel = RevenueModel::whereIn('RevenueModelId',$Revune)->orderBy('RevenueModelName')->get();
                foreach ($revenuemodel as $rw)
                {
                  $var = [
                    'RevenueModelId' => $rw->RevenueModelId,
                    'RevenueModelName' => $rw->RevenueModelName,
                    'IsActive' => $rw->IsActive
                  ];
                  array_push($revenumodeldata, $var);
                }
              }
            }

            $CampaignAdList = CampaignAdList::where('CampaignId',$Campaign->CampaignId)->where('IsDeleted', 0)->get();
            $caIds = [];
            foreach ($CampaignAdList as $value) { 
              $caIds[] = $value->AdId;
            }
            $CampaignId = $request->CampaignId;
            $assignAdList = CampaignAdList::where('UserId',$UserId)->where('CampaignId', '!=', $CampaignId)->whereHas('Ad', function($AdEnable){
                  $AdEnable->where('IsActive', 1);
                })->whereHas('Campaign', function($qr){
                  $qr->where('IsDeleted', 1);
                })->pluck('AdId');
            $UserBrand = UserAdBrand::where('UserId',$UserId)->pluck('AdBrandId');
            $UserType  = UserAdType::where('UserId',$UserId)->pluck('AdTypeId');

            $ads = Ad::whereHas('AffiliatAds', function($qr) use($UserId, $assignAdList){
                $qr->where('UserId',$UserId);
              })->orWhere('IsPublic', 1)->whereIn('AdBrandId', $UserBrand)->whereIn('AdTypeId', $UserType)->orderBy('AdId', 'desc');

            if(isset($request->AdsFilterByAdTypeId) && $request->AdsFilterByAdTypeId != ''){ 
              $AdTypeId = $request->AdsFilterByAdTypeId;
              $ads->whereHas('Type', function($qr) use($AdTypeId){
                $qr->where('AdTypeId', $AdTypeId);
              });
            }
            if(isset($request->AdsFilterByAdSizeId) && $request->AdsFilterByAdSizeId != ''){ 
              $AdSizeId = $request->AdsFilterByAdSizeId;
              $ads->whereHas('Size', function($qr) use($AdSizeId){
                $qr->where('AdSizeId', $AdSizeId);
              });
            }
            if(isset($request->AdsFilterByLanguageId) && $request->AdsFilterByLanguageId != ''){
              $LanguageId = $request->AdsFilterByLanguageId;
              $ads->whereHas('Language', function($qr) use($LanguageId){
                $qr->where('LanguageId', $LanguageId);
              });
            }
            $ads = $ads->with('Brand','Type','Size','Language','Country')->get();

            $adsSelected = [];
            foreach ($ads as  $value) {
              $adCounts = CampaignAdList::where(['AdId' => $value['AdId'], 'CampaignId' => $CampaignId])->first();
              if($adCounts['AdClicks'] > 0)
                  $AdDeletable = false;
              else
                  $AdDeletable = true;
              $IsSelected = '';
              if(in_array($value['AdId'], $caIds)){
                  $IsSelected = true;
              }else{
                  $IsSelected = false;
              }
              $var = [
                'AdId' => $value['AdId'],
                'Title' => $value['Title'],
                'BannerImage' => env('STORAGE_URL').'app/adds/'.$value['BannerImage'],
                'LandingPageURL' => $value['LandingPageURL'],
                'Brand' => $value['brand']['Title'],
                'Type' => $value['type']['Title'],
                'Size' => $value['size']['Width'].'*'.$value['size']['Height'],
                'Language' => $value['language']['LanguageName'],
                'CountryCode' => $value['country']['nicename'],
                'URL' => $value['LandingPageURL'],
                'IsActive' => $value['IsActive'],
                'IsSelect' => $IsSelected,
                'IsDeletable' => $AdDeletable
              ];
              array_push($adsSelected, $var);
            } 
            usort($adsSelected, function ($a, $b) {
              return $b['IsSelect'] - $a['IsSelect'];
            });
            $CampaignTypes = CampaignTypeMaster::where('CreatedBy', $UserId)->where('IsActive',1)->orderBy('Type')->get();
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Campaign edit.',
              "TotalCount" => $ads->count(),
              "Data" => array(
                'Campaign'=>$Campaign,
                'CampaignType'=>$CampaignTypes,
                'CommissionType'=>$revenumodeldata==''?'':$revenumodeldata,
                'CampaignAdLists'=>$adsSelected
              )
            ], 200); 
          }
          else { 
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Campaign not found.',
              "TotalCount" => 0,
              "Data" => []
            ], 200); 
          }
        }
        else
        {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Token not found.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
      }
      catch(exception $e)
      {
        $res = [
          'IsSuccess'=>false,
          'Message'=>$e,
          'TotalCount' => 0,
          'Data' => null
        ];
      }
      return response()->json($res);
    }

    public function CampaignList(Request $request)
    { 
      try{
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token'));
        if($UserId){ 
          $CampaignList = Campaign::with('CampaignType', 'CommissionType')->where(['UserId'=>$UserId, 'IsDeleted' => 1])->orderBy('CampaignId', 'desc')->get();
          if(!empty(json_decode($CampaignList))){ 
            $adsSelected = [];
            foreach ($CampaignList as $value) { 
              $adsClicks = CampaignAdList::where('CampaignId', $value['CampaignId'])->sum('AdClicks');
              $adsImpressions = CampaignAdList::where('CampaignId', $value['CampaignId'])->sum('Impressions');
              $CampaignAdList = CampaignAdList::whereHas('Ad', function($AdEnable){
                        $AdEnable->where('IsActive', 0);
                    })->where('CampaignId', $value['CampaignId'])->where('IsDeleted', 0)->count();
              if($CampaignAdList>0)
                $IsAdDesable = true;
              else
                $IsAdDesable = false;
              if($adsClicks == 0 && $adsImpressions == 0)
                  $IsDeletable = true;
              else
                  $IsDeletable = false;
              $var = [
                'CampaignId' => $value['CampaignId'],
                'CampaignName' => $value['CampaignName'],
                'CampaignType' => $value['CampaignType']['Type'],
                'CommissionType' => $value['CommissionType']['RevenueModelName'],
                'IsDeletable' => $IsDeletable,
                'HaveDisabledAds' => $IsAdDesable,
                'adsClicks' => $adsClicks,
              ];
              array_push($adsSelected, $var);
            }
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Campaign list getting successfully.',
              "TotalCount" => $CampaignList->count(),
              "Data" => array('CampaignList' => $adsSelected)
            ], 200);
          }
          else { 
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Campaign not found.',
              "TotalCount" => 0,
              "Data" => array('CampaignList' => [])
            ], 200); 
          }
        }
        else
        {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Token not found.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
      }
      catch(exception $e)
      {
        $res = [
          'IsSuccess' => false,
          'Message' => $e,
          'TotalCount' => 0,
          'Data' => null
        ];
      }
      return response()->json($res);
    }

    public function CampaignDelete(Request $request)
    { 
        try{
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));
            if($UserId){
                $Campaign = Campaign::where(['UserId'=>$UserId, 'CampaignId'=>$request->CampaignId])->first();
                if($Campaign){                    
                    $adsClicks = CampaignAdList::where('CampaignId', $Campaign->CampaignId)->sum('AdClicks');
                    $adsImpressions = CampaignAdList::where('CampaignId', $Campaign->CampaignId)->sum('Impressions');
                    // if($adsClicks == 0 && $adsImpressions == 0){
                        Campaign::where('CampaignId',$Campaign->CampaignId)->update(['IsDeleted' => 0]);
                        return response()->json([
                            'IsSuccess' => true,
                            'Message' => 'Campaign deleted successfully.',
                            "TotalCount" => 0,
                            "Data" => []
                        ], 200);
                    // }else{
                    //     return response()->json([
                    //         'IsSuccess' => false,
                    //         'Message' => 'Campaign is not deletable.',
                    //         "TotalCount" => 0,
                    //         "Data" => []
                    //     ], 200);
                    // }
                }
                else { 
                    return response()->json([
                        'IsSuccess' => true,
                        'Message' => 'Campaign not found.',
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200); 
                }
            }
            else
            {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        }
        catch(exception $e)
        {
            $res = [
                'IsSuccess'=>false,
                'Message'=>$e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }

    public function CampaignRevenueType(Request $request)
    { 
        try{
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));
            if($UserId) 
            {
                $user_avaliable = UserRevenueType::where(['UserId' => $UserId])->count();
                $revenumodeldata = [];
                if ($user_avaliable >= 1) 
                {
                    $Revune = UserRevenueType::where(['UserId' => $UserId])->select('RevenueModelId')->distinct()->get();
                    if($Revune->count() >= 1)
                    {
                        $revenuemodel = RevenueModel::whereIn('RevenueModelId',$Revune)->orderBy('RevenueModelName')->get();
                        foreach ($revenuemodel as $rw)
                        {
                            $var = [
                                'RevenueModelId' => $rw->RevenueModelId,
                                'RevenueModelName' => $rw->RevenueModelName,
                                'IsActive' => $rw->IsActive
                            ];
                            array_push($revenumodeldata,$var);
                        }

                        return response()->json([
                            'IsSuccess' => true,
                            'Message' => 'Affiliate revenue model list.',
                            "TotalCount" => $revenuemodel->count(),
                            "Data" => array('RevenueModel' => $revenumodeldata)
                        ], 200);
                    }
                    else{ 
                        return response()->json([
                            'IsSuccess' => true,
                            'Message' => 'Revenue model not found.',
                            "TotalCount" => 0,
                            "Data" => []
                        ], 200);
                    }
                }
                else {
                    return response()->json([
                        'IsSuccess' => true,
                        'Message' => 'Revenue model not assign.',
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200);
                }
            }
            else
            {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        }
        catch(exception $e)
        {
            $res = [
                'IsSuccess'=>false,
                'Message'=>$e,
                'TotalCount' => 0,
                'Data' => []
            ];
        }
        return response()->json($res);
    }

    public function AssignAddToCampaigns(Request $request)
    {
        try
        {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));
            if($UserId){
                $validator = Validator::make($request->all(), [
                    // 'CampaignIds'  => 'required',
                    'AdId'  => 'required',
                ]);
                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'Something went wrong.',
                        "TotalCount" => count($validator->errors()),
                        "Data" => array('Error' => $validator->errors())
                    ], 200);
                }
                $dt = CampaignAdList::where('UserId',$UserId)->where('AdId',$request->AdId)->select('CampaignId')->get(); 
                if($dt->count()>=1)
                {
                    $camp = CampaignAdList::where('UserId',$UserId)->WhereNotIn('CampaignId',$request->CampaignIds)->where('AdId',$request->AdId)->select('CampaignId')->get();
                    CampaignAdList::whereIn('CampaignId',$camp)->where('AdId',$request->AdId)->update([ 'IsDeleted'=> 1]);
                    CampaignAdList::where('UserId',$UserId)->WhereIn('CampaignId',$request->CampaignIds)
                        ->where('AdId',$request->AdId)->update([
                           'IsDeleted'=>0
                        ]);
                }
                $Campaign = '';                
                foreach ($request->CampaignIds as $rw)
                {
                    $Campaign = CampaignAdList::query()->updateOrCreate([
                        'CampaignId' => $rw,
                        'AdId' => $request->AdId,
                        'UserId' => $UserId
                    ]);
                }

                return response()->json([
                    "IsSuccess" => true,
                    "Message" => "Ad into campaigns updated successfully.",
                    "TotalCount" => 0,
                    "Data" => array("CampaignAdList" => $Campaign)
                ], 200);
            }
            else
            {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        }
        catch(exception $e)
        {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
            return response()->json($res,200);
        }
    }

    public function GetCampaignListForAdserver(Request $request)
    {
        try
        {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));
            if($UserId){
                $validator = Validator::make($request->all(), [
                    'AdId'  => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'Something went wrong.',
                        "TotalCount" => count($validator->errors()),
                        "Data" => array('Error' => $validator->errors())
                    ], 200);
                }

                $dt = CampaignAdList::where('UserId',$UserId)->where('AdId',$request->AdId)->select('CampaignId')->where('IsDeleted', 0)->get();

                $camp = Campaign::with('CampaignType')->where('UserId',$UserId)->where('IsDeleted',1)->WhereNotIn('CampaignId',$dt)->get();
                $camp_in = Campaign::with('CampaignType')->where('UserId',$UserId)->where('IsDeleted',1)->WhereIn('CampaignId',$dt)->get();

                    $Campnotin = [];
                    foreach ($camp as $rw)
                    {
                        $var1 = [
                    'CampaignId'=>$rw['CampaignId'],
                    'UserId'=>$rw['UserId'],
                    'CampaignName'=>$rw['CampaignName'],
                    'CampaignTypeId'=>$rw['CampaignTypeId'],
                    'CampaignType'=>$rw['campaigntype']['Type'],
                    'RevenueModelId'=>$rw['RevenueModelId'],
                    'IsDeleted'=>$rw['IsDeleted'],
                            'IsSelected'=>0
                                ];
                        array_push($Campnotin,$var1);
                    }

                $Campin = [];

                foreach ($camp_in as $rw1) {
                    $var2 = [
                        'CampaignId' => $rw1['CampaignId'],
                        'UserId' => $rw1['UserId'],
                        'CampaignName' => $rw1['CampaignName'],
                        'CampaignTypeId' => $rw1['CampaignTypeId'],
                        'CampaignType' => $rw1['campaigntype']['Type'],
                        'RevenueModelId' => $rw1['RevenueModelId'],
                        'IsDeleted' => $rw1['IsDeleted'],
                        'IsSelected' => 1
                            ];
                    array_push($Campin,$var2);
                }
                        $combine_data = array_merge($Campnotin,$Campin);

                return response()->json([
                    "IsSuccess" => true,
                    "Message" => "Show campaign successfully.",
                    "TotalCount" => count($combine_data),
                    "Data" => array("Campaign" => $combine_data)
                ], 200);
            }
            else
            {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }

        }
        catch(exception $e)
        {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
            return response()->json($res,200);
        }
    }

    public function CampaignStatistics(Request $request)
    { 
        try{
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));
            if($UserId){ 
                $validator = Validator::make($request->all(), [
                  'CampaignId'  => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'The campaign id field is required.',
                        "TotalCount" => count($validator->errors()),
                        "Data" => array('Error' => $validator->errors())
                    ], 200);
                }
                $Campaign = Campaign::where('CampaignId', $request->CampaignId)->where('UserId', $UserId)->first();
                $UserDetails = User::find($UserId);
                if($Campaign){
                    $CampaignAdList = CampaignAdList::where('CampaignId', $Campaign->CampaignId)->where('IsDeleted', 0)->pluck('CampaignAddId');
                    // 1.CampaignsClicks
                    $AdClicks = CampaignAdList::where('CampaignId', $Campaign->CampaignId)->sum('AdClicks');
                    // 2.Impressions
                    $Impressions = CampaignAdList::where('CampaignId', $Campaign->CampaignId)-> sum('Impressions'); 
                    // 4.Leads
                    $Leads = Lead::where('CampaignId', $Campaign->CampaignId);
                    $LeadList = Lead::where('CampaignId', $Campaign->CampaignId)->pluck('LeadId');
                    // 5.LeadsQualified
                    $LeadsQualified = Lead::where('CampaignId', $Campaign->CampaignId)->whereHas('LeadStatus', function($qr){
                        $qr->where('IsValid', 1);
                    });
                    // 6.ConvertedAccounts
                    $NewAccount = Lead::where('CampaignId', $Campaign->CampaignId)->where('IsConverted', 1);
                    // 7.QualifiedAccount
                    $RevenueModel = RevenueModel::where('RevenueModelId', $Campaign->RevenueModelId)->where('RevenueTypeId', 4)->first();
                    if($RevenueModel){ 
                        $RevenueModelLog = RevenueModelLog::where('RevenueModelId', $RevenueModel['RevenueModelId'])->pluck('RevenueModelLogId');
                        $QualifiedAccount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLog); 
                    }else{
                        $QualifiedAccount = 0;
                    } 
                    // 10.Commission count
                    $Commission = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('PaymentStatus',1)->where('UserBonusId', '=', null)->where('UserSubRevenueId', '=', null);

                    if(isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
                        $from = $request->DateForm;
                        $to = $request->DateTo;
                        // 1.CampaignsClicks
                        $AdClicks = CampaignAdClick::whereIn('CampaignAddId', $CampaignAdList)->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to)->count();
                        // 2.Impressions
                        $Impressions = CampaignAdImpression::whereIn('CampaignAddId', $CampaignAdList)->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to)->count();
                        // 4.Leads
                        $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
                        // 5.LeadsQualified
                        $LeadsQualified->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
                        // 6.ConvertedAccounts
                        $NewAccount->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
                        // 7.QualifiedAccount
                        if($RevenueModel)
                            $QualifiedAccount->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
                        // 10.Commission count
                        $Commission->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); 
                    }
                    // 3.CTR
                    if($Impressions == 0)
                        $CTR = 0;
                    else 
                        $CTR = $AdClicks/$Impressions;
                    // 4.Leads
                    $Leads = $Leads->count(); 
                    // 5.LeadsQualified
                    $LeadsQualified = $LeadsQualified->count();
                    // 6.ConvertedAccounts
                    $NewAccount = $NewAccount->count();
                    // 7.QualifiedAccount
                    if($RevenueModel)
                        $QualifiedAccount = $QualifiedAccount->count();

                    if($UserDetails->CurrencyId == 1)
                        $Commission = $Commission->sum('USDAmount') + $Commission->sum('SpreadUSDAmount');
                    else if($UserDetails->CurrencyId == 2)
                        $Commission = $Commission->sum('AUDAmount') + $Commission->sum('SpreadAUDAmount');
                    else if($UserDetails->CurrencyId == 3)
                        $Commission = $Commission->sum('EURAmount') + $Commission->sum('SpreadEURAmount');

                    return response()->json([
                        'IsSuccess' => true,
                        'Message' => 'Campaign statistics.',
                        "TotalCount" => 1,
                        "Data" => array('Clicks'=>$AdClicks, 'AdDisplays'=>$Impressions, 'CTR'=>round($CTR,2), 'Leads'=>$Leads, 'LeadsQualified'=>$LeadsQualified, 'NewAccount'=>$NewAccount, 'QualifiedAccount' => $QualifiedAccount, 'Commission' => round($Commission,4) )
                    ], 200);
                }else{
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'Campaign not found.',
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200);                    
                } 
            }
            else
            {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        }
        catch(exception $e)
        {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }

    public function AffiliateCampaignTypeList(Request $request)
    { 
      try{
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token'));
        if($UserId){ 
          $CampaignTypes = CampaignTypeMaster::where('CreatedBy', $UserId)->where('IsActive',1)->orderBy('Type')->get();
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Affiliate campaign types list',
            "TotalCount" => $CampaignTypes->count(),
            "Data" => array('CampaignTypes' => $CampaignTypes)
          ], 200);
        }
        else
        {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Token not found.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
      }
      catch(exception $e)
      {
        $res = [
          'IsSuccess'=>false,
          'Message'=>$e,
          'TotalCount' => 0,
          'Data' => null
        ];
      }
      return response()->json($res);
    }

}
