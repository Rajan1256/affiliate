<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\CurrencyMaster;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class CurrencyController extends BaseController
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

    public function CurrencyList()
    { 
        try{
            $Currency = CurrencyMaster::orderBy('CurrencyName')->get();
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Currency Type List',
                "TotalCount" => $Currency->count(),
                "Data" => array('Currency' => $Currency)
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