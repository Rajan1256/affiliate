<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Menu;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class MenuController extends BaseController
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

    public function MenuList()
    { 
        try{
            $Menu = Menu::orderBy('MenuName')->get(); 
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Menu List',
                "TotalCount" => $Menu->count(),
                "Data" => array('MenuList' => $Menu)
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
