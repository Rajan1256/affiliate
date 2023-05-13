<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\RoleMaster;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class RoleController extends BaseController
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

    public function RoleList()
    { 
        try{ 
            $Role = RoleMaster::orderBy('RoleName')->get();
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Role Type List',
                "TotalCount" => $Role->count(),
                "Data" => array('Role' => $Role)
            ], 200);
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