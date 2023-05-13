<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use DateTime;
use Validator;
use App\User;
use App\UserBankDetail;
use App\UserToken;
use App\UserBalance;
use App\UserVerification;
use App\UserAdBrand;
use App\UserAdType;
use App\UserRevenueType;
use App\UserBonus;
use App\RevenueType;
use App\Campaign;
use App\RevenueModel;
use Maatwebsite\Excel\Facades\Excel;
use Laravel\Lumen\Routing\Controller as BaseController;

class AdminAffiliateController extends BaseController
{
  private $request;
  private $call = [];
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct(Request $request)
  {
    $this->request = $request;
    $this->storage_path = getenv('STORAGE_URL'); 
    $this->affiliate_url = getenv('AFFILIATE_URL');
  }

  public function AdminAddUpdateAffiliate(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $messsages = array(
          'UserDetail.Title.required'    => 'The Title field is required.',
          'UserDetail.FirstName.required'    => 'The First Name field is required.',
          'UserDetail.LastName.required'    => 'The Last Name field is required.',
          'UserDetail.EmailId.required'    => 'The Email Id field is required.',
          'UserDetail.PhoneCountryId.required'    => 'The Phone Country Id field is required.',
          'UserDetail.Phone.required'    => 'The Phone field is required.',
          'UserDetail.Currency.required'    => 'The Currency field is required.',
          'UserDetail.CompanyName.required'    => 'The Company Name field is required.',
          'UserDetail.Address.required'    => 'The Address field is required.',
          'UserDetail.City.required'    => 'The City field is required.',
          'UserDetail.State.required'    => 'The State field is required.',
          'UserDetail.Country.required'    => 'The Country field is required.',
          'UserDetail.PostalCode.required'    => 'The Postal Code field is required.'
        );
        $rules = array(
          'UserDetail.Title' => 'required|max:255',
          'UserDetail.FirstName' => 'required|max:255',
          'UserDetail.LastName' => 'required|max:255',
          'UserDetail.EmailId' => 'required|email|max:255',
          'UserDetail.PhoneCountryId' => 'required',
          'UserDetail.Phone' => 'required',
          'UserDetail.Currency' => 'required',
          'UserDetail.CompanyName' => 'required',
          'UserDetail.Address' => 'required',
          'UserDetail.City' => 'required',
          'UserDetail.State' => 'required',
          'UserDetail.Country' => 'required',
          'UserDetail.PostalCode' => 'required'
        );
        $this->validate($this->request, $rules, $messsages);

        if (isset($request->UserDetail['UserId']) && $request->UserDetail['UserId'] != '') {
          if ($request->ConfigurationDetail['IsAllowSubAffiliate'])
            $IsAllowSubAffiliate = 1;
          else
            $IsAllowSubAffiliate = 0;

          $User = User::find($request->UserDetail['UserId']);
          if ($User) {
            $User->Title = $request->UserDetail['Title'];
            $User->FirstName = $request->UserDetail['FirstName'];
            $User->LastName = $request->UserDetail['LastName'];
            $User->CurrencyId = $request->UserDetail['Currency'];
            $User->CompanyName = $request->UserDetail['CompanyName'];
            $User->Phone = $request->UserDetail['Phone'];
            $User->PhoneCountryId = $request->UserDetail['PhoneCountryId'];
            $User->Address = $request->UserDetail['Address'];
            $User->City = $request->UserDetail['City'];
            $User->State = $request->UserDetail['State'];
            $User->CountryId = $request->UserDetail['Country'];
            $User->PostalCode = $request->UserDetail['PostalCode'];
            $User->IsAllowSubAffiliate = $IsAllowSubAffiliate;
            $User->UpdatedBy = $log_user->UserId;
            $User->save();

            $AssignAdTypes = $request->ConfigurationDetail['AssignAdTypes'];
            UserAdType::where('UserId', $User->UserId)->delete();
            foreach ($AssignAdTypes as $AdTypes) {
              $UserAdType = new UserAdType();
              $UserAdType->UserId = $User->UserId;
              $UserAdType->AdTypeId = $AdTypes;
              $UserAdType->UpdatedBy = $log_user->UserId;
              $UserAdType->save();
            }

            $AssignAdBrands = $request->ConfigurationDetail['AssignAdBrands'];
            UserAdBrand::where('UserId', $User->UserId)->delete();
            foreach ($AssignAdBrands as $AdBrands) {
              $UserAdBrand = new UserAdBrand();
              $UserAdBrand->UserId = $User->UserId;
              $UserAdBrand->AdBrandId = $AdBrands;
              $UserAdBrand->UpdatedBy = $log_user->UserId;
              $UserAdBrand->save();
            }

            $AssignRevenueTypes = $request->RevenueOptionDetail['AssignRevenueTypes'];
            $arr = [];
            foreach ($AssignRevenueTypes as $val) {
              array_push($arr, $val['RevenueModelId']);
            }

            $user_old_model = UserRevenueType::where('UserId', $User->UserId)->pluck('RevenueModelId');
            $arr2 = [];
            foreach ($user_old_model as $val1) {
              array_push($arr2, $val1);
            }
            $result = array_diff($arr2, $arr);

            if (count($result) >= 1) {
              foreach ($result as $rw) {
                Campaign::where('UserId', $User->UserId)->where('RevenueModelId', $rw)->update(['IsDeleted' => 0]);
              }
            }
            UserRevenueType::where('UserId', $User->UserId)->delete();
            // create UserRevenueType 8(Sub-affiliate) 
            if ($request->ConfigurationDetail['IsAllowSubAffiliate']) {
              $AssignSubAffiliateRevenueOption = $request->ConfigurationDetail['AssignSubAffiliateRevenueOption'];
              UserRevenueType::Create([
                'UserId' => $User->UserId,
                'RevenueTypeId' => 8,
                'RevenueModelId' => $AssignSubAffiliateRevenueOption,
                'CreatedBy' => $log_user->UserId,
              ]);
            }

            foreach ($AssignRevenueTypes as $UserRevenue) {
              if ($UserRevenue['RevenueTypeId'] != 8) {
                $UserRevenueType = new UserRevenueType();
                $UserRevenueType->UserId = $User->UserId;
                $UserRevenueType->RevenueTypeId = $UserRevenue['RevenueTypeId'];
                $UserRevenueType->RevenueModelId = $UserRevenue['RevenueModelId'];
                $UserRevenueType->UpdatedBy = $log_user->UserId;
                $UserRevenueType->save();
                // Auto bonus update date
                if ($UserRevenue['RevenueTypeId'] == 7) {
                  $UserBonus = UserBonus::where('UserId', $User->UserId)->orderBy('UserBonusId', 'desc')->first();
                  $RevenueModel = RevenueModel::where('RevenueModelId', $UserRevenue['RevenueModelId'])->first();
                  // Inception
                  if ($RevenueModel->Schedule == 3) {
                    $NextBonusDate = new DateTime('tomorrow');
                    $NextBonusDate->format('Y-m-d');
                  }
                  // Monthly
                  else if ($RevenueModel->Schedule == 2) {
                    $NextBonusDate = new DateTime('today');
                    $NextBonusDate->modify('+1 month');
                    $NextBonusDate->format('Y-m-d');
                  }
                  // Yearly
                  else {
                    $NextBonusDate = new DateTime('today');
                    $NextBonusDate->modify('+1 year');
                    $NextBonusDate->format('Y-m-d');
                  }
                  if ($UserBonus) {
                    // update NextBonusDate if bonus revenue model change/update
                    if ($UserBonus->RevenueModelId != $UserRevenue['RevenueModelId']) {
                      UserBonus::where('UserId', $User->UserId)->update(['NextBonusDate' => null]);
                      $UserBonus = UserBonus::create([
                        "RevenueModelId" => $RevenueModel->RevenueModelId,
                        "UserId" => $User->UserId,
                        "NextBonusDate" => $NextBonusDate,
                        "Type" => 1,
                      ]);
                    }
                  } else {
                    $UserBonus = UserBonus::create([
                      "RevenueModelId" => $RevenueModel->RevenueModelId,
                      "UserId" => $User->UserId,
                      "NextBonusDate" => $NextBonusDate,
                      "Type" => 1,
                    ]);
                  }
                }
                // End. Auto bonus update date
              }
            }
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Affiliate updated successfully.',
              "TotalCount" => 0,
              "Data" => []
            ], 200);
          } else {
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'User not found.',
              "TotalCount" => 0,
              "Data" => []
            ], 200);
          }
        } else {
          $check_email = User::where('EmailId', $request->UserDetail['EmailId'])->where('RoleId', 3)->where('IsDeleted', 1)->count();
          if ($check_email >= 1) {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'An Affiliate account with this email already exists.',
              'TotalCount' => 0,
              'Data' => null
            ]);
          }

          $password = str_random(10);
          // check admin Active Affiliate
          if ($request->ConfigurationDetail['IsActiveAffiliate'])
            $IsActiveAffiliate = 1;
          else
            $IsActiveAffiliate = 0;
          // check allow Sub Affiliate
          if ($request->ConfigurationDetail['IsAllowSubAffiliate'])
            $IsAllowSubAffiliate = 1;
          else
            $IsAllowSubAffiliate = 0;

          $User = new User();
          $User->Title = $request->UserDetail['Title'];
          $User->FirstName = $request->UserDetail['FirstName'];
          $User->LastName = $request->UserDetail['LastName'];
          $User->EmailId = $request->UserDetail['EmailId'];
          $User->Password = encrypt($password);
          $User->RoleId = 3;
          $User->CurrencyId = $request->UserDetail['Currency'];
          $User->CompanyName = $request->UserDetail['CompanyName'];
          $User->PhoneCountryId = $request->UserDetail['PhoneCountryId'];
          $User->Phone = $request->UserDetail['Phone'];
          $User->Address = $request->UserDetail['Address'];
          $User->City = $request->UserDetail['City'];
          $User->State = $request->UserDetail['State'];
          $User->CountryId = $request->UserDetail['Country'];
          $User->PostalCode = $request->UserDetail['PostalCode'];
          $User->EmailVerified = 1;
          $User->AdminVerified = $IsActiveAffiliate;
          $User->IsAllowSubAffiliate = $IsAllowSubAffiliate;
          $User->Comment = $request->ConfigurationDetail['Comment'];
          $User->CreatedBy = $log_user->UserId;
          $User->save();

          $user = User::find($User->UserId);
          $user->TrackingId = 'RC' . $User->UserId . '' . time();
          $user->save();

          UserBalance::Create([
            'UserId' => $User->UserId
          ]);
          $firstname = $User->FirstName;
          $lastname = $User->LastName;
          $email = $User->EmailId;
          $verification_code = str_random(30); //Generate verification code

          UserVerification::insert(['UserId' => $User->UserId, 'AccessToken' => $verification_code]);
          $subject = "Welcome to royal affiliate system.";
          // $url = 'http://192.168.1.76:7979/account/signin';
          // $url = 'https://differenzuat.com/affiliate/AffilatePortal/account/signin';
          $url = $this->affiliate_url . 'account/signin';
          Mail::send(
            'email.AffiliateCreatedByRoyal',
            ['firstname' => $firstname, 'lastname' => $lastname, 'email' => $email, 'password' => $password, 'url' => $url],
            function ($mail) use ($email, $firstname, $subject) {
              $mail->from(getenv('FROM_EMAIL_ADDRESS'), "Affiliate System");
              $mail->to($email, $firstname);
              $mail->subject($subject);
            }
          );

          $AssignAdTypes = $request->ConfigurationDetail['AssignAdTypes'];
          foreach ($AssignAdTypes as $AdTypes) {
            $UserAdType = new UserAdType();
            $UserAdType->UserId = $User->UserId;
            $UserAdType->AdTypeId = $AdTypes;
            $UserAdType->CreatedBy = $log_user->UserId;
            $UserAdType->save();
          }
          $AssignAdBrands = $request->ConfigurationDetail['AssignAdBrands'];
          foreach ($AssignAdBrands as $AdBrands) {
            $UserAdBrand = new UserAdBrand();
            $UserAdBrand->UserId = $User->UserId;
            $UserAdBrand->AdBrandId = $AdBrands;
            $UserAdBrand->CreatedBy = $log_user->UserId;
            $UserAdBrand->save();
          }
          // create UserRevenueType 8(Sub-affiliate)
          if ($request->ConfigurationDetail['IsAllowSubAffiliate']) {
            $AssignSubAffiliateRevenueOption = $request->ConfigurationDetail['AssignSubAffiliateRevenueOption'];
            UserRevenueType::Create([
              'UserId' => $User->UserId,
              'RevenueTypeId' => 8,
              'RevenueModelId' => $AssignSubAffiliateRevenueOption,
              'CreatedBy' => $log_user->UserId,
            ]);
          }

          $AssignRevenueTypes = $request->RevenueOptionDetail['AssignRevenueTypes'];
          foreach ($AssignRevenueTypes as $UserRevenue) {
            if ($UserRevenue['RevenueTypeId'] != 8) {
              $UserRevenueType = new UserRevenueType();
              $UserRevenueType->UserId = $User->UserId;
              $UserRevenueType->RevenueTypeId = $UserRevenue['RevenueTypeId'];
              $UserRevenueType->RevenueModelId = $UserRevenue['RevenueModelId'];
              $UserRevenueType->CreatedBy = $log_user->UserId;
              $UserRevenueType->save();
              // Auto bonus update date
              if ($UserRevenue['RevenueTypeId'] == 7) {
                $UserBonus = UserBonus::where('UserId', $User->UserId)->orderBy('UserBonusId', 'desc')->first();
                $RevenueModel = RevenueModel::where('RevenueModelId', $UserRevenue['RevenueModelId'])->first();
                // Inception
                if ($RevenueModel->Schedule == 3) {
                  $NextBonusDate = new DateTime('tomorrow');
                  $NextBonusDate->format('Y-m-d');
                }
                // Monthly
                else if ($RevenueModel->Schedule == 2) {
                  $NextBonusDate = new DateTime('today');
                  $NextBonusDate->modify('+1 month');
                  $NextBonusDate->format('Y-m-d');
                }
                // Yearly
                else {
                  $NextBonusDate = new DateTime('today');
                  $NextBonusDate->modify('+1 year');
                  $NextBonusDate->format('Y-m-d');
                }
                if ($UserBonus) {
                  // update NextBonusDate if bonus revenue model change/update
                  if ($UserBonus->RevenueModelId != $UserRevenue['RevenueModelId']) {
                    UserBonus::where('UserId', $User->UserId)->update(['NextBonusDate' => null]);
                    $UserBonus = UserBonus::create([
                      "RevenueModelId" => $RevenueModel->RevenueModelId,
                      "UserId" => $User->UserId,
                      "NextBonusDate" => $NextBonusDate,
                      "Type" => 1,
                    ]);
                  }
                } else {
                  $UserBonus = UserBonus::create([
                    "RevenueModelId" => $RevenueModel->RevenueModelId,
                    "UserId" => $User->UserId,
                    "NextBonusDate" => $NextBonusDate,
                    "Type" => 1,
                  ]);
                }
              }
              // End. Auto bonus update date
            }
          }

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Affiliate created successfully.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
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
    }
    return response()->json($res);
  }

  public function AdminListAffiliate(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $UserList = User::where('IsDeleted', 1)->where('RoleId', 3)->orderBy('UserId', 'desc')->get();
        $UserArray = [];
        foreach ($UserList as $key => $value) {
          if ($value['EmailVerified'] == 0) {
            $status = "Pending Email Verification";
          } else if ($value['AdminVerified'] == 0) {
            $status = "Pending Approval";
          } else if ($value['AdminVerified'] == 1) {
            $status = "Active";
            if ($value['IsEnabled'] == 0) {
              $status = "Disabled";
            }
          } else if ($value['AdminVerified'] == 2) {
            $status = "Rejected";
          }
          $SubAffiliate = User::where('ParentId', $value['UserId'])->count();
          if ($SubAffiliate > 0)
            $IsHaveSubAffiliate = 1;
          else
            $IsHaveSubAffiliate = 0;
          if ($value['ParentId'] != '') {
            $parentUserData = User::find($value['ParentId']);
            $parentUser = $parentUserData['FirstName'] . ' ' . $parentUserData['LastName'];
          } else {
            $parentUser = '';
          }
          $Array = [
            'FirstName' => $value['FirstName'],
            'LastName' => $value['LastName'],
            'EmailId' => $value['EmailId'],
            'EmailVerified' => $value['EmailVerified'],
            'AdminVerified' => $value['AdminVerified'],
            'IsEnabled' => $value['IsEnabled'],
            'City' => $value['City'],
            'Status' => $status,
            'UserId' => $value['UserId'],
            'IsHaveSubAffiliate' => $IsHaveSubAffiliate,
            'parentUser' => $parentUser,
          ];
          array_push($UserArray, $Array);
        }
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Affiliate listed successfully.',
          "TotalCount" => $UserList->count(),
          "Data" => array('UserList' => $UserArray)
        ], 200);
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
    }
    return response()->json($res);
  }

  public function ActiveAffiliateList(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $UserList = User::where('IsDeleted', 1)->where('IsEnabled', 1)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('RoleId', 3)->get();

        $UserArray = [];
        foreach ($UserList as $key => $value) {
          $Array = [
            'AffiliateName' => $value['FirstName'] . ' ' . $value['LastName'],
            'EmailId' => $value['EmailId'],
            'UserId' => $value['UserId']
          ];
          array_push($UserArray, $Array);
        }
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Affiliate listed successfully.',
          "TotalCount" => 0,
          "Data" => array('ActiveAffiliateList' => $UserArray)
        ], 200);
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
    }
    return response()->json($res);
  }

  public function AdminGetAffiliateDetailsByUserId(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {

        $validator = Validator::make($request->all(), [
          'UserId'  => 'required'
        ]);
        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }
        $UserData = User::with('PhoneCountry')->find($request->UserId);
        $UserAdBrand = UserAdBrand::with('Brand')->where('UserId', $request->UserId)->get();
        $UserAdType = UserAdType::with('Type')->where('UserId', $request->UserId)->get();
        $UserRevenueType = UserRevenueType::with('RevenueModel.Currency', 'RevenueType')->where('UserId', $request->UserId)->get();

        $UserId = $UserData->UserId;
        $UserBankDetail = UserBankDetail::where('UserId', $request->UserId)->first();

        if ($UserData) {
          $UserAdBrandArray = [];
          foreach ($UserAdBrand as $value) {
            $Array = [
              'AdBrandId' => $value['AdBrandId'],
              'Title' => $value['Brand']['Title'],
              'IsActive' => $value['Brand']['IsActive']
            ];
            array_push($UserAdBrandArray,  $Array);
          }

          $UserAdTypeArray = [];
          foreach ($UserAdType as $value) {
            $Array = [
              'AdTypeId' => $value['AdTypeId'],
              'Title' => $value['Type']['Title'],
              'IsActive' => $value['Type']['IsActive']
            ];
            array_push($UserAdTypeArray,  $Array);
          }
          $UserRevenueTypeArray = [];
          $UserRevenueTypeIds = [];
          foreach ($UserRevenueType as $value) {
            if ($value['RevenueType']['RevenueTypeId'] == 8) {
              $UserData['AssignSubAffiliateRevenueOption'] = $value['RevenueModel']['RevenueModelId'];
              $UserData['AssignSubAffiliateRevenueOptionName'] = $value['RevenueModel']['RevenueModelName'];
            }
            // Details C-CPA
            if ($value['RevenueType']['RevenueTypeId'] == 4) {
              if ($value['RevenueModel']['TradeType'] == 1)
                $TradeType = 'Number of lots';
              else if ($value['RevenueModel']['TradeType'] == 2)
                $TradeType = 'Total deposit';
              else
                $TradeType = 'Number of transaction';
              $Details = '<b>' . $TradeType . '</b>:' . $value['RevenueModel']['TradeValue'];
            }
            // Details Fx Reve. Share
            else if ($value['RevenueType']['RevenueTypeId'] == 6) {
              $Details = '<b>Spread</b>:' . $value['RevenueModel']['Rebate'] . '<br>' . '<b>Reference Deal</b>:' . $value['RevenueModel']['ReferenceDeal'];
            }
            // Details Bonus
            else if ($value['RevenueType']['RevenueTypeId'] == 7) {
              if ($value['RevenueModel']['Schedule'] == 1)
                $Schedule = 'Yearly';
              else if ($value['RevenueModel']['Schedule'] == 2)
                $Schedule = 'Monthly';
              else
                $Schedule = 'Inception';
              if ($value['RevenueModel']['TradeType'] == 1)
                $Details = '<b>Schedule</b>: ' . $Schedule . '<br><b>Total Account Trade Volume</b>: ' . $value['RevenueModel']['TotalAccTradeVol'];
              else
                $Details = '<b>Schedule</b>: ' . $Schedule . '<br><b>Total Introduced Account</b>: ' . $value['RevenueModel']['TotalIntroducedAcc'];
            }
            // Details Sub Aff.
            else if ($value['RevenueType']['RevenueTypeId'] == 8) {
              if ($value['RevenueModel']['Type'] == 1)
                $Type = 'From';
              else
                $Type = 'On Top Of';
              $Details = '<b>' . $Type . '</b>:' . $value['RevenueModel']['Percentage'];
            } else {
              $Details = '';
            }

            $Array = [
              'RevenueTypeId' => $value['RevenueType']['RevenueTypeId'],
              'RevenueTypeName' => $value['RevenueType']['RevenueTypeName'],
              'RevenueModelId' => $value['RevenueModel']['RevenueModelId'],
              'RevenueModelName' => $value['RevenueModel']['RevenueModelName'],
              'Details' => $Details,
              'CurrencyId' => $value['RevenueModel']['CurrencyId'],
              'Currency' => $value['RevenueModel']['Currency']['CurrencyCode'],
              'Value' => (($value['RevenueType']['RevenueTypeId'] == 1) || ($value['RevenueType']['RevenueTypeId'] == 2) || ($value['RevenueType']['RevenueTypeId'] == 3) || ($value['RevenueType']['RevenueTypeId'] == 4) || ($value['RevenueType']['RevenueTypeId'] == 6) || ($value['RevenueType']['RevenueTypeId'] == 7)) ? $value['RevenueModel']['Amount'] : $value['RevenueModel']['Percentage'],
              'TotalAccTradeVol' => $value['RevenueModel']['TotalAccTradeVol'],
              'TotalIntroducedAcc' => $value['RevenueModel']['TotalIntroducedAcc'],
              'IsActive' => $value['RevenueModel']['IsActive']
            ];
            array_push($UserRevenueTypeArray,  $Array);
            array_push($UserRevenueTypeIds,  $value['RevenueType']['RevenueTypeId']);
          }
          array_push($UserRevenueTypeIds, 8);

          $UserEditRevenueType = RevenueType::whereNotIn('RevenueTypeId', $UserRevenueTypeIds)->where('IsActive', 1)->get();

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Affiliate view successfully.',
            "TotalCount" => 0,
            "Data" => ['UserDetail' => $UserData, 'UserAdBrand' => $UserAdBrandArray, 'UserAdType' => $UserAdTypeArray, 'UserRevenueType' => $UserRevenueTypeArray, 'UserEditRevenueTypeList' => $UserEditRevenueType, 'UserBankDetail' => $UserBankDetail]
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'User not found.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
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
    }
    return response()->json($res);
  }

  public function AdminActivateAffiliate(Request $request)
  {
    // return $request->all(); die;
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        // find user details                 
        $User = User::find($request->UserDetail['UserId']);
        if ($User) {
          if ($request->ConfigurationDetail['IsActiveAffiliate']) {
            // check allow Sub Affiliate
            if ($request->ConfigurationDetail['IsAllowSubAffiliate'])
              $IsAllowSubAffiliate = 1;
            else
              $IsAllowSubAffiliate = 0;

            $User->AdminVerified = 1;
            $User->UpdatedBy = $log_user->UserId;
            $User->IsAllowSubAffiliate = $IsAllowSubAffiliate;
            $User->Comment = $request->ConfigurationDetail['Comment'];
            $User->save();

            $AssignAdTypes = $request->ConfigurationDetail['AssignAdTypes'];
            UserAdType::where('UserId', $User->UserId)->delete();
            foreach ($AssignAdTypes as $AdTypes) {
              $UserAdType = new UserAdType();
              $UserAdType->UserId = $User->UserId;
              $UserAdType->AdTypeId = $AdTypes;
              $UserAdType->CreatedBy = $log_user->UserId;
              $UserAdType->save();
            }

            $AssignAdBrands = $request->ConfigurationDetail['AssignAdBrands'];
            UserAdBrand::where('UserId', $User->UserId)->delete();
            foreach ($AssignAdBrands as $AdBrands) {
              $UserAdBrand = new UserAdBrand();
              $UserAdBrand->UserId = $User->UserId;
              $UserAdBrand->AdBrandId = $AdBrands;
              $UserAdBrand->CreatedBy = $log_user->UserId;
              $UserAdBrand->save();
            }

            $AssignRevenueTypes = $request->RevenueOptionDetail['AssignRevenueTypes'];
            UserRevenueType::where('UserId', $User->UserId)->delete();

            // create UserRevenueType 8(Sub-affiliate)
            if ($request->ConfigurationDetail['IsAllowSubAffiliate']) {
              $AssignSubAffiliateRevenueOption = $request->ConfigurationDetail['AssignSubAffiliateRevenueOption'];
              UserRevenueType::Create([
                'UserId' => $User->UserId,
                'RevenueTypeId' => 8,
                'RevenueModelId' => $AssignSubAffiliateRevenueOption,
                'CreatedBy' => $log_user->UserId,
              ]);
            }
            foreach ($AssignRevenueTypes as $RevenueTypes) {
              if ($RevenueTypes['RevenueTypeId'] != 8) {
                $UserRevenueType = new UserRevenueType();
                $UserRevenueType->UserId = $User->UserId;
                $UserRevenueType->RevenueTypeId = $RevenueTypes['RevenueTypeId'];
                $UserRevenueType->RevenueModelId = $RevenueTypes['RevenueModelId'];
                $UserRevenueType->CreatedBy = $log_user->UserId;
                $UserRevenueType->save();
                // Auto bonus add NextBonusDate
                if ($RevenueTypes['RevenueTypeId'] == 7) {
                  $UserBonus = UserBonus::where('UserId', $User->UserId)->orderBy('UserBonusId', 'desc')->first();
                  $RevenueModel = RevenueModel::where('RevenueModelId', $RevenueTypes['RevenueModelId'])->first();
                  // Inception
                  if ($RevenueModel->Schedule == 3) {
                    $NextBonusDate = new DateTime('tomorrow');
                    $NextBonusDate->format('Y-m-d');
                  }
                  // Monthly
                  else if ($RevenueModel->Schedule == 2) {
                    $NextBonusDate = new DateTime('today');
                    $NextBonusDate->modify('+1 month');
                    $NextBonusDate->format('Y-m-d');
                  }
                  // Yearly
                  else {
                    $NextBonusDate = new DateTime('today');
                    $NextBonusDate->modify('+1 year');
                    $NextBonusDate->format('Y-m-d');
                  }
                  $UserBonus = UserBonus::create([
                    "RevenueModelId" => $RevenueModel->RevenueModelId,
                    "UserId" => $User->UserId,
                    "NextBonusDate" => $NextBonusDate,
                    "Type" => 1,
                  ]);
                }
                // End. Auto bonus add NextBonusDate
              }
            }
            $firstname = $User->FirstName;
            $lastname = $User->LastName;
            $email = $User->EmailId;
            // $url = 'http://192.168.1.76:7979/account/signin';
            // $url = 'https://differenzuat.com/affiliate/AffilatePortal/account/signin';
            $url = $this->affiliate_url . 'account/signin';
            Mail::send(
              'email.AffiliateActivateByAdmin',
              ['firstname' => $firstname, 'lastname' => $lastname, 'email' => $email, 'url' => $url],
              function ($mail) use ($firstname, $email) {
                $mail->from(getenv('FROM_EMAIL_ADDRESS'), "Affiliate System");
                $mail->to($email, $firstname);
                $mail->subject("Account activate by admin");
              }
            );
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Affiliate activated successfully.',
              "TotalCount" => 0,
              "Data" => []
            ], 200);
          } else {
            $User->AdminVerified = 2;
            $User->UpdatedBy = $log_user->UserId;
            $User->Comment = $request->ConfigurationDetail['Comment'];
            $User->save();

            $firstname = $User->FirstName;
            $lastname = $User->LastName;
            $email = $User->EmailId;
            // $url = 'http://192.168.1.76:7979/account/signin';
            // $url = 'https://differenzuat.com/affiliate/AffilatePortal/account/signin';
            $url = $this->affiliate_url . 'account/signin';
            Mail::send(
              'email.AffiliateRejectByAdmin',
              ['firstname' => $firstname, 'lastname' => $lastname, 'email' => $email, 'url' => $url],
              function ($mail) use ($firstname, $email) {
                $mail->from(getenv('FROM_EMAIL_ADDRESS'), "Affiliate System");
                $mail->to($email, $firstname);
                $mail->subject('Account reject by admin');
              }
            );
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Affiliate rejected successfully.',
              "TotalCount" => 0,
              "Data" => []
            ], 200);
          }
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Affiliate not found.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => []
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
    }
    return response()->json($res);
  }

  public function AdminDeleteAffiliate(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        // find user details                 
        $User = User::find($request->UserDetail['UserId']);
        if ($User) {
          $User->IsDeleted = 0;
          $User->UpdatedBy = $log_user->UserId;
          $User->save();
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Affiliate deleted successfully.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Affiliate not found.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => []
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
    }
    return response()->json($res);
  }

  public function AdminEnableDisableAffiliate(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        // find user details                 
        $User = User::find($request->UserDetail['UserId']);
        if ($User) {
          $User->IsEnabled = $request->UserDetail['IsEnabled'];
          $User->UpdatedBy = $log_user->UserId;
          $User->save();
          if ($request->UserDetail['IsEnabled'] == 1)
            $messsage = 'Affiliate has been successfully enabled.';
          else
            $messsage = 'Affiliate has been successfully disabled.';
          return response()->json([
            'IsSuccess' => true,
            'Message' => $messsage,
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Affiliate not found.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => []
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
    }
    return response()->json($res);
  }

  public function IntegrationAffiliateList(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $UserEmailList = User::where('IsDeleted', 1)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('RoleId', 3)->orderBy('EmailId')->get();
        $UserEmailArray = [];
        foreach ($UserEmailList as $key => $value) {
          $Array = [
            'EmailId' => $value['EmailId']
          ];
          array_push($UserEmailArray, $Array);
        }

        $UserList = User::with('Country', 'Currency')->where('IsDeleted', 1)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('RoleId', 3);
        if (isset($request->EmailId) && $request->EmailId != '') {
          $UserList->where('EmailId', $request->EmailId);
        }
        if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
          $from = $request->DateForm;
          $to = $request->DateTo;
          $UserList->whereDate('CreatedAt', '>=', $from)
            ->whereDate('CreatedAt', '<=', $to);
        }
        $UserList = $UserList->orderBy('UserId', 'desc')->get();
        $TimeZoneOffSet = $request->TimeZoneOffSet;
        if ($TimeZoneOffSet == '')
          $TimeZoneOffSet = 0;

        $UserArray = [];
        foreach ($UserList as $key => $value) {
          $Array = [
            'AffiliateId' => $value['UserId'],
            'ParentId' => $value['ParentId'],
            'FirstName' => $value['FirstName'],
            'LastName' => $value['LastName'],
            'EmailId' => $value['EmailId'],
            'Phone' => $value['Phone'],
            'Country' => $value['Country']['CountryName'],
            'Currency' => $value['Currency']['CurrencyCode'],
            'RegistrationDate' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
          ];
          array_push($UserArray, $Array);
        }
        // Export list
        if ($request->IsExport) {
          Excel::create('AffiliateList', function ($excel) use ($UserArray) {
            $excel->sheet('AffiliateList', function ($sheet) use ($UserArray) {
              $sheet->fromArray($UserArray);
            });
          })->store('xls', false, true);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export affiliate sheet successfully.',
            "TotalCount" => 1,
            'Data' => ['AffiliateListExel' => 'http://differenzuat.com/affiliate_api/storage/exports/AffiliateList.xls']
          ], 200);
        }
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Affiliate listed successfully.',
          "TotalCount" => $UserList->count(),
          "Data" => array('ActiveAffiliateList' => $UserArray, 'UserEmailList' => $UserEmailArray)
        ], 200);
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
    }
    return response()->json($res);
  }

  public function SubAffiliateList(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $User = User::find($request->UserId);
        if ($User) {
          $parent = User::where('UserId', $User->UserId)->first();
          $subaffiliate = User::where('ParentId', $User->UserId)->where('IsDeleted', 1);
          // $subList = $subaffiliate->get();
          $TimeZoneOffSet = $request->TimeZoneOffSet;
          if ($TimeZoneOffSet == '')
            $TimeZoneOffSet = 0;

          // return $subaffiliate->count();

          if ($subaffiliate->count() >= 1) {
            $Users = User::with('SubAffiliate')->where('UserId', $User->UserId)->first();
            $arr = [
              'userName' => $Users['FirstName'] . ' ' . $Users['LastName'],
              'EmailId' => $Users['EmailId'],
              'ID' => $Users['UserId'],
              'IsParent' => true,
              'ParentId' => null,
              'CreatedAt' => date('d/m/Y', strtotime($TimeZoneOffSet . " minutes", strtotime($Users['CreatedAt'])))
            ];
            array_push($this->call, $arr);
            $count = 1;

            if (count($Users['SubAffiliate']) != 0) {
              $this->call = $this->call($Users['SubAffiliate'], $TimeZoneOffSet);
            }
            $arrList = $this->call;

            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Successfully get sub affiliate list.',
              "TotalCount" => $count,
              "Data" => array('userData' => $arrList)
            ], 200);
          } else {
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'No sub affiliate found.',
              "TotalCount" => 0,
              'Data' => array('userData' => [])
            ], 200);
          }
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'User not found.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
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
    }
    return response()->json($res);
  }

  public function call($data, $TimeZoneOffSet)
  {
    foreach ($data as $value1) {
      if ($value1['AdminVerified'] == 1) {
        $arr = [
          'userName' => $value1['FirstName'] . ' ' . $value1['LastName'],
          'EmailId' => $value1['EmailId'],
          'ID' => $value1['UserId'],
          'IsParent' => false,
          'ParentId' => $value1['ParentId'],
          'CreatedAt' => date('d/m/Y', strtotime($TimeZoneOffSet . " minutes", strtotime($value1['CreatedAt'])))
        ];
        array_push($this->call, $arr);
      }
    }
    foreach ($data as $value1) {
      if (count($value1['SubAffiliate']) != 0) {
        $this->call($value1['SubAffiliate'], $TimeZoneOffSet);
      }
    }
    return $this->call;
  }
}
