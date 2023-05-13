<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\AdBrandMaster;
use App\AdSizeMaster;
use App\AdTypeMaster;
use Illuminate\Http\Request;
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
    }

    public function AdBrandList()
    { 
        try{
            $AdBrands = AdBrandMaster::orderBy('Title')->get();
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Ad Brand List',
                "TotalCount" => $AdBrands->count(),
                "Data" => array('AdBrands' => $AdBrands)
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

    public function AdSizeList()
    { 
        try{
            $AdSize = AdSizeMaster::where('IsActive',1)->orderBy('Width')->get(); 
            $arr = [];
            foreach ($AdSize as $value) {
                $new = [
                    'AdSizeId' => $value['AdSizeId'],
                    'Size' => $value['Width'].'*'.$value['Height']
                ];
                array_push($arr, $new);
            }
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Ad Size List',
                "TotalCount" => $AdSize->count(),
                "Data" => array('AdSize' => $arr)
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

    public function AdTypeList()
    { 
        try{
            $AdType = AdTypeMaster::orderBy('Title')->get();
            return response()->json([
                "IsSuccess" => true,
                "Message" => "Ad Type List",
                "TotalCount" => $AdType->count(),
                "Data" => array("AdType" => $AdType)
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
