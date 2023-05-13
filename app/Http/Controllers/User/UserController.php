<?php

namespace App\Http\Controllers\User;

use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Validator;
use DateTime;
use App\User;
use App\UserBonus;
use App\UserAdBrand;
use App\UserAdType;
use App\Ad;
use App\AdTypeMaster;
use App\AdBrandMaster;
use App\LanguageMaster;
use App\RevenueType;
use App\UserToken;
use App\UserBankDetail;
use App\ResetPassword;
use App\UserVerification;
use App\UserBalance;
use App\UserRevenueType;
use App\SupportManager;
use App\UserSubRevenue;
use App\CountryMaster;
use App\Campaign;
use App\RevenueModel;
use App\CampaignAdList;
use App\CampaignAdClick;
use App\CampaignAdImpression;
use App\Lead;
use App\LeadInformation;
use App\UserRevenuePayment;
use App\RoyalRevenue;
use App\RevenueModelLog;
use Maatwebsite\Excel\Facades\Excel;
use Laravel\Lumen\Routing\Controller as BaseController;

class UserController extends BaseController
{
  private $request;
  private $call = [];
  private $call2 = [];
  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct(Request $request)
  {
    $this->request = $request;
    $this->revenue_auto_approve = env('REVENUE_AUTO_APPROVE', true);
    $this->storage_path = getenv('STORAGE_URL');
    $this->affiliate_url = getenv('AFFILIATE_URL');
  }

  public function AffiliateRegister(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'FirstName'  => 'required|max:190',
        'LastName'  => 'required|max:190',
        'EmailId'     => 'required|email|max:190',
        'ConfirmEmailId'     => 'required|email|same:EmailId',
        'Password'  => 'required',
        'ConfirmPassword'  => 'required|same:Password',
        'PhoneCountryId'  => 'required',
        'Phone'  => 'required',
        'Country'  => 'required',
        'Currency'  => 'required', 
        'RoleId' => 'required',
        'ReferenceId' => 'nullable',
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Something went wrong.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }

