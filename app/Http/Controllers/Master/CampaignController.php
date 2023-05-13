<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\CampaignTypeMaster;
use Illuminate\Http\Request;
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
    }

    public function CampaignTypeList()
    { 
        try{
            $CampaignTypes = CampaignTypeMaster::orderBy('Type')->get(); 
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Campaign Types List',
                "TotalCount" => $CampaignTypes->count(),
                "Data" => array('CampaignTypes' => $CampaignTypes)
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
