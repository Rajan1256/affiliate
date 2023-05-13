<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use DateTime;
use Validator;
use App\User;
use App\UserBonus;
use App\LeadStatusMaster;
use App\UserRevenueType;
use App\UserToken;
use App\UserSubRevenue;
use App\UserRevenuePayment;
use App\Menu;
use App\Permission;
use App\ResetPassword;
use App\RevenueType;
use App\RevenueModel;
use App\RevenueModelLog;
use App\Ad;
use App\AdTypeMaster;
use App\AdBrandMaster;
use App\AdSizeMaster;
use App\LanguageMaster;
use App\Campaign;
use App\CampaignAdList;
use App\CampaignAdClick;
use App\CampaignAdImpression;
use App\CurrencyConvert;
use App\CurrencyRate;
use App\RoyalRevenue;
use App\Lead;
use App\LeadActivity;
use App\LeadInfoFile;
use App\LeadInformation;
use Maatwebsite\Excel\Facades\Excel;
use Laravel\Lumen\Routing\Controller as BaseController;

class AdminController extends BaseController
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
    $this->revenue_auto_approve = env('REVENUE_AUTO_APPROVE', true);
    $this->storage_path = getenv('STORAGE_URL');
    $this->admin_url = getenv('ADMIN_URL');
  }

  public function authenticate(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'EmailId' => 'required|email',
        'Password' => 'required',
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Something went wrong.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }
      // find user with Email Id input
      $user = User::where('EmailId', $this->request->input('EmailId'))->where('RoleId', '!=', 3)->where('IsDeleted', 1)->first();
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
            'Message' => 'Your account is deleted by administrator.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
        if (base64_decode($request->Password) == decrypt($user->Password)) {
          $token_id = str_random(60);
          UserToken::create([
            'UserId' => $user->UserId,
            'Token' => $token_id
          ]);
          $UserId = $user->UserId;
          // $shares2 = Menu::whereHas('Permissions', function($qr) use($UserId){ 
          //                 $qr->where('UserId',$UserId);
          //             })->get();
          $shares2 = Permission::with('Menu')->where('UserId', $UserId)->get();
          $AccessArray = [];
          foreach ($shares2 as $AccValue) {
            $arr = [
              "MenuId" => $AccValue['menu']['MenuId'],
              "MenuName" => $AccValue['menu']['MenuName'],
              "AccessType" => $AccValue['AccessType']
            ];
            array_push($AccessArray, $arr);
          }

          $res = [
            'IsSuccess' => true,
            'Message' => 'Login Successfully',
            'TotalCount' => 0,
            'Data' => array(
              'User' => array(
                'id' => $user->UserId,
                'FirstName' => $user->FirstName,
                'LastName' => $user->LastName,
                'Email' => $user->EmailId,
                'Password' => decrypt($user->Password),
                'RoleId' => $user->RoleId,
                'CreatedAT' => (string) $user->CreatedAt,
                'UpdatedAT' => (string) $user->UpdatedAt
              ),
              'permission' => $AccessArray,
              'Token' => $token_id
            ),
            'Token' => $token_id,
          ];
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'Email or password is wrong.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Email does not exist.',
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

  public function ValidateToken(Request $request)
  {
    try {
      $token_id = $request->header('token');
      $log_user = UserToken::where('Token', $token_id)->first();
      if ($log_user) {
        // find user with Email Id input
        $user = User::find($log_user->UserId);
        $UserId = $user->UserId;
        $shares = Permission::with('Menu')->where('UserId', $UserId)->get();
        $AccessArray = [];
        foreach ($shares as $AccValue) {
          $arr = [
            "MenuId" => $AccValue['menu']['MenuId'],
            "MenuName" => $AccValue['menu']['MenuName'],
            "AccessType" => $AccValue['AccessType']
          ];
          array_push($AccessArray, $arr);
        }
        $res = [
          'IsSuccess' => true,
          'Message' => 'Login Successfully',
          'TotalCount' => 0,
          'Data' => array(
            'User' => array(
              'id' => $user->UserId,
              'FirstName' => $user->FirstName,
              'LastName' => $user->LastName,
              'Email' => $user->EmailId,
              'Password' => decrypt($user->Password),
              'RoleId' => $user->RoleId,
              'CreatedAT' => (string) $user->CreatedAt,
              'UpdatedAT' => (string) $user->UpdatedAt
            ),
            'permission' => $AccessArray,
            'Token' => $token_id
          ),
          'Token' => $token_id,
        ];
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

  public function Show_subadmin(Request $request)
  {
    try {
      $data = $request->json()->all();
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $query = User::orderBy('UserId', 'desc');
        $columns = ['FirstName', 'LastName', 'EmailId'];
        foreach ($columns as $column) {
          $query->orWhere($column, 'LIKE', '%' . $data['name'] . '%')
            ->where('RoleId', 2)
            ->where('IsDeleted', 1);
        }
        $user = $query->get();


        $res = [
          'IsSuccess' => true,
          'Message' => 'Sub Admin Data Show Successfully.',
          'TotalCount' => 0,
          'Data' => array('User' => $user)
        ];
        return response()->json($res, 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Token not found',
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

  public function logged_user(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $res = [
          'IsSuccess' => true,
          'Message' => 'Show Login User',
          'TotalCount' => 0,
          'Data' => array('User' => $log_user)
        ];
        return response()->json($res, 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Token not found',
          'TotalCount' => 0,
          'Data' => null,
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

  public function logged_out(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        UserToken::where('Token', $request->header('token'))->delete();
        $res = [
          'IsSuccess' => true,
          'Message' => 'Logged Out Successfully!',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Token not found',
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
  }

  public function reset_password(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'EmailId' => 'required|email',
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
      $user = User::where('EmailId', $this->request->input('EmailId'))->where('RoleId', '!=', 3)->where('IsDeleted', 1)->first();
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
          // $link_url = 'https://differenzuat.com/affiliate/AdminPortal/account/resetpassword/' . $rand_str;
          // $link_url = 'http://192.168.1.76:7007/account/resetpassword/'.$rand_str;
          $link_url = $this->admin_url . 'account/resetpassword/' . $rand_str;
          $data = array(
            'firstname' => $user->FirstName,
            'lastname' => $user->LastName,
            'email' => $user->EmailId,
            'url' => $link_url
          );
          $userEmail = $user->EmailId;
          $userName = $user->FirstName;
          Mail::send(['html' => 'ResetPassword'], $data, function ($message) use ($userEmail, $userName) {
            $message->to($userEmail, $userName)->subject('Reset Password');
            $message->from(getenv('FROM_EMAIL_ADDRESS'), 'Affiliate System');
          });

          $res = [
            'IsSuccess' => true,
            'Message' => 'Reset password link was sent to your email.',
            "TotalCount" => 0,
            "Data" => []
          ];
        } else {
          ResetPassword::create([
            'UserId' => $user->UserId,
            'PasswordResetToken' => $rand_str,
            'EmailId' => $user->EmailId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
          ]);

          // $link_url = 'https://differenzuat.com/affiliate/AdminPortal/account/resetpassword/' . $rand_str;
          // $link_url = 'http://192.168.1.76:7007/account/resetpassword/'.$rand_str;          
          $link_url = $this->admin_url . 'account/resetpassword/' . $rand_str;
          $data = array(
            'firstname' => $user->FirstName,
            'lastname' => $user->LastName,
            'email' => $user->EmailId,
            'url' => $link_url
          );
          $userEmail = $user->EmailId;
          $userName = $user->FirstName;
          Mail::send(['html' => 'ResetPassword'], $data, function ($message) use ($userEmail, $userName) {
            $message->to($userEmail, $userName)->subject('Reset Password');
            $message->from(getenv('FROM_EMAIL_ADDRESS'), 'Affiliate System');
          });

          $res = [
            'IsSuccess' => true,
            'Message' => 'Reset password link was sent to your email.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Email is not avaliable!',
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

  public function Change_password(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'Password'  => 'required',
        'token' => 'required'
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Something went wrong.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }

      $rst_pass = ResetPassword::where('PasswordResetToken', $request->token)->first();
      if ($rst_pass) {
        $val1 = date('Y-m-d H:i:s');
        $val2 = (string) $rst_pass->created_at;
        $datetime1 = new DateTime($val1);
        $datetime2 = new DateTime($val2);

        $interval = $datetime1->diff($datetime2);
        if ($interval->format("%H%") <= 5) {
          User::where('UserId', $rst_pass->UserId)->update([
            'Password' => encrypt(base64_decode($request->Password)),
          ]);
          ResetPassword::where('UserId', $rst_pass->UserId)->delete();
          $res = [
            'IsSuccess' => true,
            'Message' => 'Password update successfully.',
            'TotalCount' => 0,
            'Data' => null
          ];
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'Reset password token is expire!',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token',
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

  public function ChangePassword(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validTokenAdmin($request->header('token'));
      if ($UserId) {
        $validator = Validator::make($request->all(), [
          'OldPassword'  => 'required',
          'NewPassword'  => 'required',
        ]);
        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }
        $user = User::find($request->UserId);
        if (base64_decode($request->OldPassword) == decrypt($user->Password)) {
          User::where('UserId', $request->UserId)->update([
            'Password' => encrypt(base64_decode($request->NewPassword))
          ]);
          return response()->json([
            'IsSuccess' => true,
            'Message' => "Password Update successfully.",
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Old Password does not match.',
            "TotalCount" => 0,
            "Data" => []
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => "Invalid Token OR UserId.",
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
      return response()->json($res, 200);
    }
  }

  public function Add_menu(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        $validator = Validator::make($request->all(), [
          'MenuName' => 'required|unique:menus,MenuName',
        ]);
        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }
        Menu::create([
          'MenuName' => $request->MenuName
        ]);
        $res = [
          'IsSuccess' => true,
          'Message' => 'Menu Added Successfully.',
          'TotalCount' => 0,
          'Data' => null
        ];
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
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

  public function Edit_menu(Request $request, $menu_id)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));

      if ($log_user) {
        $menu = Menu::where('MenuId', $menu_id)->get();
        $res = [
          'IsSuccess' => true,
          'Message' => 'Edit Menu.',
          'TotalCount' => 0,
          'Data' => array('Menu' => $menu)
        ];
        return response()->json($res, 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
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
      return response()->json($res);
    }
  }

  public function Update_menu(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));

      if ($log_user) {
        $validator = Validator::make($request->all(), [
          'MenuName' => 'required|unique:menus,MenuName',
        ]);

        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }
        Menu::where('MenuId', $request->MenuId)->update([
          'MenuName' => $request->MenuName
        ]);

        $res = [
          'IsSuccess' => true,
          'Message' => 'Menu Updated Successfully.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
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

  public function Add_subadmin(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));

      if ($log_user) {
        $messsages = array(
          'UserDetail.FirstName.required' => 'Please Fill the first name.',
          'UserDetail.FirstName.min' => 'Minimum 3 charcter required for MiddleName name.',
          'UserDetail.LastName.min' => 'Minimum 3 charcter required for Last name.',
          'UserDetail.LastName.required' => 'Please Fill the Last name.',
          'UserDetail.EmailId.required' => 'Please Fill the EmailId.',
          'UserDetail.EmailId.email' => 'Please Fill the Valid EmailId.'
        );
        $rules = array(
          'UserDetail.FirstName' => 'required|min:3',
          'UserDetail.LastName' => 'required|min:3',
          'UserDetail.EmailId' => 'required|email',
        );
        $this->validate($this->request, $rules, $messsages);

        $check_email = User::where('EmailId', $request->UserDetail['EmailId'])->where('RoleId', 2)->where('IsDeleted', 1)->count();

        if ($check_email >= 1) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'This Email is allready used for sub admin!',
            'TotalCount' => 0,
            'Data' => null
          ]);
        }
        $password = str_random(10);

        $dt = User::create([
          'FirstName' => $request->UserDetail['FirstName'],
          'LastName' => $request->UserDetail['LastName'],
          'EmailId' => $request->UserDetail['EmailId'],
          'Password' => encrypt($password),
          'RoleId' => 2,
          'EmailVerified' => 1,
          'AdminVerified' => 1,
        ]);

        $userEmail = $request->UserDetail['EmailId'];
        $userName = $request->UserDetail['FirstName'];
        $lastname = $request->UserDetail['LastName'];
        $email = $request->UserDetail['EmailId'];
        $id = $dt->UserId;
        foreach ($request->MenuRight as $row) {
          $menu = Menu::get();
          foreach ($menu as $r) {
            if ($r->MenuId == $row['MenuId']) {
              Permission::updateOrCreate([
                'UserId' => $id,
                'MenuId' => $row['MenuId'],
                'MenuName' => $r['MenuName'],
                'AccessType' => $row['IsAccess']
              ]);
            }
          }
        }
        // $url = 'https://differenzuat.com/affiliate/AdminPortal/account/signin';
        // $url = 'http://192.168.1.76:7007/account/signin';
        $url = $this->admin_url . 'account/signin';
        $data = array(
          'firstname' => $userName,
          'lastname' => $lastname,
          'email' => $email,
          'password' => $password,
          'url' => $url,
        );
        Mail::send(['html' => 'SubAdminActiveMail'], $data, function ($message) use ($userEmail, $userName) {
          $message->to($userEmail, $userName)->subject('Sub admin activation mail');
          $message->from(getenv('FROM_EMAIL_ADDRESS'), 'Affiliate System');
        });
        $res = [
          'IsSuccess' => true,
          'Message' => 'Sub Admin Created Successfully.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
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

  public function Edit_subadmin(Request $request)
  {
    try {

      $data = $request->json()->all();
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      $subadmin = User::where('UserId', $data['UserId'])->first();

      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $menu_query = Menu::get();
          $arr = [];
          $arr2 = [];
          $arr_psh = [];
          foreach ($menu_query as $mq) {
            $permission_query = Permission::where('MenuId', '=', $mq->MenuId)->where('UserId', '=', $subadmin->UserId)->first();
            if ($permission_query) {
              $var = [
                'MenuId' => $permission_query['MenuId'],
                'IsSelect' => 1,
                'IsAccess' => $permission_query['AccessType']
              ];
              array_push($arr, $var);
            }
          }

          foreach ($menu_query as $mq) {
            $permission_query = Permission::where('MenuId', '=', $mq->MenuId)->where('UserId', '=', $subadmin->UserId)->first();
            //  print_r($permission_query);die;
            if ($permission_query == "" || $permission_query == NULL) {
              $var = [
                'MenuId' => $mq['MenuId']
              ];
              array_push($arr2, $var);
            }
          }

          $menu_table = Menu::get();
          foreach ($menu_table as $mt) {
            foreach ($arr as $ar) {
              if ($mt['MenuId'] == $ar['MenuId']) {
                $var = [
                  'MenuId' => $ar['MenuId'],
                  'MenuName' => $mt['MenuName'],
                  'IsSelect' => 1,
                  'IsAccess' => $ar['IsAccess']
                ];
                array_push($arr_psh, $var);
              }
            }
          }

          foreach ($menu_table as $mt) {
            foreach ($arr2 as $ar) {
              if ($mt['MenuId'] == $ar['MenuId']) {
                $var = [
                  'MenuId' => $mt['MenuId'],
                  'MenuName' => $mt['MenuName'],
                  'IsSelect' => 0,
                  'IsAccess' => 0
                ];
                array_push($arr_psh, $var);
              }
            }
          }

          usort($arr_psh, function ($a, $b) {
            return $a['MenuId'] - $b['MenuId'];
          });

          $res = [
            'IsSuccess' => true,
            'Message' => 'Show sub admin with permission.',
            'TotalCount' => 0,
            'Data' => array(
              'Subadmin' => array(
                'UserId' => $subadmin->UserId,
                'FirstName' => $subadmin->FirstName,
                'LastName' => $subadmin->LastName,
                'Email' => $subadmin->EmailId,
                'RoleId' => $subadmin->RoleId,
                'CreatedAT' => (string) $subadmin->CreatedAt,
                'UpdatedAT' => (string) $subadmin->UpdatedAt
              ),
              'permission' => $arr_psh
            )
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
          'Message' => 'Invalid Token.',
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

  public function Update_subadmin(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));

      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $messsages = array(
            'UserDetail.FirstName.required'    => 'Please Fill the first name.',
            'UserDetail.FirstName.min'    => 'Minimum 3 charcter required for First name.',
            'UserDetail.LastName.min'    => 'Minimum 3 charcter required for Last name.',
            'UserDetail.LastName.required'    => 'Please Fill the Last name.',
            'UserDetail.EmailId.required'    => 'Please Fill the EmailId.',
            'UserDetail.EmailId.email'    => 'Please Fill the Valid EmailId.'
          );
          $rules = array(
            'UserDetail.FirstName' => 'required|min:3',
            'UserDetail.LastName' => 'required|min:3',
            'UserDetail.EmailId' => 'required|email'
          );
          $this->validate(
            $this->request,
            $rules,
            $messsages
          );

          $dt = User::where('UserId', $request->UserDetail['UserId'])->update([
            'FirstName' => $request->UserDetail['FirstName'],
            'LastName' => $request->UserDetail['LastName'],
            'EmailId' => $request->UserDetail['EmailId'],
          ]);
          Permission::where('UserId', $request->UserDetail['UserId'])->delete();
          foreach ($request->MenuRight as $row) {
            $menu = Menu::get();
            foreach ($menu as $r) {
              if ($r->MenuId == $row['MenuId']) {
                Permission::Create([
                  'UserId' => $request->UserDetail['UserId'],
                  'MenuId' => $row['MenuId'],
                  'MenuName' => $r['MenuName'],
                  'AccessType' => $row['IsAccess']
                ]);
              }
            }
          }
          $res = [
            'IsSuccess' => true,
            'Message' => 'Sub Admin Updated Successfully.',
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
          'Message' => 'Invalid Token.',
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

  public function Delete_subadmin(Request $request)
  {
    try {
      $data = $request->json()->all();
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));

      if ($log_user) {
        User::where('UserId', $data['UserId'])->update([
          'IsDeleted' => 0
        ]);
        $res = [
          'IsSuccess' => true,
          'Message' => 'Sub Admin Deleted Successfully!',
          'TotalCount' => 0,
          'Data' => null
        ];
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
      return response()->json($res, 200);
    }
    return response()->json($res, 200);
  }

  public function GetRevenueModelsByRevenueTypeId(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $validator = Validator::make($request->all(), [
          'RevenueTypeId'  => 'required',
          'CurrencyId'  => 'required'
        ]);
        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }
        if ($request->RevenueTypeId == 5 || $request->RevenueTypeId == 6 || $request->RevenueTypeId == 8) {
          $RevenueModels = RevenueModel::with('Currency', 'Revenue')->where('RevenueTypeId', $request->RevenueTypeId)->where('IsActive', 1)->orderBy('RevenueModelId', 'desc')->get();
        } else if ($request->RevenueTypeId == 7) {
          $RevenueModels = RevenueModel::with('Currency', 'Revenue')->where('RevenueTypeId', $request->RevenueTypeId)->where('CurrencyId', $request->CurrencyId)->where('IsActive', 1)->orderBy('RevenueModelId', 'desc')->get();
        } else {
          $RevenueModels = RevenueModel::with('Currency', 'Revenue')->where('RevenueTypeId', $request->RevenueTypeId)->where('CurrencyId', $request->CurrencyId)->where('IsActive', 1)->orderBy('RevenueModelId', 'desc')->get();
        }

        $RevenueModelsData = [];
        $Count = 0;
        foreach ($RevenueModels as $value) {
          // Details C-CPA
          if ($value['RevenueTypeId'] == 4) {
            if ($value['TradeType'] == 1)
              $TradeType = 'Number of lots';
            else if ($value['TradeType'] == 2)
              $TradeType = 'Total deposit';
            else
              $TradeType = 'Number of transaction';
            $Details = '<b>' . $TradeType . '</b>:' . $value['TradeValue'];
          }
          // Details Fx Reve. Share
          else if ($value['RevenueTypeId'] == 6) {
            $Details = '<b>Spread</b>:' . $value['Rebate'] . '<br>' . '<b>Reference Deal</b>:' . $value['ReferenceDeal'];
          }
          // Details Bonus
          else if ($value['RevenueTypeId'] == 7) {
            if ($value['Schedule'] == 1)
              $Schedule = 'Yearly';
            else if ($value['Schedule'] == 2)
              $Schedule = 'Monthly';
            else
              $Schedule = 'Inception';
            if ($value['TradeType'] == 1)
              $Details = '<b>Schedule</b>: ' . $Schedule . '<br><b>Total Account Trade Volume</b>: ' . $value['TotalAccTradeVol'];
            else
              $Details = '<b>Schedule</b>: ' . $Schedule . '<br><b>Total Introduced Account</b>: ' . $value['TotalIntroducedAcc'];
          }
          // Details Sub Aff.
          else if ($value['RevenueTypeId'] == 8) {
            if ($value['Type'] == 1)
              $Type = 'From';
            else
              $Type = 'On Top Of';
            $Details = '<b>' . $Type . '</b>:' . $value['Percentage'];
          } else {
            $Details = '';
          }

          $var = [
            'RevenueModelId' => $value['RevenueModelId'],
            'RevenueModelName' => $value['RevenueModelName'],
            'RevenueTypeId' => $value['RevenueTypeId'],
            'RevenueTypeName' => $value['Revenue']['RevenueTypeName'],
            'Details' => $Details,
            'CurrencyId' => $value['CurrencyId'],
            'CurrencyName' => $value['Currency']['CurrencyCode'],
            'Value' => (($value['Revenue']['RevenueTypeId'] == 1) || ($value['Revenue']['RevenueTypeId'] == 2) || ($value['Revenue']['RevenueTypeId'] == 3) || ($value['Revenue']['RevenueTypeId'] == 4) || ($value['Revenue']['RevenueTypeId'] == 6) || ($value['Revenue']['RevenueTypeId'] == 7)) ? $value['Amount'] : $value['Percentage'],
            'Rebate' => $value['Rebate'],
            'Schedule' => $value['Schedule'],
            'TotalAccTradeVol' => $value['TotalAccTradeVol'],
            'TotalIntroducedAcc' => $value['TotalIntroducedAcc'],
            'BonusValue' => $value['BonusValue'],
            'Type' => $value['Type']
          ];
          array_push($RevenueModelsData, $var);
          $Count++;
        }
        $RevenueModels2 = [];

        /*foreach ($RevenueModels2 as $value) {
              $var = [
                  'RevenueModelId' => $value['RevenueModelId'],
                  'RevenueModelName' => $value['RevenueModelName'],
                  'RevenueTypeId' => $value['RevenueTypeId'],
                  'RevenueTypeName' => $value['Revenue']['RevenueTypeName'],
                  'CurrencyId' => $value['CurrencyId'],
                  'CurrencyName' => $value['Currency']['CurrencyCode'],
                  'AmountFlag' => $value['AmountFlag'],
                  'Amount' => $value['Amount'],
                  'Percentage' => $value['Percentage'],
                  'Rebate' => $value['Rebate'],
                  'Schedule' => $value['Schedule'],
                  'TotalAccTradeVol' => $value['TotalAccTradeVol'],
                  'TotalIntroducedAcc' => $value['TotalIntroducedAcc'],
                  'BonusValue' => $value['BonusValue'],
                  'Type' => $value['Type']
              ];
              array_push($RevenueModelsData, $var);
              $Count++;
          }*/

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Revenue models view successfully.',
          "TotalCount" => $Count,
          "Data" => ['RevenueModels' => $RevenueModelsData]
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

  public function GetPlainAffilaiteData(Request $request)
  {
    // return $request->all();
    $AdId = decrypt($request->AdId);
    $CampaignId = decrypt($request->CampaignId);
    $affiliate = Campaign::find($CampaignId);
    $AffiliateId = $affiliate['UserId'];

    $res = [
      'IsSuccess' => true,
      'Message' => "Successfully get data.",
      'TotalCount' => 0,
      'Data' => array('AdId' => $AdId, 'CampaignId' => $CampaignId, 'AffiliateId' => $AffiliateId)
    ];
    return response()->json($res, 200);
  }

  public function GetUrlData(Request $request)
  {
    $AdId = encrypt($request->AdId);
    // $Campaign = CampaignAdList::where(['UserId' => $user->UserId, 'AdId' => $request->AdId, 'IsDeleted' => 0])->first(); 
    $CampaignId = decrypt($request->CampaignId);

    // $user = User::where('TrackingId', $request->UserTrackingId)->first();
    $Campaign = Campaign::find($CampaignId);

    if ($Campaign) {
      $CampaignId = encrypt($Campaign->CampaignId);
      $camp =  Ad::where('AdId', $request->AdId)->first();

      if (!empty($request->AdId) && !empty($Campaign->CampaignId)) {
        $camp1 = CampaignAdList::where('CampaignId', $Campaign->CampaignId)->where('AdId', $request->AdId)->where('IsDeleted', 0)->first();
        if ($camp1->AdClicks == null)
          $total = 1;
        else
          $total = $camp1->AdClicks + 1;
        CampaignAdList::where('CampaignId', $Campaign->CampaignId)
          ->where('AdId', $request->AdId)
          ->update(['AdClicks' => $total]);

        CampaignAdClick::Create(['CampaignAddId' => $camp1->CampaignAddId]);
      }
      $UserId = base64_encode($Campaign->UserId);
      $URL = $camp->LandingPageURL . "?a=$AdId&c=$CampaignId&aid";
      $res = [
        'IsSuccess' => true,
        'Message' => "URL getting successfully.",
        'TotalCount' => 0,
        'Data' => array('URL' => $URL)
      ];
      return response()->json($res, 200);
    } else {
      $res = [
        'IsSuccess' => false,
        'Message' => "Campaign not found.",
        'TotalCount' => 1,
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }

  public function Success()
  {
    $strRandom = str_random(12);
    $res = ['ldrf' => $strRandom];
    return response()->json($res, 200);
  }

  public function FakePostLeadGeneration()
  {
    $strRandom = str_random(12);
    $res = ['ldrf' => $strRandom];
    return redirect('http://192.168.1.72:1213/Thankyou.html?ldrf=' . $strRandom);
  }

  public function GetAllLeadList(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $LeadList = Lead::orderBy('LeadId', 'DESC')->get();
          $myarr = [];
          $TimeZoneOffSet = $request->TimeZoneOffSet;
          foreach ($LeadList as $value) {
            if ($value['IsActive'] == 0)
              $isactive = 'false';
            else
              $isactive = 'true';
            $UserDetail = User::find($value['UserId']);
            if ($UserDetail)
              $AffiliateName = $UserDetail['FirstName'] . ' ' . $UserDetail['LastName'];
            else
              $AffiliateName = '';
            $Campaign = Campaign::find($value['CampaignId']);
            if ($Campaign) {
              $RevenueModel = RevenueModel::select('RevenueModelName')->where('RevenueModelId', $Campaign->RevenueModelId)->first();
              $RevenueModelName = $RevenueModel->RevenueModelName;
            } else {
              $RevenueModelName = '';
            }
            $var = [
              'LeadId' => $value['LeadId'],
              'RefId' => $value['RefId'],
              'UserId' => $value['UserId'],
              'AffiliateName' => $AffiliateName,
              'RevenueModelName' => $RevenueModelName,
              'AdId' => $value['AdId'],
              'CampaignId' => $value['CampaignId'],
              'FirstName' => $value['FirstName'],
              'LastName' => $value['LastName'],
              'Email' => $value['Email'],
              'CountryFromInput' => $value['CountryFromInput'],
              'CountryFromIp' => $value['CountryFromIp'],
              'ContryFromSheet' => $value['ContryFromSheet'],
              'PhoneNumber' => $value['PhoneNumber'],
              'LeadStatus' => $value['LeadStatus'],
              'IsActive' => $isactive,
              'RegistrationDate' => $value['RegistrationDate'],
              'IsQualified' => $value['IsQualified'],
              'DateQualified' => $value['DateQualified'],
              'IsConverted' => $value['IsConverted'],
              'DateConverted' => $value['DateConverted'],
              'AccountType' => $value['AccountType'],
              'LeadIPAddress' => $value['LeadIPAddress'],
              'FirstTimeDeposit' => $value['FirstTimeDeposit'],
              'FTDCurrency' => $value['FTDCurrency'],
              'CreatedAt' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt'])))
            ];

            array_push($myarr, $var);
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Get Lead list successfully.',
            "TotalCount" => $LeadList->count(),
            "Data" => array('LeadList' => $myarr)
          ], 200);
        } else {
          $res = [
            'IsSuccess' => true,
            'Message' => 'No request found',
            'TotalCount' => 0,
            'Data' => null
          ];
        }
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
    }
    return response()->json($res);
  }

  public function GetAllCurrencyRateList(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $CurrencyRateList = CurrencyRate::orderBy('CurrencyRateId', 'desc')->get();
          $myarr = [];
          if ($request->TimeZoneOffSet && $request->TimeZoneOffSet != '')
            $TimeZoneOffSet = $request->TimeZoneOffSet;
          else
            $TimeZoneOffSet = 0;
          foreach ($CurrencyRateList as $value) {
            $var = [
              'CurrencyRateId' => $value['CurrencyRateId'],
              'AUDUSD' => $value['AUDUSD'],
              'EURUSD' => $value['EURUSD'],
              'DateTime' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['Date']))),
              'CreatedAt' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt'])))
            ];
            array_push($myarr, $var);
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Get currency rate list successfully.',
            "TotalCount" => $CurrencyRateList->count(),
            "Data" => array('CurrencyRateList' => $myarr)
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
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
      return response()->json($res);
    }
  }

  // Currency Rate Update
  public function CurrencyRateUpdate(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          if ($request->hasFile('CurrencyConvert')) {
            // $validator = Validator::make($request->all(), [
            //   'CurrencyConvert' => 'required|mimes:xlsx,xlsb,xlsm,xls',
            // ]);
            $validator = Validator::make(
              [
                'file'      => $request->file('CurrencyConvert'),
                'extension' => strtolower($request->file('CurrencyConvert')->getClientOriginalExtension()),
              ],
              [
                'file'      => 'required',
                'extension' => 'required|in:xlsx,xlsb,xlsm,xls',
              ]
            );

            if ($validator->fails()) {
              return response()->json([
                'IsSuccess' => false,
                'Message' => 'File validation error occurred. Please upload valid file format.',
                "TotalCount" => count($validator->errors()),
                "Data" => array('Error' => $validator->errors())
              ], 200);
            }

            $CurrencyConvert = $request->file('CurrencyConvert');
            $name = 'CurrencyConvert-' . '' . time() . '.' . $CurrencyConvert->getClientOriginalExtension();
            $destinationPath = storage_path('/app/import/CurrencyConvert');
            $CurrencyConvert->move($destinationPath, $name);
            $full_path = 'storage/app/import/CurrencyConvert/' . $name;
            // $full_path = storage_path('app/import/LeadInformation/'.$name);
            $data = Excel::load($full_path)->formatDates(true)->get();
            $UploadFileKeys = (($data->first())->keys())->toArray();
            $keyArray = array(
              'audusd',
              'eurusd',
              'date',
            );
            $arrayDiff = array_diff($keyArray, $UploadFileKeys);

            if (empty($arrayDiff)) {
              $LeadFile = LeadInfoFile::Create([
                "FileName" => $name,
                "LeadFileFlage" => 3,
                "CreatedBy" => $log_user->UserId,
              ]);
              $count = 0;
              foreach ($data as $key => $val) {
                if (isset($val->audusd) && $val->audusd != '' && isset($val->eurusd) && $val->eurusd != '') {
                  $strdate = str_replace('/', '-', $val->date);
                  try {
                    $time = strtotime($strdate);
                    if ($time) {
                      try {
                        $NewDate = date('d-m-Y H:i:s', $time);
                      } catch (exception $e) {
                        $NewDate = null;
                      }
                    } else {
                      $NewDate = null;
                    }
                  } catch (ParseException $e) {
                    $NewDate = null;
                  }

                  try {
                    $d1 = date('d-m-Y H:i:s', strtotime($strdate));
                  } catch (exception $e) {
                    $d1 = null;
                  }
                  try {
                    $d2 = date('d-m-Y H:i:s', strtotime($NewDate));
                    // $d2 = new DateTime($NewDate);
                  } catch (exception $e) {
                    $d2 = null;
                  }
                  // echo $d1.'=='.$d2.'<br>';
                  if ($d1 == $d2) {
                    $CurrencyRate = CurrencyRate::where('date', date('Y-m-d H:i:s', strtotime($val->date)))->first();
                    if ($CurrencyRate == null) {
                      $NewRate = CurrencyRate::Create([
                        'LeadFileInfo' => $LeadFile->LeadFileInfo,
                        'AUDUSD' => $val->audusd,
                        'EURUSD' => $val->eurusd,
                        'Date' => date('Y-m-d H:i:s', strtotime($NewDate)),
                        'Status' => 1,
                        "CreatedBy" => $log_user->UserId,
                      ]);

                      if ($NewRate) {
                        $AUDUSD = $NewRate->AUDUSD;
                        $EURUSD = $NewRate->EURUSD;
                        $USDAUD = round(1 / $AUDUSD, 10);
                        $EURAUD = round($EURUSD / $AUDUSD, 10);
                        $USDEUR = round(1 / $EURUSD, 10);
                        $AUDEUR = round(1 / $EURAUD, 10);
                        // CurrencyConvert Insert record
                        CurrencyConvert::insert(['CurrencyRateId' => $NewRate->CurrencyRateId, 'USDAUD' => $USDAUD, 'USDEUR' => $USDEUR, 'AUDUSD' => $AUDUSD, 'AUDEUR' => $AUDEUR, 'EURUSD' => $EURUSD, 'EURAUD' => $EURAUD]);
                      }
                      $count = $count + 1;
                    }
                  }
                }
              }
              // die;

              return response()->json([
                'IsSuccess' => true,
                'Message' => 'File uploaded successfully. Total data import ' . $count . '.',
                'TotalCount' => $count,
                "Data" => array('File' => $name)
              ], 200);
            } else {
              $mismatchKey = implode(',', $arrayDiff);
              return response()->json([
                'IsSuccess' => false,
                'Message' => 'Uploaded file key mismatch. Please check ' . $mismatchKey,
                "TotalCount" => 0,
                "Data" => array('File' => $name)
              ], 200);
            }
          } else {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Please upload file.',
              "TotalCount" => 0,
              "Data" => []
            ], 200);
          }
        }
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


  /* 
    utility / Cron Job
  */
  // Currency convert utility
  public function CurrencyRateaAutoUpdate(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $count = 0;
          $ImportFiles = Storage::allFiles('upload/CurrencyConvert');
          foreach ($ImportFiles as $key => $value) {
            $newfile = 'storage/app/' . $value;
            $info = pathinfo($newfile);
            $ext = $info['extension'];
            // $filename = $info['filename']; 
            $name = 'CurrencyConvert-' . '' . time() . '.' . $ext;
            $newpath = '/import/CurrencyConvert/' . $name;
            Storage::move($value, $newpath); // move file upload folder to import
            $full_path = 'storage/app/import/CurrencyConvert/' . $name;
            // $full_path = storage_path('app/import/LeadInformation/'.$name);

            if ($ext == 'xlsx' || $ext == 'xlsb' || $ext == 'xlsm' || $ext == 'xls') {
              $data = Excel::load($full_path)->formatDates(true)->get();
              $UploadFileKeys = (($data->first())->keys())->toArray();
              $keyArray = array(
                'audusd',
                'eurusd',
                'date',
              );
              $arrayDiff = array_diff($keyArray, $UploadFileKeys);
              if (empty($arrayDiff)) {
                $LeadFile = LeadInfoFile::Create([
                  "FileName" => $name,
                  "Message" => 'Currency convert utility',
                  "LeadFileFlage" => 3,
                  "CreatedBy" => $log_user->UserId,
                ]);
                $count = 0;
                foreach ($data as $key => $val) {
                  if (isset($val->audusd) && $val->audusd != '' && isset($val->eurusd) && $val->eurusd != '') {
                    $CurrencyRate = CurrencyRate::where('date', date('Y-m-d h:i:s', strtotime($val->date)))->first();
                    if ($CurrencyRate == null) {
                      $NewRate = CurrencyRate::Create([
                        'LeadFileInfo' => $LeadFile->LeadFileInfo,
                        'AUDUSD' => $val->audusd,
                        'EURUSD' => $val->eurusd,
                        'Date' => date('Y-m-d h:i:s', strtotime($val->date)),
                        'Status' => 1,
                        "CreatedBy" => $log_user->UserId,
                      ]);
                      if ($NewRate) {
                        $AUDUSD = $NewRate->AUDUSD;
                        $EURUSD = $NewRate->EURUSD;
                        $USDAUD = round(1 / $AUDUSD, 10);
                        $EURAUD = round($EURUSD / $AUDUSD, 10);
                        $USDEUR = round(1 / $EURUSD, 10);
                        $AUDEUR = round(1 / $EURAUD, 10);
                        // CurrencyConvert Insert record
                        CurrencyConvert::insert(['CurrencyRateId' => $NewRate->CurrencyRateId, 'USDAUD' => $USDAUD, 'USDEUR' => $USDEUR, 'AUDUSD' => $AUDUSD, 'AUDEUR' => $AUDEUR, 'EURUSD' => $EURUSD, 'EURAUD' => $EURAUD]);
                      }
                      $count = $count + 1;
                    }
                  }
                }
              }
            }
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'File uploaded successfully. Total data import ' . $count . '.',
            'TotalCount' => $count,
            "Data" => []
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
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

  // General Lead Information utility
  public function GeneralLeadInformationAutoUpload(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $count = 0;
          $ImportFiles = Storage::allFiles('upload/LeadInformation');
          foreach ($ImportFiles as $value) {
            $newfile = 'storage/app/' . $value;
            $info = pathinfo($newfile);
            $ext = $info['extension'];
            // $filename = $info['filename'];
            $name = 'LeadInformation-' . '' . time() . '.' . $ext;
            $newpath = '/import/LeadInformation/' . $name;
            Storage::move($value, $newpath); // move file upload folder to import
            $full_path = 'storage/app/import/LeadInformation/' . $name;
            // $full_path = storage_path('app/import/LeadInformation/'.$name);

            if ($ext == 'xlsx' || $ext == 'xlsb' || $ext == 'xlsm' || $ext == 'xls') {
              $data = Excel::load($full_path)->formatDates(true)->get();
              $UploadFileKeys = (($data->first())->keys())->toArray();
              $keyArray = array(
                'lead_id',
                'lead_status',
                'country',
                'is_converted',
                'is_active',
                'date_converted',
                'account_id',
                'sf_account_id',
              );
              $arrayDiff = array_diff($keyArray, $UploadFileKeys);
              if (empty($arrayDiff)) {
                $LeadFile = LeadInfoFile::Create([
                  "FileName" => $name,
                  "Message" => 'General lead information utility',
                  "LeadFileFlage" => 1,
                  "CreatedBy" => $log_user->UserId,
                ]);
                foreach ($data as $key => $val) {
                  if ($val->lead_id != '') {
                    $arr  = array([
                      'LeadFileInfo' => $LeadFile->LeadFileInfo,
                      'LeadId' => $val->lead_id,
                      'LeadStatus' => $val->lead_status,
                      'Country' => $val->country,
                      'IsConverted' => $val->is_converted,
                      'IsActive' => $val->is_active,
                      'DateConverted' => $val->date_converted,
                      'AccountId' => $val->account_id,
                      'SFAccountID' => $val->sf_account_id,
                    ]);
                    LeadInformation::insert($arr);
                    $count = $count + 1;
                  }
                }
              }
            }
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'File uploaded successfully. Total data import ' . $count . '.',
            'TotalCount' => $count,
            "Data" => []
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
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

  // Process General Lead Info
  public function GeneralLeadInformationAutoRevenueGenereate()
  {
    // $processedData = 0;
    try {
      $lead_info = LeadInformation::where('ProcessStatus', 0)->take(1000)->get();
      foreach ($lead_info as $rw) {
        $LeadStatus = LeadStatusMaster::where(['Status' => $rw['LeadStatus']])->first();
        $LeadStatusValid = LeadStatusMaster::where(['Status' => $rw['LeadStatus'], 'IsValid' => 1])->first();
        $LeadData = Lead::where('RefId', $rw['LeadId'])->first(); // get lead data from Refference Id 
        if ($LeadData) {
          try {
            $strdate = str_replace('/', '-', $rw['DateConverted']);
            $time = strtotime($strdate);
            if ($time) {
              try {
                $DateConverted = date('Y-m-d H:i:s', $time);
              } catch (exception $e) {
                $DateConverted = null;
              }
            } else {
              $DateConverted = null;
            }
          } catch (ParseException $e) {
            $DateConverted = null;
          }
          Lead::where('LeadId', $LeadData->LeadId)->update([
            'LeadStatus' => $rw['LeadStatus'],
            'IsActive' => $rw['IsActive'],
            'IsConverted' => $rw['IsConverted'],
            'DateConverted' => $DateConverted,
            'AccountId' => $rw['AccountId'],
            'SFAccountID' => $rw['SFAccountID'],
          ]);
          $LeadData = Lead::where('LeadId', $LeadData['LeadId'])->first(); // get lead data from 
          $campgin_revenutype = Campaign::find($LeadData['CampaignId']);
          if ($campgin_revenutype != null) {
            $revenu_model = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->orderBy('RevenueModelLogId', 'desc')->first();
            $User = User::where('UserId', $campgin_revenutype->UserId)->first();
            //return $revenu_model; die; 
            if ($revenu_model) {
              switch ($revenu_model->RevenueTypeId) {
                case '1':
                  if ($LeadStatus) {
                    $check_revenu_pay = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->first();
                    if ($check_revenu_pay == null) {
                      $LeadDate = date('Y-m-d H:i:s', strtotime($LeadData->CreatedAt));
                      $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $LeadDate)->orderBy('CurrencyRateId', 'desc')->first();
                      if ($CurrencyRate) {
                        $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                      } else {
                        $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $LeadDate)->orderBy('CurrencyRateId', 'desc')->first();
                        if ($CurrencyRate)
                          $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                        else
                          $CurrencyConvert = false;
                      }
                      if ($CurrencyConvert) {
                        // return "CurrencyConvert['CPL']"; die;
                        if ($revenu_model->CurrencyId == 1) {
                          $USDAmount = $revenu_model->Amount;
                          $AUDAmount = $revenu_model->Amount * $CurrencyConvert->USDAUD;
                          $EURAmount = $revenu_model->Amount * $CurrencyConvert->USDEUR;
                        } else if ($revenu_model->CurrencyId == 2) {
                          $USDAmount = $revenu_model->Amount * $CurrencyConvert->AUDUSD;
                          $AUDAmount = $revenu_model->Amount;
                          $EURAmount = $revenu_model->Amount * $CurrencyConvert->AUDEUR;
                        } else if ($revenu_model->CurrencyId == 3) {
                          $USDAmount = $revenu_model->Amount * $CurrencyConvert->EURUSD;
                          $AUDAmount = $revenu_model->Amount * $CurrencyConvert->EURAUD;
                          $EURAmount = $revenu_model->Amount;
                        }
                        $userpay1 = UserRevenuePayment::Create([
                          'UserId' => $campgin_revenutype->UserId,
                          'LeadId' => $LeadData['LeadId'],
                          'RevenueModelLogId' => $revenu_model->RevenueModelLogId,
                          'LeadInformationId' => $rw['LeadInformationId'],
                          'USDAmount' => $USDAmount,
                          'AUDAmount' => $AUDAmount,
                          'EURAmount' => $EURAmount,
                          'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                          'ActualRevenueDate' => $LeadData['CreatedAt'],
                        ]);
                        if ($this->revenue_auto_approve) {
                          $this->AffiliateRevenueAutoAccept($userpay1->UserRevenuePaymentId);
                        }
                        /*if($userpay1->IsComplated==0)
                        {
                          $user_balance = UserBalance::where('UserId',$campgin_revenutype->UserId)->first();
                          if($user_balance!=null) { 
                            $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $userpay1->USDAmount;
                            $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $userpay1->AUDAmount;
                            $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $userpay1->EURAmount;
                            $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $userpay1->USDAmount;
                            $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $userpay1->AUDAmount;
                            $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $userpay1->EURAmount;
                            $user_balance->save();
                          }
                          UserRevenuePayment::where('UserRevenuePaymentId',$userpay1->UserRevenuePaymentId)->update([
                              'IsCompleted'=>1
                          ]); 
                          // $processedData++;
                        }*/
                        LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
                        if ($User->ParentId != null) {
                          $this->getRevenueFromSubAffiliate($userpay1->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                        }
                      }
                    }
                  }
                  break;

                case '2':
                  if ($LeadStatusValid) {
                    $check_revenu_pay1 = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->first();
                    if ($check_revenu_pay1 == null) {
                      $LeadDate = date('Y-m-d H:i:s', strtotime($LeadData->CreatedAt));
                      $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $LeadDate)->orderBy('CurrencyRateId', 'desc')->first();
                      if ($CurrencyRate) {
                        $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                      } else {
                        $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $LeadDate)->orderBy('CurrencyRateId', 'desc')->first();
                        if ($CurrencyRate)
                          $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                        else
                          $CurrencyConvert = false;
                      }
                      if ($CurrencyConvert) {
                        // return "CurrencyConvert['C-CPL']"; die;
                        if ($revenu_model->CurrencyId == 1) {
                          $USDAmount = $revenu_model->Amount;
                          $AUDAmount = $revenu_model->Amount * $CurrencyConvert->USDAUD;
                          $EURAmount = $revenu_model->Amount * $CurrencyConvert->USDEUR;
                        } else if ($revenu_model->CurrencyId == 2) {
                          $USDAmount = $revenu_model->Amount * $CurrencyConvert->AUDUSD;
                          $AUDAmount = $revenu_model->Amount;
                          $EURAmount = $revenu_model->Amount * $CurrencyConvert->AUDEUR;
                        } else if ($revenu_model->CurrencyId == 3) {
                          $USDAmount = $revenu_model->Amount * $CurrencyConvert->EURUSD;
                          $AUDAmount = $revenu_model->Amount * $CurrencyConvert->EURAUD;
                          $EURAmount = $revenu_model->Amount;
                        }

                        $userpay = UserRevenuePayment::Create([
                          'UserId' => $campgin_revenutype->UserId,
                          'LeadId' => $LeadData['LeadId'],
                          'RevenueModelLogId' => $revenu_model->RevenueModelLogId,
                          'LeadInformationId' => $rw['LeadInformationId'],
                          'USDAmount' => $USDAmount,
                          'AUDAmount' => $AUDAmount,
                          'EURAmount' => $EURAmount,
                          'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                          'ActualRevenueDate' => $LeadData['CreatedAt'],
                        ]);
                        if ($this->revenue_auto_approve) {
                          $this->AffiliateRevenueAutoAccept($userpay->UserRevenuePaymentId);
                        }

                        /*if($userpay->IsComplated==0)
                        {
                          $user_balance = UserBalance::where('UserId',$campgin_revenutype->UserId)->first();
                          if($user_balance!=null) { 
                            $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $userpay->USDAmount;
                            $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $userpay->AUDAmount;
                            $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $userpay->EURAmount;
                            $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $userpay->USDAmount;
                            $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $userpay->AUDAmount;
                            $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $userpay->EURAmount;
                            $user_balance->save();
                          }
                          UserRevenuePayment::where('UserRevenuePaymentId',$userpay->UserRevenuePaymentId)->update([
                              'IsCompleted'=>1
                          ]);
                          // $processedData++;
                        }*/
                        LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
                        if ($User->ParentId != null) {
                          $this->getRevenueFromSubAffiliate($userpay->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                        }
                      }
                    }
                  }
                  break;

                case '3':
                  // return 'cpa'; die;
                  if ($LeadData['IsConverted'] == 1 && $LeadData['DateConverted'] != NULL) {
                    $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $LeadData->DateConverted)->orderBy('CurrencyRateId', 'desc')->first();
                    if ($CurrencyRate) {
                      $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                    } else {
                      $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $LeadData->DateConverted)->orderBy('CurrencyRateId', 'desc')->first();
                      if ($CurrencyRate)
                        $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                      else
                        $CurrencyConvert = false;
                    }
                    if ($CurrencyConvert) {
                      // Get RevenueModel Log Id List 
                      $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                      // check User Revenue Payment already assign
                      $check_revenu_pay3 = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                      if ($check_revenu_pay3 == null) {
                        // get Lead Information data
                        $LeadInformation = LeadInformation::where('LeadId', $LeadData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                        // get CountryId from Lead Information 
                        $CountryId = CountryMaster::where('CountryNameShortCode', $LeadInformation->Country)->first();
                        if ($CountryId) {
                          // get Revenue CPA Country group from log
                          $RevenueCpaCountryLog = RevenueCpaCountryLog::where('RevenueModelLogId', $revenu_model->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId->CountryId . ', RevenueCountrys)')->first();
                          // get all leads data of Lead Activity process status = 2 for count number of transactions of this country group
                          $Leads = Lead::with('LeadInformation')->whereHas(
                            'LeadInformation',
                            function ($qr) {
                              $qr->where('ProcessStatus', 2);
                            }
                          )->where('CampaignId', $LeadData['CampaignId'])->where('IsConverted', 1)->get();

                          $count = 1;
                          // check number of coutry group revenue payment already assigned
                          foreach ($Leads as $LeadsData) {
                            foreach ($LeadsData['LeadInformation'] as $LeadInformation) {
                              if ($LeadInformation['ProcessStatus'] == 2) {
                                $leadId = Lead::where('RefId', $LeadInformation['LeadId'])->first();
                                $UserRevenuePayment = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $leadId['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->get();
                                foreach ($UserRevenuePayment as $value) {
                                  $RevenueModelLog = RevenueModelLog::find($value['RevenueModelLogId']);
                                  $LeadInformation2 = LeadInformation::where('LeadId', $LeadInformation['LeadId'])->orderBy('LeadInformationId', 'desc')->first();
                                  $CountryId2 = CountryMaster::where('CountryNameShortCode', $LeadInformation2->Country)->first();
                                  if ($CountryId2) {
                                    $RevenueCpaCountryLog1 = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId2->CountryId . ', RevenueCountrys)')->first();
                                    $CountryOld = explode(',', $RevenueCpaCountryLog1['RevenueCountrys']);
                                    $CountryNew = explode(',', $RevenueCpaCountryLog['RevenueCountrys']);
                                    $result = array_intersect($CountryOld, $CountryNew);
                                    // check new revenue country match with old payment
                                    if ($result) {
                                      $count++;
                                    }
                                  }
                                }
                              }
                            }
                          }
                          // return  'cpa-'.$count; die; 
                          $RevenueCpaTraderLog = RevenueCpaTraderLog::where('RevenueCpaCountryLogId', $RevenueCpaCountryLog->RevenueCpaCountryLogId)->get();
                          foreach ($RevenueCpaTraderLog as $value) {
                            // if Range Expression is -(in between two values)
                            if ($value['RangeExpression'] == 1) {
                              if ($count >= $value['RangeFrom'] && $count <= $value['RangeTo']) {
                                $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();

                                if ($revenu_model->CurrencyId == 1) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                  $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                } else if ($revenu_model->CurrencyId == 2) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount;
                                  $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                } else if ($revenu_model->CurrencyId == 3) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                  $EURAmount = $RevenueCpaPlanLog->Amount;
                                }

                                $UserRevenuePayment = UserRevenuePayment::Create([
                                  'UserId' => $campgin_revenutype->UserId,
                                  'LeadId' => $LeadData['LeadId'],
                                  'RevenueModelLogId' => $revenu_model->RevenueModelLogId,
                                  'LeadInformationId' => $rw['LeadInformationId'],
                                  'USDAmount' => $USDAmount,
                                  'AUDAmount' => $AUDAmount,
                                  'EURAmount' => $EURAmount,
                                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                  'ActualRevenueDate' => $LeadData['DateConverted'],
                                ]);
                                if ($this->revenue_auto_approve) {
                                  $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                }
                                /*if($UserRevenuePayment->IsCompleted == 0) 
                                {
                                  $user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                  if($user_balance) 
                                  {
                                    $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                    $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                    $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                    $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                    $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                    $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                    $user_balance->save(); 
                                  }
                                  UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                      'IsCompleted' => 1
                                  ]);
                                  // $processedData++;
                                }*/
                                LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
                                if ($User->ParentId != null) {
                                  $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                }
                              }
                            }
                            // if Range Expression is >(Greater than value)
                            if ($value['RangeExpression'] == 3) {
                              if ($count > $value['RangeFrom']) {
                                $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                // return $RevenueCpaPlanLog; die; 
                                if ($revenu_model->CurrencyId == 1) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                  $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                } else if ($revenu_model->CurrencyId == 2) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount;
                                  $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                } else if ($revenu_model->CurrencyId == 3) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                  $EURAmount = $RevenueCpaPlanLog->Amount;
                                }
                                $UserRevenuePayment = UserRevenuePayment::Create([
                                  'UserId' => $campgin_revenutype->UserId,
                                  'LeadId' => $LeadData['LeadId'],
                                  'RevenueModelLogId' => $revenu_model->RevenueModelLogId,
                                  'LeadInformationId' => $rw['LeadInformationId'],
                                  'USDAmount' => $USDAmount,
                                  'AUDAmount' => $AUDAmount,
                                  'EURAmount' => $EURAmount,
                                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                  'ActualRevenueDate' => $LeadData['DateConverted'],
                                ]);
                                if ($this->revenue_auto_approve) {
                                  $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                }
                                /*if($UserRevenuePayment->IsCompleted == 0) 
                                {
                                  $user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();

                                  if($user_balance) { 
                                    $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                    $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                    $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                    $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                    $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                    $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                    $user_balance->save(); 
                                  }
                                  UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                    'IsCompleted' => 1
                                  ]);
                                  // $processedData++;
                                }*/
                                LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
                                if ($User->ParentId != null) {
                                  $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                  break;

                default:
                  break;
              }
            }
          }
        }
        LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
      }

      $Message = 'Data is processed successfully.';
      /*$Message = 'Data is processed successfully. Total processed data '.count($lead_info).', total revenue generated '.$processedData.'.';
      $Message = 'Data is processed successfully. Total processed data '.$count.', total revenue generated '.$processedData.', duplicate data '.$exist.', new data '.$new.'.';
      LeadInfoFile::where('LeadFileInfo', $fileid)->update([
        'Message'=> $Message
      ]);*/
      return response()->json([
        'IsSuccess' => true,
        'Message' => $Message,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    } catch (exception $e) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    }
  }

  // Auto revenue approve if set by admin
  public function AffiliateRevenueAutoAccept($UserRevenuePaymentId)
  {
    // return $request->all();
    // $UserRevenuePaymentId = $request->UserRevenuePaymentId;
    $UserRevenuePayment = UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePaymentId)->where('PaymentStatus', 0)->first();
    if ($UserRevenuePayment) {
      // update user balance
      $user_balance = UserBalance::where('UserId', $UserRevenuePayment->UserId)->first();
      if ($user_balance) {
        $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
        $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
        $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
        $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
        $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
        $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
        $user_balance->save();
        UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
          'PaymentStatus' => 1
        ]);
      }
    }
  }
  // Sub-affiliate revenue generate
  public function GetRevenueFromSubAffiliate($UserRevenuePaymentId, $CurrencyConvertId)
  {
    $UserRevenuePayment =  UserRevenuePayment::find($UserRevenuePaymentId);
    $UserId = $UserRevenuePayment->UserId;  // dynamic
    $First_user = User::where('UserId', $UserId)->first();
    if ($First_user->ParentId != null) {
      $new_array = [];
      $parentUsers = DB::select("SELECT T2.UserId, T2.FirstName
                  FROM(SELECT @r AS _id, (SELECT @r := ParentId FROM users WHERE UserId = _id) AS parent_id, @l := @l + 1 AS lvl FROM (SELECT @r := $First_user->UserId, @l := 0) vars,users h
                  WHERE @r <> 0) T1
                  JOIN users T2 ON T1._id = T2.UserId
                  ORDER BY T1.lvl");

      $UserRevenuePaymentId = $UserRevenuePayment->UserRevenuePaymentId;
      $amt = $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;

      $SubAffiliateId = $First_user->UserId;  // parent user Id
      $parent_user = User::where('UserId', $SubAffiliateId)->first(); // parent user

      $CurrencyConvert = CurrencyConvert::find($CurrencyConvertId);

      foreach ($parentUsers as $rw) {
        if ($rw->UserId != $First_user->UserId) {
          $Get_revenue_Data = UserRevenueType::where('UserId', $rw->UserId)->where('RevenueTypeId', 8)->get();
          if ($Get_revenue_Data->count() >= 1) {
            foreach ($Get_revenue_Data as $Get_revenue_type) {
              $get_revenue_model = RevenueModelLog::where('RevenueModelId', $Get_revenue_type->RevenueModelId)->orderBy('RevenueModelLogId', 'desc')->first();

              if ($get_revenue_model != null) {
                $amount = $amt * $get_revenue_model->Percentage / 100;
                $amt = $amount;
                $USDAmount = $amount;
                $AUDAmount = $amount * $CurrencyConvert->USDAUD;
                $EURAmount = $amount * $CurrencyConvert->USDEUR;

                $subrevenupayment = UserSubRevenue::create([
                  'UserId' => $rw->UserId,
                  'UserRevenuePaymentId' => $UserRevenuePaymentId,
                  'USDAmount' => $USDAmount,
                  'AUDAmount' => $AUDAmount,
                  'EURAmount' => $EURAmount,
                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                ]);
                // $user_balance = UserBalance::where('UserId',$subrevenupayment->UserId)->first();
                /*if($user_balance!=null) {     
                  $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $subrevenupayment->USDAmount;
                  $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $subrevenupayment->AUDAmount;
                  $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $subrevenupayment->EURAmount;
                  $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $subrevenupayment->USDAmount;
                  $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $subrevenupayment->AUDAmount;
                  $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $subrevenupayment->EURAmount;
                  $user_balance->save();
                }*/
                $userpay1 = UserRevenuePayment::create([
                  'UserId' => $subrevenupayment->UserId,
                  'LeadId' => $UserRevenuePayment->LeadId,
                  'RevenueModelLogId' => $get_revenue_model->RevenueModelLogId,
                  'UserSubRevenueId' => $subrevenupayment->UserSubRevenueId,
                  'USDAmount' => $subrevenupayment->USDAmount,
                  'AUDAmount' => $subrevenupayment->AUDAmount,
                  'EURAmount' => $subrevenupayment->EURAmount,
                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                  'ActualRevenueDate' => $UserRevenuePayment->ActualRevenueDate,
                ]);
                if ($this->revenue_auto_approve) {
                  $this->AffiliateRevenueAutoAccept($userpay1->UserRevenuePaymentId);
                }

                if ($get_revenue_model->Type == 1) {
                  // Debit from sub affiliate(From)
                  $subrevenupaymentdr = UserSubRevenue::create([
                    'UserId' => $SubAffiliateId,
                    'UserRevenuePaymentId' => $UserRevenuePaymentId,
                    'USDAmount' => -$USDAmount,
                    'AUDAmount' => -$AUDAmount,
                    'EURAmount' => -$EURAmount,
                    'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                  ]);
                  // $user_balance = UserBalance::where('UserId', $subrevenupaymentdr->UserId)->first();
                  /*if($user_balance != null) { 
                    $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $subrevenupaymentdr->USDAmount;
                    $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $subrevenupaymentdr->AUDAmount;
                    $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $subrevenupaymentdr->EURAmount;
                    $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $subrevenupaymentdr->USDAmount;
                    $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $subrevenupaymentdr->AUDAmount;
                    $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $subrevenupaymentdr->EURAmount;
                    $user_balance->save();
                  }*/

                  $userpay2 = UserRevenuePayment::create([
                    'UserId' => $subrevenupaymentdr->UserId,
                    'LeadId' => $UserRevenuePayment->LeadId,
                    'RevenueModelLogId' => $get_revenue_model->RevenueModelLogId,
                    'UserSubRevenueId' => $subrevenupaymentdr->UserSubRevenueId,
                    'USDAmount' => $subrevenupaymentdr->USDAmount,
                    'AUDAmount' => $subrevenupaymentdr->AUDAmount,
                    'EURAmount' => $subrevenupaymentdr->EURAmount,
                    'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                    'ActualRevenueDate' => $UserRevenuePayment->ActualRevenueDate,
                  ]);
                  if ($this->revenue_auto_approve) {
                    $this->AffiliateRevenueAutoAccept($userpay2->UserRevenuePaymentId);
                  }
                } else {
                  // debit from royal revenue(on top of)
                  $subrevenupaymentdr = RoyalRevenue::Create([
                    'UserRevenuePaymentId' => $UserRevenuePayment->UserRevenuePaymentId,
                    'UserId' => $rw->UserId,
                    'LeadId' => $UserRevenuePayment->LeadId,
                    'RevenueModelLogId' => $get_revenue_model->RevenueModelLogId,
                    'LeadActivityId' => $UserRevenuePayment->LeadActivityId,
                    'USDAmount' => -$USDAmount,
                    'AUDAmount' => -$AUDAmount,
                    'EURAmount' => -$EURAmount,
                    'USDSpreadAmount' => 0,
                    'AUDSpreadAmount' => 0,
                    'EURSpreadAmount' => 0,
                    'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                    'ActualRevenueDate' => $UserRevenuePayment->ActualRevenueDate,
                  ]);
                }
                $SubAffiliateId = $rw->UserId;
              }
            }
          }
        }
      }
    }
  }

  // Daily Lead Activity utility
  public function DailyLeadActivityAutoUpload(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $count = 0;
          $ImportFiles = Storage::allFiles('upload/LeadActivity');
          foreach ($ImportFiles as $key => $value) {
            $newfile = 'storage/app/' . $value;
            $info = pathinfo($newfile);
            $ext = $info['extension'];
            $name = 'LeadActivity-' . time() . '.' . $ext;
            $newpath = '/import/LeadActivity/' . $name;
            Storage::move($value, $newpath); // move file upload folder to import
            $full_path = 'storage/app/import/LeadActivity/' . $name;
            // $full_path = storage_path('app/import/LeadInformation/'.$name);

            if ($ext == 'xlsx' || $ext == 'xlsb' || $ext == 'xlsm' || $ext == 'xls') {
              $data = Excel::load($full_path)->formatDates(true)->get();
              $UploadFileKeys = (($data->first())->keys())->toArray();
              $keyArray = array(
                'date',
                'account_id',
                'platform_login',
                'base_currency',
                'volume_traded',
                'number_of_transactions',
                'deposits_base_currency',
                'deposits_usd',
                'deposits_affiliate_currency',
                'royal_commission_base_currency',
                'royal_commission_usd',
                'royal_commission_affilliate_currency',
                'royal_spread_base_currency',
                'royal_spread_usd',
                'royal_spread_affiliate_currency',
                'affiliate_commission_base_currency',
                'affiliate_commission_usd',
                'affiliate_commission_affilliate_currency',
                'affiliate_spread_base_currency',
                'affiliate_spread_usd',
                'affiliate_spread_affiliate_currency',
              );
              $arrayDiff = array_diff($keyArray, $UploadFileKeys);
              if (empty($arrayDiff)) {
                $LeadActivityFile = LeadInfoFile::Create([
                  "FileName" => $name,
                  "Message" => 'Daily lead activity utility',
                  "LeadFileFlage" => 2,
                  "CreatedBy" => $log_user->UserId,
                ]);
                foreach ($data as $key => $val) {
                  if ($val->account_id != '') {
                    LeadActivity::Create([
                      'LeadFileInfo' => $LeadActivityFile->LeadFileInfo,
                      'LeadsActivityDate' => date('Y-m-d H:i:s', strtotime($val->date)),
                      'AccountId' => $val->account_id,
                      'PlatformLogin' => $val->platform_login,
                      'BaseCurrency' => $val->base_currency,
                      'VolumeTraded' => $val->volume_traded,
                      'NumberOfTransactions' => $val->number_of_transactions,
                      'DepositsBaseCurrency' => $val->deposits_base_currency,
                      'DepositsUSD' => $val->deposits_usd,
                      'DepositsAffCur' => $val->deposits_affiliate_currency,
                      'RoyalCommissionBaseCur' => $val->royal_commission_base_currency,
                      'RoyalCommissionUSD' => $val->royal_commission_usd,
                      'RoyalCommissionAffCur' => $val->royal_commission_affilliate_currency,
                      'RoyalSpreadBaseCur' => $val->royal_spread_base_currency,
                      'RoyalSpreadUSD' => $val->royal_spread_usd,
                      'RoyalSpreadAffCur' => $val->royal_spread_affiliate_currency,
                      'AffCommissionBaseCur' => $val->affiliate_commission_base_currency,
                      'AffCommissionUSD' => $val->affiliate_commission_usd,
                      'AffCommissionAffCur' => $val->affiliate_commission_affilliate_currency,
                      'AffSpreadBaseCur' => $val->affiliate_spread_base_currency,
                      'AffSpreadUSD' => $val->affiliate_spread_usd,
                      'AffSpreadAffCur' => $val->affiliate_spread_affiliate_currency,
                    ]);
                    $count = $count + 1;
                  }
                }
              }
            }
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'File uploaded successfully. Total data import ' . $count . '.',
            'TotalCount' => $count,
            "Data" => []
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
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

  // Process Daily Lead Activity
  public function DailyLeadActivityAutoRevenueGenereate()
  {
    // $processedData = 0;
    try {
      $lead_info = LeadActivity::where('ProcessStatus', 0)->take(1000)->get();
      foreach ($lead_info as $rw) {
        $LeadData = Lead::where('AccountId', $rw['AccountId'])->first();
        if ($LeadData) {
          $LeadStatus = LeadStatusMaster::where(['Status' => $LeadData['LeadStatus']])->first();
          $LeadStatusValid = LeadStatusMaster::where(['Status' => $LeadData['LeadStatus'], 'IsValid' => 1])->first();
          $campgin_revenutype = Campaign::where('CampaignId', $LeadData['CampaignId'])->first();

          if ($campgin_revenutype) {
            $RevenueModelLog = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->orderBy('RevenueModelLogId', 'desc')->first();
            $User = User::find($campgin_revenutype->UserId);
            if ($RevenueModelLog) {
              try {
                $strdate = str_replace('/', '-', $rw['LeadsActivityDate']);
                $time = strtotime($strdate);
                if ($time) {
                  try {
                    $LeadsActivityDate = date('Y-m-d H:i:s', $time);
                  } catch (exception $e) {
                    $LeadsActivityDate = null;
                  }
                } else {
                  $LeadsActivityDate = null;
                }
              } catch (ParseException $e) {
                $LeadsActivityDate = null;
              }
              if ($LeadsActivityDate != null) {
                $CurrencyRate = CurrencyRate::where('Status', 1)->where('Date', $LeadsActivityDate)->orderBy('CurrencyRateId', 'desc')->first();
                if ($CurrencyRate) {
                  $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                } else {
                  $CurrencyRate = CurrencyRate::where('Status', 1)->where('Date', '<', $LeadsActivityDate)->orderBy('CurrencyRateId', 'desc')->first();
                  if ($CurrencyRate) {
                    $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                  } else {
                    $CurrencyConvert = false;
                  }
                }
              } else {
                $CurrencyConvert = false;
              }
              if ($CurrencyConvert) {
                // CPL - Revenue type = 1
                if ($RevenueModelLog->RevenueTypeId == 1) {
                  $User = User::find($campgin_revenutype->UserId);
                  // Get RevenueModel Log Id List 
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  /*
                    Royal Revenue generate
                  */
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  $check_revenu_pay1 = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_revenu_pay1 == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;
                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw['LeadActivityId'],
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);
                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */
                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */
                }
                // C-CPL - Revenue type = 2
                if ($RevenueModelLog->RevenueTypeId == 2) {
                  // Get RevenueModel Log Id List 
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  /*
                    Royal Revenue generate
                  */
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  $check_revenu_pay2 = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_revenu_pay2 == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw['LeadActivityId'],
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */

                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */
                }
                // CPA - Revenue type = 3
                if ($RevenueModelLog->RevenueTypeId == 3) {
                  // Get RevenueModel Log Id List 
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  /*
                    Royal Revenue generate
                  */
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  $check_royal_revenu_pay = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_royal_revenu_pay == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw['LeadActivityId'],
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */

                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */
                }
                // C-CPA - Revenue type = 4
                else if ($RevenueModelLog->RevenueTypeId == 4) {
                  // return 'C-CPA';
                  // Get RevenueModel Log Id List
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  /*
                    Royal Revenue generate
                  */
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  $check_royal_revenu_pay = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_royal_revenu_pay == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */
                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */

                  if ($LeadData['IsConverted'] == 1) {
                    // check User Revenue Payment already assign
                    $check_revenu_pay4 = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                    if ($check_revenu_pay4 == null) {
                      // CurrencyConvert 
                      $LeadsActivityDate = $LeadData['DateConverted'];
                      if ($LeadsActivityDate != null) {
                        $CurrencyRate = CurrencyRate::where('Status', 1)->where('Date', $LeadsActivityDate)->orderBy('CurrencyRateId', 'desc')->first();
                        if ($CurrencyRate) {
                          $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                        } else {
                          $CurrencyRate = CurrencyRate::where('Status', 1)->where('Date', '<', $LeadsActivityDate)->orderBy('CurrencyRateId', 'desc')->first();
                          if ($CurrencyRate) {
                            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                          } else {
                            $CurrencyConvert = false;
                          }
                        }
                      } else {
                        $CurrencyConvert = false;
                      }
                      // End. CurrencyConvert
                      if ($CurrencyConvert) {
                        // return $RevenueModelLogIds;
                        if ($RevenueModelLog['TradeType'] == 1) {
                          // return 'TradeType = 1';
                          if ($rw['VolumeTraded'] >= $RevenueModelLog['TradeValue']) {
                            // get Lead Information data
                            $LeadInformation = LeadInformation::where('LeadId', $LeadData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                            // get CountryId from Lead Information 
                            $CountryId = CountryMaster::where('CountryNameShortCode', $LeadInformation->Country)->first();
                            if ($CountryId) {
                              // get Revenue CPA Country group from log
                              $RevenueCpaCountryLog = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId->CountryId . ', RevenueCountrys)')->first();
                              // get all leads data of Lead Activity process status = 2 for count number of transactions of this country group
                              $Leads = Lead::with('LeadActivity')->whereHas('LeadActivity', function ($qr) {
                                $qr->where('ProcessStatus', 2);
                              })->where('CampaignId', $LeadData['CampaignId'])->where('IsConverted', 1)->get();

                              $count = 1;
                              // check number of coutry group revenue payment already assigned
                              foreach ($Leads as $LeadsData) {
                                foreach ($LeadsData['LeadActivity'] as $LeadActivity) {
                                  if ($LeadActivity['ProcessStatus'] == 2) {
                                    $UserRevenuePayment = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadActivity['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->get();
                                    foreach ($UserRevenuePayment as $value) {
                                      $RevenueModelLog = RevenueModelLog::find($value['RevenueModelLogId']);
                                      $LeadInformation2 = LeadInformation::where('LeadId', $LeadsData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                                      $CountryId2 = CountryMaster::where('CountryNameShortCode', $LeadInformation2->Country)->first();
                                      if ($CountryId2) {
                                        $RevenueCpaCountryLog1 = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId2->CountryId . ', RevenueCountrys)')->first();
                                        $CountryOld = explode(',', $RevenueCpaCountryLog1['RevenueCountrys']);
                                        $CountryNew = explode(',', $RevenueCpaCountryLog['RevenueCountrys']);
                                        $result = array_intersect($CountryOld, $CountryNew);
                                        // check new revenue country match with old payment
                                        if ($result) {
                                          $count++;
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                              // return  'count:-'.$count; die; 
                              $RevenueCpaTraderLog = RevenueCpaTraderLog::where('RevenueCpaCountryLogId', $RevenueCpaCountryLog->RevenueCpaCountryLogId)->get();
                              foreach ($RevenueCpaTraderLog as $value) {
                                // if Range Expression is -(in between two values)
                                if ($value['RangeExpression'] == 1) {
                                  if ($count >= $value['RangeFrom'] && $count <= $value['RangeTo']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die;  
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }

                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance) 
                                    {  
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save(); 
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                      'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::find($rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++; 
                                  }
                                }
                                // if Range Expression is >(Greater than value)
                                if ($value['RangeExpression'] == 3) {
                                  if ($count > $value['RangeFrom']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance) 
                                    { 
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save(); 
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                      'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::where('LeadActivityId', $rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                              }
                            }
                          }
                        } else if ($RevenueModelLog['TradeType'] == 2) {
                          // return "TradeType = 2";
                          if ($rw['DepositsUSD'] >= $RevenueModelLog['TradeValue']) {
                            // return 'TradeType 2'; die;
                            // get Lead Information data
                            $LeadInformation = LeadInformation::where('LeadId', $LeadData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                            // get CountryId from Lead Information 
                            $CountryId = CountryMaster::where('CountryNameShortCode', $LeadInformation->Country)->first();
                            if ($CountryId) {
                              // get Revenue CPA Country group from log
                              $RevenueCpaCountryLog = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId->CountryId . ', RevenueCountrys)')->first();
                              // get all leads data of Lead Activity process status = 2 for count number of transactions of this country group
                              $Leads = Lead::with('LeadActivity')->whereHas('LeadActivity', function ($qr) {
                                $qr->where('ProcessStatus', 2);
                              })->where('CampaignId', $LeadData['CampaignId'])->where('IsConverted', 1)->get();

                              $count = 1;
                              // check number of coutry group revenue payment already assigned
                              foreach ($Leads as $LeadsData) {
                                foreach ($LeadsData['LeadActivity'] as $LeadActivity) {
                                  if ($LeadActivity['ProcessStatus'] == 2) {
                                    $UserRevenuePayment = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadActivity['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->get();
                                    foreach ($UserRevenuePayment as $value) {
                                      $RevenueModelLog = RevenueModelLog::find($value['RevenueModelLogId']);
                                      $LeadInformation2 = LeadInformation::where('LeadId', $LeadsData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                                      $CountryId2 = CountryMaster::where('CountryNameShortCode', $LeadInformation2->Country)->first();
                                      if ($CountryId2) {
                                        $RevenueCpaCountryLog1 = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId2->CountryId . ', RevenueCountrys)')->first();
                                        $CountryOld = explode(',', $RevenueCpaCountryLog1['RevenueCountrys']);
                                        $CountryNew = explode(',', $RevenueCpaCountryLog['RevenueCountrys']);
                                        $result = array_intersect($CountryOld, $CountryNew);
                                        // check new revenue country match with old payment
                                        if ($result) {
                                          $count++;
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                              // return  $count; die; 
                              $RevenueCpaTraderLog = RevenueCpaTraderLog::where('RevenueCpaCountryLogId', $RevenueCpaCountryLog->RevenueCpaCountryLogId)->get();
                              foreach ($RevenueCpaTraderLog as $value) {
                                // if Range Expression is -(in between two values)
                                if ($value['RangeExpression'] == 1) {
                                  if ($count >= $value['RangeFrom'] && $count <= $value['RangeTo']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance)
                                    {  
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save(); 
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                        'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::find($rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                                // if Range Expression is >(Greater than value)
                                if ($value['RangeExpression'] == 3) {
                                  if ($count > $value['RangeFrom']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance) 
                                    { 
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save();
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                        'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::where('LeadActivityId', $rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                              }
                            }
                          }
                        } else if ($RevenueModelLog['TradeType'] == 3) {
                          // return "TradeType = 3";
                          if ($rw['NumberOfTransactions'] >= $RevenueModelLog['TradeValue']) {
                            // return 'TradeType 3'; die;
                            // get Lead Information data
                            $LeadInformation = LeadInformation::where('LeadId', $LeadData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                            // get CountryId from Lead Information 
                            $CountryId = CountryMaster::where('CountryNameShortCode', $LeadInformation->Country)->first();
                            if ($CountryId) {
                              // get Revenue CPA Country group from log
                              $RevenueCpaCountryLog = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId->CountryId . ', RevenueCountrys)')->first();
                              // get all leads data of Lead Activity process status = 2 for count number of transactions of this country group
                              $Leads = Lead::with('LeadActivity')->whereHas('LeadActivity', function ($qr) {
                                $qr->where('ProcessStatus', 2);
                              })->where('CampaignId', $LeadData['CampaignId'])->where('IsConverted', 1)->get();

                              $count = 1;
                              // check number of coutry group revenue payment already assigned
                              foreach ($Leads as $LeadsData) {
                                foreach ($LeadsData['LeadActivity'] as $LeadActivity) {
                                  if ($LeadActivity['ProcessStatus'] == 2) {
                                    $UserRevenuePayment = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadActivity['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->get();
                                    foreach ($UserRevenuePayment as $value) {
                                      $RevenueModelLog = RevenueModelLog::find($value['RevenueModelLogId']);
                                      $LeadInformation2 = LeadInformation::where('LeadId', $LeadsData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                                      $CountryId2 = CountryMaster::where('CountryNameShortCode', $LeadInformation2->Country)->first();
                                      if ($CountryId2) {
                                        $RevenueCpaCountryLog1 = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId2->CountryId . ', RevenueCountrys)')->first();
                                        $CountryOld = explode(',', $RevenueCpaCountryLog1['RevenueCountrys']);
                                        $CountryNew = explode(',', $RevenueCpaCountryLog['RevenueCountrys']);
                                        $result = array_intersect($CountryOld, $CountryNew);
                                        // check new revenue country match with old payment
                                        if ($result) {
                                          $count++;
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                              // return  $count; die; 
                              $RevenueCpaTraderLog = RevenueCpaTraderLog::where('RevenueCpaCountryLogId', $RevenueCpaCountryLog->RevenueCpaCountryLogId)->get();
                              foreach ($RevenueCpaTraderLog as $value) {
                                // if Range Expression is -(in between two values)
                                if ($value['RangeExpression'] == 1) {
                                  if ($count >= $value['RangeFrom'] && $count <= $value['RangeTo']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance)
                                    {  
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save(); 
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                        'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::find($rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                                // if Range Expression is >(Greater than value)
                                if ($value['RangeExpression'] == 3) {
                                  if ($count > $value['RangeFrom']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance) 
                                    { 
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save();
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                        'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::where('LeadActivityId', $rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
                // Revenue Share - Revenue type = 5
                else if ($RevenueModelLog->RevenueTypeId == 5) {
                  // Get RevenueModel Log Id List
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  /*
                    Royal Revenue generate
                  */
                  $check_royal_revenu_pay = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_royal_revenu_pay == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */
                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */

                  $AccountId = $rw['AccountId'];
                  $check_revenue_payment = UserRevenuePayment::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal, $AccountId) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal)->where('AccountId', $AccountId);
                  })->where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->first();
                  if ($check_revenue_payment == null) {
                    // currency convert 
                    $USDAmount = $rw->RoyalCommissionUSD;
                    $AUDAmount = $rw->RoyalCommissionUSD * $CurrencyConvert->USDAUD;
                    $EURAmount = $rw->RoyalCommissionUSD * $CurrencyConvert->USDEUR;
                    // Spread amount
                    $USDAmount1 = $rw->RoyalSpreadUSD;
                    $AUDAmount1 = $rw->RoyalSpreadUSD * $CurrencyConvert->USDAUD;
                    $EURAmount1 = $rw->RoyalSpreadUSD * $CurrencyConvert->USDEUR;

                    $UserRevenuePayment = UserRevenuePayment::Create([
                      'UserId' => $campgin_revenutype->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $USDAmount * $RevenueModelLog->Percentage / 100,
                      'AUDAmount' => $AUDAmount * $RevenueModelLog->Percentage / 100,
                      'EURAmount' => $EURAmount * $RevenueModelLog->Percentage / 100,
                      'SpreadUSDAmount' => $USDAmount1 * $RevenueModelLog->Percentage / 100,
                      'SpreadAUDAmount' => $AUDAmount1 * $RevenueModelLog->Percentage / 100,
                      'SpreadEURAmount' => $EURAmount1 * $RevenueModelLog->Percentage / 100,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);
                    if ($this->revenue_auto_approve) {
                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                    }
                    // $Total_revenue = $RoyalCommissionUSD + $RoyalSpreadUSD;
                    /*if ($UserRevenuePayment->IsComplated == 0) 
                    {
                      $user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                      if ($user_balance != null) { 
                        $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
                        $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
                        $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
                        $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
                        $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
                        $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
                        $user_balance->save();
                      }
                      UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                          'IsCompleted' => 1
                      ]);
                      // $processedData++;
                    }*/
                    if ($User->ParentId != null) {
                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                    }
                  }
                }
                // FX Revenue Share - Revenue type = 6
                else if ($RevenueModelLog->RevenueTypeId == 6) {
                  // Get RevenueModel Log Id List
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  /*
                    Royal Revenue generate
                  */
                  $check_royal_revenu_pay = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_royal_revenu_pay == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */
                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */

                  $AccountId = $rw['AccountId'];
                  $check_revenue_payment = UserRevenuePayment::with('LeadActivity')->whereHas('LeadActivity',  function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal, $AccountId) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal)->where('AccountId', $AccountId);
                  })->where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->first();
                  if ($check_revenue_payment == null) {
                    // currency convert 
                    $USDAmount = $rw->AffCommissionUSD;
                    $AUDAmount = $rw->AffCommissionUSD * $CurrencyConvert->USDAUD;
                    $EURAmount = $rw->AffCommissionUSD * $CurrencyConvert->USDEUR;
                    // Spread amount
                    $USDAmount1 = $rw->AffSpreadUSD;
                    $AUDAmount1 = $rw->AffSpreadUSD * $CurrencyConvert->USDAUD;
                    $EURAmount1 = $rw->AffSpreadUSD * $CurrencyConvert->USDEUR;
                    $UserRevenuePayment = UserRevenuePayment::Create([
                      'UserId' => $campgin_revenutype->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $USDAmount,
                      'AUDAmount' => $AUDAmount,
                      'EURAmount' => $EURAmount,
                      'SpreadUSDAmount' => $USDAmount1,
                      'SpreadAUDAmount' => $AUDAmount1,
                      'SpreadEURAmount' => $EURAmount1,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);
                    if ($this->revenue_auto_approve) {
                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                    }
                    // $Total_revenue = $AffCommissionUSD + $AffSpreadUSD; // usd Total Revenue
                    /*if ($UserRevenuePayment->IsComplated == 0) {
                      $user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                      if ($user_balance != null) { 
                        $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
                        $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
                        $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;

                        $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
                        $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
                        $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
                        $user_balance->save();
                      }
                      UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                          'IsCompleted' => 1
                      ]);
                      // $processedData++;
                    }*/
                    if ($User->ParentId != null) {
                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                    }
                  }
                }
              }
            }
          }
        }
        LeadActivity::find($rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
      }

      $Message = 'Data is processed successfully.';
      return response()->json([
        'IsSuccess' => true,
        'Message' => $Message,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    } catch (exception $e) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    }
  }
  /*
    utility / Cron Job
  */


  public function GetCampaignDetailsByCampaignId(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $validator = Validator::make($request->all(), [
            'CampaignId' => 'required',
          ]);
          if ($validator->fails()) {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Please enter campaign id.',
              "TotalCount" => count($validator->errors()),
              "Data" => array('Error' => $validator->errors())
            ], 200);
          }
          if ($request->TimeZoneOffSet && $request->TimeZoneOffSet != '')
            $TimeZoneOffSet = $request->TimeZoneOffSet;
          else
            $TimeZoneOffSet = 0;

          $CampaignId = $request->CampaignId;
          $IsCampaign = Campaign::find($CampaignId);
          if ($IsCampaign) {
            $CampaignDetails = Campaign::with('CampaignType', 'CommissionType', 'User')->where('CampaignId', $CampaignId)->first();
            $CmpDetails = [
              'CampaignName' => $CampaignDetails['CampaignName'],
              'CampaignType' => $CampaignDetails['CampaignType']['Type'],
              'CampaignTypeId' => $CampaignDetails['CampaignType']['CampaignTypeId'],
              'RevenueModel' => $CampaignDetails['CommissionType']['RevenueModelName'],
              'RevenueModelId' => $CampaignDetails['CommissionType']['RevenueModelId'],
              'UserName' => $CampaignDetails['user']['FirstName'] . ' ' . $CampaignDetails['user']['LastName'],
              'UserEmailId' => $CampaignDetails['user']['EmailId'],
              'UserId' => $CampaignDetails['user']['UserId'],
              'CreatedAt' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($CampaignDetails['CreatedAt'])))
            ];
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Campaign details.',
              'TotalCount' => 0,
              'Data' => array('CampaignDetails' => $CmpDetails)
            ], 200);
          } else {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Campaign not found.',
              'TotalCount' => 0,
              'Data' => []
            ], 200);
          }
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
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

  // General Statistics For Admin
  public function GeneralStatisticsAdmin(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $UserList = [];
        $CurrencyTitle = 'USD'; // Default Currency USD
        $ImpressionsTotal = 0;
        $CampaignsClicksTotal = 0;
        $CTRTotal = 0;
        $LeadsTotal = 0;
        $LeadsQualifiedTotal = 0;
        $ConvertedAccountsTotal = 0;
        $QualifiedAccountsTotal = 0;
        $ActiveAccountsTotal = 0;
        $TotalDeposits = 0;
        $TotalVolume = 0;
        $TotalInitialRevenue = 0;
        $TotalRoyalAmount = 0;
        $TotalRoyalSpreadAmount = 0;
        $TotalCPLCPA = 0;
        $TotalUserCommission = 0;
        $AllTotalSpreadAmount = 0;
        $TotalShareAmount = 0;
        $TotalTotal = 0;
        // $TotalUserSubRevenueFrom = 0;
        // $TotalUserSubRevenueTop = 0;
        $TotalMasterAffiliateCommission = 0;
        $TotalTotalCommission = 0;
        $TotalNetRevenue = 0;
        $TotalNetUserBonus = 0;
        $TotalNetUserSubRev = 0;
        $CampaignListArr = [];
        $CampaignListExport = [];
        // Filter data list        
        $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('AdminVerified', 1)->where('RoleId', 3)->orderBy('FirstName')->get();
        foreach ($User as $value) {
          $arr = [
            "UserId" => $value['UserId'],
            "AffiliateName" => $value['FirstName'] . ' ' . $value['LastName'],
            "EmailId" => $value['EmailId'],
          ];
          array_push($UserList, $arr); // GetAffilateList
        }
        $CampaignListAll = Campaign::orderBy('CampaignName')->get();
        $GetAdList = Ad::orderBy('Title')->get();
        $GetAdTypeList = AdTypeMaster::orderBy('Title')->get();
        $GetAdBrandList = AdBrandMaster::orderBy('Title')->get();
        $GetLanguageList = LanguageMaster::orderBy('LanguageName')->get();
        $RevenueModelList = RevenueModel::orderBy('RevenueModelName')->get();
        $RevenueModelTypeList = RevenueType::orderBy('RevenueTypeName')->get();
        // End. Filter data list

        $UserBonus = UserBonus::with('User', 'RevenueModel.Revenue')->where('USDAmount', '>', 0);
        if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
          $requestUserIds = $request->UserId;
          $UserBonus = $UserBonus->whereHas('User', function ($qr) use ($requestUserIds) {
            $qr->whereIn('UserId', $requestUserIds);
          });
        }
        // Date filter apply 
        if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
          $from = $request->DateForm;
          $to = $request->DateTo;
          $UserBonus->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
        }
        $UserBonus = $UserBonus->get();

        $CampaignList = Campaign::with('User')->orderBy('CampaignId', 'desc');
        if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
          $CampaignList = $CampaignList->whereIn('UserId', $request->UserId);
        }
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
        // Campaign base
        foreach ($CampaignList as $Campaign) {
          // return $Campaign;
          $RevenueModelOfCamp = RevenueModel::with('Revenue')->where('RevenueModelId', $Campaign['RevenueModelId'])->first();
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
            $AdBrand = $request->AdBrand;
            $AdIdList = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
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
            $AdLanguage = $request->AdLanguage;
            $AdIdList = Ad::whereIn('LanguageId', $AdLanguage)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIdList = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
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
            $AdLanguage = $request->AdLanguage;
            $AdIdList = Ad::whereIn('LanguageId', $AdLanguage)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIdList = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIdList);
          }
          $AccountIds = $LeadList->pluck('AccountId');
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
            $AdLanguage = $request->AdLanguage;
            $AdIdList = Ad::whereIn('LanguageId', $AdLanguage)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIdList = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
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
            $AdLanguage = $request->AdLanguage;
            $AdIdList = Ad::whereIn('LanguageId', $AdLanguage)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIdList = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIdList);
          }

          // ActiveAccounts
          $ActiveAccounts = Lead::where('CampaignId', $Campaign['CampaignId'])->where('IsActive', 1);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIdList = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $ActiveAccounts = $ActiveAccounts->whereIn('AdId', $AdIdList);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $AdsList = Ad::whereIn('AdId', $request->Ads)->pluck('AdId');
            $ActiveAccounts = $ActiveAccounts->whereIn('AdId', $AdsList);
          }
          if (isset($request->AdLanguage) && $request->AdLanguage != '' && count($request->AdLanguage) != 0) {
            $AdLanguage = $request->AdLanguage;
            $AdIdList = Ad::whereIn('LanguageId', $AdLanguage)->pluck('AdId');
            $ActiveAccounts = $ActiveAccounts->whereIn('AdId', $AdIdList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIdList = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
            $ActiveAccounts = $ActiveAccounts->whereIn('AdId', $AdIdList);
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
            $CPLAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogCPL)->where('UserSubRevenueId', '=', null);
          }
          $RevenueModelShare = RevenueModel::where('RevenueModelId', $Campaign['RevenueModelId'])->whereIn('RevenueTypeId', [5, 6])->first();
          if ($RevenueModelShare) {
            $RevenueModelLogShare = RevenueModelLog::where('RevenueModelId', $RevenueModelShare['RevenueModelId'])->pluck('RevenueModelLogId');
            $ShareAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogShare)->where('UserSubRevenueId', '=', null);
          }
          // $LeadActivityList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('LeadActivityId', '!=', null)->pluck('LeadActivityId');
          // Deposits count
          $Deposits = LeadActivity::whereIn('AccountId', $AccountIds);
          // Volume
          $Volume = LeadActivity::whereIn('AccountId', $AccountIds);
          // InitialRevenue count
          $RoyalAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '>', 0);
          $RoyalSpreadAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDSpreadAmount', '>', 0);
          //  Commission count
          $UserAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserBonusId', null)->where('UserSubRevenueId', null)->where('PaymentStatus', 1);
          $UserSpreadAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserBonusId', null)->where('UserSubRevenueId', null)->where('PaymentStatus', 1);
          // MasterAffiliateCommission count
          $UserSubRevenueIdList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('PaymentStatus', 1)->pluck('UserSubRevenueId');
          $UserSubRevenueAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('PaymentStatus', 1);  // Master Revenue 
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $requestUserIds = $request->UserId;
            $UserSubRevenueAmount->whereIn('UserId', $requestUserIds);
          }
          $MasterAmountMain = UserSubRevenue::whereIn('UserSubRevenueId', $UserSubRevenueIdList);
          // sub revenue cut from affiliate(from sub)
          $UserSubRevenueFrom = UserSubRevenue::whereIn('UserSubRevenueId', $UserSubRevenueIdList)->where('USDAmount', '<', 0);
          // sub revenue cut from royal(on top of)
          $UserSubRevenueTop = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '<', 0);
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
            $ConvertedAccounts->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to); // ConvertedAccounts
            $ActiveAccounts->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to); // ActiveAccounts
            if ($RevenueModel) {
              $QualifiedAccounts->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to); // QualifiedAccounts
            }
            if ($RevenueModelCPL) {
              $CPLAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            }
            if ($RevenueModelShare) {
              $ShareAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            }
            // $Deposits->whereHas('Leads', function ($qr) use ($from, $to) {
            //   $qr->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to);
            // });
            $Deposits->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            // $Volume->whereHas('Leads', function ($qr) use ($from, $to) {
            //   $qr->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to);
            // });
            $Volume->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);

            $RoyalAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $MasterAmountMain->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
              $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            });
            $UserSubRevenueFrom->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
              $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            });
            $UserSubRevenueTop->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalSubAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $BonusMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserSubRevenueAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);  // Master Revenue
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
          $ActiveAccounts = $ActiveAccounts->count(); // ActiveAccounts
          if ($RevenueModel) {
            $QualifiedAccounts = $QualifiedAccounts->count(); // QualifiedAccounts
          } else {
            $QualifiedAccounts = 0; // QualifiedAccounts
          }
          // cpl/ccpl/cpa/ccpa amount
          if ($RevenueModelCPL) {
            if ($request->CurrencyId == 1) {
              $CPLMainAmount = $CPLAmount->sum('USDAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
            } else if ($request->CurrencyId == 2) {
              $CPLMainAmount = $CPLAmount->sum('AUDAmount');
              $CPLSpreadAmount = $CPLAmount->sum('SpreadAUDAmount');
            } else if ($request->CurrencyId == 3) {
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
            if ($request->CurrencyId == 1) {
              $ShareMainAmount = $ShareAmount->sum('USDAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
            } else if ($request->CurrencyId == 2) {
              $ShareMainAmount = $ShareAmount->sum('AUDAmount');
              $ShareSpreadAmount = $ShareAmount->sum('SpreadAUDAmount');
            } else if ($request->CurrencyId == 3) {
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

          $Deposits = $Deposits->sum('DepositsUSD'); // Deposits
          $Volume = $Volume->sum('VolumeTraded'); // Volume
          if ($request->CurrencyId == 1) {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $UserSubRevenueValue = $UserSubRevenueAmount->sum('USDAmount');
            $CurrencyTitle = 'USD';
          } else if ($request->CurrencyId == 2) {
            $RoyalAmount = $RoyalAmount->sum('AUDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('AUDSpreadAmount');
            $UserSubRevenueValue = $UserSubRevenueAmount->sum('AUDAmount');
            $CurrencyTitle = 'AUD';
          } else if ($request->CurrencyId == 3) {
            $RoyalAmount = $RoyalAmount->sum('EURAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('EURSpreadAmount');
            $UserSubRevenueValue = $UserSubRevenueAmount->sum('EURAmount');
            $CurrencyTitle = 'EUR';
          } else {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $UserSubRevenueValue = $UserSubRevenueAmount->sum('USDAmount');
            $CurrencyTitle = 'USD';
          }
          $InitialRevenue = $RoyalAmount + $RoyalSpreadAmount; // InitialRevenue

          if ($request->CurrencyId == 1) {
            $UserAmount = $UserAmount->sum('USDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $UserAmount = $UserAmount->sum('AUDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $UserAmount = $UserAmount->sum('EURAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadEURAmount');
          } else {
            $UserAmount = $UserAmount->sum('USDAmount');
            $UserSpreadAmount = $UserSpreadAmount->sum('SpreadUSDAmount');
          }
          $Commission = $UserAmount + $UserSpreadAmount; // Commission count
          if ($RevenueModelShare) {
            $TotalUserAmount = $UserAmount;
            $TotalSpreadAmount = $UserSpreadAmount;
          } else {
            $TotalUserAmount = 0;
            $TotalSpreadAmount = 0;
          }

          if ($request->CurrencyId == 1) {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $MasterAmount = $MasterAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $MasterAmount = $MasterAmountMain->sum('EURAmount');
          } else {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          }
          $MasterAffiliateCommission = $MasterAmount; // Master Affiliate Commission count

          if ($request->CurrencyId == 1) {
            $UserSubRevenueFromAmount = $UserSubRevenueFrom->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $UserSubRevenueFromAmount = $UserSubRevenueFrom->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $UserSubRevenueFromAmount = $UserSubRevenueFrom->sum('EURAmount');
          } else {
            $UserSubRevenueFromAmount = $UserSubRevenueFrom->sum('USDAmount');
          }
          if ($request->CurrencyId == 1) {
            $UserSubRevenueTopAmount = $UserSubRevenueTop->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $UserSubRevenueTopAmount = $UserSubRevenueTop->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $UserSubRevenueTopAmount = $UserSubRevenueTop->sum('EURAmount');
          } else {
            $UserSubRevenueTopAmount = $UserSubRevenueTop->sum('USDAmount');
          }

          if ($request->CurrencyId == 1) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('EURAmount');
          } else {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          }
          $RoyalSubAmountDebit = $RoyalSubAmount; // Master Affiliate Commission count

          if ($request->CurrencyId == 1) {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $BonusAmount = $BonusMain->sum('AUDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $BonusAmount = $BonusMain->sum('EURAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadEURAmount');
          } else {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          }
          $Bonus = $BonusAmount + $BonusSpreadAmount; // Bonus count
          $TotalCommission = $Commission + $UserSubRevenueValue; // Total Commission
          $RoyalRevenue = $InitialRevenue - ($UserAmountMain + $UserSubRevenueValue); // Royal Revenue
          $affName = $Campaign['User']['FirstName'] . ' ' . $Campaign['User']['LastName'];
          $RevenueModelName = $RevenueModelOfCamp['RevenueModelName'];
          $RevenueTypeName = $RevenueModelOfCamp['Revenue']['RevenueTypeName'];
          $CampaignName = $Campaign['CampaignName'];

          $CampaignArr = [
            "CampaignId" => $Campaign['CampaignId'],
            "Campaign" => $CampaignName,
            "UserId" => $Campaign['User']['UserId'],
            "Affiliate" => $affName,
            "RevenueModelId" => $RevenueModelOfCamp['RevenueModelId'],
            "RevenueModel" => $RevenueModelName,
            "RevenueType" => $RevenueTypeName,
            "Impressions" => $Impressions,
            "CampaignsClicks" => $CampaignsClicks,
            "CTR" => round($CTR, 2),
            "Leads" => $Leads,
            "LeadsQualified" => $LeadsQualified,
            "ConvertedAccounts" => $ConvertedAccounts,
            "QualifiedAccounts" => $QualifiedAccounts,
            "ActiveAccounts" => $ActiveAccounts,
            "Deposits" => round($Deposits, 4),  // Deposits
            "Volume" => round($Volume, 4),
            "RoyalAmount" => round($RoyalAmount, 4),
            "RoyalSpreadAmount" => round($RoyalSpreadAmount, 4),
            "InitialRevenue" => round($InitialRevenue, 4),
            "CPL/CPA" => round($CPLTotalAmount, 4), // Affiliate Revenue
            "Commission" => round($TotalUserAmount, 4),  // Affiliate Revenue
            "SpreadAmount" => round($TotalSpreadAmount, 4),  // Affiliate Revenue
            "ShareAmount" => round($ShareTotalAmount, 4),  // Affiliate Revenue
            "Total" => round($UserAmountMain + $UserSubRevenueValue, 4),  // Affiliate Revenue
            "MasterAffiliateCommission" => round($UserSubRevenueValue, 4), // Master Revenue
            "UserSubRevenueFrom" => 0,
            "UserSubRevenueTop" => 0,
            "TotalCommission" => round($TotalCommission, 4),
            "NetRevenue" => round($RoyalRevenue, 4),  // Net Revenue
            "Bonus" => 0,
          ];
          array_push($CampaignListArr, $CampaignArr);
          // Export array 
          $CampaignArrExport = [
            "Affiliate" => $Campaign['User']['FirstName'] . ' ' . $Campaign['User']['LastName'],
            "Campaign" => $Campaign['CampaignName'],
            "Ads Served" => $Impressions,
            "Ads Clicked" => $CampaignsClicks,
            "CTR" => round($CTR, 2),
            "Leads" => $Leads,
            "Qualified Leads" => $LeadsQualified,
            "Accounts" => $ConvertedAccounts,
            "Qualified Accounts" => $QualifiedAccounts,
            "Active Accounts" => $ActiveAccounts,
            "Total Volume" => round($Volume, 4),
            "Total Deposits" => round($Deposits, 4),
            "Royal Spread " . $CurrencyTitle => round($RoyalSpreadAmount, 4),
            "Royal Commission " . $CurrencyTitle => round($RoyalAmount, 4),
            "Initial Revenue " . $CurrencyTitle => round($InitialRevenue, 4),
            "Revenue Model" => $RevenueModelOfCamp['RevenueModelName'],
            "Revenue Type" => $RevenueModelOfCamp['Revenue']['RevenueTypeName'],
            "CPL/CPA " . $CurrencyTitle => round($CPLTotalAmount, 4),
            "Affiliate Spread " . $CurrencyTitle => round($TotalSpreadAmount, 4),
            "Affiliate Revenue " . $CurrencyTitle => round($ShareTotalAmount, 4),
            "Master Revenue " . $CurrencyTitle => round($UserSubRevenueValue, 4),
            "Affiliate Total Revenue " . $CurrencyTitle => round($UserAmountMain + $UserSubRevenueValue, 4),
            "Net Revenue " . $CurrencyTitle => round($RoyalRevenue, 4),
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
          $ActiveAccountsTotal = $ActiveAccountsTotal + $ActiveAccounts;
          $TotalDeposits = $TotalDeposits + $Deposits;
          $TotalVolume = $TotalVolume + $Volume;
          $TotalRoyalAmount = $TotalRoyalAmount + $RoyalAmount;
          $TotalRoyalSpreadAmount = $TotalRoyalSpreadAmount + $RoyalSpreadAmount;
          $TotalInitialRevenue = $TotalInitialRevenue + $InitialRevenue;
          $TotalCPLCPA = $TotalCPLCPA + $CPLTotalAmount;
          $TotalUserCommission = $TotalUserCommission + $TotalUserAmount;
          $AllTotalSpreadAmount = $AllTotalSpreadAmount + $TotalSpreadAmount;
          $TotalShareAmount = $TotalShareAmount + $ShareTotalAmount;
          $TotalMasterAffiliateCommission = $TotalMasterAffiliateCommission + $UserSubRevenueValue;
          $TotalTotal = $TotalTotal + $UserAmountMain + $UserSubRevenueValue;
          $TotalTotalCommission = $TotalTotalCommission + $UserAmountMain;
          $TotalNetRevenue = $TotalNetRevenue + $RoyalRevenue;
        }

        // user bonus
        foreach ($UserBonus as $Bonus) {
          if ($request->CurrencyId == 1) {
            $TotalCommission = $Bonus['USDAmount'];
            $CurrencyTitle = 'USD';
          } else if ($request->CurrencyId == 2) {
            $TotalCommission = $Bonus['AUDAmount'];
            $CurrencyTitle = 'AUD';
          } else if ($request->CurrencyId == 3) {
            $TotalCommission = $Bonus['EURAmount'];
            $CurrencyTitle = 'EUR';
          } else {
            $TotalCommission = $Bonus['USDAmount'];
            $CurrencyTitle = 'USD';
          }
          $affName = $Bonus['User']['FirstName'] . ' ' . $Bonus['User']['LastName'];
          $RevenueModelName = $Bonus['RevenueModel']['RevenueModelName'];
          if ($Bonus['RevenueModel']['Revenue']['RevenueTypeName'])
            $RevenueTypeName = $Bonus['RevenueModel']['Revenue']['RevenueTypeName'];
          else
            $RevenueTypeName = 'Manual Bonus';

          $BonusArr = [
            "CampaignId" => '',
            "Campaign" => '',
            "UserId" => $Bonus['User']['UserId'],
            "Affiliate" => $affName,
            "RevenueModelId" => $Bonus['RevenueModel']['RevenueModelId'],
            "RevenueModel" => $RevenueModelName,
            "RevenueType" => $RevenueTypeName,
            "Impressions" => 0,
            "CampaignsClicks" => 0,
            "CTR" => 0,
            "Leads" => 0,
            "LeadsQualified" => 0,
            "ConvertedAccounts" => 0,
            "QualifiedAccounts" => 0,
            "ActiveAccounts" => 0,
            "Deposits" => 0,  // Deposits
            "Volume" => 0,
            "InitialRevenue" => 0,
            "CPL/CPA" => 0,  // Affiliate Revenue
            "Commission" => 0,  // Affiliate Revenue
            "SpreadAmount" => 0,  // Affiliate Revenue
            "ShareAmount" => 0,  // Affiliate Revenue
            "Total" => round($TotalCommission, 4),  // Affiliate Revenue
            "MasterAffiliateCommission" => 0, // Master Revenue
            "TotalCommission" => round($TotalCommission, 4),
            "NetRevenue" => round(-$TotalCommission, 4),
          ];
          array_push($CampaignListArr, $BonusArr);
          // Export array 
          $BonusArrExport = [
            "Affiliate" => $Bonus['User']['FirstName'] . ' ' . $Bonus['User']['LastName'],
            "Campaign" => '',
            "Ads Served" => 0,
            "Ads Clicked" => 0,
            "CTR" => 0,
            "Leads" => 0,
            "Qualified Leads" => 0,
            "Accounts" => 0,
            "Qualified Accounts" => 0,
            "Active Accounts" => 0,
            "Total Volume" => 0,
            "Total Deposits" => 0,
            "Royal Spread " . $CurrencyTitle => 0,
            "Royal Commission " . $CurrencyTitle => 0,
            "Initial Revenue " . $CurrencyTitle => 0,
            "Revenue Model" => $RevenueModelName,
            "Revenue Type" => $RevenueTypeName,
            "CPL/CPA " . $CurrencyTitle => 0,
            "Affiliate Spread " . $CurrencyTitle => 0,
            "Affiliate Revenue " . $CurrencyTitle => 0,
            "Master Revenue " . $CurrencyTitle => 0,
            "Affiliate Total Revenue " . $CurrencyTitle => round($TotalCommission, 4),
            "Net Revenue " . $CurrencyTitle => round(-$TotalCommission, 4),
          ];
          array_push($CampaignListExport, $BonusArrExport);

          $TotalTotal = $TotalTotal + $TotalCommission;
          $TotalNetUserBonus = $TotalNetUserBonus + $TotalCommission;
          $TotalTotalCommission = $TotalTotalCommission + $TotalCommission;
          $TotalNetRevenue = $TotalNetRevenue - $TotalCommission;
        }

        $TotalArray = array(
          'Impressions' => $ImpressionsTotal,
          'CampaignsClicks' => $CampaignsClicksTotal,
          'CTR' => round($CTRTotal, 4),
          'Leads' => $LeadsTotal,
          'LeadsQualified' => $LeadsQualifiedTotal,
          'ConvertedAccounts' => $ConvertedAccountsTotal,
          'QualifiedAccounts' => $QualifiedAccountsTotal,
          'ActiveAccounts' => $ActiveAccountsTotal = $ActiveAccountsTotal,
          'Deposits' => round($TotalDeposits, 4),
          'Volume' => round($TotalVolume, 4),
          'InitialRevenue' => round($TotalInitialRevenue, 4),
          'CPL/CPA' => round($TotalCPLCPA, 4),
          'UserCommission' => round($TotalUserCommission, 4),
          'SpreadAmount' => round($AllTotalSpreadAmount, 4),
          'ShareAmount' => round($TotalShareAmount, 4),
          'MasterAffiliateCommission' => round($TotalMasterAffiliateCommission, 4),
          'UserBonus' => round($TotalNetUserBonus, 4),
          'Commission' => round($TotalTotalCommission, 4),
          'NetRevenue' => round($TotalNetRevenue, 4),
        );
        // total row for export
        $CampaignArrExportTotal = array(
          "Affiliate" => '',
          "Campaign" => '',
          "Ads Served" => $ImpressionsTotal,
          "Ads Clicked" => $CampaignsClicksTotal,
          "CTR" => round($CTRTotal, 4),
          "Leads" => $LeadsTotal,
          "Qualified Leads" => $LeadsQualifiedTotal,
          "Accounts" => $ConvertedAccountsTotal,
          "Qualified Accounts" => $QualifiedAccountsTotal,
          "Active Accounts" => $ActiveAccountsTotal,
          "Total Volume" => $TotalVolume,
          "Total Deposits" => $TotalDeposits,
          "Royal Spread " . $CurrencyTitle => $TotalRoyalSpreadAmount,
          "Royal Commission " . $CurrencyTitle => $TotalRoyalAmount,
          "Initial Revenue " . $CurrencyTitle => $TotalInitialRevenue,
          "Revenue Model" => '',
          "Revenue Type" => '',
          "CPL/CPA " . $CurrencyTitle => $TotalCPLCPA,
          "Affiliate Spread " . $CurrencyTitle => $AllTotalSpreadAmount,
          "Affiliate Revenue " . $CurrencyTitle => $TotalUserCommission,
          "Master Revenue " . $CurrencyTitle => $TotalMasterAffiliateCommission,
          "Affiliate Total Revenue " . $CurrencyTitle => $TotalTotal,
          "Net Revenue " . $CurrencyTitle => $TotalNetRevenue,
        );
        array_push($CampaignListExport, $CampaignArrExportTotal);
        // Export report in xls file
        if ($request->IsExport) {
          Excel::create('GeneralStatistics', function ($excel) use ($CampaignListExport) {
            $excel->sheet('GeneralStatistics', function ($sheet) use ($CampaignListExport) {
              $sheet->fromArray($CampaignListExport);
            });
          })->store('xls', false, true);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export campaign list successfully.',
            "TotalCount" => 1,
            'Data' => ['GeneralStatistics' => $this->storage_path . 'exports/GeneralStatistics.xls'],
          ], 200);
        }

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($CampaignListArr),
          'Data' => array(
            'AffilateList' => $UserList,
            'CampaignList' => $CampaignListAll,
            'AdList' => $GetAdList,
            'AdTypeList' => $GetAdTypeList,
            'AdBrandList' => $GetAdBrandList,
            'LanguageList' => $GetLanguageList,
            'RevenueModelList' => $RevenueModelList,
            'RevenueModelTypeList' => $RevenueModelTypeList,
            'GeneralStatistics' => $CampaignListArr,
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

  // Affiliate Statistics for Admin
  public function AffiliateStatisticsAdmin(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $UserListFilter = [];
        $CurrencyTitle = 'USD'; // Default Currency USD
        $LeadsTotal = 0;
        $LeadsQualifiedTotal = 0;
        $ConvertedAccountsTotal = 0;
        $QualifiedAccountsTotal = 0;
        $ActiveAccountsTotal = 0;
        $DepositsTotal = 0;
        $VolumeTotal = 0;
        $RoyalAmountTotal = 0;
        $RoyalSpreadAmountTotal = 0;
        $InitialRevenueTotal = 0;
        $CPLTotalAmountTotal = 0;
        $TotalUserAmountTotal = 0;
        $TotalSpreadAmountTotal = 0;
        $ShareTotalAmountTotal = 0;
        $MasterAffiliateCommissionTotal = 0;
        $BonusTotal = 0;
        $TotalTotal = 0;
        $TotalCommissionTotal = 0;
        $RoyalRevenueTotal = 0;
        $UserListArr = [];
        $UserListExport = [];

        // Filter data list
        $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('RoleId', 3)->orderBy('FirstName')->get();
        foreach ($User as $value) {
          $arr = [
            "UserId" => $value['UserId'],
            "AffiliateName" => $value['FirstName'] . ' ' . $value['LastName'],
            "EmailId" => $value['EmailId'],
          ];
          array_push($UserListFilter, $arr); // GetAffilateList
        }
        $GetAdList = Ad::orderBy('Title')->get(); // GetAdList
        $GetAdTypeList = AdTypeMaster::orderBy('Title')->get(); // GetAdTypeList
        $GetAdBrandList = AdBrandMaster::orderBy('Title')->get(); // GetAdBrandList
        $AdSizeList = AdSizeMaster::orderBy('Height')->get(); // GetAdBrandList
        // End. Filter data list
        $UserList = User::where('RoleId', 3)->orderBy('UserId', 'desc');
        if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
          $UserList = $UserList->whereIn('UserId', $request->UserId);
        }
        $UserList = $UserList->get();

        foreach ($UserList as $User) {
          $Leads = Lead::where('UserId', $User['UserId']);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIds = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIds = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdSize) && $request->AdSize != '' && count($request->AdSize) != 0) {
            $AdSize = $request->AdSize;
            $AdIds = Ad::whereIn('AdSizeId', $AdSize)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdIds);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $Ads = $request->Ads;
            $AdIds = Ad::whereIn('AdId', $Ads)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdIds);
          }

          $LeadList = Lead::where('UserId', $User['UserId']);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIds = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIds = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdSize) && $request->AdSize != '' && count($request->AdSize) != 0) {
            $AdSize = $request->AdSize;
            $AdIds = Ad::whereIn('AdSizeId', $AdSize)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIds);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $Ads = $request->Ads;
            $AdIds = Ad::whereIn('AdId', $Ads)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdIds);
          }
          $AccountIds = $LeadList->pluck('AccountId');
          $LeadList = $LeadList->pluck('LeadId');

          // LeadsQualified
          $LeadsQualified = Lead::where('UserId', $User['UserId'])->whereHas('LeadStatus', function ($qr) {
            $qr->where('IsValid', 1);
          });
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIds = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIds = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdSize) && $request->AdSize != '' && count($request->AdSize) != 0) {
            $AdSize = $request->AdSize;
            $AdIds = Ad::whereIn('AdSizeId', $AdSize)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdIds);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $Ads = $request->Ads;
            $AdIds = Ad::whereIn('AdId', $Ads)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdIds);
          }

          // ConvertedAccounts
          $ConvertedAccounts = Lead::where('UserId', $User['UserId'])->where('IsConverted', 1);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIds = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIds = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdSize) && $request->AdSize != '' && count($request->AdSize) != 0) {
            $AdSize = $request->AdSize;
            $AdIds = Ad::whereIn('AdSizeId', $AdSize)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIds);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $Ads = $request->Ads;
            $AdIds = Ad::whereIn('AdId', $Ads)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdIds);
          }

          // ActiveAccounts
          $ActiveAccounts = Lead::where('UserId', $User['UserId'])->where('IsActive', 1);
          if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
            $AdType = $request->AdType;
            $AdIds = Ad::whereIn('AdTypeId', $AdType)->pluck('AdId');
            $ActiveAccounts = $ActiveAccounts->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrand = $request->AdBrand;
            $AdIds = Ad::whereIn('AdBrandId', $AdBrand)->pluck('AdId');
            $ActiveAccounts = $ActiveAccounts->whereIn('AdId', $AdIds);
          }
          if (isset($request->AdSize) && $request->AdSize != '' && count($request->AdSize) != 0) {
            $AdSize = $request->AdSize;
            $AdIds = Ad::whereIn('AdSizeId', $AdSize)->pluck('AdId');
            $ActiveAccounts = $ActiveAccounts->whereIn('AdId', $AdIds);
          }
          if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
            $Ads = $request->Ads;
            $AdIds = Ad::whereIn('AdId', $Ads)->pluck('AdId');
            $ActiveAccounts = $ActiveAccounts->whereIn('AdId', $AdIds);
          }

          $RevenueModel = RevenueModel::where('RevenueTypeId', 4)->pluck('RevenueModelId');
          $RevenueModelLog = RevenueModelLog::whereIn('RevenueModelId', $RevenueModel)->pluck('RevenueModelLogId');
          $QualifiedAccounts = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLog)->where('UserId', $User['UserId']);

          $RevenueModelCPL = RevenueModel::whereIn('RevenueTypeId', [1, 2, 3, 4])->pluck('RevenueModelId');
          $RevenueModelLogCPL = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelCPL)->pluck('RevenueModelLogId');
          $CPLAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogCPL);
          $RevenueModelShare = RevenueModel::whereIn('RevenueTypeId', [5, 6])->pluck('RevenueModelId');
          $RevenueModelLogShare = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelShare)->pluck('RevenueModelLogId');
          $ShareAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogShare);
          $LeadActivityList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('LeadActivityId', '!=', null)->pluck('LeadActivityId');
          // Deposits count
          $Deposits = LeadActivity::whereIn('AccountId', $AccountIds);
          // Volume
          $Volume = LeadActivity::whereIn('AccountId', $AccountIds);
          // InitialRevenue count
          $RoyalAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '>', 0);
          $RoyalSpreadAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDSpreadAmount', '>', 0);
          //  Commission count
          $UserAmountMain = UserRevenuePayment::where('UserId', $User['UserId'])->where('UserBonusId', null)->where('UserSubRevenueId', null)->where('PaymentStatus', 1);
          // MasterAffiliateCommission count 
          $MasterAmountMain = UserSubRevenue::where('UserId', $User['UserId']);
          // $RoyalSubAmountMain = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '<', 0);  // cut sub revenue from royal
          // Bonus count
          $BonusMain = UserRevenuePayment::where('UserId', $User['UserId'])->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);

          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to = $request->DateTo;
            $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // 4.Leads
            $LeadsQualified->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // LeadsQualified
            $ConvertedAccounts->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to); // ConvertedAccounts
            $ActiveAccounts->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to); // ActiveAccounts
            $QualifiedAccounts->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to); // QualifiedAccounts
            $CPLAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $ShareAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $Deposits->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $Volume->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserAmountMain->whereDate('UserRevenuePayment', '>=', $from)->whereDate('UserRevenuePayment', '<=', $to);
            // $UserSpreadAmount->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
            $MasterAmountMain->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
              $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            });
            // $RoyalSubAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $BonusMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          }
          $Leads = $Leads->count(); // Leads
          $LeadsQualified = $LeadsQualified->count(); // LeadsQualified
          $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts
          $ActiveAccounts = $ActiveAccounts->count(); // ActiveAccounts
          $QualifiedAccounts = $QualifiedAccounts->count(); // QualifiedAccounts 

          // cpl/ccpl/cpa/ccpa amount          
          if ($request->CurrencyId == 1) {
            $CPLMainAmount = $CPLAmount->sum('USDAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $CPLMainAmount = $CPLAmount->sum('AUDAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $CPLMainAmount = $CPLAmount->sum('EURAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadEURAmount');
          } else {
            $CPLMainAmount = $CPLAmount->sum('USDAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
          }
          $CPLTotalAmount = $CPLMainAmount + $CPLSpreadAmount;

          // Revenue Share + FX Share amount
          if ($request->CurrencyId == 1) {
            $ShareMainAmount = $ShareAmount->sum('USDAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $ShareMainAmount = $ShareAmount->sum('AUDAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $ShareMainAmount = $ShareAmount->sum('EURAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadEURAmount');
          } else {
            $ShareMainAmount = $ShareAmount->sum('USDAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          }
          $ShareTotalAmount = $ShareMainAmount + $ShareSpreadAmount;

          $Deposits = $Deposits->sum('DepositsUSD'); // Deposits
          $Volume = $Volume->sum('VolumeTraded'); // Volume
          if ($request->CurrencyId == 1) {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $CurrencyTitle = 'USD';
          } else if ($request->CurrencyId == 2) {
            $RoyalAmount = $RoyalAmount->sum('AUDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('AUDSpreadAmount');
            $CurrencyTitle = 'AUD';
          } else if ($request->CurrencyId == 3) {
            $RoyalAmount = $RoyalAmount->sum('EURAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('EURSpreadAmount');
            $CurrencyTitle = 'EUR';
          } else {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $CurrencyTitle = 'USD';
          }
          $InitialRevenue = $RoyalAmount + $RoyalSpreadAmount; // InitialRevenue

          if ($request->CurrencyId == 1) {
            $UserAmount = $ShareAmount->sum('USDAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $UserAmount = $ShareAmount->sum('AUDAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $UserAmount = $ShareAmount->sum('EURAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadEURAmount');
          } else {
            $UserAmount = $ShareAmount->sum('USDAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          }
          $Commission = $UserAmount + $UserSpreadAmount; // Commission count

          if ($RevenueModelShare) {
            $TotalUserAmount = $UserAmount;
            $TotalSpreadAmount = $UserSpreadAmount;
          } else {
            $TotalUserAmount = 0;
            $TotalSpreadAmount = 0;
          }

          if ($request->CurrencyId == 1) {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $MasterAmount = $MasterAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $MasterAmount = $MasterAmountMain->sum('EURAmount');
          } else {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          }
          $MasterAffiliateCommission = $MasterAmount; // Master Affiliate Commission count

          /* if ($request->CurrencyId == 1) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('EURAmount');
          } else {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          }
          $RoyalSubAmountDebit = $RoyalSubAmount; // Master Affiliate Commission count */

          if ($request->CurrencyId == 1) {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $BonusAmount = $BonusMain->sum('AUDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $BonusAmount = $BonusMain->sum('EURAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadEURAmount');
          } else {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          }
          $Bonus = $BonusAmount + $BonusSpreadAmount; // Bonus count

          $TotalCommission = $CPLTotalAmount + $Commission + $MasterAffiliateCommission + $Bonus; // Total Commission
          $RoyalRevenue = $InitialRevenue - $TotalCommission; // Royal Revenue

          $affName = $User['FirstName'] . ' ' . $User['LastName'];
          $UserArr = [
            "UserId" => $User['UserId'],
            "Affiliate" => $affName,
            "Leads" => $Leads,
            "LeadsQualified" => $LeadsQualified,
            "ConvertedAccounts" => $ConvertedAccounts,
            "QualifiedAccounts" => $QualifiedAccounts,
            "ActiveAccounts" => $ActiveAccounts,
            "Deposits" => round($Deposits, 4),
            "Volume" => round($Volume, 4),
            "RoyalAmount" => round($RoyalAmount, 4),
            "RoyalSpreadAmount" => round($RoyalSpreadAmount, 4),
            "InitialRevenue" => round($InitialRevenue, 4),
            "CPL/CPA" => round($CPLTotalAmount, 4),  // Affiliate Revenue
            "Commission" => round($TotalUserAmount, 4),  // Affiliate Revenue
            "SpreadAmount" => round($TotalSpreadAmount, 4),  // Affiliate Revenue
            "ShareAmount" => round($ShareTotalAmount, 4),  // Affiliate Revenue
            "MasterAffiliateCommission" => round($MasterAffiliateCommission, 4),
            "TotalCommission" => round($TotalCommission, 4),
            "Bonus" => round($Bonus, 4),
            "Total" => round($TotalCommission, 4),  // Affiliate Revenue
            "NetRevenue" => round($RoyalRevenue, 4),  // Net Revenue
          ];
          array_push($UserListArr, $UserArr);
          // Export array 
          $UserArrExport = [
            "Affiliate" => $User['FirstName'] . ' ' . $User['LastName'],
            "Leads" => $Leads,
            "Qualified Leads" => $LeadsQualified,
            "Accounts" => $ConvertedAccounts,
            "Qualified Accounts" => $QualifiedAccounts,
            "Active Accounts" => $ActiveAccounts,
            "Total Volume" => round($Volume, 4),
            "Total Deposits" => round($Deposits, 4),
            "Royal Spread " . $CurrencyTitle => round($RoyalSpreadAmount, 4),
            "Royal Revenue " . $CurrencyTitle => round($RoyalAmount, 4),
            "Initial Revenue " . $CurrencyTitle => round($InitialRevenue, 4),
            "CPL/CPA " . $CurrencyTitle => round($CPLTotalAmount, 4),
            "Affiliate Spread " . $CurrencyTitle => round($TotalSpreadAmount, 4),
            "Affiliate Revenue " . $CurrencyTitle => round($ShareTotalAmount, 4),
            "Master Revenue " . $CurrencyTitle => round($MasterAffiliateCommission, 4),
            "Bonus " . $CurrencyTitle => round($Bonus, 4),
            "Affiliate Total Revenue " . $CurrencyTitle => round($TotalCommission, 4),
            "Net Revenue " . $CurrencyTitle => round($RoyalRevenue, 4),
          ];
          array_push($UserListExport, $UserArrExport);

          $LeadsTotal = $LeadsTotal + $Leads;
          $LeadsQualifiedTotal = $LeadsQualifiedTotal + $LeadsQualified;
          $ConvertedAccountsTotal = $ConvertedAccountsTotal + $ConvertedAccounts;
          $QualifiedAccountsTotal = $QualifiedAccountsTotal + $QualifiedAccounts;
          $ActiveAccountsTotal = $ActiveAccountsTotal + $ActiveAccounts;
          $DepositsTotal = $DepositsTotal + $Deposits;
          $VolumeTotal = $VolumeTotal + $Volume;
          $RoyalAmountTotal = $RoyalAmountTotal + $RoyalAmount;
          $RoyalSpreadAmountTotal = $RoyalSpreadAmountTotal + $RoyalSpreadAmount;
          $InitialRevenueTotal = $InitialRevenueTotal + $InitialRevenue;
          $CPLTotalAmountTotal = $CPLTotalAmountTotal + $CPLTotalAmount;
          $TotalUserAmountTotal = $TotalUserAmountTotal + $TotalUserAmount;
          $TotalSpreadAmountTotal = $TotalSpreadAmountTotal + $TotalSpreadAmount;
          $ShareTotalAmountTotal = $ShareTotalAmountTotal + $ShareTotalAmount;
          $MasterAffiliateCommissionTotal = $MasterAffiliateCommissionTotal + $MasterAffiliateCommission;
          $BonusTotal = $BonusTotal + $Bonus;
          $TotalTotal = $TotalTotal + $Commission + $Bonus;
          $TotalCommissionTotal = $TotalCommissionTotal + $TotalCommission;
          $RoyalRevenueTotal = $RoyalRevenueTotal + $RoyalRevenue;
        }
        // Export total row 
        $UserArrExportTotal = [
          "Affiliate" => '',
          "Leads" => $LeadsTotal,
          "Qualified Leads" => $LeadsQualifiedTotal,
          "Accounts" => $ConvertedAccountsTotal,
          "Qualified Accounts" => $QualifiedAccountsTotal,
          "Active Accounts" => $ActiveAccountsTotal,
          "Total Volume" => round($VolumeTotal, 4),
          "Total Deposits" => round($DepositsTotal, 4),
          "Royal Spread " . $CurrencyTitle => round($RoyalSpreadAmountTotal, 4),
          "Royal Revenue " . $CurrencyTitle => round($RoyalAmountTotal, 4),
          "Initial Revenue " . $CurrencyTitle => round($InitialRevenueTotal, 4),
          "CPL/CPA " . $CurrencyTitle => round($CPLTotalAmountTotal, 4),
          "Affiliate Spread " . $CurrencyTitle => round($TotalSpreadAmountTotal, 4),
          "Affiliate Revenue " . $CurrencyTitle => round($TotalUserAmountTotal, 4),
          "Master Revenue " . $CurrencyTitle => round($MasterAffiliateCommissionTotal, 4),
          "Bonus " . $CurrencyTitle => $BonusTotal,
          "Affiliate Total Revenue " . $CurrencyTitle => round($TotalCommissionTotal, 4),
          "Net Revenue " . $CurrencyTitle => round($RoyalRevenueTotal, 4),
        ];
        array_push($UserListExport, $UserArrExportTotal);

        $TotalArray = array(
          "Leads" => $LeadsTotal,
          "LeadsQualified" => $LeadsQualifiedTotal,
          "ConvertedAccounts" => $ConvertedAccountsTotal,
          "QualifiedAccounts" => $QualifiedAccountsTotal,
          "ActiveAccounts" => $ActiveAccountsTotal,
          "Deposits" => round($DepositsTotal, 4),
          "Volume" => round($VolumeTotal, 4),
          "RoyalAmount" => round($RoyalAmountTotal, 4),
          "RoyalSpreadAmount" => round($RoyalSpreadAmountTotal, 4),
          "InitialRevenue" => round($InitialRevenueTotal, 4),
          "CPL/CPA" => round($CPLTotalAmountTotal, 4),
          "Commission" => round($TotalUserAmountTotal, 4),
          "SpreadAmount" => round($TotalSpreadAmountTotal, 4),
          "ShareAmount" => round($ShareTotalAmountTotal, 4),
          "MasterAffiliateCommission" => round($MasterAffiliateCommissionTotal, 4),
          "Bonus" => round($BonusTotal, 4),
          "TotalRevenue" => round($TotalTotal, 4),
          "TotalCommission" => round($TotalCommissionTotal, 4),
          "NetRevenue" => round($RoyalRevenueTotal, 4),
        );
        // Export report in xls file
        if ($request->IsExport) {
          Excel::create('AffiliateStatisticsAdmin', function ($excel) use ($UserListExport) {
            $excel->sheet('AffiliateStatisticsAdmin', function ($sheet) use ($UserListExport) {
              $sheet->fromArray($UserListExport);
            });
          })->store('xls', false, true);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export user list successfully.',
            "TotalCount" => 1,
            'Data' => ['AffiliateStatisticsAdmin' => $this->storage_path . 'exports/AffiliateStatisticsAdmin.xls'],
          ], 200);
        }

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($UserListArr),
          'Data' => array(
            'AffilateList' => $UserListFilter,
            'AdList' => $GetAdList,
            'AdTypeList' => $GetAdTypeList,
            'AdBrandList' => $GetAdBrandList,
            'AdSizeList' => $AdSizeList,
            'AffiliateStatisticsAdmin' => $UserListArr,
            'AffiliateStatisticsAdminTotal' => $TotalArray,
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

  // Ad Statistics for Admin
  public function AdStatisticsAdmin(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $UserListFilter = [];
        $AdListArr = [];
        $AdListExport = [];
        $CurrencyTitle = 'USD'; // Default Currency USD
        $AdImpressionTotal = 0;
        $AdClickTotal = 0;
        $LeadsTotal = 0;
        $LeadsQualifiedTotal = 0;
        $ConvertedAccountsTotal = 0;
        $ActiveAccountsTotal = 0;
        $QualifiedAccountsTotal = 0;
        $DepositsTotal = 0;
        $VolumeTotal = 0;
        $CTRTotal = 0;
        $RoyalAmountTotal = 0;
        $RoyalSpreadAmountTotal = 0;
        $InitialRevenueTotal = 0;
        $CPLTotalAmountTotal = 0;
        $TotalUserAmountTotal = 0;
        $TotalSpreadAmountTotal = 0;
        $ShareTotalAmountTotal = 0;
        $MasterAffiliateCommissionTotal = 0;
        $TotalCommissionTotal = 0;
        $CommissionTotal = 0;
        $RoyalRevenueTotal = 0;

        // Filter data list        
        $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('RoleId', 3)->orderBy('FirstName')->get();
        foreach ($User as $value) {
          $arr = [
            "UserId" => $value['UserId'],
            "AffiliateName" => $value['FirstName'] . ' ' . $value['LastName'],
            "EmailId" => $value['EmailId'],
          ];
          array_push($UserListFilter, $arr); // GetAffilateList
        }
        $GetAdList = Ad::orderBy('Title')->get(); // GetAdList
        $GetAdTypeList = AdTypeMaster::orderBy('Title')->get(); // GetAdTypeList
        $GetAdBrandList = AdBrandMaster::orderBy('Title')->get(); // GetAdBrandList
        $AdSizeList = AdSizeMaster::orderBy('Height')->get(); // GetAdBrandList
        // End. Filter data list

        $AdList = Ad::orderBy('AdId', 'desc');
        if (isset($request->Ads) && $request->Ads != '' && count($request->Ads) != 0) {
          $Ads = $request->Ads;
          $AdList = $AdList->whereIn('AdId', $Ads);
        }
        if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
          $UsersList = $request->UserId;
          $AdListN = CampaignAdList::whereIn('UserId', $UsersList)->groupBy('AdId')->pluck('AdId');
          $AdList = $AdList->whereIn('AdId', $AdListN);
        }
        if (isset($request->AdType) && $request->AdType != '' && count($request->AdType) != 0) {
          $AdType = $request->AdType;
          $AdList = $AdList->whereIn('AdTypeId', $AdType);
        }
        if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
          $AdBrand = $request->AdBrand;
          $AdList = $AdList->whereIn('AdBrandId', $AdBrand);
        }
        if (isset($request->AdSize) && $request->AdSize != '' && count($request->AdSize) != 0) {
          $AdSize = $request->AdSize;
          $AdList = $AdList->whereIn('AdSizeId', $AdSize);
        }
        $AdList = $AdList->get();

        foreach ($AdList as $AdData) {
          $AdId = $AdData['AdId'];
          $CampaignAddIds = CampaignAdList::where('AdId', $AdId);
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $CampaignAddIds = $CampaignAddIds->whereIn('UserId', $UsersList);
          }
          $CampaignAddIds = $CampaignAddIds->pluck('CampaignAddId');
          $CampaignAdClick = CampaignAdClick::whereIn('CampaignAddId', $CampaignAddIds); // CampaignsClicks
          $CampaignAdImpression = CampaignAdImpression::whereIn('CampaignAddId', $CampaignAddIds); // Impressions
          /*$CampaignAdImpression = CampaignAdImpression::whereHas('CampaignAdList', function($qr) use($AdId){
            $qr->where('AdId', $AdId);
          });
          $CampaignAdClick = CampaignAdClick::whereHas('CampaignAdList', function($qr) use($AdId){
            $qr->where('AdId', $AdId);
          });*/
          $Leads = Lead::where('AdId', $AdData['AdId']);
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $Leads = $Leads->whereIn('UserId', $UsersList);
          }

          $LeadList = Lead::where('AdId', $AdData['AdId']);
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $LeadList = $LeadList->whereIn('UserId', $UsersList);
          }
          $AccountIds = $LeadList->pluck('AccountId');
          $LeadList = $LeadList->pluck('LeadId');

          $LeadsQualified = Lead::where('AdId', $AdData['AdId'])->whereHas('LeadStatus', function ($qr) {
            $qr->where('IsValid', 1);
          }); // LeadsQualified
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $LeadsQualified = $LeadsQualified->whereIn('UserId', $UsersList);
          }

          $ConvertedAccounts = Lead::where('AdId', $AdData['AdId'])->where('IsConverted', 1); // ConvertedAccounts 
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $ConvertedAccounts = $ConvertedAccounts->whereIn('UserId', $UsersList);
          }

          $ActiveAccounts = Lead::where('AdId', $AdData['AdId'])->where('IsActive', 1); // ActiveAccounts
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $ActiveAccounts = $ActiveAccounts->whereIn('UserId', $UsersList);
          }

          $RevenueModel = RevenueModel::where('RevenueTypeId', 4)->pluck('RevenueModelId');
          $RevenueModelLog = RevenueModelLog::whereIn('RevenueModelId', $RevenueModel)->pluck('RevenueModelLogId');
          $QualifiedAccounts = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLog); // QualifiedAccounts
          $RevenueModelCPL = RevenueModel::whereIn('RevenueTypeId', [1, 2, 3, 4])->pluck('RevenueModelId');
          $RevenueModelLogCPL = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelCPL)->pluck('RevenueModelLogId');
          $CPLAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogCPL);
          $RevenueModelShare = RevenueModel::whereIn('RevenueTypeId', [5, 6])->pluck('RevenueModelId');
          $RevenueModelLogShare = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelShare)->pluck('RevenueModelLogId');
          $ShareAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogShare);
          // $LeadActivityList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('LeadActivityId', '!=', null)->pluck('LeadActivityId');
          $Deposits = LeadActivity::whereIn('AccountId', $AccountIds); // Deposits count 
          $Volume = LeadActivity::whereIn('AccountId', $AccountIds); // Volume 
          $RoyalAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '>', 0); // InitialRevenue count
          $RoyalSpreadAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDSpreadAmount', '>', 0);
          $UserAmountMain = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserBonusId', null)->where('UserSubRevenueId', null)->where('PaymentStatus', 1); //  Commission count          
          $UserSubRevenueIdList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('PaymentStatus', 1)->pluck('UserSubRevenueId');
          $MasterAmountMain = UserSubRevenue::whereIn('UserSubRevenueId', $UserSubRevenueIdList); // MasterAffiliateCommission count          
          $UserSubRevenueDeduct = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('PaymentStatus', 1);
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $UserSubRevenueDeduct = $UserSubRevenueDeduct->whereIn('UserId', $UsersList);
          }
          $RoyalSubAmountMain = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '<', 0);  // cut sub revenue from royal 

          // Date filter apply 
          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to = $request->DateTo;
            $CampaignAdImpression->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
            $CampaignAdClick->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
            $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // Leads
            $LeadsQualified->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // LeadsQualified
            $ConvertedAccounts->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to); // ConvertedAccounts
            $ActiveAccounts->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to); // ActiveAccounts
            $QualifiedAccounts->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to); // QualifiedAccounts
            $CPLAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $ShareAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $Deposits->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $Volume->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $MasterAmountMain->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
              $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            });
            $RoyalSubAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserSubRevenueDeduct->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          }
          $CampaignAdImpression = $CampaignAdImpression->count(); // Leads
          $CampaignAdClick = $CampaignAdClick->count(); // Leads
          // CTR
          if ($CampaignAdImpression == 0)
            $CTR = 0;
          else
            $CTR = $CampaignAdClick / $CampaignAdImpression;
          $Leads = $Leads->count(); // Leads
          $LeadsQualified = $LeadsQualified->count(); // LeadsQualified
          $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts
          $ActiveAccounts = $ActiveAccounts->count(); // ActiveAccounts
          $QualifiedAccounts = $QualifiedAccounts->count(); // QualifiedAccounts 

          // cpl/ccpl/cpa/ccpa amount          
          if ($request->CurrencyId == 1) {
            $CPLMainAmount = $CPLAmount->sum('USDAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
            $DeducAmount = $UserSubRevenueDeduct->sum('USDAmount');
          } else if ($request->CurrencyId == 2) {
            $CPLMainAmount = $CPLAmount->sum('AUDAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadAUDAmount');
            $DeducAmount = $UserSubRevenueDeduct->sum('AUDAmount');
          } else if ($request->CurrencyId == 3) {
            $CPLMainAmount = $CPLAmount->sum('EURAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadEURAmount');
            $DeducAmount = $UserSubRevenueDeduct->sum('EURAmount');
          } else {
            $CPLMainAmount = $CPLAmount->sum('USDAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
            $DeducAmount = $UserSubRevenueDeduct->sum('USDAmount');
          }
          $CPLTotalAmount = $CPLMainAmount + $CPLSpreadAmount;

          // Revenue Share + FX Share amount
          if ($request->CurrencyId == 1) {
            $ShareMainAmount = $ShareAmount->sum('USDAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $ShareMainAmount = $ShareAmount->sum('AUDAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $ShareMainAmount = $ShareAmount->sum('EURAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadEURAmount');
          } else {
            $ShareMainAmount = $ShareAmount->sum('USDAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          }
          $ShareTotalAmount = $ShareMainAmount + $ShareSpreadAmount;

          $Deposits = $Deposits->sum('DepositsUSD'); // Deposits
          $Volume = $Volume->sum('VolumeTraded'); // Volume
          if ($request->CurrencyId == 1) {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $CurrencyTitle = 'USD';
          } else if ($request->CurrencyId == 2) {
            $RoyalAmount = $RoyalAmount->sum('AUDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('AUDSpreadAmount');
            $CurrencyTitle = 'AUD';
          } else if ($request->CurrencyId == 3) {
            $RoyalAmount = $RoyalAmount->sum('EURAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('EURSpreadAmount');
            $CurrencyTitle = 'EUR';
          } else {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $CurrencyTitle = 'USD';
          }
          $InitialRevenue = $RoyalAmount + $RoyalSpreadAmount; // InitialRevenue

          if ($request->CurrencyId == 1) {
            $UserAmount = $ShareAmount->sum('USDAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $UserAmount = $ShareAmount->sum('AUDAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $UserAmount = $ShareAmount->sum('EURAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadEURAmount');
          } else {
            $UserAmount = $ShareAmount->sum('USDAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          }
          $Commission = $UserAmount + $UserSpreadAmount; // Commission count

          if ($RevenueModelShare) {
            $TotalUserAmount = $UserAmount;
            $TotalSpreadAmount = $UserSpreadAmount;
          } else {
            $TotalUserAmount = 0;
            $TotalSpreadAmount = 0;
          }

          if ($request->CurrencyId == 1) {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $MasterAmount = $MasterAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $MasterAmount = $MasterAmountMain->sum('EURAmount');
          } else {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          }
          $MasterAffiliateCommission = $MasterAmount; // Master Affiliate Commission count

          if ($request->CurrencyId == 1) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('EURAmount');
          } else {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          }
          $RoyalSubAmountDebit = $RoyalSubAmount; // Master Affiliate Commission count
          $TotalCommission = $Commission + $CPLTotalAmount  + $MasterAffiliateCommission; // Total Commission
          $RoyalRevenue = $InitialRevenue - $TotalCommission; // Royal Revenue

          $AdTitle = $AdData['Title'];

          $AdArr = [
            "AdId" => $AdData['AdId'],
            "AdTitle" => $AdTitle,
            "AdImpression" => $CampaignAdImpression,
            "AdClick" => $CampaignAdClick,
            "CTR" => round($CTR, 2),
            "Leads" => $Leads,
            "LeadsQualified" => $LeadsQualified,
            "ConvertedAccounts" => $ConvertedAccounts,
            "QualifiedAccounts" => $QualifiedAccounts,
            "ActiveAccounts" => $ActiveAccounts,
            "Deposits" => round($Deposits, 4),  // Deposits
            "Volume" => round($Volume, 4),
            "RoyalAmount" => round($RoyalAmount, 4),
            "RoyalSpreadAmount" => round($RoyalSpreadAmount, 4),
            "InitialRevenue" => round($InitialRevenue, 4),
            "CPL/CPA" => round($CPLTotalAmount, 4),  // Affiliate Revenue
            "Commission" => round($TotalUserAmount, 4),  // Affiliate Revenue
            "SpreadAmount" => round($TotalSpreadAmount, 4),  // Affiliate Revenue
            "ShareAmount" => round($Commission, 4),  // Affiliate Revenue
            "MasterAffiliateCommission" => round($MasterAffiliateCommission, 4), // Master Revenue
            "TotalCommission" => round($TotalCommission, 4),
            "Total" => round($TotalCommission, 4),  // Affiliate Revenue
            "NetRevenue" => round($RoyalRevenue, 4),  // Net Revenue
          ];
          array_push($AdListArr, $AdArr);
          // Export array 
          $AdArrExport = [
            "Ad" => $AdData['Title'],
            "Ad Displays" => $CampaignAdImpression,
            "Ad Clicks" => $CampaignAdClick,
            "CTR" => $CTR,
            "Leads" => $Leads,
            "Qualified Leads" => $LeadsQualified,
            "Accounts" => $ConvertedAccounts,
            "Qualified Accounts" => $QualifiedAccounts,
            "Active Accounts" => $ActiveAccounts,
            "Total Volume" => $Volume,
            "Total Deposits" => $Deposits,
            "Royal Spread " . $CurrencyTitle => $RoyalSpreadAmount,
            "Royal Revenue " . $CurrencyTitle => $RoyalAmount,
            "Initial Revenue " . $CurrencyTitle => $InitialRevenue,
            "CPL/CPA " . $CurrencyTitle => round($CPLTotalAmount, 4),
            "Affiliate Spread " . $CurrencyTitle => round($TotalSpreadAmount, 4),
            "Affiliate Revenue " . $CurrencyTitle => round($TotalUserAmount, 4),
            "Master Revenue " . $CurrencyTitle => round($MasterAffiliateCommission, 4),
            "Affiliate Total Revenue " . $CurrencyTitle => round($TotalCommission, 4),
            "Net Revenue " . $CurrencyTitle => round($RoyalRevenue, 4),
          ];
          array_push($AdListExport, $AdArrExport);

          $AdImpressionTotal = $AdImpressionTotal + $CampaignAdImpression;
          $AdClickTotal = $AdClickTotal + $CampaignAdClick;
          if ($AdImpressionTotal == 0)
            $CTRTotal = 0;
          else
            $CTRTotal = $AdClickTotal / $AdImpressionTotal;
          $LeadsTotal = $LeadsTotal + $Leads;
          $LeadsQualifiedTotal = $LeadsQualifiedTotal + $LeadsQualified;
          $ConvertedAccountsTotal = $ConvertedAccountsTotal + $ConvertedAccounts;
          $QualifiedAccountsTotal = $QualifiedAccountsTotal + $QualifiedAccounts;
          $ActiveAccountsTotal = $ActiveAccountsTotal + $ActiveAccounts;
          $DepositsTotal = $DepositsTotal + $Deposits;
          $VolumeTotal = $VolumeTotal + $Volume;
          $RoyalAmountTotal = $RoyalAmountTotal + $RoyalAmount;
          $RoyalSpreadAmountTotal = $RoyalSpreadAmountTotal + $RoyalSpreadAmount;
          $InitialRevenueTotal = $InitialRevenueTotal + $InitialRevenue;
          $CPLTotalAmountTotal = $CPLTotalAmountTotal + $CPLTotalAmount;
          $TotalUserAmountTotal = $TotalUserAmountTotal + $TotalUserAmount;
          $TotalSpreadAmountTotal = $TotalSpreadAmountTotal + $TotalSpreadAmount;
          $ShareTotalAmountTotal = $ShareTotalAmountTotal + $ShareTotalAmount;
          $MasterAffiliateCommissionTotal = $MasterAffiliateCommissionTotal + $MasterAffiliateCommission;
          $TotalCommissionTotal = $TotalCommissionTotal + $TotalCommission;
          $CommissionTotal = $CommissionTotal + $TotalCommission;
          $RoyalRevenueTotal = $RoyalRevenueTotal + $RoyalRevenue;
        }

        // Export array
        /*  Ad Statistics */
        /*  Ad, Ad Displays, Ad Clicks, CTR, Leads, Qualified Leads, Accounts, Qualified Accounts, Active Accounts, Total Volume, Total Deposits, Royal Spread, Royal Revenue, Initial Revenue, CPL/CPA, Affiliate Spread, Affiliate Revenue, Master Revenue, Total Affiliate Revenue, Net Revenue  */
        $AdArrExportTotal = [
          "Ad" => '',
          "Ad Displays" => $AdImpressionTotal,
          "Ad Clicks" => $AdClickTotal,
          "CTR" => $CTRTotal,
          "Leads" => $LeadsTotal,
          "Qualified Leads" => $LeadsQualifiedTotal,
          "Accounts" => $ConvertedAccountsTotal,
          "Qualified Accounts" => $QualifiedAccountsTotal,
          "Active Accounts" => $ActiveAccountsTotal,
          "Total Volume" => $VolumeTotal,
          "Total Deposits" => $DepositsTotal,
          "Royal Spread " . $CurrencyTitle => round($RoyalSpreadAmountTotal, 4),
          "Royal Revenue " . $CurrencyTitle => round($RoyalAmountTotal, 4),
          "Initial Revenue " . $CurrencyTitle => round($InitialRevenueTotal, 4),
          "CPL/CPA " . $CurrencyTitle => round($CPLTotalAmountTotal, 4),
          "Affiliate Spread " . $CurrencyTitle => round($TotalSpreadAmountTotal, 4),
          "Affiliate Revenue " . $CurrencyTitle => round($TotalUserAmountTotal, 4),
          "Master Revenue " . $CurrencyTitle => round($MasterAffiliateCommissionTotal, 4),
          "Affiliate Total Revenue " . $CurrencyTitle => round($CommissionTotal, 4),
          "Net Revenue " . $CurrencyTitle => round($RoyalRevenueTotal, 4),
        ];
        array_push($AdListExport, $AdArrExportTotal);
        $AdTotal = array(
          "AdImpression" => $AdImpressionTotal,
          "AdClick" => $AdClickTotal,
          "CTR" => $CTRTotal,
          "Leads" => $LeadsTotal,
          "LeadsQualified" => $LeadsQualifiedTotal,
          "ConvertedAccounts" => $ConvertedAccountsTotal,
          "QualifiedAccounts" => $QualifiedAccountsTotal,
          "ActiveAccounts" => $ActiveAccountsTotal,
          "Deposits" => $DepositsTotal,
          "Volume" => $VolumeTotal,
          "RoyalAmount" => round($RoyalAmountTotal, 4),
          "RoyalSpreadAmount" => round($RoyalSpreadAmountTotal, 4),
          "InitialRevenue" => round($InitialRevenueTotal, 4),
          "CPL/CPA" => round($CPLTotalAmountTotal, 4),
          "Commission" => round($TotalUserAmountTotal, 4),
          "SpreadAmount" => round($TotalSpreadAmountTotal, 4),
          "ShareAmount" => round($ShareTotalAmountTotal, 4),
          "MasterAffiliateCommission" => round($MasterAffiliateCommissionTotal, 4),
          "TotalCommission" => round($TotalCommissionTotal, 4),
          "Total" => round($CommissionTotal, 4),
          "NetRevenue" => round($RoyalRevenueTotal, 4),
        );
        // Export report in xls file
        if ($request->IsExport) {
          Excel::create('AdStatisticsAdmin', function ($excel) use ($AdListExport) {
            $excel->sheet('AdStatisticsAdmin', function ($sheet) use ($AdListExport) {
              $sheet->fromArray($AdListExport);
            });
          })->store('xls', false, true);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export ad list successfully.',
            "TotalCount" => 1,
            'Data' => ['AdStatisticsAdmin' => $this->storage_path . 'exports/AdStatisticsAdmin.xls'],
          ], 200);
        }

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($AdListArr),
          'Data' => array(
            'AffilateList' => $UserListFilter,
            'AdList' => $GetAdList,
            'AdTypeList' => $GetAdTypeList,
            'AdBrandList' => $GetAdBrandList,
            'AdSizeList' => $AdSizeList,
            'AdStatisticsAdmin' => $AdListArr,
            'AdStatisticsAdminTotal' => $AdTotal,
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

  // Ad Statistics for Admin
  public function RevenueModelStatisticsAdmin(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $UserListFilter = [];
        $CurrencyTitle = 'USD';  // Default Currency USD
        $RevenueModelListArr = [];
        $RevenueModelListExport = [];
        $LeadsTotal = 0;
        $LeadsQualifiedTotal = 0;
        $ConvertedAccountsTotal = 0;
        $QualifiedAccountsTotal = 0;
        $ActiveAccountsTotal = 0;
        $DepositsTotal = 0;
        $VolumeTotal = 0;
        $RoyalAmountTotal = 0;
        $RoyalSpreadAmountTotal = 0;
        $InitialRevenueTotal = 0;
        $CPLTotalAmountTotal = 0;
        $TotalUserAmountTotal = 0;
        $TotalSpreadAmountTotal = 0;
        $ShareTotalAmountTotal = 0;
        $TotalCommissionTotal = 0;
        $MasterAffiliateCommissionTotal = 0;
        $TotalTotal = 0;
        $RoyalRevenueTotal = 0;
        $BonusTotal = 0;

        // Filter data list        
        $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('RoleId', 3)->orderBy('FirstName')->get();
        foreach ($User as $value) {
          $arr = [
            "UserId" => $value['UserId'],
            "AffiliateName" => $value['FirstName'] . ' ' . $value['LastName'],
            "EmailId" => $value['EmailId'],
          ];
          array_push($UserListFilter, $arr); // GetAffilateList
        }
        $RevenueModels = RevenueModel::orderBy('RevenueModelName')->get();
        $RevenueModelTypes = RevenueType::orderBy('RevenueTypeName')->get();
        $GetAdBrandList = AdBrandMaster::orderBy('Title')->get(); // GetAdBrandList
        $AdSizeList = AdSizeMaster::orderBy('Height')->get(); // GetAdBrandList
        // End. Filter data list        
        $RevenueModelList = RevenueModel::orderBy('RevenueModelId', 'desc');
        if (isset($request->RevenueModelId) && $request->RevenueModelId != '' && count($request->RevenueModelId) != 0) {
          $RevenueModelList = $RevenueModelList->whereIn('RevenueModelId', $request->RevenueModelId);
        }
        if (isset($request->RevenueModelType) && $request->RevenueModelType != '' && count($request->RevenueModelType) != 0) {
          $RevenueModelList = $RevenueModelList->whereIn('RevenueTypeId', $request->RevenueModelType);
        }
        if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
          $UsersList = $request->UserId;
          $RevenueModelListN = UserRevenueType::whereIn('UserId', $UsersList)->groupBy('RevenueModelId')->pluck('RevenueModelId');
          $RevenueModelList = $RevenueModelList->whereIn('RevenueModelId', $RevenueModelListN);
        }
        if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
          $AdBrandList = $request->AdBrand;
          $AdList = Ad::whereIn('AdBrandId', $AdBrandList)->pluck('AdId');
          $CampaignIds = CampaignAdList::whereIn('AdId', $AdList)->groupBy('CampaignId')->pluck('CampaignId');
          $RevenueModelIds = Campaign::whereIn('CampaignId', $CampaignIds)->groupBy('RevenueModelId')->pluck('RevenueModelId');
          $RevenueModelList = $RevenueModelList->whereIn('RevenueModelId', $RevenueModelIds);
        }

        $RevenueModelList = $RevenueModelList->get();
        foreach ($RevenueModelList as $RMData) {
          $CampaignIdList = Campaign::where('RevenueModelId', $RMData['RevenueModelId'])->pluck('CampaignId');
          $Leads = Lead::whereIn('CampaignId', $CampaignIdList);
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $Leads = $Leads->whereIn('UserId', $UsersList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrandList = $request->AdBrand;
            $AdList = Ad::whereIn('AdBrandId', $AdBrandList)->pluck('AdId');
            $Leads = $Leads->whereIn('AdId', $AdList);
          }

          $LeadList = Lead::whereIn('CampaignId', $CampaignIdList);
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $LeadList = $LeadList->whereIn('UserId', $UsersList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrandList = $request->AdBrand;
            $AdList = Ad::whereIn('AdBrandId', $AdBrandList)->pluck('AdId');
            $LeadList = $LeadList->whereIn('AdId', $AdList);
          }
          $AccountIds = $LeadList->pluck('AccountId');
          $LeadList = $LeadList->pluck('LeadId');

          $LeadsQualified = Lead::whereIn('CampaignId', $CampaignIdList)->whereHas('LeadStatus', function ($qr) {
            $qr->where('IsValid', 1);
          }); // LeadsQualified
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $LeadsQualified = $LeadsQualified->whereIn('UserId', $UsersList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrandList = $request->AdBrand;
            $AdList = Ad::whereIn('AdBrandId', $AdBrandList)->pluck('AdId');
            $LeadsQualified = $LeadsQualified->whereIn('AdId', $AdList);
          }

          $ConvertedAccounts = Lead::whereIn('CampaignId', $CampaignIdList)->where('IsConverted', 1); // ConvertedAccounts
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $ConvertedAccounts = $ConvertedAccounts->whereIn('UserId', $UsersList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrandList = $request->AdBrand;
            $AdList = Ad::whereIn('AdBrandId', $AdBrandList)->pluck('AdId');
            $ConvertedAccounts = $ConvertedAccounts->whereIn('AdId', $AdList);
          }

          $ActiveAccounts = Lead::whereIn('CampaignId', $CampaignIdList)->where('IsActive', 1); // ActiveAccounts
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $ActiveAccounts = $ActiveAccounts->whereIn('UserId', $UsersList);
          }
          if (isset($request->AdBrand) && $request->AdBrand != '' && count($request->AdBrand) != 0) {
            $AdBrandList = $request->AdBrand;
            $AdList = Ad::whereIn('AdBrandId', $AdBrandList)->pluck('AdId');
            $ActiveAccounts = $ActiveAccounts->whereIn('AdId', $AdList);
          }

          $RevenueModel = RevenueModel::where('RevenueTypeId', 4)->pluck('RevenueModelId');
          $RevenueModelLog = RevenueModelLog::whereIn('RevenueModelId', $RevenueModel)->pluck('RevenueModelLogId');
          $QualifiedAccounts = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLog); // QualifiedAccounts
          $RevenueModelCPL = RevenueModel::whereIn('RevenueTypeId', [1, 2, 3, 4])->pluck('RevenueModelId');
          $RevenueModelLogCPL = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelCPL)->pluck('RevenueModelLogId');
          $CPLAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogCPL);
          $RevenueModelShare = RevenueModel::whereIn('RevenueTypeId', [5, 6])->pluck('RevenueModelId');
          $RevenueModelLogShare = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelShare)->pluck('RevenueModelLogId');
          $ShareAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogShare);
          // $LeadActivityList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('LeadActivityId', '!=', null)->pluck('LeadActivityId');
          $Deposits = LeadActivity::whereIn('AccountId', $AccountIds); // Deposits count          
          $Volume = LeadActivity::whereIn('AccountId', $AccountIds); // Volume
          $RoyalAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '>', 0); // InitialRevenue count
          $RoyalSpreadAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDSpreadAmount', '>', 0);
          $UserAmountMain = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserBonusId', null)->where('UserSubRevenueId', null)->where('PaymentStatus', 1); //  Commission count

          $RevenueModelLogSub = RevenueModelLog::where('RevenueModelId', $RMData['RevenueModelId'])->pluck('RevenueModelLogId');
          $MasterAmountMain = UserRevenuePayment::whereIn('RevenueModelLogId', $RevenueModelLogSub)->where('UserSubRevenueId', '!=', null)->where('PaymentStatus', 1); // MasterAffiliateCommission count 
          if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
            $UsersList = $request->UserId;
            $MasterAmountMain = $MasterAmountMain->whereIn('UserId', $UsersList);
          }
          $RoyalSubAmountMain = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '<', 0);  // cut sub revenue from royal          
          $BonusMain = UserRevenuePayment::whereIn('RevenueModelLogId', $RevenueModelLog)->where('UserBonusId', '!=', null)->where('PaymentStatus', 1); // Bonus count

          // Date filter apply 
          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to = $request->DateTo;
            $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // 4.Leads
            $LeadsQualified->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // LeadsQualified
            $ConvertedAccounts->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to); // ConvertedAccounts
            $ActiveAccounts->whereDate('DateConverted', '>=', $from)->whereDate('DateConverted', '<=', $to); // ActiveAccounts
            $QualifiedAccounts->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to); // QualifiedAccounts
            $CPLAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $ShareAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $Deposits->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $Volume->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $MasterAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalSubAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $BonusMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          }
          $Leads = $Leads->count(); // Leads
          $LeadsQualified = $LeadsQualified->count(); // LeadsQualified
          $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts
          $ActiveAccounts = $ActiveAccounts->count(); // ActiveAccounts
          $QualifiedAccounts = $QualifiedAccounts->count(); // QualifiedAccounts 

          // cpl/ccpl/cpa/ccpa amount          
          if ($request->CurrencyId == 1) {
            $CPLMainAmount = $CPLAmount->sum('USDAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $CPLMainAmount = $CPLAmount->sum('AUDAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $CPLMainAmount = $CPLAmount->sum('EURAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadEURAmount');
          } else {
            $CPLMainAmount = $CPLAmount->sum('USDAmount');
            $CPLSpreadAmount = $CPLAmount->sum('SpreadUSDAmount');
          }
          $CPLTotalAmount = $CPLMainAmount + $CPLSpreadAmount;

          // Revenue Share + FX Share amount
          if ($request->CurrencyId == 1) {
            $ShareMainAmount = $ShareAmount->sum('USDAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $ShareMainAmount = $ShareAmount->sum('AUDAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $ShareMainAmount = $ShareAmount->sum('EURAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadEURAmount');
          } else {
            $ShareMainAmount = $ShareAmount->sum('USDAmount');
            $ShareSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          }
          $ShareTotalAmount = $ShareMainAmount + $ShareSpreadAmount;

          $Deposits = $Deposits->sum('DepositsUSD'); // Deposits
          $Volume = $Volume->sum('VolumeTraded'); // Volume
          if ($request->CurrencyId == 1) {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $CurrencyTitle = 'USD';
          } else if ($request->CurrencyId == 2) {
            $RoyalAmount = $RoyalAmount->sum('AUDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('AUDSpreadAmount');
            $CurrencyTitle = 'AUD';
          } else if ($request->CurrencyId == 3) {
            $RoyalAmount = $RoyalAmount->sum('EURAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('EURSpreadAmount');
            $CurrencyTitle = 'EUR';
          } else {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $CurrencyTitle = 'USD';
          }
          $InitialRevenue = $RoyalAmount + $RoyalSpreadAmount; // InitialRevenue

          if ($request->CurrencyId == 1) {
            $UserAmount = $ShareAmount->sum('USDAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $UserAmount = $ShareAmount->sum('AUDAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $UserAmount = $ShareAmount->sum('EURAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadEURAmount');
          } else {
            $UserAmount = $ShareAmount->sum('USDAmount');
            $UserSpreadAmount = $ShareAmount->sum('SpreadUSDAmount');
          }
          $Commission = $UserAmount + $UserSpreadAmount; // Commission count

          if ($RevenueModelShare) {
            $TotalUserAmount = $UserAmount;
            $TotalSpreadAmount = $UserSpreadAmount;
          } else {
            $TotalUserAmount = 0;
            $TotalSpreadAmount = 0;
          }

          if ($request->CurrencyId == 1) {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $MasterAmount = $MasterAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $MasterAmount = $MasterAmountMain->sum('EURAmount');
          } else {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          }
          $MasterAffiliateCommission = $MasterAmount; // Master Affiliate Commission count

          if ($request->CurrencyId == 1) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('EURAmount');
          } else {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          }
          $RoyalSubAmountDebit = $RoyalSubAmount; // Master Affiliate Commission count

          if ($request->CurrencyId == 1) {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $BonusAmount = $BonusMain->sum('AUDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $BonusAmount = $BonusMain->sum('EURAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadEURAmount');
          } else {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          }
          $Bonus = $BonusAmount + $BonusSpreadAmount; // Bonus count

          $TotalCommission = $Commission + $CPLTotalAmount + $MasterAffiliateCommission; // Total Commission
          $RoyalRevenue = $InitialRevenue - $TotalCommission; // Royal Revenue

          $RMName = $RMData['RevenueModelName'];
          $RmArr = [
            "RevenueModelId" => $RMData['RevenueModelId'],
            "RevenueModelName" => $RMName,
            "Leads" => $Leads,
            "LeadsQualified" => $LeadsQualified,
            "ConvertedAccounts" => $ConvertedAccounts,
            "QualifiedAccounts" => $QualifiedAccounts,
            "ActiveAccounts" => $ActiveAccounts,
            "Deposits" => round($Deposits, 4),  // Deposits
            "Volume" => round($Volume, 4),
            "RoyalAmount" => round($RoyalAmount, 4),
            "RoyalSpreadAmount" => round($RoyalSpreadAmount, 4),
            "InitialRevenue" => round($InitialRevenue, 4),
            "CPL/CPA" => round($CPLTotalAmount, 4),  // Affiliate Revenue
            "Commission" => round($TotalUserAmount, 4),  // Affiliate Revenue
            "SpreadAmount" => round($TotalSpreadAmount, 4),  // Affiliate Revenue
            "ShareAmount" => round($ShareTotalAmount, 4),  // Affiliate Revenue
            "MasterAffiliateCommission" => round($MasterAffiliateCommission, 4), // Master Revenue
            "TotalCommission" => round($TotalCommission, 4),
            "Total" => round($TotalCommission, 4),  // Affiliate Revenue
            "NetRevenue" => round($RoyalRevenue, 4),  // Net Revenue
            "Bonus" => $Bonus,
          ];
          array_push($RevenueModelListArr, $RmArr);
          // Export array 
          $RmArrExport = [
            "Revenue Model" => $RMData['RevenueModelName'],
            "Leads" => $Leads,
            "Qualified Leads" => $LeadsQualified,
            "Accounts" => $ConvertedAccounts,
            "Qualified Accounts" => $QualifiedAccounts,
            "Active Accounts" => $ActiveAccounts,
            "Total Volume" => round($Volume, 4),
            "Total Deposits" => round($Deposits, 4),
            "Royal Spread " . $CurrencyTitle => round($RoyalSpreadAmount, 4),
            "Royal Revenue " . $CurrencyTitle => round($RoyalAmount, 4),
            "Initial Revenue " . $CurrencyTitle => round($InitialRevenue, 4),
            "CPL/CPA " . $CurrencyTitle => round($CPLTotalAmount, 4),
            "Affiliate Spread " . $CurrencyTitle => round($TotalSpreadAmount, 4),
            "Affiliate Revenue " . $CurrencyTitle => round($TotalUserAmount, 4),
            "Master Revenue " . $CurrencyTitle => round($MasterAffiliateCommission, 4),
            "Affiliate Total Revenue " . $CurrencyTitle => round($TotalCommission, 4),
            "Net Revenue " . $CurrencyTitle => round($RoyalRevenue, 4),
          ];
          array_push($RevenueModelListExport, $RmArrExport);
          $LeadsTotal = $LeadsTotal + $Leads;
          $LeadsQualifiedTotal = $LeadsQualifiedTotal + $LeadsQualified;
          $ConvertedAccountsTotal = $ConvertedAccountsTotal + $ConvertedAccounts;
          $QualifiedAccountsTotal = $QualifiedAccountsTotal + $QualifiedAccounts;
          $ActiveAccountsTotal = $ActiveAccountsTotal + $ActiveAccounts;
          $DepositsTotal = $DepositsTotal + $Deposits;
          $VolumeTotal = $VolumeTotal + $Volume;
          $RoyalAmountTotal = $RoyalAmountTotal + $RoyalAmount;
          $RoyalSpreadAmountTotal = $RoyalSpreadAmountTotal + $RoyalSpreadAmount;
          $InitialRevenueTotal = $InitialRevenueTotal + $InitialRevenue;
          $CPLTotalAmountTotal = $CPLTotalAmountTotal + $CPLTotalAmount;
          $TotalUserAmountTotal = $TotalUserAmountTotal + $TotalUserAmount;
          $TotalSpreadAmountTotal = $TotalSpreadAmountTotal + $TotalSpreadAmount;
          $ShareTotalAmountTotal = $ShareTotalAmountTotal + $ShareTotalAmount;
          $MasterAffiliateCommissionTotal = $MasterAffiliateCommissionTotal + abs($MasterAffiliateCommission);
          $TotalCommissionTotal = $TotalCommissionTotal + $TotalCommission;
          $TotalTotal = $TotalTotal + $Commission;
          $RoyalRevenueTotal = $RoyalRevenueTotal + $RoyalRevenue;
          $BonusTotal = $BonusTotal + $Bonus;
        }

        $Ubon = 0;
        $UBonus = UserBonus::where('Type', 0);

        if (count($request->RevenueModelType) == 0 || in_array(7, $request->RevenueModelType)) {
          if (count($request->AdBrand) == 0 && count($request->RevenueModelId) == 0) {
            if (isset($request->UserId) && $request->UserId != '' && count($request->UserId) != 0) {
              $UsersList = $request->UserId;
              $UBonus = UserBonus::whereIn('UserId', $UsersList);
            }
            if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
              $from = $request->DateForm;
              $to = $request->DateTo;
              $UBonus->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // 4.User Bonus
            }
            $UBonus = $UBonus->get();
            foreach ($UBonus as $UBounsData) {
              if ($request->CurrencyId == 1) {
                $Ubon += $UBounsData->USDAmount;
              } else if ($request->CurrencyId == 2) {
                $Ubon += $UBounsData->AUDAmount;
              } else if ($request->CurrencyId == 3) {
                $Ubon += $UBounsData->EURAmount;
              } else {
                $Ubon += $UBounsData->USDAmount;
              }
            }

            $BArr = [
              "RevenueModelId" => 0,
              "RevenueModelName" => 'Manual Bouns',
              "Leads" => 0,
              "LeadsQualified" => 0,
              "ConvertedAccounts" => 0,
              "QualifiedAccounts" => 0,
              "ActiveAccounts" => 0,
              "Deposits" => 0,  // Deposits
              "Volume" => 0,
              "RoyalAmount" => 0,
              "RoyalSpreadAmount" => 0,
              "InitialRevenue" => 0,
              "CPL/CPA" => 0,  // Affiliate Revenue
              "Commission" => 0,  // Affiliate Revenue
              "SpreadAmount" => 0,  // Affiliate Revenue
              "ShareAmount" => 0,  // Affiliate Revenue
              "MasterAffiliateCommission" => 0, // Master Revenue
              "TotalCommission" => 0,
              "Total" => $Ubon,  // Affiliate Revenue
              "NetRevenue" => -$Ubon,  // Net Revenue
              "Bonus" => 0,
            ];
            array_push($RevenueModelListArr, $BArr);

            // Export array 
            $RmArrExport = [
              "Revenue Model" => 'Manual Bouns',
              "Leads" => 0,
              "Qualified Leads" => 0,
              "Accounts" => 0,
              "Qualified Accounts" => 0,
              "Active Accounts" => 0,
              "Total Volume" => 0,
              "Total Deposits" => 0,
              "Royal Spread " . $CurrencyTitle => 0,
              "Royal Revenue " . $CurrencyTitle => 0,
              "Initial Revenue " . $CurrencyTitle => 0,
              "CPL/CPA " . $CurrencyTitle => 0,
              "Affiliate Spread " . $CurrencyTitle => 0,
              "Affiliate Revenue " . $CurrencyTitle => 0,
              "Master Revenue " . $CurrencyTitle => 0,
              "Affiliate Total Revenue " . $CurrencyTitle => $Ubon,
              "Net Revenue " . $CurrencyTitle => -$Ubon,
            ];
            array_push($RevenueModelListExport, $RmArrExport);
            $TotalCommissionTotal = $TotalCommissionTotal + $Ubon;
            $RoyalRevenueTotal = $RoyalRevenueTotal - $Ubon;
          }
        }

        // Export array
        $RmArrExportTotal = [
          "Revenue Model" => '',
          "Leads" => $LeadsTotal,
          "Qualified Leads" => $LeadsQualifiedTotal,
          "Accounts" => $ConvertedAccountsTotal,
          "Qualified Accounts" => $QualifiedAccountsTotal,
          "Active Accounts" => $ActiveAccountsTotal,
          "Total Volume" => $VolumeTotal,
          "Total Deposits" => $DepositsTotal,
          "Royal Spread " . $CurrencyTitle => $RoyalSpreadAmountTotal,
          "Royal Revenue " . $CurrencyTitle => $RoyalAmountTotal,
          "Initial Revenue " . $CurrencyTitle => $InitialRevenueTotal,
          "CPL/CPA " . $CurrencyTitle => round($CPLTotalAmountTotal, 4),
          "Affiliate Spread " . $CurrencyTitle => round($TotalSpreadAmountTotal, 4),
          "Affiliate Revenue " . $CurrencyTitle => round($TotalUserAmountTotal, 4),
          "Master Revenue " . $CurrencyTitle => round($MasterAffiliateCommissionTotal, 4),
          "Affiliate Total Revenue " . $CurrencyTitle => round($TotalCommissionTotal, 4),
          "Net Revenue " . $CurrencyTitle => round($RoyalRevenueTotal, 4),
        ];
        array_push($RevenueModelListExport, $RmArrExportTotal);
        $RevenueModelTotal = [
          "Leads" => $LeadsTotal,
          "LeadsQualified" => $LeadsQualifiedTotal,
          "ConvertedAccounts" => $ConvertedAccountsTotal,
          "QualifiedAccounts" => $QualifiedAccountsTotal,
          "ActiveAccounts" => $ActiveAccountsTotal,
          "Deposits" => $DepositsTotal,
          "Volume" => $VolumeTotal,
          "RoyalAmount" . $CurrencyTitle => $RoyalAmountTotal,
          "RoyalSpreadAmount" . $CurrencyTitle => $RoyalSpreadAmountTotal,
          "InitialRevenue" . $CurrencyTitle => $InitialRevenueTotal,
          "CPL/CPA" => round($CPLTotalAmountTotal, 4),
          "Commission" => round($TotalUserAmountTotal, 4),
          "SpreadAmount" => round($TotalSpreadAmountTotal, 4),
          "ShareAmount" => round($ShareTotalAmountTotal, 4),
          "MasterAffiliateCommission" => round($MasterAffiliateCommissionTotal, 4),
          "TotalCommission" => round($TotalCommissionTotal, 4),
          "Total" => round($TotalTotal, 4),
          "NetRevenue" => round($RoyalRevenueTotal, 4),
          "Bonus" => round($BonusTotal, 4),
        ];
        // Export report in xls file
        if ($request->IsExport) {
          Excel::create('RevenueModelStatisticsAdmin', function ($excel) use ($RevenueModelListExport) {
            $excel->sheet('RevenueModelStatisticsAdmin', function ($sheet) use ($RevenueModelListExport) {
              $sheet->fromArray($RevenueModelListExport);
            });
          })->store('xls', false, true);
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export ad list successfully.',
            "TotalCount" => 1,
            'Data' => ['RevenueModelStatisticsAdmin' => $this->storage_path . 'exports/RevenueModelStatisticsAdmin.xls'],
          ], 200);
        }
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($RevenueModelListArr),
          'Data' => array(
            'AffilateList' => $UserListFilter,
            'AdBrandList' => $GetAdBrandList,
            'RevenueModelList' => $RevenueModels,
            'RevenueModelTypeList' => $RevenueModelTypes,
            'RevenueModelStatisticsAdmin' => $RevenueModelListArr,
            'RevenueModelStatisticsAdminTotal' => $RevenueModelTotal,
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

  /*
    Admin Dashboard API
  */
  public function GeneralStatisticsAdminDashboard(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $LeadsTotal = 0;
        $ConvertedAccountsTotal = 0;
        $InitialRevenueTotal = 0;
        $TotalUserAmountTotal = 0;
        $TotalSpreadAmountTotal = 0;
        $BonusTotal = 0;
        $TotalTotal = 0;
        $TotalCommissionTotal = 0;
        $RoyalRevenueTotal = 0;
        $UserListArr = [];

        $UserList = User::where('RoleId', 3)->orderBy('UserId', 'desc')->get();

        foreach ($UserList as $User) {
          $Leads = Lead::where('UserId', $User['UserId']); // New Affiliates
          $LeadList = Lead::where('UserId', $User['UserId'])->pluck('LeadId');
          $ConvertedAccounts = Lead::where('UserId', $User['UserId'])->where('IsConverted', 1); // ConvertedAccounts  
          $RevenueModel = RevenueModel::where('RevenueTypeId', 4)->pluck('RevenueModelId');
          $RevenueModelLog = RevenueModelLog::whereIn('RevenueModelId', $RevenueModel)->pluck('RevenueModelLogId');
          $LeadActivityList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('LeadActivityId', '!=', null)->pluck('LeadActivityId');
          $RoyalAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '>', 0);
          $RoyalSpreadAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDSpreadAmount', '>', 0);
          $UserAmountMain = UserRevenuePayment::where('UserId', $User['UserId'])->where('PaymentStatus', 1);
          $MasterAmountMain = UserSubRevenue::where('UserId', $User['UserId'])->where('USDAmount', '>', 0);
          $RoyalSubAmountMain = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '<', 0);  // cut sub revenue from royal
          // Bonus count
          $BonusMain = UserRevenuePayment::where('UserId', $User['UserId'])->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);

          // Date filter apply
          if (isset($request->DateFilter) && $request->DateFilter != '') {
            if ($request->DateFilter == 1) {
              // this week
              $from = date('Y-m-d', strtotime('this week'));
              $to = date('Y-m-d', strtotime('this week +6 days'));
            } else if ($request->DateFilter == 2) {
              // last week
              $from = date('Y-m-d', strtotime('last week'));
              $to = date('Y-m-d', strtotime('last week +6 days'));
            } else if ($request->DateFilter == 3) {
              // this month
              $from = date('Y-m-d', strtotime('first day of this month'));
              $to = date('Y-m-d', strtotime('last day of this month'));
            } else if ($request->DateFilter == 4) {
              // last month
              $from = date('Y-m-d', strtotime('first day of last month'));
              $to = date('Y-m-d', strtotime('last day of last month'));
            } else {
              $from = date('Y-m-d', strtotime('this week'));
              $to = date('Y-m-d', strtotime('this week +6 days'));
            }
          } else {
            // $ToDay = date('Y-m-d');
            $from = date('Y-m-d', strtotime('this week'));
            $to = date('Y-m-d', strtotime('this week +6 days'));
          }
          // return $from.' to '.$to; die;

          // Start. date filter
          $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
          $ConvertedAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
          $RoyalAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          $RoyalSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          $UserAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          $MasterAmountMain->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
            $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          });
          $RoyalSubAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          $BonusMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          // End. date filter        
          $Leads = $Leads->count(); // Leads
          $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts 
          if ($request->CurrencyId == 1) {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $CurrencyTitle = 'USD';
          } else if ($request->CurrencyId == 2) {
            $RoyalAmount = $RoyalAmount->sum('AUDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('AUDSpreadAmount');
            $CurrencyTitle = 'AUD';
          } else if ($request->CurrencyId == 3) {
            $RoyalAmount = $RoyalAmount->sum('EURAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('EURSpreadAmount');
            $CurrencyTitle = 'EUR';
          } else {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $CurrencyTitle = 'USD';
          }
          $InitialRevenue = $RoyalAmount + $RoyalSpreadAmount; // InitialRevenue

          if ($request->CurrencyId == 1) {
            $UserAmount = $UserAmountMain->sum('USDAmount');
            $UserSpreadAmount = $UserAmountMain->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $UserAmount = $UserAmountMain->sum('AUDAmount');
            $UserSpreadAmount = $UserAmountMain->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $UserAmount = $UserAmountMain->sum('EURAmount');
            $UserSpreadAmount = $UserAmountMain->sum('SpreadEURAmount');
          } else {
            $UserAmount = $UserAmountMain->sum('USDAmount');
            $UserSpreadAmount = $UserAmountMain->sum('SpreadUSDAmount');
          }
          $Commission = $UserAmount + $UserSpreadAmount; // Commission count
          $TotalUserAmount = $UserAmount;
          $TotalSpreadAmount = $UserSpreadAmount;

          if ($request->CurrencyId == 1) {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $MasterAmount = $MasterAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $MasterAmount = $MasterAmountMain->sum('EURAmount');
          } else {
            $MasterAmount = $MasterAmountMain->sum('USDAmount');
          }
          $MasterAffiliateCommission = $MasterAmount; // Master Affiliate Commission count

          if ($request->CurrencyId == 1) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          } elseif ($request->CurrencyId == 2) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('AUDAmount');
          } elseif ($request->CurrencyId == 3) {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('EURAmount');
          } else {
            $RoyalSubAmount = $RoyalSubAmountMain->sum('USDAmount');
          }
          $RoyalSubAmountDebit = $RoyalSubAmount; // Master Affiliate Commission count

          if ($request->CurrencyId == 1) {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $BonusAmount = $BonusMain->sum('AUDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $BonusAmount = $BonusMain->sum('EURAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadEURAmount');
          } else {
            $BonusAmount = $BonusMain->sum('USDAmount');
            $BonusSpreadAmount = $BonusMain->sum('SpreadUSDAmount');
          }
          $Bonus = $BonusAmount + $BonusSpreadAmount; // Bonus count
          $TotalCommission = $Commission + $MasterAffiliateCommission; // Total Commission
          $RoyalRevenue = $InitialRevenue - $Commission; // Royal Revenue

          $UserArr = [
            "UserId" => $User['UserId'],
            "Affiliate" => $User['FirstName'] . ' ' . $User['LastName'],
            "Leads" => $Leads,
            "ConvertedAccounts" => $ConvertedAccounts,
            "InitialRevenue" => round($InitialRevenue, 4),
            "Commission" => round($TotalUserAmount, 4),  // Affiliate Revenue
            "SpreadAmount" => round($TotalSpreadAmount, 4),  // Affiliate Revenue
            "TotalCommission" => round($TotalCommission, 4),
            "Bonus" => round($Bonus, 4),
            "Total" => round($Commission, 4),  // Affiliate Revenue
            "NetRevenue" => round($RoyalRevenue, 4),  // Net Revenue
          ];
          array_push($UserListArr, $UserArr);

          $LeadsTotal = $LeadsTotal + $Leads;
          $ConvertedAccountsTotal = $ConvertedAccountsTotal + $ConvertedAccounts;
          $InitialRevenueTotal = $InitialRevenueTotal + $InitialRevenue;
          $TotalUserAmountTotal = $TotalUserAmountTotal + $TotalUserAmount;
          $TotalSpreadAmountTotal = $TotalSpreadAmountTotal + $TotalSpreadAmount;
          $BonusTotal = $BonusTotal + $Bonus;
          $TotalTotal = $TotalTotal + $Commission;
          $TotalCommissionTotal = $TotalCommissionTotal + $TotalCommission;
          $RoyalRevenueTotal = $RoyalRevenueTotal + $RoyalRevenue;
        }
        // top bonus affiliate
        usort($UserListArr, function ($a, $b) {
          return $b['Bonus'] - $a['Bonus'];
        });
        $HighestBonus = [
          'BonusTop' => $UserListArr[0]['Bonus'],
          'Affiliate' => $UserListArr[0]['Affiliate'],
          'AffiliateId' => $UserListArr[0]['UserId']
        ];
        if ($UserListArr[0]['Bonus'] == 0) {
          $HighestBonus = [
            'BonusTop' => 0,
            'Affiliate' => '--',
            'AffiliateId' => ''
          ];
        }
        // top accounts affiliate
        usort($UserListArr, function ($a, $b) {
          return $b['ConvertedAccounts'] - $a['ConvertedAccounts'];
        });
        $MostIntroducedAccounts = [
          'ConvertedAccounts' => $UserListArr[0]['ConvertedAccounts'],
          'Affiliate' => $UserListArr[0]['Affiliate'],
          'AffiliateId' => $UserListArr[0]['UserId']
        ];
        if ($UserListArr[0]['ConvertedAccounts'] == 0) {
          $MostIntroducedAccounts = [
            'ConvertedAccounts' => 0,
            'Affiliate' => '--',
            'AffiliateId' => ''
          ];
        }
        $Affiliates = User::where('IsDeleted', 1)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('RoleId', 3)->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to)->count(); // New Affiliates
        $TotalArray = array(
          "NewAffiliates" => $Affiliates, // New Affiliates
          "Leads" => $LeadsTotal, // New Leads
          "ConvertedAccounts" => $ConvertedAccountsTotal, // New Accounts
          "AffiliateRevenue" => round($TotalTotal, 4), // Affiliate Revenue
          "RoyalRevenue" => round($InitialRevenueTotal, 4), // Royal Revenue
          "NetRoyalRevenue" => round($InitialRevenueTotal - $TotalTotal, 4), // Royal Net Revenue
        );

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get data.',
          'TotalCount' => count($UserListArr),
          'Data' => array(
            'GeneralStatistics' => $TotalArray,
            'MostIntroducedAccounts' => $MostIntroducedAccounts,
            'HighestBonus' => $HighestBonus,
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

  public function ViewDetailBonusByAdminDashboard(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $arrayList = [];
        $TimeZoneOffSet = $request->TimeZoneOffSet;
        if ($TimeZoneOffSet == '')
          $TimeZoneOffSet = 0;

        if (isset($request->DateFilter) && $request->DateFilter != '') {
          if ($request->DateFilter == 1) {
            // this week
            $from = date('Y-m-d', strtotime('this week'));
            $to = date('Y-m-d', strtotime('this week +6 days'));
          } else if ($request->DateFilter == 2) {
            // last week
            $from = date('Y-m-d', strtotime('last week'));
            $to = date('Y-m-d', strtotime('last week +6 days'));
          } else if ($request->DateFilter == 3) {
            // this month
            $from = date('Y-m-d', strtotime('first day of this month'));
            $to = date('Y-m-d', strtotime('last day of this month'));
          } else if ($request->DateFilter == 4) {
            // last month
            $from = date('Y-m-d', strtotime('first day of last month'));
            $to = date('Y-m-d', strtotime('last day of last month'));
          } else {
            $from = date('Y-m-d', strtotime('this week'));
            $to = date('Y-m-d', strtotime('this week +6 days'));
          }
        } else {
          // $ToDay = date('Y-m-d');
          $from = date('Y-m-d', strtotime('this week'));
          $to = date('Y-m-d', strtotime('this week +6 days'));
        }
        $UserRevenuePayments = UserRevenuePayment::with(['Affiliate', 'LeadDetail.Ad.Brand', 'LeadDetail.Ad.Type', 'LeadDetail.Ad.size', 'LeadDetail.Ad.Language', 'LeadActivity', 'LeadInformation', 'UserSubRevenue', 'UserBonus.RevenueModel', 'RevenueModelLog.RevenueModel.Revenue'])->orderBy('UserRevenuePaymentId', 'desc')->where('UserId', $request->AffiliateId)->where('UserBonusId', '!=', null)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1)->get();
        // return $UserRevenuePayments; die;
        foreach ($UserRevenuePayments as $UserRevenuePayment) {
          if ($UserRevenuePayment['UserBonusId']) {
            if ($request->CurrencyId == '') {
              $Amount = round($UserRevenuePayment['USDAmount'] + $UserRevenuePayment['SpreadUSDAmount'], 8);
            } elseif ($request->CurrencyId == 1) {
              $Amount = round($UserRevenuePayment['USDAmount'] + $UserRevenuePayment['SpreadUSDAmount'], 8);
            } elseif ($request->CurrencyId == 2) {
              $Amount = round($UserRevenuePayment['AUDAmount'] + $UserRevenuePayment['SpreadAUDAmount'], 8);
            } elseif ($request->CurrencyId == 3) {
              $Amount = round($UserRevenuePayment['EURAmount'] + $UserRevenuePayment['SpreadEURAmount'], 8);
            } else
              $Amount = round($UserRevenuePayment['USDAmount'] + $UserRevenuePayment['SpreadUSDAmount'], 8);
            $array = [
              'UserRevenuePaymentId' => $UserRevenuePayment['UserRevenuePaymentId'],
              'UserId' => $UserRevenuePayment['UserId'],
              'Affiliate' => $UserRevenuePayment['Affiliate']['FirstName'] . ' ' . $UserRevenuePayment['Affiliate']['LastName'],
              'LeadId' => '',
              'AdId' => '',
              'AdTitle' => '',
              'AdBrand' => '',
              'AdType' => '',
              'AdSize' => '',
              'AdLanguage' => '',
              'RevenueModelLogId' => '',
              'RevenueModelName' => $UserRevenuePayment['UserBonus']['RevenueModel']['RevenueModelName'],
              'RevenueTypeName' => '' . $UserRevenuePayment['RevenueModelLogId'] == NULL ? 'Bonus(Manual)' : 'Bonus(Auto)',
              'UserBonusId' => $UserRevenuePayment['UserBonusId'],
              'UserSubRevenueId' => '',
              'LeadInformationId' => '',
              'LeadActivityId' => '',
              'Amount' => $Amount,
              // 'SpreadAmount' => round($UserRevenuePayment['SpreadUSDAmount'],8),
              'CurrencyConvertId' => $UserRevenuePayment['CurrencyConvertId'],
              'PaymentStatus' => $UserRevenuePayment['PaymentStatus'],
              'ActualRevenueDate' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['ActualRevenueDate']))),
              'CreatedAt' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
            ];
            array_push($arrayList, $array);
          }
        }
        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Bonus list.',
          "TotalCount" => count($arrayList),
          'Data' => array('BonusList' => $arrayList)
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

  public function AffiliateStatisticsAdminDashboard(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $UserListFilter = [];
        $UserListArr = [];
        // Filter data list
        $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('RoleId', 3)->orderBy('FirstName')->get();
        foreach ($User as $value) {
          $arr = [
            "UserId" => $value['UserId'],
            "AffiliateName" => $value['FirstName'] . ' ' . $value['LastName'],
            "EmailId" => $value['EmailId'],
          ];
          array_push($UserListFilter, $arr); // GetAffilateList
        }
        // End. Filter data list
        $UserList = User::where('RoleId', 3)->orderBy('UserId', 'desc')->get();

        foreach ($UserList as $User) {
          $Leads = Lead::where('UserId', $User['UserId']);
          $LeadList = Lead::where('UserId', $User['UserId'])->pluck('LeadId');
          $LeadsQualified = Lead::where('UserId', $User['UserId'])->whereHas('LeadStatus', function ($qr) {
            $qr->where('IsValid', 1);
          });
          $ConvertedAccounts = Lead::where('UserId', $User['UserId'])->where('IsConverted', 1);
          $ActiveAccounts = Lead::where('UserId', $User['UserId'])->where('IsActive', 1);
          $RevenueModel = RevenueModel::where('RevenueTypeId', 4)->pluck('RevenueModelId');
          $RevenueModelLog = RevenueModelLog::whereIn('RevenueModelId', $RevenueModel)->pluck('RevenueModelLogId');
          $QualifiedAccounts = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLog)->where('UserId', $User['UserId']);
          $RevenueModelCPL = RevenueModel::whereIn('RevenueTypeId', [1, 2, 3, 4])->pluck('RevenueModelId');
          $RevenueModelLogCPL = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelCPL)->pluck('RevenueModelLogId');
          $CPLAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogCPL);
          $RevenueModelShare = RevenueModel::whereIn('RevenueTypeId', [5, 6])->pluck('RevenueModelId');
          $RevenueModelLogShare = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelShare)->pluck('RevenueModelLogId');
          $ShareAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogShare); 
          $RoyalAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '>', 0);
          $RoyalSpreadAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDSpreadAmount', '>', 0);
          $UserAmountMain = UserRevenuePayment::where('UserId', $User['UserId'])->where('PaymentStatus', 1);
          $MasterAmountMain = UserSubRevenue::where('UserId', $User['UserId'])->where('USDAmount', '>', 0);
          $RoyalSubAmountMain = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '<', 0);
          $BonusMain = UserRevenuePayment::where('UserId', $User['UserId'])->where('UserBonusId', '!=', null)->where('PaymentStatus', 1);

          // Date filter apply
          if (isset($request->DateFilter) && $request->DateFilter != '') {
            if ($request->DateFilter == 1) {
              // this month
              $from = date('Y-m-d', strtotime('first day of this month'));
              $to = date('Y-m-d', strtotime('last day of this month'));
            } else if ($request->DateFilter == 2) {
              // last month
              $from = date('Y-m-d', strtotime('first day of last month'));
              $to = date('Y-m-d', strtotime('last day of last month'));
            } else if ($request->DateFilter == 3) {
              // since inception
              if (isset($request->DateFrom) && $request->DateFrom != '' && isset($request->DateTo) && $request->DateTo != '') {
                $from = $request->DateFrom;
                $to = $request->DateTo;
              } else {
                $from = date('Y-m-d', strtotime('first day of this month'));
                $to = date('Y-m-d', strtotime('last day of this month'));
              }
            } else {
              $from = date('Y-m-d', strtotime('first day of this month'));
              $to = date('Y-m-d', strtotime('last day of this month'));
            }
          } else {
            $from = date('Y-m-d', strtotime('first day of this month'));
            $to = date('Y-m-d', strtotime('last day of this month'));
          }
          // return $from.' to '.$to; die;
          if ($request->DateFilter != 3) {
            // start date filter
            $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // 4.Leads
            $LeadsQualified->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // LeadsQualified
            $ConvertedAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // ConvertedAccounts
            $ActiveAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // ActiveAccounts
            $QualifiedAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // QualifiedAccounts
            $CPLAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $ShareAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to); 
            $RoyalAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $MasterAmountMain->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
              $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            });
            $RoyalSubAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $BonusMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          }
          // end date filter
          $Leads = $Leads->count(); // Leads
          $LeadsQualified = $LeadsQualified->count(); // LeadsQualified
          $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts
          $ActiveAccounts = $ActiveAccounts->count(); // ActiveAccounts
          $QualifiedAccounts = $QualifiedAccounts->count(); // QualifiedAccounts  
 
          if ($request->CurrencyId == 1) {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
          } else if ($request->CurrencyId == 2) {
            $RoyalAmount = $RoyalAmount->sum('AUDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('AUDSpreadAmount');
          } else if ($request->CurrencyId == 3) {
            $RoyalAmount = $RoyalAmount->sum('EURAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('EURSpreadAmount');
          } else {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
          }
          $InitialRevenue = $RoyalAmount + $RoyalSpreadAmount; // InitialRevenue

          if ($request->CurrencyId == 1) {
            $UserAmount = $UserAmountMain->sum('USDAmount');
            $UserSpreadAmount = $UserAmountMain->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $UserAmount = $UserAmountMain->sum('AUDAmount');
            $UserSpreadAmount = $UserAmountMain->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $UserAmount = $UserAmountMain->sum('EURAmount');
            $UserSpreadAmount = $UserAmountMain->sum('SpreadEURAmount');
          } else {
            $UserAmount = $UserAmountMain->sum('USDAmount');
            $UserSpreadAmount = $UserAmountMain->sum('SpreadUSDAmount');
          }
          $Commission = $UserAmount + $UserSpreadAmount; // Commission count

          if ($Commission > 0) {
            $affName = $User['FirstName'] . ' ' . $User['LastName'];
            if (strlen($affName) > 15) {
              $affName = substr($affName, 0, 13) . "..";
            } else {
              $affName = $affName;
            }
            $UserArr = [
              "Affiliate" => $affName,
              "Leads" => $Leads,
              "Accounts" => $ConvertedAccounts,
              "AffiliateRevenue" => round($Commission, 4),
              "RoyalRevenue" => round($InitialRevenue, 4),
              "NetRoyalRevenue" => round($InitialRevenue - $Commission, 4),
            ];
            array_push($UserListArr, $UserArr);
          }
        }
        usort($UserListArr, function ($a, $b) {
          return $b['AffiliateRevenue'] - $a['AffiliateRevenue'];
        });
        $UserListArr = array_slice($UserListArr, 0, 10); // get top 10 affiliate

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($UserListArr),
          'Data' => array(
            'AffiliateStatisticsAdmin' => $UserListArr,
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

  public function AdStatisticsAdminDashboard(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $AdListArr = [];
        $AdList = Ad::orderBy('AdId', 'desc')->get();
        foreach ($AdList as $AdData) {
          $AdId = $AdData['AdId'];
          $CampaignAddIds = CampaignAdList::where('AdId', $AdId)->pluck('CampaignAddId');
          $CampaignAdClick = CampaignAdClick::whereIn('CampaignAddId', $CampaignAddIds);
          $CampaignAdImpression = CampaignAdImpression::whereIn('CampaignAddId', $CampaignAddIds);
          $Leads = Lead::where('AdId', $AdData['AdId']);
          $LeadList = Lead::where('AdId', $AdData['AdId'])->pluck('LeadId');
          $LeadsQualified = Lead::where('AdId', $AdData['AdId'])->whereHas('LeadStatus', function ($qr) {
            $qr->where('IsValid', 1);
          });
          $ConvertedAccounts = Lead::where('AdId', $AdData['AdId'])->where('IsConverted', 1);
          $ActiveAccounts = Lead::where('AdId', $AdData['AdId'])->where('IsActive', 1);
          $RevenueModel = RevenueModel::where('RevenueTypeId', 4)->pluck('RevenueModelId');
          $RevenueModelLog = RevenueModelLog::whereIn('RevenueModelId', $RevenueModel)->pluck('RevenueModelLogId');
          $QualifiedAccounts = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLog);
          $RevenueModelCPL = RevenueModel::whereIn('RevenueTypeId', [1, 2, 3, 4])->pluck('RevenueModelId');
          $RevenueModelLogCPL = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelCPL)->pluck('RevenueModelLogId');
          $CPLAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogCPL);
          $RevenueModelShare = RevenueModel::whereIn('RevenueTypeId', [5, 6])->pluck('RevenueModelId');
          $RevenueModelLogShare = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelShare)->pluck('RevenueModelLogId');
          $ShareAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogShare); 
          $RoyalAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '>', 0);
          $RoyalSpreadAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDSpreadAmount', '>', 0);
          $UserAmountMain = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('PaymentStatus', 1);
          $UserSubRevenueIdList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('PaymentStatus', 1)->where('USDAmount', '<', 0)->pluck('UserSubRevenueId');
          $MasterAmountMain = UserSubRevenue::whereIn('UserSubRevenueId', $UserSubRevenueIdList)->where('USDAmount', '<', 0);
          $RoyalSubAmountMain = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '<', 0);

          // Date filter apply
          if (isset($request->DateFilter) && $request->DateFilter != '') {
            if ($request->DateFilter == 1) {
              // this month
              $from = date('Y-m-d', strtotime('first day of this month'));
              $to = date('Y-m-d', strtotime('last day of this month'));
            } else if ($request->DateFilter == 2) {
              // last month
              $from = date('Y-m-d', strtotime('first day of last month'));
              $to = date('Y-m-d', strtotime('last day of last month'));
            } else if ($request->DateFilter == 3) {
              // since inception
              if (isset($request->DateFrom) && $request->DateFrom != '' && isset($request->DateTo) && $request->DateTo != '') {
                $from = $request->DateFrom;
                $to = $request->DateTo;
              } else {
                $from = date('Y-m-d', strtotime('first day of this month'));
                $to = date('Y-m-d', strtotime('last day of this month'));
              }
            } else {
              $from = date('Y-m-d', strtotime('first day of this month'));
              $to = date('Y-m-d', strtotime('last day of this month'));
            }
          } else {
            $from = date('Y-m-d', strtotime('first day of this month'));
            $to = date('Y-m-d', strtotime('last day of this month'));
          }
          // return $from.' to '.$to; die;
          if ($request->DateFilter != 3) {
            $CampaignAdImpression->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
            $CampaignAdClick->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
            $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // Leads
            $LeadsQualified->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // LeadsQualified
            $ConvertedAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // ConvertedAccounts 
            $CPLAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $ShareAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to); 
            $RoyalAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $RoyalSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $UserAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            $MasterAmountMain->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
              $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            });
            $RoyalSubAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          }

          $CampaignAdImpression = $CampaignAdImpression->count(); // Leads
          $CampaignAdClick = $CampaignAdClick->count(); // Leads
          // CTR
          if ($CampaignAdImpression == 0)
            $CTR = 0;
          else
            $CTR = $CampaignAdClick / $CampaignAdImpression;
          $Leads = $Leads->count(); // Leads
          $LeadsQualified = $LeadsQualified->count(); // LeadsQualified
          $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts 
 
          if ($request->CurrencyId == 1) {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $AffiliateRevenue = $UserAmountMain->sum('USDAmount') + $UserAmountMain->sum('SpreadUSDAmount');
          } else if ($request->CurrencyId == 2) {
            $RoyalAmount = $RoyalAmount->sum('AUDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('AUDSpreadAmount');
            $AffiliateRevenue = $UserAmountMain->sum('AUDAmount') + $UserAmountMain->sum('SpreadAUDAmount');
          } else if ($request->CurrencyId == 3) {
            $RoyalAmount = $RoyalAmount->sum('EURAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('EURSpreadAmount');
            $AffiliateRevenue = $UserAmountMain->sum('EURAmount') + $UserAmountMain->sum('SpreadEURAmount');
          } else {
            $RoyalAmount = $RoyalAmount->sum('USDAmount');
            $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            $AffiliateRevenue = $UserAmountMain->sum('USDAmount') + $UserAmountMain->sum('SpreadUSDAmount');
          }
          $InitialRevenue = $RoyalAmount + $RoyalSpreadAmount; // InitialRevenue 

          if ($ConvertedAccounts > 0) {
            $adName = $AdData['Title'];
            $AdArr = [
              "AdTitle" => $adName,
              "AdDisplays" => $CampaignAdImpression,
              "AdClicks" => $CampaignAdClick,
              "CTR" => round($CTR, 2),
              "Leads" => $Leads,
              "Accounts" => $ConvertedAccounts,
              "RoyalRevenue" => round($InitialRevenue, 4),
              "NetRoyalRevenue" => round($InitialRevenue - $AffiliateRevenue, 4),
            ];
            array_push($AdListArr, $AdArr);
          }
        }
        usort($AdListArr, function ($a, $b) {
          return $b['Accounts'] - $a['Accounts'];
        });
        $AdListArr = array_slice($AdListArr, 0, 10); // get top 10

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($AdListArr),
          'Data' => array(
            'AdStatisticsAdmin' => $AdListArr,
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

  public function RevenueModelStatisticsAdminDashboard(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) { 
        $RevenueModelTypeStatistics = [];

        $RevenueModelTypes = RevenueType::whereNotIn('RevenueTypeId', [8])->get();
        foreach ($RevenueModelTypes as $RMTData) {
          $LeadsTotal = 0;
          $ConvertedAccountsTotal = 0;
          $QualifiedAccountsTotal = 0;
          $InitialRevenueTotal = 0;
          $TotalTotal = 0;
          $AffiliateTotal = 0;
          $RevenueModelList = RevenueModel::where('RevenueTypeId', $RMTData['RevenueTypeId'])->orderBy('RevenueModelId', 'desc')->get();
          foreach ($RevenueModelList as $RMData) {
            $CampaignIdList = Campaign::where('RevenueModelId', $RMData['RevenueModelId'])->pluck('CampaignId');
            $AffiliateCount = UserRevenueType::where('RevenueModelId', $RMData['RevenueModelId']);
            $Leads = Lead::whereIn('CampaignId', $CampaignIdList);
            $LeadList = Lead::whereIn('CampaignId', $CampaignIdList)->pluck('LeadId');
            $LeadsQualified = Lead::whereIn('CampaignId', $CampaignIdList)->whereHas('LeadStatus', function ($qr) {
              $qr->where('IsValid', 1);
            }); // LeadsQualified
            $ConvertedAccounts = Lead::whereIn('CampaignId', $CampaignIdList)->where('IsConverted', 1); // ConvertedAccounts
            $ActiveAccounts = Lead::whereIn('CampaignId', $CampaignIdList)->where('IsActive', 1); // ActiveAccounts           
            $RevenueModel = RevenueModel::where('RevenueTypeId', 4)->pluck('RevenueModelId');
            $RevenueModelLog = RevenueModelLog::whereIn('RevenueModelId', $RevenueModel)->pluck('RevenueModelLogId');
            $QualifiedAccounts = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLog); // QualifiedAccounts
            $RevenueModelCPL = RevenueModel::whereIn('RevenueTypeId', [1, 2, 3, 4])->pluck('RevenueModelId');
            $RevenueModelLogCPL = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelCPL)->pluck('RevenueModelLogId');
            $CPLAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogCPL);
            $RevenueModelShare = RevenueModel::whereIn('RevenueTypeId', [5, 6])->pluck('RevenueModelId');
            $RevenueModelLogShare = RevenueModelLog::whereIn('RevenueModelId', $RevenueModelShare)->pluck('RevenueModelLogId');
            $ShareAmount = UserRevenuePayment::whereIn('LeadId', $LeadList)->whereIn('RevenueModelLogId', $RevenueModelLogShare); 
            $RoyalAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '>', 0); // InitialRevenue count
            $RoyalSpreadAmount = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDSpreadAmount', '>', 0);
            $UserAmountMain = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('PaymentStatus', 1); //  Commission count
            if ($RMData['RevenueTypeId'] == 7) {
              $RevenueModelLogidBonus = RevenueModelLog::where('RevenueModelId', $RMData['RevenueModelId'])->pluck('RevenueModelLogId');
              $UserAmountMain = UserRevenuePayment::whereIn('RevenueModelLogId', $RevenueModelLogidBonus)->where('PaymentStatus', 1); //  Commission count
            }
            $UserSubRevenueIdList = UserRevenuePayment::whereIn('LeadId', $LeadList)->where('UserSubRevenueId', '!=', null)->where('PaymentStatus', 1)->where('USDAmount', '<', 0)->pluck('UserSubRevenueId'); // MasterAffiliateCommission count
            $MasterAmountMain = UserSubRevenue::whereIn('UserSubRevenueId', $UserSubRevenueIdList)->where('USDAmount', '<', 0);
            // $RoyalSubAmountMain = RoyalRevenue::whereIn('LeadId', $LeadList)->where('USDAmount', '<', 0);  // cut sub revenue from royal          
            $BonusMain = UserRevenuePayment::whereIn('RevenueModelLogId', $RevenueModelLog)->where('UserBonusId', '!=', null)->where('PaymentStatus', 1); // Bonus count

            // Date filter apply
            if (isset($request->DateFilter) && $request->DateFilter != '') {
              if ($request->DateFilter == 1) {
                // this month
                $from = date('Y-m-d', strtotime('first day of this month'));
                $to = date('Y-m-d', strtotime('last day of this month'));
              } else if ($request->DateFilter == 2) {
                // last month
                $from = date('Y-m-d', strtotime('first day of last month'));
                $to = date('Y-m-d', strtotime('last day of last month'));
              } else if ($request->DateFilter == 3) {
                // since inception
                if (isset($request->DateFrom) && $request->DateFrom != '' && isset($request->DateTo) && $request->DateTo != '') {
                  $from = $request->DateFrom;
                  $to = $request->DateTo;
                } else {
                  $from = date('Y-m-d', strtotime('first day of this month'));
                  $to = date('Y-m-d', strtotime('last day of this month'));
                }
              } else {
                $from = date('Y-m-d', strtotime('first day of this month'));
                $to = date('Y-m-d', strtotime('last day of this month'));
              }
            } else {
              $from = date('Y-m-d', strtotime('first day of this month'));
              $to = date('Y-m-d', strtotime('last day of this month'));
            }
            if ($request->DateFilter != 3) {
              $Leads->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // Leads
              $LeadsQualified->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // LeadsQualified
              $ConvertedAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // ConvertedAccounts
              $ActiveAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // ActiveAccounts
              $QualifiedAccounts->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to); // QualifiedAccounts
              $CPLAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
              $ShareAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to); 
              $RoyalAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
              $RoyalSpreadAmount->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
              $UserAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
              $MasterAmountMain->whereHas('UserRevenuePayment', function ($qr) use ($from, $to) {
                $qr->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
              });
              // $RoyalSubAmountMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
              $BonusMain->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
            }

            $Leads = $Leads->count(); // Leads
            $LeadsQualified = $LeadsQualified->count(); // LeadsQualified
            $ConvertedAccounts = $ConvertedAccounts->count(); // ConvertedAccounts
            $ActiveAccounts = $ActiveAccounts->count(); // ActiveAccounts
            $QualifiedAccounts = $QualifiedAccounts->count(); // QualifiedAccounts 
            $AffiliateCount =  $AffiliateCount->count(); 

            if ($request->CurrencyId == 1) {
              $RoyalAmount = $RoyalAmount->sum('USDAmount');
              $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            } else if ($request->CurrencyId == 2) {
              $RoyalAmount = $RoyalAmount->sum('AUDAmount');
              $RoyalSpreadAmount = $RoyalSpreadAmount->sum('AUDSpreadAmount');
            } else if ($request->CurrencyId == 3) {
              $RoyalAmount = $RoyalAmount->sum('EURAmount');
              $RoyalSpreadAmount = $RoyalSpreadAmount->sum('EURSpreadAmount');
            } else {
              $RoyalAmount = $RoyalAmount->sum('USDAmount');
              $RoyalSpreadAmount = $RoyalSpreadAmount->sum('USDSpreadAmount');
            }
            $InitialRevenue = $RoyalAmount + $RoyalSpreadAmount; // InitialRevenue

            if ($request->CurrencyId == 1) {
              $UserAmount = $UserAmountMain->sum('USDAmount');
              $UserSpreadAmount = $UserAmountMain->sum('SpreadUSDAmount');
            } else if ($request->CurrencyId == 2) {
              $UserAmount = $UserAmountMain->sum('AUDAmount');
              $UserSpreadAmount = $UserAmountMain->sum('SpreadAUDAmount');
            } else if ($request->CurrencyId == 3) {
              $UserAmount = $UserAmountMain->sum('EURAmount');
              $UserSpreadAmount = $UserAmountMain->sum('SpreadEURAmount');
            } else {
              $UserAmount = $UserAmountMain->sum('USDAmount');
              $UserSpreadAmount = $UserAmountMain->sum('SpreadUSDAmount');
            }
            $Commission = $UserAmount + $UserSpreadAmount; // Commission count

            $LeadsTotal = $LeadsTotal + $Leads;
            $ConvertedAccountsTotal = $ConvertedAccountsTotal + $ConvertedAccounts;
            $QualifiedAccountsTotal = $QualifiedAccountsTotal + $QualifiedAccounts;
            $TotalTotal = $TotalTotal + $Commission;
            $InitialRevenueTotal = $InitialRevenueTotal + $InitialRevenue;
            $AffiliateTotal = $AffiliateTotal + $AffiliateCount;
          }
          $RevenueTypeName = $RMTData['RevenueTypeName'];
          if ($RMData['RevenueTypeId'] == 1) {
            $RevenueTypeNameShort = 'CPL';
          } else if ($RMData['RevenueTypeId'] == 2) {
            $RevenueTypeNameShort = 'CCPL';
          } else if ($RMData['RevenueTypeId'] == 3) {
            $RevenueTypeNameShort = 'CPA';
          } else if ($RMData['RevenueTypeId'] == 4) {
            $RevenueTypeNameShort = 'CCPA';
          } else if ($RMData['RevenueTypeId'] == 5) {
            $RevenueTypeNameShort = 'RS';
          } else if ($RMData['RevenueTypeId'] == 6) {
            $RevenueTypeNameShort = 'FX RS';
          } else if ($RMData['RevenueTypeId'] == 7) {
            $RevenueTypeNameShort = 'Bonus';
          } else if ($RMData['RevenueTypeId'] == 8) {
            $RevenueTypeNameShort = 'Sub-Aff';
          } else {
            $RevenueTypeNameShort = '';
          }

          $RevenueModelTypeStatisticsArr  = [
            'RevenueModelType' => $RevenueTypeName,
            'RevenueTypeNameShort' => $RevenueTypeNameShort,
            'NumberOfAffiliates' => $AffiliateTotal,
            'AffiliateRevenue' => $TotalTotal,
            'NumberOfleads' => $LeadsTotal,
            'numberOfAccounts' => $ConvertedAccountsTotal,
            'RoyalRevenue' => $InitialRevenueTotal,
            'NetRoyalRevenue' => $InitialRevenueTotal - $TotalTotal,
          ];
          array_push($RevenueModelTypeStatistics, $RevenueModelTypeStatisticsArr);
        }

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Get list.',
          'TotalCount' => count($RevenueModelTypeStatistics),
          'Data' => array(
            // 'RevenueModelTypeList' => $RevenueModelTypes,
            'RevenueModelTypeStatistics' => $RevenueModelTypeStatistics,
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
  /* 
    End. Admin Dashboard API 
  */

  public function decrypt(Request $request)
  {
    return decrypt($request->id);
  }
  
  public function IsValidAdminLoginToken(Request $request)
  {
    $ckeck = new UserToken();
    $UserId = $ckeck->validTokenAdmin($request->header('Token'));
    if ($UserId) { 
      return response()->json([
        'IsSuccess' => true,
        'Message' => 'Valid token.',
        "TotalCount" => 1,
        "Data" => []
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
  
  public function LeadActivityDate(Request $request)
  { 
    $LeadActivity = LeadActivity::get();
    $count = 0;
    foreach($LeadActivity as $LeadActivitys){
      $LeadActivityData = LeadActivity::find($LeadActivitys->LeadActivityId);
      $strdate = str_replace('/', '-', $LeadActivityData['LeadsActivityDate']);
      $time = strtotime($strdate);
      $LeadActivityData->ActualRevenueDate = date('Y-m-d H:i:s', $time); 
      $LeadActivityData->save(); 
      $count = $count + 1;
    }
    return 'Successfully converted records = '. $count;
  }

}