      $check_email = User::where('EmailId', $request->EmailId)->where('RoleId', 3)->where('IsDeleted', 1)->count();
      if ($check_email >= 1) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'An Affiliate account with this email already exists.',
          'TotalCount' => 0,
          'Data' => null
        ]);
      }
      $ParentId = NULL;
      if (isset($request->ReferenceId) && $request->ReferenceId != '') {
        $ParrentUser = User::where('TrackingId', $request->ReferenceId)->first();
        if ($ParrentUser) {
          $ParentId = $ParrentUser->UserId;
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Invalid reference id.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
      }
      $request->merge([
        'Password' => encrypt(base64_decode($request->Password)),
        'CountryId' => $request->Country,
        'CurrencyId' => $request->Currency,
        'ParentId' => $ParentId
      ]);
      $User = User::create($request->all());
      $name = $request->FirstName . ' ' . $request->LastName;
      $firstname = $request->FirstName;
      $lastname = $request->LastName;
      $email = $request->EmailId;
      $verification_code = str_random(30); //Generate verification code

      UserVerification::insert(['UserId' => $User->UserId, 'AccessToken' => $verification_code]);
      $subject = "Please verify your email address.";
      // $link_url = 'https://differenzuat.com/affiliate/AffilatePortal/account/ConfirmEmail/' . $verification_code;  
      $link_url = $this->affiliate_url . 'account/ConfirmEmail/' . $verification_code;
      Mail::send(
        'email.verify',
        ['email' => $email, 'firstname' => $firstname, 'lastname' => $lastname, 'url' => $link_url],
        function ($mail) use ($email, $name, $subject) {
          $mail->from(getenv('FROM_EMAIL_ADDRESS'), "Affiliate System");
          $mail->to($email, $name);
          $mail->subject($subject);
        }
      );
      return response()->json([
        'IsSuccess' => true,
        'Message' => 'Thanks for signing up. Please check your email to complete your registration.',
        "TotalCount" => 0,
        "Data" => []
      ], 200);
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

  public function verifyUser(Request $request)
  {
    try {
      $check = UserVerification::where('AccessToken', $request->verification_code)->first();
      if (!is_null($check)) {
        $user = User::where('UserId', $check->UserId)->where('RoleId', 3)->first();
        $verified = UserVerification::find($check->UserVerificationId);
        if ($user->EmailVerified == 1) {
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Account already verified.',
            "TotalCount" => 0,
            "Data" => array('EmailId' => $user->EmailId)
          ], 200);
        }
        $user->EmailVerified = 1;
        $user->TrackingId = 'RC' . $user->UserId . '' . time();
        $user->save();
        $verified->IsCompleted = 1;
        $verified->save();

        UserBalance::Create([
          'UserId' => $user->UserId
        ]);

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'You have successfully verified your email address.',
          "TotalCount" => 0,
          "Data" => array('EmailId' => $user->EmailId)
        ], 200);
      }
      return response()->json([
        'IsSuccess' => false,
        'Message' => "Verification code is invalid.",
        "TotalCount" => 0,
        "Data" => []
      ], 200);
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

  public function loginAffiliate(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'EmailId'     => 'required|email',
        'Password'  => 'required'
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Something went wrong.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }
      // Find the user by email
      $user = User::with('Currency')->where('EmailId', $this->request->input('EmailId'))->where('RoleId', 3)->where('IsDeleted', 1)->first();

      if ($user) {

       
        // check user is active, verified by email or admin
        if ($user->EmailVerified == 0) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Email verification is pending. Please check your email and complete your registration.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else if ($user->AdminVerified == 0) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Your account is pending admin approval.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else if ($user->AdminVerified == 2) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Your account is rejected by administrator.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else if ($user->IsDeleted == 0) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Your account is deleted by Administrator.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else if ($user->IsEnabled == 0) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Your account is disabled by Administrator.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
        if (base64_decode($request->Password) == decrypt($user->Password)) {

          $token_id = str_random(60);
          User::where('UserId', $user->UserId)->update([
            'LastLogin' => date("Y-m-d H:i:s")
          ]);
          UserToken::create([
            'UserId' => $user->UserId,
            'Token' => $token_id
          ]);
          $totalUnreadMessage = SupportManager::where('ToUserId', $user->UserId)->where('IsRead', 0)->count();
          return response()->json([
            'Message' => 'Login successfully.',
            'IsSuccess' => true,
            'Data' => array(
              'User' => array(
                'UserId' => $user->UserId,
                'FirstName' => $user->FirstName,
                'LastName' => $user->LastName,
                'Email' => $user->EmailId,
                'CurrencyCode' => $user->Currency['CurrencyCode'],
                'RoleId' => $user->RoleId,
                'IsAllowSubAffiliate' => $user->IsAllowSubAffiliate,
                'TrackingId' => $user->TrackingId,
                'CreatedAt' => $user->CreatedAt,
                'UpdatedAt' => $user->UpdatedAt
              )
            ),
            'SupportCount' => $totalUnreadMessage,
            'AccessToken' => $token_id
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Invalid email or password.',
            "TotalCount" => count($validator->errors()),
            "Data" => []
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Email does not exist.',
          "TotalCount" => count($validator->errors()),
          "Data" => []
        ], 200);
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

  public function AffiliateResetPassword(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'EmailId'     => 'required|email',
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Something went wrong.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }

      // Find the user by email
      $user = User::where('EmailId', $this->request->input('EmailId'))->where('RoleId', 3)->where('IsDeleted', 1)->first();
      $rand_str = str_random(16);
      if ($user) {
        // check user is active, verified by email or admin
        if ($user->EmailVerified == 0) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Email verification is pending. Please check your email and complete your registration.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else if ($user->AdminVerified == 0) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Your account is pending admin approval.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else if ($user->AdminVerified == 2) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Your account is rejected by administrator.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else if ($user->IsDeleted == 0) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Your account is deleted by Administrator.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }

        $pass = ResetPassword::where('UserId', $user->UserId)->count();
        if ($pass >= 1) {
          ResetPassword::where('UserId', $user->UserId)->update([
            'PasswordResetToken' => $rand_str,
            'EmailId' => $user->EmailId,
            'UpdatedAt' => date('Y-m-d H:i:s'),
          ]);
          // $link_url = 'https://differenzuat.com/affiliate/AffilatePortal/account/resetpassword/' . $rand_str;
          // $link_url = 'http://192.168.1.76:7979/account/resetpassword/'.$rand_str;
          $link_url = $this->affiliate_url . 'account/resetpassword/' . $rand_str;
          $data = array(
            'firstname' => $user->FirstName,
            'lastname' => $user->LastName,
            'email' => $user->EmailId,
            'url' => $link_url,
          );
          $userEmail = $user->EmailId;
          $userName = $user->FirstName;
          Mail::send('email.AffiliateResetPassword', $data, function ($message) use ($userEmail, $userName) {
            $message->to($userEmail, $userName)->subject('Reset Password');
            $message->from(getenv('FROM_EMAIL_ADDRESS'), "Affiliate System");
          });
          return response()->json([
            'IsSuccess' => true,
            'Message' => "Reset password link was sent to your email.",
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else {
          ResetPassword::create([
            'UserId' => $user->UserId,
            'PasswordResetToken' => $rand_str,
            'EmailId' => $user->EmailId,
          ]);
          // $link_url = 'https://differenzuat.com/affiliate/AffilatePortal/account/resetpassword/' . $rand_str;
          // $link_url = 'http://192.168.1.76:7979/account/resetpassword/'.$rand_str;
          $link_url = $this->affiliate_url . 'account/resetpassword/' . $rand_str;
          $data = array(
            'firstname' => $user->FirstName,
            'lastname' => $user->LastName,
            'url' => $link_url
          );
          $userEmail = $user->EmailId;
          $userName = $user->FirstName;
          Mail::send('email.AffiliateResetPassword', $data, function ($message) use ($userEmail, $userName) {
            $message->to($userEmail, $userName)->subject('Reset Password');
            $message->from(getenv('FROM_EMAIL_ADDRESS'), 'Affiliate System');
          });

          return response()->json([
            'IsSuccess' => true,
            'Message' => "Reset password link was sent to your email.",
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => "Email is not available.",
          "TotalCount" => 0,
          "Data" => []
        ], 200);
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

  public function affiliateChangePassword(Request $request)
  {
    try {
      $rst_pass = ResetPassword::where('PasswordResetToken', $request->Token)->first();
      if ($rst_pass) {
        $validator = Validator::make($request->all(), [
          'Password'  => 'required',
        ]);
        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }

        User::where('UserId', $rst_pass->UserId)->update([
          'Password' => encrypt(base64_decode($request->Password))
        ]);

        ResetPassword::where('UserId', $rst_pass->UserId)->delete();
        return response()->json([
          'IsSuccess' => true,
          'Message' => "Password updated successfully.",
          "TotalCount" => 0,
          "Data" => []
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => "Invalid token.",
          "TotalCount" => 0,
          "Data" => []
        ], 200);
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

  public function GetAffiliateDetailByUserId(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));

      $validator = Validator::make($request->all(), [
        'UserId'     => 'required',
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Something went wrong.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }

      if ($UserId) {
        $user = User::with('Currency', 'Country', 'PhoneCountry', 'Title')->find($request->UserId);
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Successfully get data.',
          "TotalCount" => 0,
          "Data" => array('userData' => $user)
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
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
    }
    return response()->json($res);
  }

  public function GetAffiliateDetailsTreeView(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));

      $validator = Validator::make($request->all(), [
        'UserId'     => 'required',
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Something went wrong.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }

      if ($UserId) {
        $user = User::with('Currency', 'Country', 'PhoneCountry', 'Title')->find($request->UserId);
        $UserRevenueType = UserRevenueType::with('RevenueModel')->where('UserId', $request->UserId)->where('RevenueTypeId', 8)->first();
        // return $UserRevenueType['revenueModel']; die;
        if ($UserRevenueType) {
          $user['SubAffiliateRevenueModelName'] = $UserRevenueType['revenueModel']['RevenueModelName'];
          $user['SubAffiliateRevenuePercentage'] = $UserRevenueType['revenueModel']['Percentage'];
          if ($UserRevenueType['revenueModel']['Type'] == 1)
            $user['SubAffiliateRevenueType'] = 'From';
          else
            $user['SubAffiliateRevenueType'] = 'On top of';
        } else {
          $user['SubAffiliateRevenueModelName'] = '';
          $user['SubAffiliateRevenuePercentage'] = '';
          $user['SubAffiliateRevenueType'] = '';
        }
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Successfully get data.',
          "TotalCount" => 0,
          "Data" => array('userData' => $user)
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
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
    }
    return response()->json($res);
  }

  public function UpdateProfileDetail(Request $request)
  {
    $ckeck = new UserToken();
    $UserId = $ckeck->validToken($request->header('Token'));
    if ($UserId) {
      $validator = Validator::make($request->all(), [
        'UserId'     => 'required',
        'Title'     => 'required|max:150',
        'FirstName'     => 'required|max:150',
        'LastName'     => 'required|max:150',
        'Phone'     => 'required|max:15',
        'PhoneCountryId' => 'required',
        'Address'     => 'required|max:255',
        'City'     => 'required',
        'State'     => 'required',
        'Country'     => 'required',
        'PostalCode'     => 'required',
        'Company'     => 'required|max:150',
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Something went wrong.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }
      if ($UserId != $request->UserId) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid token OR user id.',
          "TotalCount" => 0,
          "Data" => []
        ], 200);
      }
      $request->merge([
        'CountryId' => $request->Country
      ]);
      $user = User::find($UserId);
      $user->update($request->all());  // Update user details

      return response()->json([
        'IsSuccess' => true,
        'Message' => 'Profile updated successfully.',
        "TotalCount" => 1,
        "Data" => array('userData' => $user)
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

  public function GetPaymentDetailByUserId(Request $request)
  {
    $check = new UserToken();
    $UserId = $check->validToken($request->header('Token'));
    $validator = Validator::make($request->all(), [
      'UserId'     => 'required',
    ]);
    if ($validator->fails()) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => 'Something went wrong.',
        "TotalCount" => count($validator->errors()),
        "Data" => array('Error' => $validator->errors())
      ], 200);
    }
    if ($UserId != $request->UserId) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => 'Invalid token OR user id.',
        "TotalCount" => 0,
        "Data" => []
      ], 200);
    }

    if ($UserId) {
      $detals = UserBankDetail::where('UserId', $UserId)->first();
      if ($detals) {
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Successfully get data.',
          "TotalCount" => $detals->count(),
          "Data" => array('BankDetails' => $detals)
        ], 200);
      } else {
        /* $arrayEmpty = [
                  "PaymenstTypeId" => "",
                  "BankName" => "",
                  "AccountBeneficiary" => "",
                  "AccountNumber" => "",
                  "BankBranch" => "",
                  "CountryId" => "",
                  "BankCity" => "",
                  "SwiftCode" => "",
                  "IBANNumber" => "",
                  "ABANumber" => "",
                  "BankCorrespondent" => "",
                  "VATNumber" => "",
                  "MT4LoginNumber" => ""
              ]; */
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'No payment details found. Please fill payment details.',
          "TotalCount" => 0,
          "Data" => array('BankDetails' => [])
        ], 200);
      }
    } else {
      return response()->json([
        'IsSuccess' => false,
        'Message' => 'Invalid token OR user id.',
        "TotalCount" => 0,
        'Data' => []
      ], 200);
    }
  }

  public function UpdatePaymentDetail(Request $request)
  {
    $check = new UserToken();
    $UserId = $check->validToken($request->header('Token'));
    if ($UserId) {
      $validator = Validator::make($request->all(), [
        'PaymentTypeId' => 'required',
        'ABANumber' => 'nullable',
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Something went wrong. Please enter valid input.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }
      $details = UserBankDetail::where('UserId', $UserId)->first();
      // return $details; die;

      if ($details) {
        $details->PaymentTypeId = $request->PaymentTypeId;

        /*if ($request->hasFile('BankStatement')) {
                  $image = $request->file('BankStatement');
                  $name = 'BankStatement-'.$UserId.''.time().'.'.$image->getClientOriginalExtension();
                  $destinationPath = storage_path('app/BankStatement');
                  $image->move($destinationPath, $name);
                  // $full_path = env('STORAGE_URL') . 'storage/app/adds/'.$name;
              }else{ 
                  $name = "";
              }*/

        if ($request->PaymentTypeId == 2) {
          $details->MT4LoginNumber = (empty($request->MT4LoginNumber) ? NULL : $request->MT4LoginNumber);
        } else {
          $details->BankName = $request->BankName;
          $details->AccountBeneficiary = $request->AccountBeneficiary;
          $details->AccountNumber = $request->AccountNumber;
          $details->BankBranch = $request->BankBranch;
          $details->CountryId = (empty($request->CountryId) ? NULL : $request->CountryId);
          $details->BankCity = $request->BankCity;
          $details->SwiftCode = $request->SwiftCode;
          $details->IBANNumber = $request->IBANNumber;
          $details->BSB = $request->BSB;
          $details->SortCode = $request->SortCode;
          $details->AccountCurrency = (empty($request->AccountCurrency) ? NULL : $request->AccountCurrency);
          $details->ABANumber = (empty($request->ABANumber) ? NULL : $request->ABANumber);
          $details->BankCorrespondent = (empty($request->BankCorrespondent) ? NULL : $request->BankCorrespondent);
          $details->VATNumber = (empty($request->VATNumber) ? NULL : $request->VATNumber);
          // $details->BankStatement = $name; 
        }
        $details->PaymentTypeId =  $request->PaymentTypeId;
        $details->UpdatedBy = $UserId;
        $details->save();  // update bank details

        $details = $details->refresh();
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Payment details updated successfully.',
          "TotalCount" => $details->count(),
          "Data" => array('BankDetails' => $details)
        ], 200);
      } else {
        $details = new UserBankDetail(); // create new record and save details
        if ($request->PaymentTypeId == 2) {
          $details->MT4LoginNumber = (empty($request->MT4LoginNumber) ? NULL : $request->MT4LoginNumber);
        } else {
          $details->PaymentTypeId = $request->PaymentTypeId;
          $details->BankName = $request->BankName;
          $details->AccountBeneficiary = $request->AccountBeneficiary;
          $details->AccountNumber = $request->AccountNumber;
          $details->BankBranch = $request->BankBranch;
          $details->CountryId = (empty($request->CountryId) ? NULL : $request->CountryId);
          $details->BankCity = $request->BankCity;
          $details->SwiftCode = $request->SwiftCode;
          $details->IBANNumber = (empty($request->IBANNumber) ? NULL : $request->IBANNumber);
          $details->BSB = $request->BSB;
          $details->SortCode = $request->SortCode;
          $details->AccountCurrency = (empty($request->AccountCurrency) ? NULL : $request->AccountCurrency);
          $details->ABANumber = (empty($request->ABANumber) ? NULL : $request->ABANumber);
          $details->BankCorrespondent = (empty($request->BankCorrespondent) ? NULL : $request->BankCorrespondent);
          $details->VATNumber = (empty($request->VATNumber) ? NULL : $request->VATNumber);
        }
        $details->UserId = $UserId;
        $details->CreatedBy = $UserId;
        $details->PaymentTypeId =  $request->PaymentTypeId;
        $details->save();  // update bank details

        $details = $details->refresh();
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Payment details updated successfully.',
          "TotalCount" => $details->count(),
          "Data" => array('BankDetails' => $details)
        ], 200);
      }
    } else {
      return response()->json([
        'IsSuccess' => false,
        'Message' => 'Token not found.',
        "TotalCount" => 0,
        'Data' => []
      ], 200);
    }
  }

  public function ShowSubAffiliate(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));

      if ($UserId) {
        $parent = User::where('UserId', $UserId)->first();
        $subaffiliate = User::where('ParentId', $UserId)
          ->where('IsDeleted', 1);

        if (isset($request->SubaffiliateId) && $request->SubaffiliateId != '') {
          $SubaffiliateId = $request->SubaffiliateId;
          $subaffiliate->where('UserId', $SubaffiliateId);
        }
        $TimeZoneOffSet = $request->TimeZoneOffSet;
        $sub = $subaffiliate->get();

        if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
          $from = $request->DateForm;
          $to =  $request->DateTo;
        } else {
          $from = null;
          $to =  null;
        }

        // if select afifiliate filter
        if (isset($request->SubaffiliateId) && $request->SubaffiliateId != '') {
          $UserArray = [];
          $UserId = $request->SubaffiliateId;
          $userData = User::find($UserId);
          $Revenue = 0;
          $UserSubRevenue = UserSubRevenue::whereHas('UserRevenuePayment', function ($qr) use ($UserId) {
            $qr->where('UserId', $UserId)->where('PaymentStatus', 1);
          })->where('UserId', $parent['UserId']);
          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to =  $request->DateTo;
            $UserSubRevenue->whereHas('UserRevenuePayment', function ($qr) use ($UserId, $from, $to) {
              $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1);
            });
          }
          $UserSubRevenue = $UserSubRevenue->get();

          if ($parent->CurrencyId == 1) {
            foreach ($UserSubRevenue as $Rev) {
              $Revenue = $Revenue + $Rev['USDAmount'];
            }
          } else if ($parent->CurrencyId == 2) {
            foreach ($UserSubRevenue as $Rev) {
              $Revenue = $Revenue + $Rev['AUDAmount'];
            }
          } else if ($parent->CurrencyId == 3) {
            foreach ($UserSubRevenue as $Rev) {
              $Revenue = $Revenue + $Rev['EURAmount'];
            }
          }

          if ($userData['EmailVerified'] == 0) {
            $status = "Pending email verification";
          } else if ($userData['AdminVerified'] == 0) {
            $status = "Pending admin approval";
          } else if ($userData['AdminVerified'] == 1) {
            $status = "Active";
          } else if ($userData['AdminVerified'] == 2) {
            $status = "Reject";
          }
          $ParrentUser = User::find($userData->ParentId);
          if ($ParrentUser) {
            $ParrentData = $ParrentUser;
          } else {
            $ParrentData = $parent;
          }

          $Array = [
            'SubAffiliateName' => $userData['FirstName'] . ' ' . $userData['LastName'],
            'ParentAffilate' => $ParrentData['FirstName'] . ' ' . $ParrentData['LastName'],
            'EmailId' => $userData['EmailId'],
            'EmailVerified' => $userData['EmailVerified'],
            'AdminVerified' => $userData['AdminVerified'],
            'IsEnabled' => $userData['IsEnabled'],
            'City' => $userData['City'],
            'Status' => $status,
            'UserId' => $userData['UserId'],
            'RevenueInPersantage' => 0,
            'Revenue' => round($Revenue, 4),
            'Date' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($userData['CreatedAt'])))
          ];
          array_push($UserArray, $Array);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Successfully get sub affiliate data.',
            "TotalCount" => $subaffiliate->count(),
            "Data" => array('userData' =>  $UserArray)
          ], 200);
        } else {
          // New
          if ($sub->count() >= 1) {
            $count = 0;
            $Users = User::with('SubAffiliate')->where('UserId', $UserId)->first();
            if (count($Users['SubAffiliate']) != 0) {
              $this->call2 = $this->call2($Users['SubAffiliate'], $TimeZoneOffSet, $parent, $from, $to);
            }
            $arrList = $this->call2;
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
          // End. New
        }
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
      return response()->json($res);
    }
  }

  public function UserSubAffiliateList(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));

      if ($UserId) {
        $parent = User::where('UserId', $UserId)->first();
        $subaffiliate = User::where('ParentId', $UserId)
          ->where('IsDeleted', 1);
        $sub = $subaffiliate->get();

        if ($sub->count() >= 1) {
          $UserArray = [];
          foreach ($sub as $key => $value) {
            $Array = [
              'SubAffiliateName' => $value['FirstName'] . ' ' . $value['LastName'],
              'SubAffiliateId' => $value['UserId']
            ];
            array_push($UserArray, $Array);
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Successfully get sub affiliate data.',
            "TotalCount" => $subaffiliate->count(),
            "Data" => array('userData' =>  $UserArray)
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'No sub affiliate Found.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
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
    }
    return response()->json($res);
  }

  public function SubAffiliateTree(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));

      if ($UserId) {
        $parent = User::where('UserId', $UserId)->first();
        $subaffiliate = User::where('ParentId', $UserId)
          ->where('IsDeleted', 1);
        $subList = $subaffiliate->get();

        if ($subList->count() >= 1) {
          $Users = User::with('SubAffiliate')->where('UserId', $UserId)->get();

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Successfully get sub affiliate tree.',
            "TotalCount" => $Users->count(),
            "Data" => array('userData' => $Users)
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
    }
    return response()->json($res);
  }

  public function SubAffiliateList(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));

      if ($UserId) {
        $parent = User::where('UserId', $UserId)->first();
        $subaffiliate = User::where('ParentId', $UserId)
          ->where('IsDeleted', 1);
        $subList = $subaffiliate->get();
        $TimeZoneOffSet = $request->TimeZoneOffSet;
        if ($TimeZoneOffSet == '')
          $TimeZoneOffSet = 0;

        if ($subList->count() >= 1) {
          $Users = User::with('SubAffiliate')->where('UserId', $UserId)->first();

          // $arr = [
          //   'userName' => $Users['FirstName'] . ' ' . $Users['LastName'],
          //   'EmailId' => $Users['EmailId'],
          //   'ID' => $Users['UserId'],
          //   'IsParent' => true,
          //   'ParentId' => null,
          //   'CreatedAt' => date('d/m/Y', strtotime($TimeZoneOffSet . " minutes", strtotime($Users['CreatedAt'])))
          // ];
          // array_push($this->call, $arr);
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
    // foreach ($data as $value1) {
    //   if (count($value1['SubAffiliate']) != 0) {
    //     $this->call($value1['SubAffiliate'], $TimeZoneOffSet);
    //   }
    // }
    return $this->call;
  }

  public function call2($data, $TimeZoneOffSet, $parent, $from, $to)
  {
    foreach ($data as $value1) {
      $UserId = $value1['UserId'];
      $Revenue = 0;
      $Users = User::with('SubAffiliate')->where('UserId', $UserId)->first();
      $UserSubRevenue = UserSubRevenue::whereHas('UserRevenuePayment', function ($qr) use ($UserId) {
        $qr->where('UserId', $UserId)->where('PaymentStatus', 1);
      })->where('UserId', $parent['UserId']);

      if ($from != '' && $to != '') {
        $UserSubRevenue->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
          $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1);
        });
      }
      $UserSubRevenue = $UserSubRevenue->get();

      if ($parent['CurrencyId'] == 1) {
        foreach ($UserSubRevenue as $Rev) {
          $Revenue = $Revenue + $Rev['USDAmount'];
        }
      } else if ($parent['CurrencyId'] == 2) {
        foreach ($UserSubRevenue as $Rev) {
          $Revenue = $Revenue + $Rev['AUDAmount'];
        }
      } else if ($parent['CurrencyId'] == 3) {
        foreach ($UserSubRevenue as $Rev) {
          $Revenue = $Revenue + $Rev['EURAmount'];
        }
      }

      if ($value1['EmailVerified'] == 0) {
        $status = "Pending email verification";
      } else if ($value1['AdminVerified'] == 0) {
        $status = "Pending admin approval";
      } else if ($value1['AdminVerified'] == 1) {
        $status = "Active";
      } else if ($value1['AdminVerified'] == 2) {
        $status = "Reject";
      }
      $Array = [
        'SubAffiliateName' => $value1['FirstName'] . ' ' . $value1['LastName'],
        'ParentAffilate' => $parent['FirstName'] . ' ' . $parent['LastName'],
        'EmailId' => $value1['EmailId'],
        'EmailVerified' => $value1['EmailVerified'],
        'AdminVerified' => $value1['AdminVerified'],
        'IsEnabled' => $value1['IsEnabled'],
        'City' => $value1['City'],
        'Status' => $status,
        'UserId' => $value1['UserId'],
        'RevenueInPersantage' => 0,
        'Revenue' => round($Revenue, 4),
        'Date' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value1['CreatedAt'])))
      ];
      array_push($this->call2, $Array);
    }
    return $this->call2;
  }

  public function AffiliateValidateToken(Request $request)
  {
    try {
      $token_id = $request->header('token');
      $log_user = UserToken::where('Token', $token_id)->first();
      if ($log_user) {
        $user = User::where('UserId', $log_user->UserId)->first();
        $totalUnreadMessage = SupportManager::where('ToUserId', $user->UserId)->where('IsRead', 0)->count();
        return response()->json([
          'Message' => 'Login successfully.',
          'IsSuccess' => true,
          'Data' => array(
            'User' => array(
              'UserId' => $user->UserId,
              'FirstName' => $user->FirstName,
              'LastName' => $user->LastName,
              'Email' => $user->EmailId,
              'RoleId' => $user->RoleId,
              'IsAllowSubAffiliate' => $user->IsAllowSubAffiliate,
              'TrackingId' => $user->TrackingId,
              'CreatedAt' => $user->CreatedAt,
              'UpdatedAt' => $user->UpdatedAt
            )
          ),
          'SupportCount' => $totalUnreadMessage,
          'AccessToken' => $token_id
        ], 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'User token not found.',
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
        'Data' => null
      ];
    }
    return response()->json($res, 200);
  }

  /* 
    Statistics reports 
  */
  public function GeneralStatisticsAffiliate(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));
      if ($UserId) {
        $userData = User::find($UserId);
        $CurrencyTitle = 'USD'; // Default Currency USD
        if ($userData->CurrencyId == 1) {
          $CurrencyTitle = 'USD';
        } else if ($userData->CurrencyId == 2) {
          $CurrencyTitle = 'AUD';
        } else if ($userData->CurrencyId == 3) {
          $CurrencyTitle = 'EUR';
        } else {
          $CurrencyTitle = 'USD';
        }
        $ImpressionsTotal = 0;
        $CampaignsClicksTotal = 0;
        $CTRTotal = 0;
        $LeadsTotal = 0;
        $LeadsQualifiedTotal = 0;
        $ConvertedAccountsTotal = 0;
        $QualifiedAccountsTotal = 0;
        $TotalCPLCPA = 0;
        $TotalUserCommission = 0;
        $AllTotalSpreadAmount = 0;
        $TotalShareAmount = 0;
        $TotalMasterAffiliateCommission = 0;
        $TotalMasterAffiliateDeduct = 0;
        $TotalTotalCommission = 0;
        $TotalNetRevenue = 0;
        $TotalNetUserBonus = 0;
        $TotalDeducAmount = 0;
        $RevenueModelType = [];
        $CampaignListArr = [];
        $CampaignListExport = [];
        // Filter data list
        $CampaignListAll = Campaign::where('UserId', $UserId)->orderBy('CampaignName')->get(); // GetCampaignListAll
        $assignAdList = CampaignAdList::where('UserId', $UserId)->pluck('AdId');
        $UserBrand = UserAdBrand::where('UserId', $UserId)->pluck('AdBrandId');
        $UserType  = UserAdType::where('UserId', $UserId)->pluck('AdTypeId');
        $GetAdList = Ad::whereHas('AffiliatAds', function ($qr) use ($UserId, $assignAdList) {
          $qr->where('UserId', $UserId);
        })->orWhere('IsPublic', 1)->whereIn('AdBrandId', $UserBrand)->whereIn('AdTypeId', $UserType)->orderBy('Title')->get();
        $GetAdTypeList = AdTypeMaster::orderBy('Title')->get();
        $GetAdBrandList = AdBrandMaster::orderBy('Title')->get();
        $GetLanguageList = LanguageMaster::orderBy('LanguageName')->get();
        $RevenueModelList = RevenueModel::whereHas('UserRevenueType', function ($qr) use ($UserId) {
          $qr->where('UserId', $UserId);
        })->orderBy('RevenueModelName')->get();
        $RevenueModelTypeList = RevenueType::orderBy('RevenueTypeName')->get();
        // End. Filter data list
        $UserBonus = UserBonus::with('User', 'RevenueModel.Revenue')->where('USDAmount', '>', 0)->where('UserId', $UserId);
        $UserSubRevenue = UserSubRevenue::with('User')->where('USDAmount', '>', 0)->where('UserId', $UserId);
        if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
          $from = $request->DateForm;
          $to = $request->DateTo;
          $UserBonus->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
          $UserSubRevenue->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
            $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1);
          });
        }
        $UserBonus = $UserBonus->get();
        $UserSubRevenue = $UserSubRevenue->get();

        $CampaignList = Campaign::with('User')->orderBy('CampaignId', 'desc')->where('UserId', $UserId);
        if (isset($request->CampaignId) && $request->CampaignId != '' && count($request->CampaignId) != 0) {
          $CampaignList = $CampaignList->whereIn('CampaignId', $request->CampaignId);
          $UserBonus = [];
        }
        if (isset($request->RevenueModelId) && $request->RevenueModelId != '' && count($request->RevenueModelId) != 0) {
          $CampaignList = $CampaignList->whereIn('RevenueModelId', $request->RevenueModelId);
          $UserBonus = [];
        }
        if (isset($request->RevenueModelType) && $request->RevenueModelType != '' && count($request->RevenueModelType) != 0) {
          $RevenueModelType = $request->RevenueModelType;
          $CampaignList = $CampaignList->whereHas('CommissionType', function ($qr) use ($RevenueModelType) {
            $qr->whereIn('RevenueTypeId', $RevenueModelType);
          });
          if (in_array('7', $RevenueModelType)) {
            $UserBonus = $UserBonus;
          } else {
            $UserBonus = [];
          }
          if (in_array('8', $RevenueModelType)) {
            $UserSubRevenue = $UserSubRevenue;
            // $RoyalRevenueTop = $RoyalRevenueTop;
          } else {
            $UserSubRevenue = [];
            // $RoyalRevenueTop = [];
          }
        }
        if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
          $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
          $CampaignList = $CampaignList->whereHas('CampaignAdList', function ($qr2) use ($AdsList) {
            $qr2->whereIn('AdId', $AdsList);
          });
          $UserBonus = [];
        }
        if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
          $AdType = $request->AdType;
          $AdIdList = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
          $CampaignList = $CampaignList->whereHas('CampaignAdList', function ($qr3) use ($AdIdList) {
            $qr3->whereIn('AdId', $AdIdList);
          });
          $UserBonus = [];
        }
        if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
          $AdBrand = $request->AdBrand;
          $AdIdList = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
          $CampaignList = $CampaignList->whereHas('CampaignAdList', function ($qr4) use ($AdIdList) {
            $qr4->whereIn('AdId', $AdIdList);
          });
          $UserBonus = [];
        }
        if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
          $AdLanguage = $request->AdLanguage;
          $AdIdList = Ad::whereIn('LanguageId', $AdLanguage)->pluck('AdId');
          $CampaignList = $CampaignList->whereHas('CampaignAdList', function ($qr4) use ($AdIdList) {
            $qr4->whereIn('AdId', $AdIdList);
          });
          $UserBonus = [];
        }
        $CampaignList = $CampaignList->get();

        // Campaign
        foreach ($CampaignList as $Campaign) {
          // return $Campaign;
          $RevenueModelOfCamp = RevenueModel::where('RevenueModelId', $Campaign['RevenueModelId'])->first();
          $CampaignAddIds = CampaignAdList::where('CampaignId', $Campaign['CampaignId']);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIdList = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $CampaignAddIds = $CampaignAddIds->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $CampaignAddIds = $CampaignAddIds->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdLanguage = $request->AdLanguage;
            $AdIdList = Ad::whereIn('LanguageId', $AdLanguage)->pluck('AdId');
            $CampaignAddIds = $CampaignAddIds->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdIdList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $CampaignAddIds = $CampaignAddIds->whereIn('AdId', $AdIdList);
          }
          $CampaignAddIds = $CampaignAddIds->pluck('CampaignAddId');

          // CampaignsClicks
          $CampaignsClicks = CampaignAdClick::whereIn('CampaignAddId', $CampaignAddIds);
          // Impressions
          $Impressions = CampaignAdImpression::whereIn('CampaignAddId', $CampaignAddIds);
          // Leads
          $Leads = Lead::where('CampaignId', $Campaign['CampaignId']);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIdList = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdIdList = Ad::whereIn('LanguageId', $request->AdLanguage)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdIdList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdIdList);
          }

          $LeadList = Lead::where('CampaignId', $Campaign['CampaignId']);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIdList = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdIdList = Ad::whereIn('LanguageId', $request->AdLanguage)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdIdList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIdList);
          }
          $LeadList = $LeadList->pluck('LeadId');

          // LeadsQualified
          $LeadsQualified = Lead::where('CampaignId', $Campaign['CampaignId'])->whereHas('LeadStatus', function ($qr) {
            $qr->where('IsValid', 1);
          });
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIdList = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdIdList = Ad::whereIn('LanguageId', $request->AdLanguage)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdIdList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdIdList);
          }

          // ConvertedAccounts
          $ConvertedAccounts = Lead::where('CampaignId', $Campaign['CampaignId'])->where('IsConverted', 1);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIdList = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdIdList = Ad::whereIn('LanguageId', $request->AdLanguage)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdIdList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIdList);
          }

          // QualifiedAccounts
          $RevenueModel = RevenueModel::where('RevenueModelId', $Campaign['RevenueModelId'])->where('RevenueTypeId', 4)->first();

          if ($RevenueModel) {
            $RevenueModelLog = RevenueModelLog::where('RevenueModelId', $RevenueModel['RevenueModelId'])->pluck('RevenueModelLogId');
            $QualifiedAccounts = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLog);
          }
          $RevenueModelCPL = RevenueModel::where('RevenueModelId', $Campaign['RevenueModelId'])->whereIn('RevenueTypeId', [1, 2, 3, 4])->first();
          if ($RevenueModelCPL) {
            $RevenueModelLogCPL = RevenueModelLog::where('RevenueModelId', $RevenueModelCPL['RevenueModelId'])->pluck('RevenueModelLogId');
            $CPLAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogCPL);
          }
          $RevenueModelShare = RevenueModel::where('RevenueModelId', $Campaign['RevenueModelId'])->whereIn('RevenueTypeId', [5, 6])->first();
          if ($RevenueModelShare) {
            $RevenueModelLogShare = RevenueModelLog::where('RevenueModelId', $RevenueModelShare['RevenueModelId'])->pluck('RevenueModelLogId');
            $ShareAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogShare);
          }
          $LeadActivityList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('LeadActivityId', '!=', null)->pluck('LeadActivityId');
          //  Commission count
          $UserAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserBonusId', '=', null)->where('PaymentStatus', 1);
          $UserSpreadAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserBonusId', '=', null)->where('PaymentStatus', 1);
          // MasterAffiliateCommission count
          $UserSubRevenueIdList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('PaymentStatus', 1)->where('USDAmount', '<', 0)->pluck('UserSubRevenueId');
          $UserSubRevenueDeduct = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('UserId', $Campaign['User']['UserId'])->where('PaymentStatus', 1);
          $MasterAmountMain = UserSubRevenue::whereIn('UserSubRevenueId', $UserSubRevenueIdList)->where('USDAmount', '<', 0)->where('UserId', $Campaign['User']['UserId']);
          $RoyalSubAmountMain = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '<', 0);  // cut sub revenue from royal
          // Bonus count
          $BonusMain = UserRevenuePayment::where('UserId', $Campaign['User']['UserId'])->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);

          // Date filter apply 
          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to = $request->DateTo;
            $CampaignsClicks->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);  // CampaignsClicks
            $Impressions->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);  // Impressions
            $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // 4.Leads
            $LeadsQualified->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // LeadsQualified
            $ConvertedAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // ConvertedAccounts
            if ($RevenueModel) {
              $QualifiedAccounts->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to); // QualifiedAccounts
            }
            if ($RevenueModelCPL) {
              $CPLAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            }
            if ($RevenueModelShare) {
              $ShareAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            }
            // $RoyalAmount->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
            // $RoyalSpreadAmount->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
            $UserAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $MasterAmountMain->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
              $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1);
            });
            $RoyalSubAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $BonusMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserSubRevenueDeduct->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          }
          $CampaignsClicks = $CampaignsClicks->count(); // CampaignsClicks
          $Impressions = $Impressions->count(); // Impressions
          if ($Impressions == 0)
            $CTR = 0;
          else
            $CTR = $CampaignsClicks / $Impressions;
          $Leads = $Leads->count(); // Leads
          $LeadsQualified = $LeadsQualified->count(); // LeadsQualified
          $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts
          if ($RevenueModel) {
            $QualifiedAccounts = $QualifiedAccounts->count(); // QualifiedAccounts
          } else {
            $QualifiedAccounts = 0; // QualifiedAccounts
          }
          // cpl/ccpl/cpa/ccpa amount
          if ($RevenueModelCPL) {
            if ($userData->CurrencyId == 1) {
              $CPLMainAmount = $CPLAmount->sum('USDAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
            } else if ($userData->CurrencyId == 2) {
              $CPLMainAmount = $CPLAmount->sum('AUDAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadAUDAmount');
            } else if ($userData->CurrencyId == 3) {
              $CPLMainAmount = $CPLAmount->sum('EURAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadEURAmount');
            } else {
              $CPLMainAmount = $CPLAmount->sum('USDAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
            }
            $CPLTotalAmount = $CPLMainAmount + $CPLSpreadAmount;
            $UserAmountMain = $CPLMainAmount + $CPLSpreadAmount;
          } else {
            $CPLTotalAmount = 0;
          }
          // Revenue Share + FX Share amount
          if ($RevenueModelShare) {
            if ($userData->CurrencyId == 1) {
              $ShareMainAmount = $ShareAmount->sum('USDAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
            } else if ($userData->CurrencyId == 2) {
              $ShareMainAmount = $ShareAmount->sum('AUDAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadAUDAmount');
            } else if ($userData->CurrencyId == 3) {
              $ShareMainAmount = $ShareAmount->sum('EURAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadEURAmount');
            } else {
              $ShareMainAmount = $ShareAmount->sum('USDAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
            }
            $ShareTotalAmount = $ShareMainAmount + $ShareSpreadAmount;
            $UserAmountMain = $ShareMainAmount + $ShareSpreadAmount;
          } else {
            $ShareTotalAmount = 0;
          }

          if ($userData->CurrencyId == 1) {
            $UserAmount = $UserAmount->sum('USDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadUSDAmount');
            $DeducAmount = $UserSubRevenueDeduct->sum('USDAmount');
          } else if ($userData->CurrencyId == 2) {
            $UserAmount = $UserAmount->sum('AUDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadAUDAmount');
            $DeducAmount = $UserSubRevenueDeduct->sum('AUDAmount');
          } else if ($userData->CurrencyId == 3) {
            $UserAmount = $UserAmount->sum('EURAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadEURAmount');
            $DeducAmount = $UserSubRevenueDeduct->sum('EURAmount');
          } else {
            $UserAmount = $UserAmount->sum('USDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadUSDAmount');
            $DeducAmount = $UserSubRevenueDeduct->sum('USDAmount');
          }
          $Commission = $UserAmount + $UserSpreadAmount; // Commission count          
          if ($RevenueModelShare) {
            $TotalUserAmount = $UserAmount;
            $TotalSpreadAmount = $UserSpreadAmount;
          } else {
            $TotalUserAmount = 0;
            $TotalSpreadAmount = 0;
          }

          if ($userData->CurrencyId == 1) {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          } elseif ($userData->CurrencyId == 2) {
            $MasterAmount = $MasterAmountMain->sum('AUDAmount');
          } elseif ($userData->CurrencyId == 3) {
            $MasterAmount = $MasterAmountMain->sum('EURAmount');
          } else {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          }
          $MasterAffiliateCommission = $MasterAmount; // Master Affiliate Commission count

          if ($userData->CurrencyId == 1) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          } elseif ($userData->CurrencyId == 2) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('AUDAmount');
          } elseif ($userData->CurrencyId == 3) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('EURAmount');
          } else {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          }
          $RoyalSubAmountDebit = $RoyalSubAmount; // Master Affiliate Commission count

          if ($userData->CurrencyId == 1) {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          } else if ($userData->CurrencyId == 2) {
            $BonusAmount = $BonusMain->sum('AUDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadAUDAmount');
          } else if ($userData->CurrencyId == 3) {
            $BonusAmount = $BonusMain->sum('EURAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadEURAmount');
          } else {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          }
          $Bonus = $BonusAmount + $BonusSpreadAmount; // Bonus count

          $TotalCommission = $Commission + $MasterAffiliateCommission; // Total Commission
          // $RoyalRevenue = $Commission; // Royal Revenue

          $RMName = $RevenueModelOfCamp['RevenueModelName'];
          // if (strlen($RMName) > 15)
          //   $RMName = substr($RMName, 0, 13) . "..";
          $CampaignName = $Campaign['CampaignName'];
          // if (strlen($CampaignName) > 15)
          //   $CampaignName = substr($CampaignName, 0, 13) . "..";

          $CampaignArr = [
            "CampaignId" => $Campaign['CampaignId'],
            "Campaign" => $CampaignName,
            "RevenueModelId" => $RevenueModelOfCamp['RevenueModelId'],
            "RevenueModel" => $RMName,
            "RevenueType" => $RevenueModelOfCamp['RevenueTypeId'],
            "Impressions" => $Impressions,
            "CampaignsClicks" => $CampaignsClicks,
            "CTR" => round($CTR, 2),
            "Leads" => $Leads,
            "LeadsQualified" => $LeadsQualified,
            "ConvertedAccounts" => $ConvertedAccounts,
            "QualifiedAccounts" => $QualifiedAccounts,
            "CPL/CPA" => round($CPLTotalAmount, 4),
            "Commission" => round($TotalUserAmount, 4),
            "SpreadAmount" => round($TotalSpreadAmount, 4),
            "ShareAmount" => round($ShareTotalAmount, 4),
            "Total" => round($UserAmountMain, 4),
            "MasterAffiliateCommission" => round($DeducAmount, 4),
            "TotalCommission" => round($TotalCommission, 4),
            "NetRevenue" => round($UserAmountMain + $MasterAffiliateCommission, 4),
            "Bonus" => 0,
          ];
          array_push($CampaignListArr, $CampaignArr);
          // Export array
          $CampaignArrExport = [
            "Campaign" => $Campaign['CampaignName'],
            "Revenue Model" => $RevenueModelOfCamp['RevenueModelName'],
            "Impressions" => $Impressions,
            "Campaigns Clicks" => $CampaignsClicks,
            "CTR" => round($CTR, 2),
            "Leads" => $Leads,
            "Leads Qualified" => $LeadsQualified,
            "Converted Accounts" => $ConvertedAccounts,
            "Qualified Accounts" => $QualifiedAccounts,
            "CPL/CPA" => round($CPLTotalAmount, 4),
            "Affiliate Commission" => round($TotalUserAmount, 4),
            "Affiliate Spread" => round($TotalSpreadAmount, 4),
            "Affiliate Revenue " . $CurrencyTitle => round($UserAmountMain, 4),
            "Master Revenue " . $CurrencyTitle => round($DeducAmount, 4),
            "Net Revenue " . $CurrencyTitle => round($UserAmountMain + $MasterAffiliateCommission, 4),
          ];
          array_push($CampaignListExport, $CampaignArrExport);

          $ImpressionsTotal = $ImpressionsTotal + $Impressions;
          $CampaignsClicksTotal = $CampaignsClicksTotal + $CampaignsClicks;
          if ($ImpressionsTotal == 0)
            $CTRTotal = 0;
          else
            $CTRTotal = $CampaignsClicksTotal / $ImpressionsTotal;
          $LeadsTotal = $LeadsTotal + $Leads;
          $LeadsQualifiedTotal = $LeadsQualifiedTotal + $LeadsQualified;
          $ConvertedAccountsTotal = $ConvertedAccountsTotal + $ConvertedAccounts;
          $QualifiedAccountsTotal = $QualifiedAccountsTotal + $QualifiedAccounts;
          $TotalCPLCPA = $TotalCPLCPA + $CPLTotalAmount;
          $TotalUserCommission = $TotalUserCommission + $TotalUserAmount;
          $AllTotalSpreadAmount = $AllTotalSpreadAmount + $TotalSpreadAmount;
          $TotalShareAmount = $TotalShareAmount + $ShareTotalAmount;
          $TotalMasterAffiliateDeduct = $TotalMasterAffiliateDeduct + $MasterAffiliateCommission;
          $TotalTotalCommission = $TotalTotalCommission + $UserAmountMain;
          $TotalNetRevenue = $TotalNetRevenue + $UserAmountMain + $MasterAffiliateCommission;
          $TotalDeducAmount = $TotalDeducAmount + $DeducAmount;
        }

        // Bonus
        foreach ($UserBonus as $Bonus) {
          if ($userData->CurrencyId == 1) {
            $TotalCommission = $Bonus['USDAmount'];
          } else if ($userData->CurrencyId == 2) {
            $TotalCommission = $Bonus['AUDAmount'];
          } else if ($userData->CurrencyId == 3) {
            $TotalCommission = $Bonus['EURAmount'];
          } else {
            $TotalCommission = $Bonus['USDAmount'];
          }
          $RMName = $Bonus['RevenueModel']['RevenueModelName'];
          $TotalTotalCommission = $TotalTotalCommission + $TotalCommission;
          $TotalNetRevenue = $TotalNetRevenue + $TotalCommission;
          $TotalNetUserBonus = $TotalNetUserBonus + $TotalCommission;
        }
        if ($TotalNetUserBonus > 0 || in_array('7', $RevenueModelType)) {
          $BonusArr = [
            "CampaignId" => '',
            "Campaign" => '',
            "RevenueModelId" => '',
            "RevenueModel" => 'Bonus',
            "RevenueType" => 'Bonus',
            "Impressions" => 0,
            "CampaignsClicks" => 0,
            "CTR" => 0,
            "Leads" => 0,
            "LeadsQualified" => 0,
            "ConvertedAccounts" => 0,
            "QualifiedAccounts" => 0,
            "CPL/CPA" => 0,
            "Commission" => 0,
            "SpreadAmount" => 0,
            "ShareAmount" => 0,
            "Total" => round($TotalNetUserBonus, 4),
            "MasterAffiliateCommission" => 0,
            "TotalCommission" => 0,
            "NetRevenue" => round($TotalNetUserBonus, 4),
            "Bonus" => round($TotalNetUserBonus, 4),
          ];
          array_push($CampaignListArr, $BonusArr);
          // Export
          $BonusArrExport = [
            "Campaign" => '',
            "Revenue Model" => 'Bonus',
            "Impressions" => 0,
            "Campaigns Clicks" => 0,
            "CTR" => 0,
            "Leads" => 0,
            "Leads Qualified" => 0,
            "Converted Accounts" => 0,
            "Qualified Accounts" => 0,
            "CPL/CPA" => 0,
            "Affiliate Commission" => 0,
            "Affiliate Spread" => 0,
            "Affiliate Revenue " . $CurrencyTitle => round($TotalNetUserBonus, 4),
            "Master Revenue " . $CurrencyTitle => 0,
            "Net Revenue " . $CurrencyTitle => round($TotalNetUserBonus, 4),
          ];
          array_push($CampaignListExport, $BonusArrExport);
        }

        // User Sub Revenue
        foreach ($UserSubRevenue as $SubRevData) {
          if ($userData->CurrencyId == 1) {
            $TotalSubRev = $SubRevData['USDAmount'];
          } else if ($userData->CurrencyId == 2) {
            $TotalSubRev = $SubRevData['AUDAmount'];
          } else if ($userData->CurrencyId == 3) {
            $TotalSubRev = $SubRevData['EURAmount'];
          } else {
            $TotalSubRev = $SubRevData['USDAmount'];
          }
          $TotalMasterAffiliateCommission = $TotalMasterAffiliateCommission + $TotalSubRev;
          $TotalNetRevenue = $TotalNetRevenue + $TotalSubRev;
        }
        if ($TotalMasterAffiliateCommission > 0 || in_array('8', $RevenueModelType)) {
          $SubRevDataArr = [
            "CampaignId" => '',
            "Campaign" => '',
            "RevenueModelId" => '',
            "RevenueModel" => 'Sub Affiliate',
            "RevenueType" => '',
            "Impressions" => 0,
            "CampaignsClicks" => 0,
            "CTR" => 0,
            "Leads" => 0,
            "LeadsQualified" => 0,
            "ConvertedAccounts" => 0,
            "QualifiedAccounts" => 0,
            "CPL/CPA" => 0,
            "Commission" => 0,
            "SpreadAmount" => 0,
            "ShareAmount" => 0,
            "Total" => round($TotalMasterAffiliateCommission, 4),
            "MasterAffiliateCommission" => 0,
            "TotalCommission" => round($TotalMasterAffiliateCommission, 4),
            "NetRevenue" => round($TotalMasterAffiliateCommission, 4),
            "Bonus" => 0,
          ];
          array_push($CampaignListArr, $SubRevDataArr);

          $SubRevArrExport = [
            "Campaign" => '',
            "Revenue Model" => 'Sub Affiliate',
            "Impressions" => 0,
            "Campaigns Clicks" => 0,
            "CTR" => 0,
            "Leads" => 0,
            "Leads Qualified" => 0,
            "Converted Accounts" => 0,
            "Qualified Accounts" => 0,
            "CPL/CPA" => 0,
            "Affiliate Commission" => 0,
            "Affiliate Spread" => 0,
            "Affiliate Revenue " . $CurrencyTitle => round($TotalMasterAffiliateCommission, 4),
            "Master Revenue " . $CurrencyTitle => 0,
            "Net Revenue " . $CurrencyTitle => round($TotalMasterAffiliateCommission, 4),
          ];
          array_push($CampaignListExport, $SubRevArrExport);
        }

        $CampaignArrExportTotal = array(
          "Campaign" => '',
          "Revenue Model" => '',
          "Impressions" => $ImpressionsTotal,
          "Campaigns Clicks" => $CampaignsClicksTotal,
          "CTR" => round($CTRTotal, 4),
          "Leads" => $LeadsTotal,
          "Leads Qualified" => $LeadsQualifiedTotal,
          "Converted Accounts" => $ConvertedAccountsTotal,
          "Qualified Accounts" => $QualifiedAccountsTotal,
          "CPL/CPA" => round($TotalCPLCPA, 4),
          "Affiliate Commission" => round($TotalUserCommission, 4),
          "Affiliate Spread" => round($AllTotalSpreadAmount,  4),
          "Affiliate Revenue " . $CurrencyTitle => round($TotalTotalCommission + $TotalMasterAffiliateCommission, 4),
          "Master Revenue " . $CurrencyTitle => $TotalMasterAffiliateDeduct,
          "Net Revenue " . $CurrencyTitle => round($TotalNetRevenue, 4),
        );
        array_push($CampaignListExport, $CampaignArrExportTotal);
        // Export report in xls file
        if ($request->IsExport) {
          Excel::create('GeneralStatisticsAffiliate', function ($excel) use ($CampaignListExport) {
            $excel->sheet('GeneralStatisticsAffiliate', function ($sheet) use ($CampaignListExport) {
              $sheet->fromArray($CampaignListExport);
            });
          })->store('xls', false, true);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export report successfully.',
            "TotalCount" => 1,
            'Data' => ['GeneralStatisticsAffiliate' => $this->storage_path . 'exports/GeneralStatisticsAffiliate.xls'],
          ], 200);
        }

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($CampaignListArr),
          'Data' => array(
            'CampaignList' => $CampaignListAll,
            'TotalDeducAmount' => $TotalDeducAmount,
            'AdList' => $GetAdList,
            'AdTypeList' => $GetAdTypeList,
            'AdBrandList' => $GetAdBrandList,
            'LanguageList' => $GetLanguageList,
            'RevenueModelList' => $RevenueModelList,
            'RevenueModelTypeList' => $RevenueModelTypeList,
            'GeneralStatisticsAffiliate' => $CampaignListArr,
            'GeneralStatisticsAffiliateTotal' => $CampaignArrExportTotal,
          )
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => null
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res);
    }
  }

  public function CampaignStatisticsAffiliate(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));
      if ($UserId) {
        $userData = User::find($UserId);
        $CurrencyTitle = 'USD'; // Default Currency USD
        if ($userData->CurrencyId == 1) {
          $CurrencyTitle = 'USD';
        } else if ($userData->CurrencyId == 2) {
          $CurrencyTitle = 'AUD';
        } else if ($userData->CurrencyId == 3) {
          $CurrencyTitle = 'EUR';
        } else {
          $CurrencyTitle = 'USD';
        }
        $ImpressionsTotal = 0;
        $CampaignsClicksTotal = 0;
        $LeadsTotal = 0;
        $LeadsQualifiedTotal = 0;
        $ConvertedAccountsTotal = 0;
        $QualifiedAccountsTotal = 0;
        $TotalCPLCPA = 0;
        $TotalUserCommission = 0;
        $AllTotalSpreadAmount = 0;
        $TotalShareAmount = 0;
        $TotalMasterAffiliateCommission = 0;
        $TotalTotalCommission = 0;
        $TotalNetRevenue = 0;
        $TotalDeducAmount = 0;
        $TotalNetUserBonus = 0;
        $MasterAffiliateCommission = 0;
        $TotalMasterAffiliateDeduct = 0;
        $RevenueModelType = [];
        $CampaignListArr = [];
        $CampaignListExport = [];

        // Filter data list
        $CampaignListAll = Campaign::where('UserId', $UserId)->orderBy('CampaignName')->get(); // GetCampaignListAll
        $assignAdList = CampaignAdList::where('UserId', $UserId)->pluck('AdId');
        $UserBrand = UserAdBrand::where('UserId', $UserId)->pluck('AdBrandId');
        $UserType  = UserAdType::where('UserId', $UserId)->pluck('AdTypeId');
        $GetAdList = Ad::whereHas(
          'AffiliatAds',
          function ($qr) use ($UserId, $assignAdList) {
            $qr->where('UserId', $UserId);
          }
        )->orWhere('IsPublic', 1)->whereIn('AdBrandId', $UserBrand)->whereIn('AdTypeId', $UserType)->orderBy('Title')->get();
        // $GetAdList = Ad::get(); // GetAdList
        $GetAdTypeList = AdTypeMaster::orderBy('Title')->get(); // GetAdTypeList
        $GetAdBrandList = AdBrandMaster::orderBy('Title')->get(); // GetAdBrandList
        $GetLanguageList = LanguageMaster::orderBy('LanguageName')->get(); // GetLanguageList
        $RevenueModelList = RevenueModel::whereHas('UserRevenueType', function ($qr) use ($UserId) {
          $qr->where('UserId', $UserId);
        })->orderBy('RevenueModelName')->get(); // RevenueModelList
        $RevenueModelTypeList = RevenueType::orderBy('RevenueTypeName')->get(); // RevenueModelTypeList        
        // End. Filter data list 
        $CampaignList = Campaign::with('User')->orderBy('CampaignId', 'desc')->where('UserId', $UserId);
        $UserBonus = UserBonus::with('User', 'RevenueModel.Revenue')->where('USDAmount', '>', 0)->where('UserId', $UserId);
        $UserSubRevenue = UserSubRevenue::with('User')->where('USDAmount', '>', 0)->where('UserId', $UserId);
        if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
          $from = $request->DateForm;
          $to = $request->DateTo;
          $UserBonus->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
          $UserSubRevenue->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
            $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1);
          });
        }
        $UserBonus = $UserBonus->get();
        $UserSubRevenue = $UserSubRevenue->get();

        if (isset($request->CampaignId) && $request->CampaignId != '' && count($request->CampaignId) != 0) {
          $CampaignList = $CampaignList->whereIn('CampaignId', $request->CampaignId);
        }
        if (isset($request->RevenueModelId) && $request->RevenueModelId != '' && count($request->RevenueModelId) != 0) {
          $CampaignList = $CampaignList->whereIn('RevenueModelId', $request->RevenueModelId);
          $UserBonus = [];
          $UserSubRevenue = [];
        }
        if (isset($request->RevenueModelType) && $request->RevenueModelType != '' && count($request->RevenueModelType) != 0) {
          $RevenueModelType = $request->RevenueModelType;
          $CampaignList = $CampaignList->whereHas('CommissionType', function ($qr) use ($RevenueModelType) {
            $qr->whereIn('RevenueTypeId', $RevenueModelType);
          });
          if (in_array('7', $RevenueModelType)) {
            $UserBonus = $UserBonus;
          } else {
            $UserBonus = [];
          }
          if (in_array('8', $RevenueModelType)) {
            $UserSubRevenue = $UserSubRevenue;
          } else {
            $UserSubRevenue = [];
          }
        }
        if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
          $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
          $CampaignList = $CampaignList->whereHas('CampaignAdList', function ($qr2) use ($AdsList) {
            $qr2->whereIn('AdId', $AdsList);
          });
          $UserBonus = [];
          $UserSubRevenue = [];
        }
        if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
          $AdType = $request->AdType;
          $AdIdList = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
          $CampaignList = $CampaignList->whereHas('CampaignAdList', function ($qr3) use ($AdIdList) {
            $qr3->whereIn('AdId', $AdIdList);
          });
          $UserBonus = [];
          $UserSubRevenue = [];
        }
        if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
          $AdBrand = $request->AdBrand;
          $AdIdList = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
          $CampaignList = $CampaignList->whereHas('CampaignAdList', function ($qr4) use ($AdIdList) {
            $qr4->whereIn('AdId', $AdIdList);
          });
          $UserBonus = [];
          $UserSubRevenue = [];
        }
        if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
          $AdLanguage = $request->AdLanguage;
          $AdIdList = Ad::whereIn('LanguageId', $AdLanguage)->pluck('AdId');
          $CampaignList = $CampaignList->whereHas('CampaignAdList', function ($qr4) use ($AdIdList) {
            $qr4->whereIn('AdId', $AdIdList);
          });
          $UserBonus = [];
          $UserSubRevenue = [];
        }
        $CampaignList = $CampaignList->get();

        // Campaign list
        foreach ($CampaignList as $Campaign) {
          // return $Campaign;
          $RevenueModelOfCamp = RevenueModel::with('Revenue')->where('RevenueModelId', $Campaign['RevenueModelId'])->first();
          $CampaignAddIds = CampaignAdList::where('CampaignId', $Campaign['CampaignId']);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdIdList = Ad::whereIn('AdTypeId', $request->AdType)->pluck('AdId');
            $CampaignAddIds = $CampaignAddIds->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $CampaignAddIds = $CampaignAddIds->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdsList = Ad::whereIn('LanguageId', $request->AdLanguage)->pluck('AdId');
            $CampaignAddIds = $CampaignAddIds->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdsList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $CampaignAddIds = $CampaignAddIds->whereIn('AdId', $AdsList);
          }
          $CampaignAddIds = $CampaignAddIds->pluck('CampaignAddId');

          // CampaignsClicks
          $CampaignsClicks = CampaignAdClick::whereIn('CampaignAddId', $CampaignAddIds);
          // Impressions
          $Impressions = CampaignAdImpression::whereIn('CampaignAddId', $CampaignAddIds);
          // Leads
          $Leads = Lead::where('CampaignId', $Campaign['CampaignId'])->where('UserId', $UserId);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdIdList = Ad::whereIn('AdTypeId', $request->AdType)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdsList = Ad::whereIn('LanguageId', $request->AdLanguage)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdsList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdsList);
          }

          $LeadList = Lead::where('CampaignId', $Campaign['CampaignId'])->where('UserId', $UserId);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdIdList = Ad::whereIn('AdTypeId', $request->AdType)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdsList = Ad::whereIn('LanguageId', $request->AdLanguage)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdsList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdsList);
          }
          $LeadList = $LeadList->pluck('LeadId');

          // LeadsQualified
          $LeadsQualified = Lead::where('CampaignId', $Campaign['CampaignId'])->whereHas('LeadStatus', function ($qr) {
            $qr->where('IsValid', 1);
          });
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdIdList = Ad::whereIn('AdTypeId', $request->AdType)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdsList = Ad::whereIn('LanguageId', $request->AdLanguage)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdsList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdsList);
          }

          // ConvertedAccounts
          $ConvertedAccounts = Lead::where('CampaignId', $Campaign['CampaignId'])->where('IsConverted', 1);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdIdList = Ad::whereIn('AdTypeId', $request->AdType)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdsList = Ad::whereIn('LanguageId', $request->AdLanguage)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdsList = Ad::whereIn('AdBrandId', $request->AdBrand)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdsList);
          }

          // QualifiedAccounts
          $RevenueModel = RevenueModel::where('RevenueModelId', $Campaign['RevenueModelId'])->where('RevenueTypeId', 4)->first();
          if ($RevenueModel) {
            $RevenueModelLog = RevenueModelLog::where('RevenueModelId', $RevenueModel['RevenueModelId'])->pluck('RevenueModelLogId');
            $QualifiedAccounts = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLog);
          }
          $RevenueModelCPL = RevenueModel::where('RevenueModelId', $Campaign['RevenueModelId'])->whereIn('RevenueTypeId', [1, 2, 3, 4])->first();
          if ($RevenueModelCPL) {
            $RevenueModelLogCPL = RevenueModelLog::where('RevenueModelId', $RevenueModelCPL['RevenueModelId'])->pluck('RevenueModelLogId');
            $CPLAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogCPL);
          }
          $RevenueModelShare = RevenueModel::where('RevenueModelId', $Campaign['RevenueModelId'])->whereIn('RevenueTypeId', [5, 6])->first();
          if ($RevenueModelShare) {
            $RevenueModelLogShare = RevenueModelLog::where('RevenueModelId', $RevenueModelShare['RevenueModelId'])->pluck('RevenueModelLogId');
            $ShareAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogShare);
          }
          $LeadActivityList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('LeadActivityId', '!=', null)->pluck('LeadActivityId');
          //  Commission count
          $UserAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSpreadAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          // MasterAffiliateCommission count
          $UserSubRevenueIdList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('PaymentStatus', 1)->where('USDAmount', '<', 0)->pluck('UserSubRevenueId');
          $UserSubRevenueDeduct = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('UserId', $Campaign['User']['UserId'])->where('PaymentStatus', 1);

          $MasterAmountMain = UserSubRevenue::whereIn('UserSubRevenueId', $UserSubRevenueIdList)->where('USDAmount', '<', 0)->where('UserId', $Campaign['User']['UserId']);
          // Date filter apply 
          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to = $request->DateTo;
            $CampaignsClicks->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);  // CampaignsClicks
            $Impressions->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);  // Impressions
            $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // 4.Leads
            $LeadsQualified->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // LeadsQualified
            $ConvertedAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // ConvertedAccounts
            if ($RevenueModel) {
              $QualifiedAccounts->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to); // QualifiedAccounts
            }
            if ($RevenueModelCPL) {
              $CPLAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            }
            if ($RevenueModelShare) {
              $ShareAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            }
            $UserAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $MasterAmountMain->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
              $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1);
            });
            $UserSubRevenueDeduct->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          }
          $CampaignsClicks = $CampaignsClicks->count(); // CampaignsClicks
          $Impressions = $Impressions->count(); // Impressions
          if ($Impressions == 0)
            $CTR = 0;
          else
            $CTR = $CampaignsClicks / $Impressions;
          $Leads = $Leads->count(); // Leads
          $LeadsQualified = $LeadsQualified->count(); // LeadsQualified
          $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts
          if ($RevenueModel) {
            $QualifiedAccounts = $QualifiedAccounts->count(); // QualifiedAccounts
          } else {
            $QualifiedAccounts = 0; // QualifiedAccounts
          }
          // cpl/ccpl/cpa/ccpa amount
          if ($RevenueModelCPL) {
            if ($userData->CurrencyId == 1) {
              $CPLMainAmount = $CPLAmount->sum('USDAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
            } else if ($userData->CurrencyId == 2) {
              $CPLMainAmount = $CPLAmount->sum('AUDAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadAUDAmount');
            } else if ($userData->CurrencyId == 3) {
              $CPLMainAmount = $CPLAmount->sum('EURAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadEURAmount');
            } else {
              $CPLMainAmount = $CPLAmount->sum('USDAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
            }
            $CPLTotalAmount = $CPLMainAmount + $CPLSpreadAmount;
          } else {
            $CPLTotalAmount = 0;
          }
          // Revenue Share + FX Share amount
          if ($RevenueModelShare) {
            if ($userData->CurrencyId == 1) {
              $ShareMainAmount = $ShareAmount->sum('USDAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
            } else if ($userData->CurrencyId == 2) {
              $ShareMainAmount = $ShareAmount->sum('AUDAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadAUDAmount');
            } else if ($userData->CurrencyId == 3) {
              $ShareMainAmount = $ShareAmount->sum('EURAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadEURAmount');
            } else {
              $ShareMainAmount = $ShareAmount->sum('USDAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
            }
            $ShareTotalAmount = $ShareMainAmount + $ShareSpreadAmount;
          } else {
            $ShareTotalAmount = 0;
          }

          if ($userData->CurrencyId == 1) {
            $UserAmount = $UserAmount->sum('USDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadUSDAmount');
            $DeductAmount = $UserSubRevenueDeduct->sum('USDAmount');
          } else if ($userData->CurrencyId == 2) {
            $UserAmount = $UserAmount->sum('AUDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadAUDAmount');
            $DeductAmount = $UserSubRevenueDeduct->sum('AUDAmount');
          } else if ($userData->CurrencyId == 3) {
            $UserAmount = $UserAmount->sum('EURAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadEURAmount');
            $DeductAmount = $UserSubRevenueDeduct->sum('EURAmount');
          } else {
            $UserAmount = $UserAmount->sum('USDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadUSDAmount');
            $DeductAmount = $UserSubRevenueDeduct->sum('USDAmount');
          }
          $Commission = $UserAmount + $UserSpreadAmount; // Commission count
          if ($RevenueModelShare) {
            $TotalUserAmount = $UserAmount;
            $TotalSpreadAmount = $UserSpreadAmount;
          } else {
            $TotalUserAmount = 0;
            $TotalSpreadAmount = 0;
          }

          if ($userData->CurrencyId == 1) {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          } elseif ($userData->CurrencyId == 2) {
            $MasterAmount = $MasterAmountMain->sum('AUDAmount');
          } elseif ($userData->CurrencyId == 3) {
            $MasterAmount = $MasterAmountMain->sum('EURAmount');
          } else {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          }
          $MasterAffiliateCommission = $MasterAmount; // Master Affiliate Commission count
          $TotalCommission = $Commission + $MasterAffiliateCommission; // Total Commission
          $NetRevenue = $Commission; // Royal Revenue
          $RMName = $RevenueModelOfCamp['RevenueModelName'];
          $CampaignName = $Campaign['CampaignName'];
          $CampaignArr = [
            "CampaignId" => $Campaign['CampaignId'],
            "Campaign" => $CampaignName,
            "RevenueModelId" => $RevenueModelOfCamp['RevenueModelId'],
            "RevenueModel" => $RMName,
            "RevenueType" => $RevenueModelOfCamp['Revenue']['RevenueTypeName'],
            "Impressions" => $Impressions,
            "CampaignsClicks" => $CampaignsClicks,
            "CTR" => round($CTR, 2),
            "Leads" => $Leads,
            "LeadsQualified" => $LeadsQualified,
            "ConvertedAccounts" => $ConvertedAccounts,
            "QualifiedAccounts" => $QualifiedAccounts,
            "AffiliateRevenue" => round($Commission, 4),
            "MasterRevenue" => round($DeductAmount, 4),
            "NetRevenue" => round($TotalCommission, 4),
          ];
          array_push($CampaignListArr, $CampaignArr);
          // Export array
          $CampaignArrExport = [
            "Revenue Model" => $RevenueModelOfCamp['RevenueModelName'],
            "Campaign" => $Campaign['CampaignName'],
            "Revenue Type" => $RevenueModelOfCamp['Revenue']['RevenueTypeName'],
            "Leads" => $Leads,
            "Leads Qualified" => $LeadsQualified,
            "Accounts" => $ConvertedAccounts,
            "Qualified Accounts" => $QualifiedAccounts,
            "Affiliate Revenue " . $CurrencyTitle => round($Commission, 4),
            "Master Revenue " . $CurrencyTitle => round($DeductAmount, 4),
            "Net Revenue " . $CurrencyTitle => round($TotalCommission, 4),
          ];
          array_push($CampaignListExport, $CampaignArrExport);

          $ImpressionsTotal = $ImpressionsTotal + $Impressions;
          $CampaignsClicksTotal = $CampaignsClicksTotal + $CampaignsClicks;
          if ($ImpressionsTotal == 0)
            $CTRTotal = 0;
          else
            $CTRTotal = $CampaignsClicksTotal / $ImpressionsTotal;
          $LeadsTotal = $LeadsTotal + $Leads;
          $LeadsQualifiedTotal = $LeadsQualifiedTotal + $LeadsQualified;
          $ConvertedAccountsTotal = $ConvertedAccountsTotal + $ConvertedAccounts;
          $QualifiedAccountsTotal = $QualifiedAccountsTotal + $QualifiedAccounts;
          $TotalCPLCPA = $TotalCPLCPA + $CPLTotalAmount;
          $TotalUserCommission = $TotalUserCommission + $TotalCommission;
          $AllTotalSpreadAmount = $AllTotalSpreadAmount + $TotalSpreadAmount;
          $TotalShareAmount = $TotalShareAmount + $ShareTotalAmount;
          $TotalTotalCommission = $TotalTotalCommission + $NetRevenue;
          $TotalNetRevenue = $TotalNetRevenue + $NetRevenue;
          $TotalDeducAmount = $TotalDeducAmount + $DeductAmount;
          $TotalMasterAffiliateDeduct = $TotalMasterAffiliateDeduct + $MasterAffiliateCommission;
        }

        // Bonus
        foreach ($UserBonus as $Bonus) {
          if ($userData->CurrencyId == 1) {
            $TotalCommission = $Bonus['USDAmount'];
          } else if ($userData->CurrencyId == 2) {
            $TotalCommission = $Bonus['AUDAmount'];
          } else if ($userData->CurrencyId == 3) {
            $TotalCommission = $Bonus['EURAmount'];
          } else {
            $TotalCommission = $Bonus['USDAmount'];
          }
          $RMName = $Bonus['RevenueModel']['RevenueModelName'];
          $TotalTotalCommission = $TotalTotalCommission + $TotalCommission;
          $TotalNetRevenue = $TotalNetRevenue + $TotalCommission;
          $TotalNetUserBonus = $TotalNetUserBonus + $TotalCommission;
        }
        if ($TotalNetUserBonus > 0 || in_array('7', $RevenueModelType)) {
          $BonusDataArr = [
            "CampaignId" => '',
            "Campaign" => '',
            "RevenueModelId" => '',
            "RevenueModel" => 'Bonus',
            "RevenueType" => 'Bonus',
            "Impressions" => 0,
            "CampaignsClicks" => 0,
            "CTR" => 0,
            "Leads" => 0,
            "LeadsQualified" => 0,
            "ConvertedAccounts" => 0,
            "QualifiedAccounts" => 0,
            "AffiliateRevenue" => round($TotalNetUserBonus, 4),
            "MasterRevenue" => 0,
            "NetRevenue" => round($TotalNetUserBonus, 4),
          ];
          array_push($CampaignListArr, $BonusDataArr);
          $BonusExportTotal = array(
            "Revenue Model" => 'Bonus',
            "Campaign" => '',
            "Revenue Type" => '',
            "Leads" => 0,
            "Leads Qualified" => 0,
            "Accounts" => 0,
            "Qualified Accounts" => 0,
            "Affiliate Revenue " . $CurrencyTitle => round($TotalNetUserBonus, 4),
            "Master Revenue " . $CurrencyTitle => 0,
            "Net Revenue " . $CurrencyTitle => round($TotalNetUserBonus, 4),
          );
          array_push($CampaignListExport, $BonusExportTotal);
        }

        // User Sub Revenue
        foreach ($UserSubRevenue as $SubRevData) {
          if ($userData->CurrencyId == 1) {
            $TotalSubRev = $SubRevData['USDAmount'];
          } else if ($userData->CurrencyId == 2) {
            $TotalSubRev = $SubRevData['AUDAmount'];
          } else if ($userData->CurrencyId == 3) {
            $TotalSubRev = $SubRevData['EURAmount'];
          } else {
            $TotalSubRev = $SubRevData['USDAmount'];
          }
          $TotalMasterAffiliateCommission = $TotalMasterAffiliateCommission + $TotalSubRev;
          $TotalNetRevenue = $TotalNetRevenue + $TotalSubRev;
        }
        if ($TotalMasterAffiliateCommission > 0 || in_array('8', $RevenueModelType)) {
          $SubRevDataArr = [
            "CampaignId" => '',
            "Campaign" => '',
            "RevenueModelId" => '',
            "RevenueModel" => 'Sub-Affiliate',
            "RevenueType" => 'Sub-Affiliate',
            "Impressions" => 0,
            "CampaignsClicks" => 0,
            "CTR" => 0,
            "Leads" => 0,
            "LeadsQualified" => 0,
            "ConvertedAccounts" => 0,
            "QualifiedAccounts" => 0,
            "AffiliateRevenue" => round($TotalMasterAffiliateCommission, 4),
            "MasterRevenue" => 0,
            "NetRevenue" => round($TotalMasterAffiliateCommission, 4),
          ];
          array_push($CampaignListArr, $SubRevDataArr);
          $masterArrExportTotal = array(
            "Revenue Model" => 'Sub-Affiliate',
            "Campaign" => '',
            "Revenue Type" => '',
            "Leads" => 0,
            "Leads Qualified" => 0,
            "Accounts" => 0,
            "Qualified Accounts" => 0,
            "Affiliate Revenue " . $CurrencyTitle => round($TotalMasterAffiliateCommission, 4),
            "Master Revenue " . $CurrencyTitle => 0,
            "Net Revenue " . $CurrencyTitle => round($TotalMasterAffiliateCommission, 4),
          );
          array_push($CampaignListExport, $masterArrExportTotal);
        }

        $TotalArray = array(
          'TotalAffiliateRevenue' => round($TotalUserCommission, 4),
          'TotalMasterRevenue' => round($TotalMasterAffiliateCommission, 4),
          'TotalNetRevenue' => round($TotalNetRevenue, 4),
        );
        $CampaignArrExportTotal = array(
          "Revenue Model" => '',
          "Campaign" => '',
          "Revenue Type" => '',
          "Leads" => $LeadsTotal,
          "Leads Qualified" => $LeadsQualifiedTotal,
          "Accounts" => $ConvertedAccountsTotal,
          "Qualified Accounts" => $QualifiedAccountsTotal,
          "Affiliate Revenue " . $CurrencyTitle => round($TotalTotalCommission + $TotalMasterAffiliateCommission, 4),
          "Master Revenue " . $CurrencyTitle => $TotalMasterAffiliateDeduct,
          "Net Revenue " . $CurrencyTitle => round($TotalUserCommission + $TotalMasterAffiliateCommission, 4),
        );
        array_push($CampaignListExport, $CampaignArrExportTotal);
        // Export report in xls file
        if ($request->IsExport) {
          Excel::create('CampaignStatisticsAffiliate', function ($excel) use ($CampaignListExport) {
            $excel->sheet('CampaignStatisticsAffiliate', function ($sheet) use ($CampaignListExport) {
              $sheet->fromArray($CampaignListExport);
            });
          })->store('xls', false, true);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export campaign list successfully.',
            "TotalCount" => 1,
            'Data' => ['CampaignStatisticsAffiliate' => $this->storage_path . 'exports/CampaignStatisticsAffiliate.xls'],
          ], 200);
        }

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($CampaignListArr),
          'Data' => array(
            'CampaignList' => $CampaignListAll,
            'TotalDeducAmount' => $TotalDeducAmount,
            'AdList' => $GetAdList,
            'AdTypeList' => $GetAdTypeList,
            'AdBrandList' => $GetAdBrandList,
            'LanguageList' => $GetLanguageList,
            'RevenueModelList' => $RevenueModelList,
            'RevenueModelTypeList' => $RevenueModelTypeList,
            'CampaignStatisticsAffiliate' => $CampaignListArr,
            'GrandTotal' => $TotalArray,
          )
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => null
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res);
    }
  }

  public function AffiliateRevenueList(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));
      if ($UserId) {
        $userData = User::find($UserId);
        $arrayList = [];
        $CurrencyTitle = 'USD'; // Default Currency USD
        if ($userData->CurrencyId == 1) {
          $CurrencyTitle = 'USD';
        } else if ($userData->CurrencyId == 2) {
          $CurrencyTitle = 'AUD';
        } else if ($userData->CurrencyId == 3) {
          $CurrencyTitle = 'EUR';
        } else {
          $CurrencyTitle = 'USD';
        }
        $arrayExportList = [];
        $TotalAmount = 0;
        $TotalSpread = 0;
        $RevenueModelTypeList = RevenueType::orderBy('RevenueTypeName')->get();
        $UserRevenuePayments = UserRevenuePayment::with(['Affiliate', 'LeadDetail.LeadInformation', 'LeadDetail.Campaign', 'LeadDetail.Ad.Brand', 'LeadDetail.Ad.Type', 'LeadDetail.Ad.size', 'LeadDetail.Ad.Language', 'LeadActivity', 'LeadInformation', 'UserSubRevenue', 'UserBonus.RevenueModel', 'RevenueModelLog.RevenueModel.Revenue'])->orderBy('UserRevenuePaymentId', 'desc')->where(function ($query) {
          return $query->whereNull('UserSubRevenueId')->orWhere('USDAmount', '>=', 0);
        })->where('UserId', $UserId);

        if (isset($request->RevenueTypeId) && $request->RevenueTypeId != '') {
          $RevenueType = $request->RevenueTypeId;
          if ($RevenueType != '') {
            $UserRevenuePayments->whereHas('RevenueModelLog.RevenueModel.Revenue', function ($qr) use ($RevenueType) {
              $qr->whereIn('RevenueTypeId', $RevenueType);
            });
          }
          if (in_array('7', $RevenueType)) {
            $UserRevenuePayments->orWhere(function ($qr) use ($UserId) {
              $qr->where('UserBonusId', '!=', '')->where('UserId', $UserId);
            });
          }
        }
        $TimeZoneOffSet = $request->TimeZoneOffSet;
        if ($TimeZoneOffSet == '')
          $TimeZoneOffSet = 0;
        // date filter
        if (isset($request->DateFrom) && $request->DateFrom != '' && isset($request->DateTo) && $request->DateTo != '') {
          $from = $request->DateFrom;
          $to = $request->DateTo;
          $UserRevenuePayments->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
        }
        $UserRevenuePaymentData = $UserRevenuePayments->get();
        foreach ($UserRevenuePaymentData as $UserRevenuePayment) {
          if ($UserRevenuePayment['LeadDetail']['LeadInformation'][0]['Country']) {
            $CountryNameShortCode = $UserRevenuePayment['LeadDetail']['LeadInformation'][0]['Country'];
            $Country = CountryMaster::where('CountryNameShortCode', $CountryNameShortCode)->first();
            $CountryName = $Country['CountryName'];
          } else {
            $CountryName = '';
          }
          $UserSubRevenue = UserSubRevenue::with('UserSubRevenuePayment.RevenueModelLog')->where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->where('UserId', $UserId)->first();
          $percentage = 0;
          if ($UserSubRevenue)
            $percentage = $UserSubRevenue['UserSubRevenuePayment']['RevenueModelLog']['Percentage'];

          if ($userData->CurrencyId == '') {
            $Amount = round($UserRevenuePayment['USDAmount'], 4);
            $Spread = round($UserRevenuePayment['SpreadUSDAmount'], 4);
          } else if ($userData->CurrencyId == 1) {
            $Amount = round($UserRevenuePayment['USDAmount'], 4);
            $Spread = round($UserRevenuePayment['SpreadUSDAmount'], 4);
          } else if ($userData->CurrencyId == 2) {
            $Amount = round($UserRevenuePayment['AUDAmount'], 4);
            $Spread = round($UserRevenuePayment['SpreadAUDAmount'], 4);
          } else if ($userData->CurrencyId == 3) {
            $Amount = round($UserRevenuePayment['EURAmount'], 4);
            $Spread = round($UserRevenuePayment['SpreadEURAmount'], 4);
          } else {
            $Amount = round($UserRevenuePayment['USDAmount'], 4);
            $Spread = round($UserRevenuePayment['SpreadUSDAmount'], 4);
          }
          $AmountDeduct = 0;
          $SpreadDeduct = 0;
          if ($UserSubRevenue) {
            $AmountDeduct = $Amount * $percentage / 100;
            $SpreadDeduct = $Spread * $percentage / 100;
          }
          $Amount = round($Amount - $AmountDeduct, 4);
          $Spread = round($Spread - $SpreadDeduct, 4);
          $TotalRevenue = round($Amount + $Spread, 4);
          $affName = '';

          if ($UserRevenuePayment['UserBonusId']) {
            $CampaignName = $UserRevenuePayment['LeadDetail']['Campaign']['CampaignName'];
            $array = [
              'UserRevenuePaymentId' => $UserRevenuePayment['UserRevenuePaymentId'],
              'UserId' => $UserRevenuePayment['UserId'],
              'Affiliate' => $affName,
              'LeadId' => '',
              'AdId' => '',
              'AdTitle' => '',
              'AdBrand' => '',
              'AdType' => '',
              'AdSize' => '',
              'AdLanguage' => '',
              'RevenueModelName' => $UserRevenuePayment['UserBonus']['RevenueModel']['RevenueModelName'],
              'RevenueTypeName' => '' . $UserRevenuePayment['RevenueModelLogId'] == NULL ? 'Bonus(Manual)' : 'Bonus(Auto)',
              'Campaign' => $CampaignName,
              'CountryName' => $CountryName,
              'Amount' => $Amount,
              'Spread' => $Spread,
              'TotalRevenue' => $TotalRevenue,
              'Comment' => $UserRevenuePayment['UserBonus']['Comment'],
              'ActualRevenueDate' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['ActualRevenueDate']))),
              'CreatedAt' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
            ];
            $arrayExport = [
              'Campaign' => $CampaignName,
              'Affiliate' => $affName,
              'AdTitle' => '',
              'AdBrand' => '',
              'AdType' => '',
              'AdSize' => '',
              'AdLanguage' => '',
              'RevenueModelName' => $UserRevenuePayment['UserBonus']['RevenueModel']['RevenueModelName'],
              'RevenueTypeName' => '' . $UserRevenuePayment['UserBonus'] == 0 ? 'Bonus(Manual)' : 'Bonus(Auto)',
              'CountryName' => $CountryName,
              'Amount ' . $CurrencyTitle => $Amount,
              'Spread ' . $CurrencyTitle => $Spread,
              'TotalRevenue' => $TotalRevenue,
              'Comment' => $UserRevenuePayment['UserBonus']['Comment'],
              'Effective Date' => date('d/m/Y h:2403i A', strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['ActualRevenueDate']))),
              'Insertion Date' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
            ];
          } else {
            $adName = $UserRevenuePayment['LeadDetail']['Ad']['Title'];
            $RevenueTypeName = $UserRevenuePayment['RevenueModelLog']['RevenueModel']['Revenue']['RevenueTypeName'];
            $RMName = $UserRevenuePayment['RevenueModelLog']['RevenueModelName'];
            $CampaignName = $UserRevenuePayment['LeadDetail']['Campaign']['CampaignName'];
            $affName = '';
            if ($UserRevenuePayment['UserSubRevenueId'] != NULL) {
              $CampaignName = '';
              $UserSubRevenueId = $UserRevenuePayment['UserSubRevenueId'];
              $SubRev = UserSubRevenue::find($UserSubRevenueId);
              $RevPayment = UserRevenuePayment::find($SubRev->UserRevenuePaymentId);
              $ParentUser = User::find($RevPayment->UserId);
              $affName = $ParentUser->FirstName . ' ' . $ParentUser->LastName;
            }
            $array = [
              'UserRevenuePaymentId' => $UserRevenuePayment['UserRevenuePaymentId'],
              'UserId' => $UserRevenuePayment['UserId'],
              'Affiliate' => $affName,
              'LeadId' => $UserRevenuePayment['LeadId'],
              'AdId' => $UserRevenuePayment['LeadDetail']['AdId'],
              'AdTitle' => $adName,
              'AdBrand' => $UserRevenuePayment['LeadDetail']['Ad']['Brand']['Title'],
              'AdType' => $UserRevenuePayment['LeadDetail']['Ad']['Type']['Title'],
              'AdSize' => $UserRevenuePayment['LeadDetail']['Ad']['Size']['Width'] . '*' . $UserRevenuePayment['LeadDetail']['Ad']['Size']['Height'],
              'AdLanguage' => $UserRevenuePayment['LeadDetail']['Ad']['Language']['LanguageName'],
              'RevenueModelName' => $RMName,
              'RevenueTypeName' => $RevenueTypeName,
              'Campaign' => $CampaignName,
              'CountryName' => $CountryName,
              'Amount' => $Amount,
              'Spread' => $Spread,
              'TotalRevenue' => $TotalRevenue,
              'Comment' => '',
              'ActualRevenueDate' => date('d/m/Y h:i A', strtotime($UserRevenuePayment['ActualRevenueDate'])),
              'CreatedAt' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
            ];
            $arrayExport = [
              'Campaign' => $CampaignName,
              'Affiliate' => $affName,
              'AdTitle' => $adName,
              'AdBrand' => $UserRevenuePayment['LeadDetail']['Ad']['Brand']['Title'],
              'AdType' => $UserRevenuePayment['LeadDetail']['Ad']['Type']['Title'],
              'AdSize' => $UserRevenuePayment['LeadDetail']['Ad']['Size']['Width'] . '*' . $UserRevenuePayment['LeadDetail']['Ad']['Size']['Height'],
              'AdLanguage' => $UserRevenuePayment['LeadDetail']['Ad']['Language']['LanguageName'],
              'RevenueModelName' => $UserRevenuePayment['RevenueModelLog']['RevenueModelName'],
              'RevenueTypeName' => $RevenueTypeName,
              'CountryName' => $CountryName,
              'Amount ' . $CurrencyTitle => $Amount,
              'Spread ' . $CurrencyTitle => $Spread,
              'TotalRevenue' => $TotalRevenue,
              'Comment' => '',
              'Effective Date' => date('d/m/Y h:i A', strtotime($UserRevenuePayment['ActualRevenueDate'])),
              'Insertion Date' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
            ];
          }
          array_push($arrayList, $array);
          array_push($arrayExportList, $arrayExport);
          $TotalAmount = $TotalAmount + $Amount;
          $TotalSpread = $TotalSpread + $Spread;
        }

        $TotalAmountArrayExp = array(
          'Campaign' => '',
          'Affiliate' => '',
          'AdTitle' => '',
          'AdBrand' => '',
          'AdType' => '',
          'AdSize' => '',
          'AdLanguage' => '',
          'RevenueModelName' => '',
          'RevenueTypeName' => '',
          'CountryName' => '',
          'Amount' => $TotalAmount,
          'Spread' => $TotalSpread,
          'TotalRevenue' => $TotalAmount + $TotalSpread,
          'Comment' => '',
          'Effective Date' => '',
          'Insertion Date' => '',
        );
        array_push($arrayExportList, $TotalAmountArrayExp);
        $TotalAmountArray = array(
          'TotalAmount' => round($TotalAmount, 4),
          'TotalSpread' => round($TotalSpread, 4),
        );
        // Export report in xls file
        if ($request->IsExport) {
          Excel::create('AffiliateRevenueList', function ($excel) use ($arrayExportList) {
            $excel->sheet('AffiliateRevenueList', function ($sheet) use ($arrayExportList) {
              $sheet->fromArray($arrayExportList);
            });
          })->store('xls', false, true);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export affiliate revenue list successfully.',
            "TotalCount" => 1,
            'Data' => ['AffiliateRevenueList' => $this->storage_path . 'exports/AffiliateRevenueList.xls'],
          ], 200);
        }

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Revenue list.',
          "TotalCount" => count($UserRevenuePaymentData),
          'Data' => array(
            'RevenueModelTypeList' => $RevenueModelTypeList,
            'RevenueList' => $arrayList,
            'RevenueListTotal' => $TotalAmountArray
          )
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
          "TotalCount" => 0,
          'Data' => []
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }
  /*
    End. Statistics reports
  */

  public function CampaignTopFivePerforming(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));
      if ($UserId) {
        $userData = User::find($UserId);
        $CampaignListArr = [];
        $CampaignList = Campaign::with('User')->orderBy('CampaignId', 'desc')->where('UserId', $UserId)->get();
        // Campaign list
        foreach ($CampaignList as $Campaign) {
          $RevenueModelOfCamp = RevenueModel::with('Revenue')->where('RevenueModelId', $Campaign['RevenueModelId'])->first();
          $Leads = Lead::where('CampaignId', $Campaign['CampaignId'])->where('UserId', $UserId);
          $LeadList = Lead::where('CampaignId', $Campaign['CampaignId'])->where('UserId', $UserId)->pluck('LeadId');
          $ConvertedAccounts = Lead::where('CampaignId', $Campaign['CampaignId'])->where('IsConverted', 1);
          $UserAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSubRevenueDeduct = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('UserId', $UserId)->where('PaymentStatus', 1);
          $UserSpreadAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $Leads = $Leads->count(); // Leads
          $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts 

          if ($userData->CurrencyId == 1) {
            $UserAmount = $UserAmount->sum('USDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadUSDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
          } else if ($userData->CurrencyId == 2) {
            $UserAmount = $UserAmount->sum('AUDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadAUDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('AUDAmount');
          } else if ($userData->CurrencyId == 3) {
            $UserAmount = $UserAmount->sum('EURAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadEURAmount');
            $Deduct = $UserSubRevenueDeduct->sum('EURAmount');
          } else {
            $UserAmount = $UserAmount->sum('USDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadUSDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
          }
          $Commission = $UserAmount + $UserSpreadAmount + $Deduct;

          if ($Commission > 0) {
            $CampaignName = $Campaign['CampaignName'];
            if (strlen($Campaign['CampaignName']) > 10) {
              $CampaignName = substr($Campaign['CampaignName'], 0, 8) . "..";
            }
            $CampaignArr = [
              "CampaignId" => $Campaign['CampaignId'],
              "Campaign" => $CampaignName,
              "RevenueModel" => $RevenueModelOfCamp['RevenueModelName'],
              "Leads" => $Leads,
              "ConvertedAccounts" => $ConvertedAccounts,
              "NetRevenue" => round($Commission, 4),
            ];
            array_push($CampaignListArr, $CampaignArr);
          }
        }
        usort($CampaignListArr, function ($a, $b) {
          return $b['NetRevenue'] - $a['NetRevenue'];
        });
        $CampaignListArr = array_slice($CampaignListArr, 0, 5);

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($CampaignListArr),
          'Data' => array(
            'CampaignTopFivePerforming' => $CampaignListArr,
          )
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => null
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res);
    }
  }

  public function MonthlyRevenueByRevenueType(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));
      if ($UserId) {
        $userData = User::find($UserId);
        $CampaignListArr = [];
        $month = [];
        $UserRevenueType = RevenueType::get();

        /* $day1 = date("Y-m-d");
        $day2 = date('Y-m-d',strtotime($day1.'-1 month'));
        $day3 = date('Y-m-d',strtotime($day1.'-2 month'));
        $day4 = date('Y-m-d',strtotime($day1.'-3 month'));*/

        // $day1 = date("Y-m-d");
        $month1d1T = new DateTime('first day of this month');
        $month1d1 = $month1d1T->format('Y-m-d');
        $month1d2T = new DateTime('last day of this month');
        $month1d2 = $month1d2T->format('Y-m-d');
        $month1 = date('F', strtotime('this month'));

        $month2d1K = new DateTime('first day of last month');
        $month2d1 = $month2d1K->format('Y-m-d');
        $month2d2K = new DateTime('last day of last month');
        $month2d2 = $month2d2K->format('Y-m-d');
        $month2 = date('F', strtotime($month2d1));

        $month3d1K = new DateTime('first day of -2 month');
        $month3d1 = $month3d1K->format('Y-m-d');
        $month3d2K = new DateTime('last day of -2 month');
        $month3d2 = $month3d2K->format('Y-m-d');
        $month3 = Date('F', strtotime($month1d1 . " -2 month"));
        array_push($month, $month3);
        array_push($month, $month2);
        array_push($month, $month1);

        // echo $month1d1.' To '.$month1d2.'<br>';
        // echo  $month2d1.' To '.$month2d2.'<br>';
        // echo  $month3d1.' To '.$month3d2.'<br>'; die;

        foreach ($UserRevenueType as $UserRevenue) {
          $RevenueModelIds = RevenueModel::where('RevenueTypeId', $UserRevenue->RevenueTypeId)->pluck('RevenueModelId');
          $CampaignIds = Campaign::where('UserId', $UserId)->whereIn('RevenueModelId', $RevenueModelIds)->pluck('CampaignId');
          $LeadList = Lead::whereIn('CampaignId', $CampaignIds)->where('UserId', $UserId)->pluck('LeadId');
          // echo '<br>'.$CampaignIds.' = '.$LeadList.'<br>';
          $UserAmount1 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSpreadAmount1 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSubRevenueDeduct1 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('UserId', $UserId)->where('PaymentStatus', 1);

          $UserAmount2 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSpreadAmount2 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSubRevenueDeduct2 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('UserId', $UserId)->where('PaymentStatus', 1);

          $UserAmount3 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSpreadAmount3 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSubRevenueDeduct3 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('UserId', $UserId)->where('PaymentStatus', 1);

          if ($UserRevenue->RevenueTypeId == 7) {
            $UserAmount1 = UserRevenuePayment::where('UserId', $UserId)->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);
            $UserSpreadAmount1 = UserRevenuePayment::where('UserId', $UserId)->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);
            $UserAmount2 = UserRevenuePayment::where('UserId', $UserId)->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);
            $UserSpreadAmount2 = UserRevenuePayment::where('UserId', $UserId)->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);
            $UserAmount3 = UserRevenuePayment::where('UserId', $UserId)->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);
            $UserSpreadAmount3 = UserRevenuePayment::where('UserId', $UserId)->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);
          }
          if ($UserRevenue->RevenueTypeId == 8) {
            $UserAmount1 = UserRevenuePayment::where('UserId', $UserId)->where('UserSubRevenueId', '!=', null)->where('USDAmount', '>=', 0)->where('PaymentStatus', 1);
            $UserSpreadAmount1 = UserRevenuePayment::where('UserId', $UserId)->where('UserSubRevenueId', '!=', null)->where('USDAmount', '>=', 0)->where('PaymentStatus', 1);
            $UserAmount2 = UserRevenuePayment::where('UserId', $UserId)->where('UserSubRevenueId', '!=', null)->where('USDAmount', '>=', 0)->where('PaymentStatus', 1);
            $UserSpreadAmount2 = UserRevenuePayment::where('UserId', $UserId)->where('UserSubRevenueId', '!=', null)->where('USDAmount', '>=', 0)->where('PaymentStatus', 1);
            $UserAmount3 = UserRevenuePayment::where('UserId', $UserId)->where('UserSubRevenueId', '!=', null)->where('USDAmount', '>=', 0)->where('PaymentStatus', 1);
            $UserSpreadAmount3 = UserRevenuePayment::where('UserId', $UserId)->where('UserSubRevenueId', '!=', null)->where('USDAmount', '>=', 0)->where('PaymentStatus', 1);
          }

          // month 3 last
          $UserAmount1->whereDate('ActualRevenueDate', '>=', $month3d1)->whereDate('ActualRevenueDate', '<=', $month3d2);
          $UserSpreadAmount1->whereDate('ActualRevenueDate', '>=', $month3d1)->whereDate('ActualRevenueDate', '<=', $month3d2);
          $UserSubRevenueDeduct1->whereDate('ActualRevenueDate', '>=', $month3d1)->whereDate('ActualRevenueDate', '<=', $month3d2);

          if ($userData->CurrencyId == 1) {
            $UserAmount1 = $UserAmount1->sum('USDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadUSDAmount');
            $Deduct1 = $UserSubRevenueDeduct1->sum('USDAmount');
          } else if ($userData->CurrencyId == 2) {
            $UserAmount1 = $UserAmount1->sum('AUDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadAUDAmount');
            $Deduct1 = $UserSubRevenueDeduct1->sum('AUDAmount');
          } else if ($userData->CurrencyId == 3) {
            $UserAmount1 = $UserAmount1->sum('EURAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadEURAmount');
            $Deduct1 = $UserSubRevenueDeduct1->sum('EURAmount');
          } else {
            $UserAmount1 = $UserAmount1->sum('USDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadUSDAmount');
            $Deduct1 = $UserSubRevenueDeduct1->sum('USDAmount');
          }
          $Commission1 = round($UserAmount1 + $UserSpreadAmount1 + $Deduct1, 4);

          // last month
          $UserAmount2->whereDate('ActualRevenueDate', '>=', $month2d1)->whereDate('ActualRevenueDate', '<=', $month2d2);
          $UserSpreadAmount2->whereDate('ActualRevenueDate', '>=', $month2d1)->whereDate('ActualRevenueDate', '<=', $month2d2);
          $UserSubRevenueDeduct2->whereDate('ActualRevenueDate', '>=', $month2d1)->whereDate('ActualRevenueDate', '<=', $month2d2);
          if ($userData->CurrencyId == 1) {
            $UserAmount2 = $UserAmount2->sum('USDAmount');
            $UserSpreadAmount2 = $UserSpreadAmount2->sum('SpreadUSDAmount');
            $Deduct2 = $UserSubRevenueDeduct2->sum('USDAmount');
          } else if ($userData->CurrencyId == 2) {
            $UserAmount2 = $UserAmount2->sum('AUDAmount');
            $UserSpreadAmount2 = $UserSpreadAmount2->sum('SpreadAUDAmount');
            $Deduct2 = $UserSubRevenueDeduct2->sum('USDAmount');
          } else if ($userData->CurrencyId == 3) {
            $UserAmount2 = $UserAmount2->sum('EURAmount');
            $UserSpreadAmount2 = $UserSpreadAmount2->sum('SpreadEURAmount');
            $Deduct2 = $UserSubRevenueDeduct2->sum('USDAmount');
          } else {
            $UserAmount2 = $UserAmount2->sum('USDAmount');
            $UserSpreadAmount2 = $UserSpreadAmount2->sum('SpreadUSDAmount');
            $Deduct2 = $UserSubRevenueDeduct2->sum('USDAmount');
          }
          $Commission2 = round($UserAmount2 + $UserSpreadAmount2 + $Deduct2, 4);

          // this month
          $UserAmount3->whereDate('ActualRevenueDate', '>=', $month1d1)->whereDate('ActualRevenueDate', '<=', $month1d2);
          $UserSpreadAmount3->whereDate('ActualRevenueDate', '>=', $month1d1)->whereDate('ActualRevenueDate', '<=', $month1d2);
          $UserSubRevenueDeduct3->whereDate('ActualRevenueDate', '>=', $month1d1)->whereDate('ActualRevenueDate', '<=', $month1d2);
          if ($userData->CurrencyId == 1) {
            $UserAmount3 = $UserAmount3->sum('USDAmount');
            $UserSpreadAmount3 = $UserSpreadAmount3->sum('SpreadUSDAmount');
            $Deduct3 = $UserSubRevenueDeduct3->sum('USDAmount');
          } else if ($userData->CurrencyId == 2) {
            $UserAmount3 = $UserAmount3->sum('AUDAmount');
            $UserSpreadAmount3 = $UserSpreadAmount3->sum('SpreadAUDAmount');
            $Deduct3 = $UserSubRevenueDeduct3->sum('AUDAmount');
          } else if ($userData->CurrencyId == 3) {
            $UserAmount3 = $UserAmount3->sum('EURAmount');
            $UserSpreadAmount3 = $UserSpreadAmount3->sum('SpreadEURAmount');
            $Deduct3 = $UserSubRevenueDeduct3->sum('EURAmount');
          } else {
            $UserAmount3 = $UserAmount3->sum('USDAmount');
            $UserSpreadAmount3 = $UserSpreadAmount3->sum('SpreadUSDAmount');
            $Deduct3 = $UserSubRevenueDeduct3->sum('USDAmount');
          }
          $Commission3 = round($UserAmount3 + $UserSpreadAmount3 + $Deduct3, 4);

          if ($UserRevenue->RevenueTypeId == 1)
            $tooltip = 'CPL';
          else if ($UserRevenue->RevenueTypeId == 2)
            $tooltip = 'CCPL';
          else if ($UserRevenue->RevenueTypeId == 3)
            $tooltip = 'CPA';
          else if ($UserRevenue->RevenueTypeId == 4)
            $tooltip = 'CCPA';
          else if ($UserRevenue->RevenueTypeId == 5)
            $tooltip = 'RS';
          else if ($UserRevenue->RevenueTypeId == 6)
            $tooltip = 'FX RS';
          else if ($UserRevenue->RevenueTypeId == 7)
            $tooltip = 'Bonus';
          else if ($UserRevenue->RevenueTypeId == 8)
            $tooltip = 'Sub-Aff';
          else
            $tooltip = '';

          if ($Commission1 > 0 || $Commission2 > 0 || $Commission3 > 0) {
            $CampaignArr = [
              'data' => array($Commission1, $Commission2, $Commission3),
              'label' => $tooltip,
              'tooltip' => $UserRevenue['RevenueTypeName'],
            ];
            array_push($CampaignListArr, $CampaignArr);
          }
          /*$CampaignArr1 = array(
              'name' => $UserRevenue['RevenueType']['RevenueTypeName'],
              'value' => $Commission1
            );
          $CampaignArr2 = array(
              'name' => $UserRevenue['RevenueType']['RevenueTypeName'],
              'value' => $Commission2
            );
          $CampaignArr3 = array(
              'name' => $UserRevenue['RevenueType']['RevenueTypeName'],
              'value' => $Commission3
            );
          array_push($CampaignListArr1, $CampaignArr1);
          array_push($CampaignListArr2, $CampaignArr2);
          array_push($CampaignListArr3, $CampaignArr3);*/
        }
        // die;

        /*$newArr = [
          array( 
            'name' => 'Month 1',
            'series' => $CampaignListArr1
          ),
          array( 
            'name' => 'Month 2',
            'series' => $CampaignListArr2
          ),
          array( 
            'name' => 'Month 3',
            'series' => $CampaignListArr3
          ),
        ];*/

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($CampaignListArr),
          'Data' => array(
            'MonthlyRevenueByRevenueType' => $CampaignListArr,
            'Months' => $month,
          )
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => null
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res);
    }
  }

  public function TopPerformingAds(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));
      if ($UserId) {
        $userData = User::find($UserId);
        $AdListArr = [];
        $UserBrand = UserAdBrand::where('UserId', $UserId)->pluck('AdBrandId');
        $UserType  = UserAdType::where('UserId', $UserId)->pluck('AdTypeId');
        $AdList = Ad::orderBy('AdId', 'desc')->whereHas(
          'AffiliatAds',
          function ($qr) use ($UserId) {
            $qr->where('UserId', $UserId);
          }
        )->orWhere('IsPublic', 1)->whereIn('AdBrandId', $UserBrand)->whereIn('AdTypeId', $UserType)->get();
        // return $AdList; die;
        foreach ($AdList as $AdData) {
          $CampaignAddIds = CampaignAdList::where('UserId', $UserId)->where('AdId', $AdData['AdId'])->pluck('CampaignAddId');
          $Displays = CampaignAdImpression::whereIn('CampaignAddId', $CampaignAddIds)->count();
          $Clicks = CampaignAdClick::whereIn('CampaignAddId', $CampaignAddIds)->count();
          $Leads = Lead::where('AdId', $AdData['AdId'])->where('UserId', $UserId)->count();

          $CampaignArr = [
            "name" => $AdData['Title'],
            "series" => [
              array('name' => 'Displays', 'value' => $Displays),
              array('name' => 'Clicks', 'value' => $Clicks),
              array('name' => 'Leads', 'value' => $Leads),
              array('name' => 'Total', 'value' => $Displays + $Clicks + $Leads),
            ]
          ];
          array_push($AdListArr, $CampaignArr);
        }
        usort($AdListArr, function ($a, $b) {
          return $b['series'][3]['value'] - $a['series'][3]['value'];
        });
        $AdListArr = array_slice($AdListArr, 0, 5); // get top 10 ads
        usort($AdListArr, function ($a, $b) {
          return $b['series'][2]['value'] - $a['series'][2]['value'];
        });

        $labelArr = [];
        $ViewsArr = [];
        $ClicksArr = [];
        $LeadsArr = [];
        foreach ($AdListArr as $Ad) {
          // if (strlen($Ad['name']) > 10) {
          //   $adName = substr($Ad['name'], 0, 8) . "..";
          // } else {
          $adName = $Ad['name'];
          // }
          array_push($labelArr, $adName);
          array_push($ViewsArr, $Ad['series'][0]['value']);
          array_push($ClicksArr, $Ad['series'][1]['value']);
          array_push($LeadsArr, $Ad['series'][2]['value']);
        }
        $finalArr = [
          array('label' => 'Views', 'data' => $ViewsArr),
          array('label' => 'Clicks', 'data' => $ClicksArr),
          array('label' => 'Leads', 'data' => $LeadsArr),
        ];
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($AdListArr),
          'Data' => array(
            'TopPerformingAdsLabel' => $labelArr,
            'TopPerformingAds' => $finalArr,
          )
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => null
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res);
    }
  }

  public function RevenuePerRevenueModel(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));
      if ($UserId) {
        $userData = User::find($UserId);
        $RMListArr = [];
        $RMArrLabel = [];
        // $UserBrand = UserAdBrand::where('UserId', $UserId)->pluck('AdBrandId');
        // $UserType  = UserAdType::where('UserId', $UserId)->pluck('AdTypeId');
        $RevenueModelList = RevenueModel::whereHas('UserRevenueType', function ($qr) use ($UserId) {
          $qr->where('UserId', $UserId);
        })->orderBy('RevenueModelId', 'desc')->get();
        // return $RevenueModelList; die;
        foreach ($RevenueModelList as $RMData) {
          $CampaignIds = Campaign::where('RevenueModelId', $RMData['RevenueModelId'])->where('UserId', $UserId)->pluck('CampaignId');
          $LeadList = Lead::whereIn('CampaignId', $CampaignIds)->where('UserId', $UserId)->pluck('LeadId');
          $UserAmount1 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          if ($RMData->RevenueTypeId == 7) {
            $UserAmount1 = UserRevenuePayment::where('UserId', $UserId)->where('UserBonusId', '!=', null)->where('RevenueModelLogId', '!=', null)->where('PaymentStatus', 1);
          }
          if ($RMData->RevenueTypeId == 8) {
            $UserAmount1 = UserRevenuePayment::where('UserId', $UserId)->where('UserSubRevenueId', '!=', null)->where('RevenueModelLogId', '!=', null)->where('USDAmount', '>=', 0)->where('PaymentStatus', 1);
          }
          $UserSpreadAmount1 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSubRevenueDeduct = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('UserId', $UserId)->where('PaymentStatus', 1);

          if ($userData->CurrencyId == 1) {
            $UserAmount1 = $UserAmount1->sum('USDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadUSDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
          } else if ($userData->CurrencyId == 2) {
            $UserAmount1 = $UserAmount1->sum('AUDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadAUDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('AUDAmount');
          } else if ($userData->CurrencyId == 3) {
            $UserAmount1 = $UserAmount1->sum('EURAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadEURAmount');
            $Deduct = $UserSubRevenueDeduct->sum('EURAmount');
          } else {
            $UserAmount1 = $UserAmount1->sum('USDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadUSDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
          }
          $Commission1 = $UserAmount1 + $UserSpreadAmount1 + $Deduct;

          array_push($RMArrLabel, $RMData['RevenueModelName']);
          // $RMArr = array(
          array_push($RMListArr, $Commission1);
          // );
          // array_push($RMListArr, $RMArr);
        }
        // usort($RMListArr, function ($a, $b) {
        //   return $b - $a;
        // });

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($RMListArr),
          'Data' => array(
            'RevenuePerRevenueModelLabel' => $RMArrLabel,
            'RevenuePerRevenueModel' => $RMListArr,
          )
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => null
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res);
    }
  }

  public function RevenuePerCountry(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));
      if ($UserId) {
        $CountryArrLabel = [];
        $CountryArrList = [];
        $userData = User::find($UserId);
        $CountryIds = LeadInformation::whereHas('LeadData', function ($qr) use ($UserId) {
          $qr->where('UserId', $UserId);
        })->groupBy('Country')->pluck('Country');
        $CountryMaster = CountryMaster::whereIn('CountryNameShortCode', $CountryIds)->get();
        $CommissionTotal = 0;
        $otherCommission = 0;

        foreach ($CountryMaster as $Country) {
          // if($Country['CountryId'] != 13)
          //  return $LeadIds;
          $LeadInformations = LeadInformation::whereHas('LeadData', function ($qr) use ($UserId) {
            $qr->where('UserId', $UserId);
          })->where('Country', $Country['CountryNameShortCode'])->groupBy('LeadId')->get();
          $LeadIds = [];

          foreach ($LeadInformations as $LeadInformation) {
            $LeadInformationsData = LeadInformation::where('LeadId', $LeadInformation->LeadId)->orderBy('LeadInformationId', 'desc')->first();
            if ($LeadInformationsData->Country == $Country['CountryNameShortCode']) {
              array_push($LeadIds, $LeadInformationsData->LeadId);
            }
          }
          // if($Country['CountryId'] != 13)
          //   return $LeadIds;

          // $LeadIds = LeadInformation::whereHas('LeadData', function ($qr) use ($UserId) {
          //   $qr->where('UserId', $UserId);
          // })->where('Country', $Country['CountryNameShortCode'])->groupBy('LeadId')->pluck('LeadId');
          $LeadList = Lead::whereIn('RefId', $LeadIds)->where('UserId', $UserId)->pluck('LeadId');
          $UserAmount1 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSpreadAmount1 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSubRevenueDeduct = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('UserId', $UserId)->where('PaymentStatus', 1);

          if ($userData->CurrencyId == 1) {
            $UserAmount1 = $UserAmount1->sum('USDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadUSDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
          } else if ($userData->CurrencyId == 2) {
            $UserAmount1 = $UserAmount1->sum('AUDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadAUDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('AUDAmount');
          } else if ($userData->CurrencyId == 3) {
            $UserAmount1 = $UserAmount1->sum('EURAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadEURAmount');
            $Deduct = $UserSubRevenueDeduct->sum('EURAmount');
          } else {
            $UserAmount1 = $UserAmount1->sum('USDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadUSDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
          }
          $CommissionTotal = $CommissionTotal + round($UserAmount1 + $UserSpreadAmount1 + $Deduct, 2);
        }

        $percentage = 10;
        $BreakP = $CommissionTotal * $percentage / 100;

        foreach ($CountryMaster as $Country) {
          $LeadInformations = LeadInformation::whereHas('LeadData', function ($qr) use ($UserId) {
            $qr->where('UserId', $UserId);
          })->where('Country', $Country['CountryNameShortCode'])->groupBy('LeadId')->get();
          $LeadIds = [];

          foreach ($LeadInformations as $LeadInformation) {
            $LeadInformationsData = LeadInformation::where('LeadId', $LeadInformation->LeadId)->orderBy('LeadInformationId', 'desc')->first();
            if ($LeadInformationsData->Country == $Country['CountryNameShortCode']) {
              array_push($LeadIds, $LeadInformationsData->LeadId);
            }
          }

          // $LeadIds = LeadInformation::whereHas('LeadData', function ($qr) use ($UserId) {
          //   $qr->where('UserId', $UserId);
          // })->where('Country', $Country['CountryNameShortCode'])->groupBy('LeadId')->pluck('LeadId');
          $LeadList = Lead::whereIn('RefId', $LeadIds)->where('UserId', $UserId)->pluck('LeadId');
          $UserAmount1 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSpreadAmount1 = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '=', null)->where('PaymentStatus', 1);
          $UserSubRevenueDeduct = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('UserId', $UserId)->where('PaymentStatus', 1);

          if ($userData->CurrencyId == 1) {
            $UserAmount1 = $UserAmount1->sum('USDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadUSDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
          } else if ($userData->CurrencyId == 2) {
            $UserAmount1 = $UserAmount1->sum('AUDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadAUDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('AUDAmount');
          } else if ($userData->CurrencyId == 3) {
            $UserAmount1 = $UserAmount1->sum('EURAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadEURAmount');
            $Deduct = $UserSubRevenueDeduct->sum('EURAmount');
          } else {
            $UserAmount1 = $UserAmount1->sum('USDAmount');
            $UserSpreadAmount1 = $UserSpreadAmount1->sum('SpreadUSDAmount');
            $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
          }
          $Commission1 = round($UserAmount1 + $UserSpreadAmount1 + $Deduct, 2);
          /*$CountryArr = array(
            'name' => $Country['CountryName'],
            'value' => $Commission1,
          );*/
          if ($Commission1 > $BreakP) {
            array_push($CountryArrLabel, $Country['CountryName']);
            array_push($CountryArrList, $Commission1);
          } else {
            $otherCommission += $Commission1;
          }
        }
        array_push($CountryArrLabel, 'Other');
        array_push($CountryArrList, $otherCommission);
        // usort($CountryArrList, function ($a, $b) {
        //   return $b - $a;
        // });

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($CountryArrList),
          'Data' => array(
            'RevenuePerCountryLabels' => $CountryArrLabel,
            'RevenuePerCountry' => $CountryArrList,
          )
        ], 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => null
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res);
    }
  }

  public function IsValidLoginToken(Request $request)
  {
    $ckeck = new UserToken();
    $UserId = $ckeck->validToken($request->header('Token'));
    if ($UserId) {
      return response()->json([
        'IsSuccess' => true,
        'Message' => 'Valid token.',
        "TotalCount" => 1,
        "Data" => array('accesstoken' => $request->header('Token'))
      ], 200);
    } else {
      return response()->json([
        'IsSuccess' => false,
        'Message' => 'Token not found.',
        "TotalCount" => 0,
        'Data' => array('accesstoken' => '')
      ], 200);
    }
  }
}
