<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\CountryMaster;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class CountryController extends BaseController
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

    public function CountryList()
    { 
        try{
            $Country = CountryMaster::orderBy('CountryName')->get();
            $adsSelected = [];
            foreach ($Country as  $value) {  
                $var = [
                    'CountryId' => $value['CountryId'],
                    'name' => $value['CountryName'],
                    'CountryNameShortCode' => $value['CountryNameShortCode'],
                    'CountryCode' => $value['CountryCode']
                ];
                array_push($adsSelected, $var);
            }
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Country List',
                "TotalCount" => $Country->count(),
                "Data" => array('CountryList' => $adsSelected)
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
