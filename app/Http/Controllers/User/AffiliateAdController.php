<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\User;
use App\Lead;
use App\UserRevenuePayment;
use App\UserToken;
use App\Campaign;
use App\CampaignAdList;
use App\CampaignTypeMaster;
use App\UserRevenueType;
use App\UserSubRevenue;
use App\RevenueModel;
use App\RevenueModelLog;
use App\UserAdBrand;
use App\UserAdType;
use App\Ad;
use App\CountryMaster;
use Laravel\Lumen\Routing\Controller as BaseController;

class AffiliateAdController extends BaseController
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

    public function AdListCampaign(Request $request)
    {
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token'));
        if ($UserId) {
            $user_avaliable = UserRevenueType::where(['UserId' => $UserId])->count();
            $revenumodeldata = [];
            if ($user_avaliable >= 1) {
                $Revune = UserRevenueType::where(['UserId' => $UserId])->whereNotIn('RevenueTypeId', [8, 7])->select('RevenueModelId')->distinct()->get();
                if ($Revune->count() >= 1) {
                    $revenuemodel = RevenueModel::whereIn('RevenueModelId', $Revune)->get();
                    foreach ($revenuemodel as $rw) {
                        $var = [
                            'RevenueModelId' => $rw->RevenueModelId,
                            'RevenueModelName' => $rw->RevenueModelName,
                            'IsActive' => $rw->IsActive
                        ];
                        array_push($revenumodeldata, $var);
                    }
                }
            }

            $assignAdList = CampaignAdList::where('UserId', $UserId)
                ->whereHas('Campaign', function ($qr) {
                    $qr->where('IsDeleted', 1);
                })->pluck('AdId');
            $UserBrand = UserAdBrand::where('UserId', $UserId)->pluck('AdBrandId');
            $UserType  = UserAdType::where('UserId', $UserId)->pluck('AdTypeId');
            $ads = Ad::orderBy('AdId', 'desc')->whereHas(
                'AffiliatAds',
                function ($qr) use ($UserId, $assignAdList) {
                    $qr->where('UserId', $UserId);
                }
            )->orWhere('IsPublic', 1)->whereIn('AdBrandId', $UserBrand)->whereIn('AdTypeId', $UserType)->where('IsActive', 1);

            if (isset($request->AdsFilterByAdTypeId) && $request->AdsFilterByAdTypeId != '') {
                $AdTypeId = $request->AdsFilterByAdTypeId;
                $ads->whereHas('Type', function ($qr) use ($AdTypeId) {
                    $qr->where('AdTypeId', $AdTypeId);
                });
            }
            if (isset($request->AdsFilterByAdSizeId) && $request->AdsFilterByAdSizeId != '') {
                $AdSizeId = $request->AdsFilterByAdSizeId;
                $ads->whereHas('Size', function ($qr) use ($AdSizeId) {
                    $qr->where('AdSizeId', $AdSizeId);
                });
            }
            if (isset($request->AdsFilterByLanguageId) && $request->AdsFilterByLanguageId != '') {
                $LanguageId = $request->AdsFilterByLanguageId;
                $ads->whereHas('Language', function ($qr) use ($LanguageId) {
                    $qr->where('LanguageId', $LanguageId);
                });
            }
            $ads = $ads->with('Brand', 'Type', 'Size', 'Language', 'Country')->get();

            $adsSelected = [];
            if ($ads->count() == 0) {
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Not found ad list.',
                    "TotalCount" => $ads->count(),
                    "Data" => array('CampaignType' => [], 'CommissionType' => [], 'Ads' => [])
                ], 200);
            }
            foreach ($ads as  $value) {
                $var = [
                    'AdId' => $value['AdId'],
                    'Title' => $value['Title'],
                    'BannerImage' => env('STORAGE_URL') . 'app/adds/' . $value['BannerImage'],
                    'LandingPageURL' => $value['LandingPageURL'],
                    'Brand' => $value['brand']['Title'],
                    'Type' => $value['type']['Title'],
                    'Size' => $value['size']['Width'] . '*' . $value['size']['Height'],
                    'Language' => $value['language']['LanguageName'],
                    'CountryCode' => $value['country']['CountryCode'],
                    'URL' => $value['LandingPageURL'],
                    'AdTrackingId' => $value['AdTrackingId'],
                ];
                array_push($adsSelected, $var);
            }
            $CampaignTypes = CampaignTypeMaster::where('CreatedBy', $UserId)->where('IsActive', 1)->orderBy('Type')->get();

            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Affiliate ads listed successfully.',
                "TotalCount" => $ads->count(),
                "Data" => [
                    'CampaignType' => $CampaignTypes,
                    'CommissionType' => $revenumodeldata == '' ? '' : $revenumodeldata,
                    'Ads' => $adsSelected
                ]
            ], 200);
        } else {
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Token not found.',
                "TotalCount" => 0,
                'Data' => []
            ], 200);
        }
    }

    public function GetAdsList(Request $request)
    {
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token'));
        if ($UserId) {
            $user_avaliable = UserRevenueType::where(['UserId' => $UserId])->count();
            $revenumodeldata = [];
            if ($user_avaliable >= 1) {
                $Revune = UserRevenueType::where(['UserId' => $UserId])->select('RevenueModelId')->distinct()->get();
                if ($Revune->count() >= 1) {
                    $revenuemodel = RevenueModel::whereIn('RevenueModelId', $Revune)->get();
                    foreach ($revenuemodel as $rw) {
                        $var = [
                            'RevenueModelId' => $rw->RevenueModelId,
                            'RevenueModelName' => $rw->RevenueModelName,
                            'IsActive' => $rw->IsActive
                        ];
                        array_push($revenumodeldata, $var);
                    }
                }
            }

            $assignAdList = CampaignAdList::where('UserId', $UserId)
                ->whereHas('Campaign', function ($qr) {
                    $qr->where('IsDeleted', 1);
                })->pluck('AdId');
            $selectedIds = [];
            foreach ($assignAdList as $value2) {
                $selectedIds[] = $value2;
            }

            $UserBrand = UserAdBrand::where('UserId', $UserId)->pluck('AdBrandId');
            $UserType = UserAdType::where('UserId', $UserId)->pluck('AdTypeId');

            /* $assignAdList = CampaignAdList::where('UserId',$UserId)
                ->whereHas('Campaign', function($qr){
                    $qr->where('IsDeleted', 1);
                })->pluck('AdId');
            $UserBrand = UserAdBrand::where('UserId',$UserId)->pluck('AdBrandId');
            $UserType  = UserAdType::where('UserId',$UserId)->pluck('AdTypeId');

            $ads = Ad::with('Brand','Type','Size','Language','Country')
                    ->whereHas('AffiliatAds', function($qr) use($UserId, $assignAdList){
                        $qr->where('UserId',$UserId)->whereNotIn('AdId', $assignAdList);
                    })->orWhere('IsPublic', 1)->whereIn('AdBrandId', $UserBrand)->whereIn('AdTypeId', $UserType)->whereNotIn('AdId', $assignAdList)->where('IsActive',1)->orderBy('AdId')->get(); */

            $CreatedAt = $request->AdsFilterByAdDate;
            $AdBrandId = $request->AdsFilterByAdBrandId;
            $AdTypeId = $request->AdsFilterByAdTypeId;
            $AdSizeId = $request->AdsFilterByAdSizeId;
            $CountryId = $request->AdsFilterByAdCountryId;
            $LanguageId = $request->AdsFilterByLanguageId;
            $TimeZoneOffSet = $request->TimeZoneOffSet;
            if ($TimeZoneOffSet == '')
                $TimeZoneOffSet = 0;


            $ads = Ad::with('Brand', 'Type', 'Size', 'Language')->where('IsActive', 1)->whereHas(
                'AffiliatAds',
                function ($qr1) use ($UserId, $AdBrandId, $CreatedAt, $AdTypeId, $AdSizeId, $CountryId, $LanguageId) {
                    $qr1->where('UserId', $UserId)->whereHas(
                        'Ads',
                        function ($qrSearch) use ($AdBrandId, $CreatedAt, $AdTypeId, $AdSizeId, $CountryId, $LanguageId) {
                            if ($CreatedAt != '')
                                $qrSearch->whereDate('CreatedAt', '=', $CreatedAt);
                            if ($AdBrandId != '')
                                $qrSearch->where('AdBrandId', $AdBrandId);
                            if ($AdTypeId != '')
                                $qrSearch->where('AdTypeId', $AdTypeId);
                            if ($AdSizeId != '')
                                $qrSearch->where('AdSizeId', $AdSizeId);
                            if ($CountryId != '')
                                $qrSearch->whereRaw('FIND_IN_SET(' . $CountryId . ',CountryList)')->orWhere(function ($query) use ($AdBrandId, $CreatedAt, $AdTypeId, $AdSizeId, $CountryId, $LanguageId) {
                                    $query->where('IsAllCountrySelected', 1);
                                    if ($CreatedAt != '')
                                        $query->whereDate('CreatedAt', '=', $CreatedAt);
                                    if ($AdBrandId != '')
                                        $query->where('AdBrandId', $AdBrandId);
                                    if ($AdTypeId != '')
                                        $query->where('AdTypeId', $AdTypeId);
                                    if ($AdSizeId != '')
                                        $query->where('AdSizeId', $AdSizeId);
                                    if ($LanguageId != '')
                                        $query->where('LanguageId', $LanguageId);
                                });
                            if ($LanguageId != '')
                                $qrSearch->where('LanguageId', $LanguageId);
                        }
                    );
                }
            )->orWhere('IsPublic', 1)->whereIn('AdBrandId', $UserBrand)->whereIn('AdTypeId', $UserType);

            if (isset($request->AdsFilterByAdDate) && $request->AdsFilterByAdDate != '') {
                $ads->whereDate('CreatedAt', '=', $CreatedAt);
            }
            if (isset($request->AdsFilterByAdBrandId) && $request->AdsFilterByAdBrandId != '') {
                $ads->where('AdBrandId', $AdBrandId);
            }
            if (isset($request->AdsFilterByAdTypeId) && $request->AdsFilterByAdTypeId != '') {
                $ads->where('AdTypeId', $AdTypeId);
            }
            if (isset($request->AdsFilterByAdSizeId) && $request->AdsFilterByAdSizeId != '') {
                $ads->where('AdSizeId', $AdSizeId);
            }
            if (isset($request->AdsFilterByAdCountryId) && $request->AdsFilterByAdCountryId != '') {
                $ads->whereRaw('FIND_IN_SET(' . $CountryId . ',CountryList)')->orWhere(function ($query2) use ($AdBrandId, $CreatedAt, $AdTypeId, $AdSizeId, $CountryId, $LanguageId) {
                    $query2->where('IsAllCountrySelected', 1);
                    if ($CreatedAt != '')
                        $query2->whereDate('CreatedAt', '=', $CreatedAt);
                    if ($AdBrandId != '')
                        $query2->where('AdBrandId', $AdBrandId);
                    if ($AdTypeId != '')
                        $query2->where('AdTypeId', $AdTypeId);
                    if ($AdSizeId != '')
                        $query2->where('AdSizeId', $AdSizeId);
                    if ($LanguageId != '')
                        $query2->where('LanguageId', $LanguageId);
                });
            }
            if (isset($request->AdsFilterByLanguageId) && $request->AdsFilterByLanguageId != '') {
                $ads->where('LanguageId', $LanguageId);
            }
            $ads = $ads->orderBy('AdId', 'desc')->get();

            $adsSelected = [];
            if ($ads->count() == 0) {
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Not found ad list.',
                    "TotalCount" => $ads->count(),
                    "Data" => array('AdsList' => $adsSelected)
                ], 200);
            }
            foreach ($ads as $value) {
                if ($value['IsAllCountrySelected'] == 0) {
                    $cList = explode(',', $value['CountryList']);
                    $CountryListData = CountryMaster::select('CountryName')->whereIn('CountryId', $cList)->pluck('CountryName');
                    $CountryList = '';
                    $i = 0;
                    $len = count($CountryListData);
                    foreach ($CountryListData as $value1) {
                        if ($i == $len - 1) {
                            $CountryList .= $value1;
                        } else {
                            $CountryList .= $value1 . ',';
                        }
                        $i++;
                    }
                } else {
                    $CountryList = '';
                }
                if (in_array($value['AdId'], $selectedIds)) {
                    $CampaignName = CampaignAdList::with('Campaignmany')->where('UserId', $UserId)->where('AdId', $value['AdId'])->whereHas('Campaignmany', function ($cqr) {
                        $cqr->where('IsDeleted', 1);
                    })->where('IsDeleted', 0)->first();
                    $IsSelected = true;

                    $CampaignNameDataList = CampaignAdList::with('Campaignmany')->where('UserId', $UserId)->where('AdId', $value['AdId'])->whereHas('Campaignmany', function ($cqr) {
                        $cqr->where('IsDeleted', 1);
                    })->where('IsDeleted', 0)->get();

                    $sarray = [];
                    $fvar = [];
                    foreach ($CampaignNameDataList as $rw) {
                        foreach ($rw['campaignmany'] as $row) {
                            $sarray[] = $row['CampaignName'];
                        }
                        // array_push($sarray,$var);
                    }
                    $CampNameListData = implode(',', $sarray);

                    /*if($value['CampaignAd']['campaign']){
                        $CampaignName = $value['CampaignAd']['campaign']['CampaignName'];
                    }
                    else{
                        $IsSelected = false;
                        $CampaignName = '';
                    }*/
                } else {
                    $IsSelected = false;
                    $CampNameListData = '';
                    $CampaignName = '';
                }

                // $leaddata = Lead::where('UserId',$UserId)->where('AdId', $value['AdId'])->get();
                $UserDetails = User::find($UserId);

                $NetRevenue = UserRevenuePayment::with('LeadDetail')->where('UserId', $UserId)->where('UserBonusId', null)->where('UserSubRevenueId', null)->where('PaymentStatus', 1);
                $AdsId = $value['AdId'];
                $NetRevenue->whereHas('LeadDetail', function ($qr) use ($AdsId) {
                    $qr->where('AdId', $AdsId);
                });
                $UserSubRevenue = UserRevenuePayment::with('LeadDetail')->where('UserId', $UserId)->where('UserSubRevenueId', '!=', null)->where('USDAmount', '<', 0)->where('PaymentStatus', 1);
                $UserSubRevenue->whereHas('LeadDetail', function ($qr) use ($AdsId) {
                    $qr->where('AdId', $AdsId);
                });
                $AmountDeduct = 0;
                if ($UserDetails->CurrencyId == 1){
                    $NetRevenue = $NetRevenue->sum('USDAmount') + $NetRevenue->sum('SpreadUSDAmount');
                    $AmountDeduct = $UserSubRevenue->sum('USDAmount');
                }
                else if ($UserDetails->CurrencyId == 2){
                    $NetRevenue = $NetRevenue->sum('AUDAmount') + $NetRevenue->sum('SpreadAUDAmount');
                    $AmountDeduct = $UserSubRevenue->sum('AUDAmount');
                }
                else if ($UserDetails->CurrencyId == 3){
                    $NetRevenue = $NetRevenue->sum('EURAmount') + $NetRevenue->sum('SpreadEURAmount');
                    $AmountDeduct = $UserSubRevenue->sum('EURAmount');
                }
                $NetRevenue = round($NetRevenue + $AmountDeduct, 4);

                $adclickdata = CampaignAdList::where('UserId', $UserId)->where('AdId', $value['AdId'])->where('IsDeleted', 0)->sum('AdClicks');
                $adImpressiondata = CampaignAdList::where('UserId', $UserId)->where('AdId', $value['AdId'])->where('IsDeleted', 0)->sum('Impressions');
                $Lead = Lead::where('UserId', $UserId)->where('AdId', $value['AdId'])->count();
                $LeadsQualified = Lead::where('UserId', $UserId)->where('AdId', $value['AdId'])->whereHas('LeadStatus', function ($qr) {
                    $qr->where('IsValid', 1);
                })->count();
                $Newaccount = Lead::where('UserId', $UserId)->where('AdId', $value['AdId'])->where('IsConverted', 1)->count();
                // $Qulifiedaccount = Lead::where('UserId', $UserId)->where('AdId', $value['AdId'])->where('IsConverted', 1)->count();
                // 7.QualifiedAccounts
                $QualifiedAccounts = 0;
                $LeadList = Lead::where('UserId', $UserId)->where('AdId', $value['AdId'])->get();
                foreach ($LeadList as $LeadDetail) {
                    $Campaign = Campaign::find($LeadDetail->CampaignId);
                    $RevenueModel = RevenueModel::where('RevenueModelId', $Campaign->RevenueModelId)->where('RevenueTypeId', 4)->first();
                    if ($RevenueModel) {
                        $RevenueModelLog = RevenueModelLog::where('RevenueModelId', $RevenueModel['RevenueModelId'])->pluck('RevenueModelLogId');
                        $QualifiedAccountPlus = UserRevenuePayment::where('LeadId', $LeadDetail->LeadId)->whereIn('RevenueModelLogId', $RevenueModelLog)->count();
                        $QualifiedAccounts = $QualifiedAccounts + $QualifiedAccountPlus;
                    }
                }

                if ($adImpressiondata == 0 || $adclickdata == 0 || $adImpressiondata == '' || $adclickdata == '')
                    $totalctr = 0;
                else
                    $totalctr = (int) $adclickdata / (int) $adImpressiondata;

                $var = [
                    'AdId' => $value['AdId'],
                    'Title' => $value['Title'],
                    'Brand' => $value['brand']['Title'],
                    'Type' => $value['type']['Title'],
                    'Size' => $value['size']['Width'] . '*' . $value['size']['Height'],
                    'Language' => $value['language']['LanguageName'],
                    'BannerImage' => $this->storage_path . 'app/adds/' . $value['BannerImage'],
                    'LandingPageURL' => $value['LandingPageURL'],
                    'IsAllCountrySelected' => $value['IsAllCountrySelected'],
                    'CountryList' => $CountryList,
                    'CampaignNameList' => $CampNameListData,
                    'Clicks' => (int) $adclickdata == '' ? 0 : (int) $adclickdata,
                    'CTR' => round($totalctr, 2),
                    'Displays' => (int) $adImpressiondata == '' ? 0 : (int) $adImpressiondata,
                    'NetRevenue' => round($NetRevenue, 4),
                    'Lead' => $Lead,
                    'LeadsQualified' => $LeadsQualified,
                    'NewAccount' => $Newaccount,
                    'NewQualifiedAccount' => $QualifiedAccounts,
                    'CreatedAt' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
                    'IsSelected' => $IsSelected,
                ];
                array_push($adsSelected, $var);
            }

            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Affiliate ads listed successfully.',
                "TotalCount" => $ads->count(),
                "Data" => array('AdsList' => $adsSelected)
            ], 200);
        } else {
            return response()->json([
                'IsSuccess' => false,
                'Message' => 'Token not found.',
                "TotalCount" => 0,
                'Data' => []
            ], 200);
        }
    }
}
