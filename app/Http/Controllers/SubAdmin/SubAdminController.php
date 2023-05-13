<?php

namespace App\Http\Controllers\SubAdmin; 
use Illuminate\Http\Request;
use Validator;
use App\User;
use App\UserToken;
use App\Menu;
use App\Permission;
use App\SupportManager;
use Laravel\Lumen\Routing\Controller as BaseController;

class SubAdminController extends BaseController
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
    }

    public function GetSubadminPermission(Request $request)
    { 
        try
        { 
            $check = new UserToken();
            $log_user = $check->validTokenAdmin($request->header('token'));
            if($log_user)
            {
                $getuser = User::where('UserId',$request->UserId)->first();
                if($getuser){
                    $UserId = $request->UserId; 
                    $shares = Permission::with('Menu')->where('UserId', $UserId)->get();
                    $NewSupportMsgCount = SupportManager::where('ToUserId',1)->where('IsRead', 0)->count();
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
                        'IsSuccess'=>true,
                        'Message'=>'Show permission.',
                        'TotalCount' => 0,
                        'Data'=>array(
                            'User'=>array(
                                        'UserId'=> $getuser->UserId,
                                        'FirstName'=> $getuser->FirstName,
                                        'LastName'=> $getuser->LastName,
                                        'Email'=> $getuser->EmailId, 
                                        'RoleId'=> $getuser->RoleId,
                                        'CreatedAt'=> (string)$getuser->CreatedAt,
                                        'UpdatedAt'=> (string)$getuser->UpdatedAt
                                    ),
                                    'permission' => $AccessArray
                                ),
                            'NewSupportMsgCount' => $NewSupportMsgCount
                    ];
                    return response()->json($res,200);             
                }else{
                    $res = [
                        'IsSuccess'=>false,
                        'Message'=>'User not found.',
                        'TotalCount' => 0,
                        'Data'=> []
                    ];
                    return response()->json($res,200);
                }                 
            }
            else
            {
                $res = [
                    'IsSuccess'=>false,
                    'Message'=>'Invalid Token.',
                    'TotalCount' => 0,
                    'Data' => null
                ];
                return response()->json($res,200);
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
            return response()->json($res,200);
        }
    }

}
