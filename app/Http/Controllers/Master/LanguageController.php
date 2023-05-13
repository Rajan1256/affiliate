<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\LanguageMaster;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class LanguageController extends BaseController
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

    public function LanguageList()
    { 
        try{
            $Language = LanguageMaster::orderby('LanguageName')->get(); 
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Language List',
                "TotalCount" => $Language->count(),
                "Data" => array('LanguageList' => $Language)
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
            return response()->json($res);
        }
    }
}
