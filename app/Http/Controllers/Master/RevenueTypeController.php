<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\RevenueType;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class RevenueTypeController extends BaseController
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

    public function RevenueTypeList()
    { 
        try{
            $RevenueType = RevenueType::get();
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Revenue Type List',
                "TotalCount" => $RevenueType->count(),
                "Data" => array('RevenueType' => $RevenueType)
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

    public function RevenueTypeListForAssignAffiliate()
    { 
        try{
            $RevenueType = RevenueType::where('RevenueTypeId', '!=', 8)->get();
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Revenue Type List',
                "TotalCount" => $RevenueType->count(),
                "Data" => array('RevenueType' => $RevenueType)
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