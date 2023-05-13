<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Validator;
use App\Ad;
use App\AdAffiliateList;
use App\User;
use App\UserToken;
use App\AdBrandMaster;
use App\AdTypeMaster;
use App\AdSizeMaster;
use App\CampaignAdList;
use App\Campaign;
use App\CampaignAdImpression;
use Laravel\Lumen\Routing\Controller as BaseController;

class AdController extends BaseController
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
        // $this->storage_path = 'https://differenzuat.com/affiliate_api/storage/';
    }

    public function AddUpdateAd(Request $request)
    {
        try {
            $check = new UserToken();
            $log_user = $check->validTokenAdmin($request->header('token'));

            if ($log_user) {
                if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
                    $InputData = json_decode($request->AdInputData);

                    if ($request->hasFile('BannerImage')) {
                        $image = $request->file('BannerImage');
                        $name = 'Banner-' . $log_user->UserId . '' . time() . '.' . $image->getClientOriginalExtension();
                        $destinationPath = storage_path('/app/adds');
                        $image->move($destinationPath, $name);
                        // $full_path = env('STORAGE_URL') . 'storage/app/adds/'.$name;
                    } else {
                        $name = "";
                    }
                    if ($InputData->IsPublic)
                        $IsPublic = 1;
                    else
                        $IsPublic = 0;

                    if (isset($InputData->AdId) && $InputData->AdId != '') {
                        $AdData = Ad::find($InputData->AdId);
                        $image_path = $AdData->BannerImage;
                        if ($request->hasFile('BannerImage')) {
                            $image_data = storage_path("app/adds/{$AdData->BannerImage}");
                            if (File::exists($image_data)) {
                                unlink($image_data);
                            }
                        }

                        $AdData->Title = $InputData->Title;
                        if ($request->hasFile('BannerImage')) {
                            $AdData->BannerImage = $name;
                        }
                        $AdData->LandingPageURL = $InputData->LandingPageURL;
                        $AdData->AdBrandId = $InputData->AdBrandId;
                        $AdData->AdTypeId = $InputData->AdTypeId;
                        $AdData->AdSizeId = $InputData->AdSizeId;
                        $AdData->LanguageId = $InputData->LanguageId;
                        $AdData->IsAllCountrySelected = $InputData->IsAllCountrySelected;
                        $AdData->CountryList = $InputData->CountryList;
                        $AdData->IsPublic = $IsPublic;
                        $AdData->UpdatedBy = $log_user->UserId;
                        $AdData->save();
                        $id = $AdData->AdId;
                        AdAffiliateList::where('AdId', $id)->delete();
                        $Message = 'Advertisement updated successfully.';
                    } else {
                        $create = Ad::create([
                            'Title' => $InputData->Title,
                            'BannerImage' => $name,
                            'LandingPageURL' => $InputData->LandingPageURL,
                            'AdBrandId' => $InputData->AdBrandId,
                            'AdTypeId' => $InputData->AdTypeId,
                            'AdSizeId' => $InputData->AdSizeId,
                            'LanguageId' => $InputData->LanguageId,
                            'IsAllCountrySelected' => $InputData->IsAllCountrySelected,
                            'CountryList' => $InputData->CountryList,
                            'IsPublic' => $IsPublic,
                            'CreatedBy' => $log_user->UserId
                        ]);
                        $id = $create->AdId;
                        $Message = 'Advertisement added successfully.';

                        $strRandom = str_random(6);
                        $AdTrackingId = time() . $id . $strRandom;
                        Ad::where('AdId', $id)->update(['AdTrackingId' => $AdTrackingId]);
                    }

                    if ($InputData->IsPublic == 0) {
                        $new_array = $InputData->AffiliateUserIds;
                        foreach ($new_array as $rw) {
                            AdAffiliateList::create([
                                'AdId' => $id,
                                'UserId' => $rw,
                                'CreatedBy' => $log_user->UserId
                            ]);
                        }
                    }
                    $res = [
                        'IsSuccess' => true,
                        'Message' => $Message,
                        'TotalCount' => 0,
                        'Data' => null
                    ];
                    return response()->json($res, 200);
                }
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'Invalid token.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res, 200);
            } else {
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res, 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
            return response()->json($res, 200);
        }
        return response()->json($res, 200);
    }

    public function AdsList(Request $request)
    {
        try {
            $check = new UserToken();

            $log_user = $check->validTokenAdmin($request->header('token'));
            if ($log_user) {
                if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {

                    /*$adds = DB::select("SELECT a.AdId,a.Title,a.BannerImage,a.LandingPageURL, 
                    adm.Title,atm.Title,asm.Size,lm.LanguageName,cm.CountryName,a.LandingPageURL as URL,IF(a.IsActive=1,'Enable','Disable') as Status,a.IsActive,a.CreatedAT,a.UpdatedAT
                    FROM `ads` a
                    LEFT JOIN ad_brand_masters adm on adm.AdBrandId=a.AdBrandId
                    LEFT JOIN ad_type_masters atm on atm.AdTypeId=a.AdTypeId
                    LEFT JOIN ad_size_masters asm on asm.AdSizeId=a.AdSizeId
                    LEFT JOIN language_masters lm on lm.LanguageId=a.LanguageId
                    LEFT JOIN country_masters cm on cm.CountryId=a.CountryId");*/

                    $adds = Ad::orderBy('AdId', 'desc');
                    if (isset($request->AdsFilterByAdTypeId) && $request->AdsFilterByAdTypeId != '') {
                        $AdTypeId = $request->AdsFilterByAdTypeId;
                        $adds->whereHas('Type', function ($qr) use ($AdTypeId) {
                            $qr->where('AdTypeId', $AdTypeId);
                        });
                    }
                    if (isset($request->AdsFilterByAdSizeId) && $request->AdsFilterByAdSizeId != '') {
                        $AdSizeId = $request->AdsFilterByAdSizeId;
                        $adds->whereHas('Size', function ($qr) use ($AdSizeId) {
                            $qr->where('AdSizeId', $AdSizeId);
                        });
                    }
                    if (isset($request->AdsFilterByLanguageId) && $request->AdsFilterByLanguageId != '') {
                        $LanguageId = $request->AdsFilterByLanguageId;
                        $adds->whereHas('Language', function ($qr) use ($LanguageId) {
                            $qr->where('LanguageId', $LanguageId);
                        });
                    }
                    // $adds = Ad::with(['Brand','Type','Size','Language','Country'])->where('IsActive','=',1)->orderBy('AdId', 'desc')->get();
                    $adds = $adds->with(['Brand', 'Type', 'Size', 'Language', 'Country'])->get();

                    $adsSelected = [];
                    foreach ($adds as  $value) {
                        if ($value['BannerImage'] != '')
                            $BannerImage = $this->storage_path . 'app/adds/' . $value['BannerImage'];
                        else
                            $BannerImage = '';
                        if ($value['IsActive'] == 0)
                            $Status = 'Disable';
                        else
                            $Status = 'Enable';

                        $var = [
                            'AdId' => $value['AdId'],
                            'Title' => $value['Title'],
                            'BannerImage' => $BannerImage,
                            'LandingPageURL' => $value['LandingPageURL'],
                            'Brand' => $value['brand']['Title'],
                            'Type' => $value['type']['Title'],
                            'Size' => $value['size']['Height'] . '*' . $value['size']['Width'],
                            'Language' => $value['language']['LanguageName'],
                            'Country' => $value['country']['CountryName'],
                            'URL' => $value['LandingPageURL'],
                            'Status' => $Status,
                            'IsActive' => $value['IsActive'],
                            'CreatedAT' => (string) $value['CreatedAt'],
                            'UpdatedAT' => (string) $value['UpdatedAt']
                        ];
                        array_push($adsSelected, $var);
                    }

                    $res = [
                        'IsSuccess' => true,
                        'Message' => 'List all advertisement.',
                        'TotalCount' => $adds->count(),
                        'Data' => array('AdList' => $adsSelected)
                    ];
                } else {
                    $res = [
                        'IsSuccess' => false,
                        'Message' => 'You are not admin.',
                        'TotalCount' => 0,
                        'Data' => null
                    ];
                    return response()->json($res, 200);
                }
            } else {
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'Invalid token.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res, 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res, 200);
    }

    public function AdView(Request $request)
    {
        try {
            $check = new UserToken();
            $log_user = $check->validTokenAdmin($request->header('token'));
            if ($log_user) {

                $validator = Validator::make($request->all(), [
                    'AdId' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'Something went wrong.',
                        "TotalCount" => count($validator->errors()),
                        "Data" => array('Error' => $validator->errors())
                    ], 200);
                }

                if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
                    $adData = Ad::with('Brand', 'Type', 'Size', 'Language', 'Country')->find($request->AdId);
                    $IsPublic = ($adData['IsPublic'] == 0) ? 'Private' : 'Public';

                    // $user = AdAffiliateList::with('UserSingle')->where('AdId', '=', $request->AdId)->get();
                    $AdId = $request->AdId;
                    $user = User::with(['AdAffiliate' => function ($qr) use ($AdId) {
                        $qr->where('AdId', '=', $AdId);
                    }])->where('RoleId', 3)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('IsDeleted', 1)->get();
                    $userList = [];
                    foreach ($user as $value) {
                        $IsSelected = 0;
                        if ($value['AdAffiliate']) {
                            $IsSelected = 1;
                        }
                        $arr = [
                            'UserId' => $value['UserId'],
                            'AffiliateName' => $value['FirstName'] . ' ' . $value['LastName'],
                            'EmailId' => $value['EmailId'],
                            'IsSelected' => $IsSelected
                        ];
                        array_push($userList, $arr);
                    }

                    $AdDetails = [
                        'AdId' => $adData['AdId'],
                        'Title' => $adData['Title'],
                        'BannerImage' => $this->storage_path . 'app/adds/' . $adData['BannerImage'],
                        'LandingPageURL' => $adData['LandingPageURL'],
                        'Brand' => $adData['brand']['Title'],
                        'AdBrandId' => $adData['brand']['AdBrandId'],
                        'Type' => $adData['type']['Title'],
                        'AdTypeId' => $adData['type']['AdTypeId'],
                        'Size' => $adData['size']['Width'] . '*' . $adData['size']['Height'],
                        'AdSizeId' => $adData['size']['AdSizeId'],
                        'Language' => $adData['language']['LanguageName'],
                        'LanguageId' => $adData['language']['LanguageId'],
                        'IsAllCountrySelected' => $adData['IsAllCountrySelected'],
                        'CountryList' => $adData['CountryList'],
                        'URL' => $adData['LandingPageURL'],
                        'AdType' => $IsPublic,
                        'IsPublic' => $adData['IsPublic'],
                        'IsActive' => $adData['IsActive'],
                        'CreatedAT' => (string) $adData['CreatedAt'],
                        'UpdatedAT' => (string) $adData['UpdatedAt'],
                        'AssignAffiliateList' => $userList
                    ];
                    $res = [
                        'IsSuccess' => true,
                        'Message' => 'View ad with assign affiliates.',
                        'TotalCount' => 0,
                        'Data' => array('AdDetails' => $AdDetails)
                    ];
                } else {
                    $res = [
                        'IsSuccess' => false,
                        'Message' => 'You are not admin.',
                        'TotalCount' => 0,
                        'Data' => null
                    ];
                    return response()->json($res, 200);
                }
            } else {
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'Invalid token.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res, 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res, 200);
    }

    public function AdEdit(Request $request)
    {
        try {
            $check = new UserToken();
            $log_user = $check->validTokenAdmin($request->header('token'));
            if ($log_user) {

                $validator = Validator::make($request->all(), [
                    'AdId' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'Something went wrong.',
                        "TotalCount" => count($validator->errors()),
                        "Data" => array('Error' => $validator->errors())
                    ], 200);
                }

                if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
                    $AdDetails = Ad::find($request->AdId);
                    $AdAffiliateList = AdAffiliateList::where('AdId', '=', $request->AdId)->get();

                    $userList = [];
                    foreach ($AdAffiliateList as $value) {
                        array_push($userList, $value['UserId']);
                    }

                    $res = [
                        'IsSuccess' => true,
                        'Message' => 'View ad with assign affiliates.',
                        'TotalCount' => 0,
                        'Data' => array('AdDetails' => $AdDetails, 'AssignAffiliateList' => $userList)
                    ];
                } else {
                    $res = [
                        'IsSuccess' => false,
                        'Message' => 'You are not admin.',
                        'TotalCount' => 0,
                        'Data' => null
                    ];
                    return response()->json($res, 200);
                }
            } else {
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'Invalid token.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res, 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res, 200);
    }

    public function AdEnableDisable(Request $request)
    {
        try {
            $check = new UserToken();
            $log_user = $check->validTokenAdmin($request->header('token'));
            if ($log_user) {
                if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {

                    $validator = Validator::make($request->all(), [
                        'AdId' => 'required',
                    ]);

                    if ($validator->fails()) {
                        return response()->json([
                            'IsSuccess' => false,
                            'Message' => 'Something went wrong.',
                            "TotalCount" => count($validator->errors()),
                            "Data" => array('Error' => $validator->errors())
                        ], 200);
                    }
                    Ad::where('AdId', $request->AdId)->update([
                        'IsActive' => $request->Status
                    ]);
                    if ($request->Status == 1) {
                        $ActiveMessage = "Advertisement enable successfully.";
                    } else {
                        $ActiveMessage = "Advertisement disable successfully.";
                    }
                    $res = [
                        'IsSuccess' => true,
                        'Message' => $ActiveMessage,
                        'TotalCount' => 0,
                        'Data' => null
                    ];
                } else {
                    $res = [
                        'IsSuccess' => false,
                        'Message' => 'You are not admin.',
                        'TotalCount' => 0,
                        'Data' => null
                    ];
                    return response()->json($res, 200);
                }
            } else {
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'Invalid token.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res, 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res, 200);
    }

    public function AddNewAdBrand(Request $request)
    {
        try {
            $check = new UserToken();
            $User = $check->validTokenAdmin($request->header('Token'));
            if ($User) {
                $validator = Validator::make($request->all(), [
                    'Title'  => 'unique:ad_brand_masters',
                ]);
                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => "Brand already exists.",
                        "TotalCount" => 1,
                        "Data" => []
                    ], 200);
                }
                $request->merge([
                    'IsActive' => 1,
                    'CreatedBy' => $User->UserId
                ]);
                $AdBrand = AdBrandMaster::create($request->all());  // add new record

                $AdBrandList = AdBrandMaster::where('IsActive', 1)->orderBy('Title')->get();

                return response()->json([
                    "IsSuccess" => true,
                    "Message" => "Ad brand created successfully.",
                    "TotalCount" => 0,
                    "Data" => array("AdBrand" => $AdBrandList)
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
            return response()->json($res, 200);
        }
    }

    public function AddNewAdType(Request $request)
    {
        try {
            $check = new UserToken();
            $User = $check->validTokenAdmin($request->header('Token'));
            if ($User) {
                $validator = Validator::make($request->all(), [
                    'Title'  => 'unique:ad_type_masters',
                ]);
                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => "Ad type already exists.",
                        "TotalCount" => 1,
                        "Data" => []
                    ], 200);
                }
                $request->merge([
                    'IsActive' => 1,
                    'CreatedBy' => $User->UserId
                ]);
                $AdType = AdTypeMaster::create($request->all());  // add new record

                $AdTypeList = AdTypeMaster::where('IsActive', 1)->orderBy('Title')->get();

                return response()->json([
                    "IsSuccess" => true,
                    "Message" => "Ad type created successfully.",
                    "TotalCount" => 0,
                    "Data" => array("AdType" => $AdTypeList)
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
            return response()->json($res, 200);
        }
    }

    public function AddNewAdSize(Request $request)
    {
        // return $request->all();
        try {
            $check = new UserToken();
            $User = $check->validTokenAdmin($request->header('Token'));
            if ($User) {
                $validator = Validator::make($request->all(), [
                    'Width'  => 'required|numeric',
                    'Height'  => 'required|numeric',
                ]);
                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'Something went wrong.',
                        "TotalCount" => count($validator->errors()),
                        "Data" => array('Error' => $validator->errors())
                    ], 200);
                }

                $exists = AdSizeMaster::where('Width', $request->Width)->where('Height', $request->Height)->first();
                if (isset($exists) && $exists != null) {
                    return response()->json([
                        "IsSuccess" => false,
                        "Message" => "Ad size already exists.",
                        "TotalCount" => 0,
                        "Data" => []
                    ], 200);
                }

                $request->merge([
                    'IsActive' => 1,
                    'CreatedBy' => $User->UserId
                ]);
                $AdSize = AdSizeMaster::create($request->all());  // add new record

                $AdSizeList = AdSizeMaster::where('IsActive', 1)->orderBy('Width')->get();
                $arr = [];
                foreach ($AdSizeList as $value) {
                    $new = [
                        'AdSizeId' => $value['AdSizeId'],
                        'Size' => $value['Width'] . '*' . $value['Height']
                    ];
                    array_push($arr, $new);
                }

                return response()->json([
                    "IsSuccess" => true,
                    "Message" => "Ad size created successfully.",
                    "TotalCount" => 0,
                    "Data" => array("AdSize" => $arr)
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            return response()->json([
                'IsSuccess' => false,
                'Message' => $e,
                "TotalCount" => 0,
                'Data' => []
            ], 200);
        }
    }

    public function GetAdDetailsByAdId(Request $request)
    {
        // return $request->all();
        try {
            $validator = Validator::make($request->all(), [
                'AdTrackingId' => 'required',
                'CampaignId' => 'required',
                // 'UserTrackingId' => 'required'
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Some input fields is required.',
                    "TotalCount" => count($validator->errors()),
                    "Data" => []
                ], 200);
            }

            try {
                $AdTrackingId = decrypt($request->AdTrackingId);
            } catch (DecryptException $e) {
                $AdDetails = [
                    'AdId' => '',
                    'Title' => '',
                    'BannerImage' => $this->storage_path . 'app/Dummy/no_ad_available.png',
                    'LandingPageURL' => '',
                    'Brand' => '',
                    'AdBrandId' => '',
                    'Type' => '',
                    'AdTypeId' => '',
                    'Size' => '',
                    'AdSizeId' => '',
                    'Language' => '',
                    'LanguageId' => '',
                    'IsAllCountrySelected' => '',
                    'CountryList' => '',
                    'URL' => '',
                    'AdType' => '',
                    'IsPublic' => '',
                    'IsActive' => '',
                    'CreatedAT' => '',
                    'UpdatedAT' => ''
                ];
                $res = [
                    'IsSuccess' => true,
                    'Message' => 'No ad available.',
                    'TotalCount' => 0,
                    'Data' => array('AdDetails' => $AdDetails)
                ];
                return response()->json($res, 200);
            }

            try {
                $CampaignId = decrypt($request->CampaignId);
            } catch (DecryptException $e) {
                $AdDetails = [
                    'AdId' => '',
                    'Title' => '',
                    'BannerImage' => $this->storage_path . 'app/Dummy/no_ad_available.png',
                    'LandingPageURL' => '',
                    'Brand' => '',
                    'AdBrandId' => '',
                    'Type' => '',
                    'AdTypeId' => '',
                    'Size' => '',
                    'AdSizeId' => '',
                    'Language' => '',
                    'LanguageId' => '',
                    'IsAllCountrySelected' => '',
                    'CountryList' => '',
                    'URL' => '',
                    'AdType' => '',
                    'IsPublic' => '',
                    'IsActive' => '',
                    'CreatedAT' => '',
                    'UpdatedAT' => ''
                ];
                $res = [
                    'IsSuccess' => true,
                    'Message' => 'No ad available.',
                    'TotalCount' => 0,
                    'Data' => array('AdDetails' => $AdDetails)
                ];
                return response()->json($res, 200);
            }

            $adData = Ad::with('Brand', 'Type', 'Size', 'Language', 'Country')->where('AdTrackingId', $AdTrackingId)->first();
            // $UserData = User::where('TrackingId', $request->UserTrackingId)->first();

            if ($adData) {
                if ($adData->IsActive == 0) {
                    $AdDetails = [
                        'AdId' => '',
                        'Title' => '',
                        'BannerImage' => $this->storage_path . 'app/Dummy/no_ad_available.png',
                        'LandingPageURL' => '',
                        'Brand' => '',
                        'AdBrandId' => '',
                        'Type' => '',
                        'AdTypeId' => '',
                        'Size' => '',
                        'AdSizeId' => '',
                        'Language' => '',
                        'LanguageId' => '',
                        'IsAllCountrySelected' => '',
                        'CountryList' => '',
                        'URL' => '',
                        'AdType' => '',
                        'IsPublic' => '',
                        'IsActive' => '',
                        'CreatedAT' => '',
                        'UpdatedAT' => ''
                    ];
                    $res = [
                        'IsSuccess' => true,
                        'Message' => 'No ad available.',
                        'TotalCount' => 0,
                        'Data' => array('AdDetails' => $AdDetails)
                    ];
                    return response()->json($res, 200);
                }
                $Campaign = Campaign::find($CampaignId);
                if ($Campaign->IsDeleted == 0) {
                    $AdDetails = [
                        'AdId' => '',
                        'Title' => '',
                        'BannerImage' => $this->storage_path . 'app/Dummy/no_ad_available.png',
                        'LandingPageURL' => '',
                        'Brand' => '',
                        'AdBrandId' => '',
                        'Type' => '',
                        'AdTypeId' => '',
                        'Size' => '',
                        'AdSizeId' => '',
                        'Language' => '',
                        'LanguageId' => '',
                        'IsAllCountrySelected' => '',
                        'CountryList' => '',
                        'URL' => '',
                        'AdType' => '',
                        'IsPublic' => '',
                        'IsActive' => '',
                        'CreatedAT' => '',
                        'UpdatedAT' => ''
                    ];
                    $res = [
                        'IsSuccess' => true,
                        'Message' => 'No ad available.',
                        'TotalCount' => 0,
                        'Data' => array('AdDetails' => $AdDetails)
                    ];
                    return response()->json($res, 200);
                }
                $AdId = encrypt($adData['AdId']);
                $CampaignId = encrypt($Campaign->CampaignId);

                $CampaignAdList = CampaignAdList::where(['AdId' => $adData->AdId, 'CampaignId' => $Campaign->CampaignId, 'IsDeleted' => 0])->first();
                if ($CampaignAdList) {
                    $URL = $adData['LandingPageURL'] . "?a=$AdId&c=$CampaignId";
                    $LandingPageURL = $request->LandingPageUrl . "?a=$AdId&c=$CampaignId";
                    // $URL = "http://192.168.1.72:7700/LandingRouter"."?ad_id=$AdId&affiliate_id=$UserData->TrackingId&campaign_id=$CampaignId";

                    $IsPublic = ($adData['IsPublic'] == 0) ? 'Private' : 'Public';
                    $AdDetails = [
                        'AdId' => $adData['AdId'],
                        'Title' => $adData['Title'],
                        'BannerImage' => $this->storage_path . 'app/adds/' . $adData['BannerImage'],
                        'LandingPageURL' => $LandingPageURL,
                        // 'LandingPageURL' => $adData['LandingPageURL'], 
                        'Brand' => $adData['brand']['Title'],
                        'AdBrandId' => $adData['brand']['AdBrandId'],
                        'Type' => $adData['type']['Title'],
                        'AdTypeId' => $adData['type']['AdTypeId'],
                        'Size' => $adData['size']['Size'],
                        'AdSizeId' => $adData['size']['AdSizeId'],
                        'Language' => $adData['language']['LanguageName'],
                        'LanguageId' => $adData['language']['LanguageId'],
                        'IsAllCountrySelected' => $adData['IsAllCountrySelected'],
                        'CountryList' => $adData['CountryList'],
                        'URL' => $URL,
                        'AdType' => $IsPublic,
                        'IsPublic' => $adData['IsPublic'],
                        'IsActive' => $adData['IsActive'],
                        'CreatedAT' => (string) $adData['CreatedAt'],
                        'UpdatedAT' => (string) $adData['UpdatedAt']
                    ];
                    CampaignAdList::where(['AdId' => $adData->AdId, 'CampaignId' => $Campaign->CampaignId])->increment('Impressions', 1);

                    CampaignAdImpression::Create(['CampaignAddId' => $CampaignAdList->CampaignAddId]);

                    $res = [
                        'IsSuccess' => true,
                        'Message' => 'View ad details successfully.',
                        'TotalCount' => 0,
                        'Data' => array('AdDetails' => $AdDetails)
                    ];
                    return response()->json($res, 200);
                } else {
                    $AdDetails = [
                        'AdId' => '',
                        'Title' => '',
                        'BannerImage' => $this->storage_path . 'app/Dummy/no_ad_available.png',
                        'LandingPageURL' => '',
                        'Brand' => '',
                        'AdBrandId' => '',
                        'Type' => '',
                        'AdTypeId' => '',
                        'Size' => '',
                        'AdSizeId' => '',
                        'Language' => '',
                        'LanguageId' => '',
                        'IsAllCountrySelected' => '',
                        'CountryList' => '',
                        'URL' => '',
                        'AdType' => '',
                        'IsPublic' => '',
                        'IsActive' => '',
                        'CreatedAT' => '',
                        'UpdatedAT' => ''
                    ];
                    $res = [
                        'IsSuccess' => true,
                        'Message' => 'No ad available.',
                        'TotalCount' => 0,
                        'Data' => array('AdDetails' => $AdDetails)
                    ];
                    return response()->json($res, 200);
                }
            } else {
                $AdDetails = [
                    'AdId' => '',
                    'Title' => '',
                    'BannerImage' => $this->storage_path . 'app/Dummy/no_ad_available.png',
                    'LandingPageURL' => '',
                    'Brand' => '',
                    'AdBrandId' => '',
                    'Type' => '',
                    'AdTypeId' => '',
                    'Size' => '',
                    'AdSizeId' => '',
                    'Language' => '',
                    'LanguageId' => '',
                    'IsAllCountrySelected' => '',
                    'CountryList' => '',
                    'URL' => '',
                    'AdType' => '',
                    'IsPublic' => '',
                    'IsActive' => '',
                    'CreatedAT' => '',
                    'UpdatedAT' => ''
                ];
                $res = [
                    'IsSuccess' => true,
                    'Message' => 'No ad available.',
                    'TotalCount' => 0,
                    'Data' => array('AdDetails' => $AdDetails)
                ];
                return response()->json($res, 200);
            }
        } catch (Exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
            return response()->json($res, 200);
        }
    }
}
