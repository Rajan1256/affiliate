<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\TicketType;
use App\Priority;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class TicketTypeController extends BaseController
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

    public function TicketTypeList()
    { 
        try{
            $TicketType = TicketType::orderBy('TicketTypeTitle')->get();
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Ticket Type List',
                "TotalCount" => $TicketType->count(),
                "Data" => array('TicketType' => $TicketType)
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
    
    public function PriorityList()
    {
        try{
            $PriorityId = Priority::orderBy('PriorityId')->get();
            $TicketStatusList = array(
                    [
                        'StatusId'=>0,
                        'Status'=>'Open'
                    ],
                    [
                        'StatusId'=>1,
                        'Status'=>'Closed'
                    ]
                );
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Priority List',
                "TotalCount" => $PriorityId->count(),
                "Data" => array('Priority' => $PriorityId, 'TicketStatusList' => $TicketStatusList)
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

    public function TicketStatus()
    {
        try{
            $PriorityId = array(
                    [
                        'StatusId'=>0,
                        'Status'=>'Open'
                    ],
                    [
                        'StatusId'=>1,
                        'Status'=>'Closed'
                    ]
                );
            return response()->json([
                'IsSuccess' => true,
                'Message' => 'Status List',
                "TotalCount" => 2,
                "Data" => array('TicketStatusList' => $PriorityId)
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