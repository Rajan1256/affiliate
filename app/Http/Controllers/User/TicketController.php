<?php


namespace App\Http\Controllers\User;

use App\SupportTicket;
use Illuminate\Http\Request;
use Validator;
use App\User;
use App\SupportManager;
use App\UserToken;
use Carbon\Carbon;
use Laravel\Lumen\Routing\Controller as BaseController;

class TicketController extends BaseController
{
    private $request;
    private $message = "Thank you for writing in. We will surely get back to you very soon. We appreciate your comments, and hope you'll continue to share them with us.";

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function GenerateTicket(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));

            if ($UserId) {

                $validator = Validator::make($request->all(), [
                    'TicketTitle' => 'required',
                    'TicketSubject' => 'required',
                    'TicketTypeId' => 'required',
                    'priorityTypeId' => 'required',
                    'TicketDescription' => 'required',
                ]);
                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'Something went wrong.',
                        "TotalCount" => count($validator->errors()),
                        "Data" => array('Error' => $validator->errors())
                    ], 200);
                }

                $Title = $request->TicketTitle;
                $Subject = $request->TicketSubject;
                $TicketTypeId = $request->TicketTypeId;
                $TicketPriority = $request->priorityTypeId;
                $TicketDescription = $request->TicketDescription;

                $Ticket_create = SupportTicket::create([
                    'UserId' => $UserId,
                    'TicketTitle' => $Title,
                    'TicketSubject' => $Subject,
                    'TicketTypeId' => $TicketTypeId,
                    'PriorityId' => $TicketPriority,
                    'TicketDescription' => $TicketDescription,
                    'TicketStatuss' => 0,
                ]);

                SupportManager::create([
                    'TicketId' => $Ticket_create->TicketId,
                    'FromUserId' => $UserId,
                    'ToUserId' => 1,
                    'IsRead' => 0,
                    'Message' => $request->TicketDescription,
                ]);

                SupportManager::create([
                    'TicketId' => $Ticket_create->TicketId,
                    'FromUserId' => 1,
                    'ToUserId' => $UserId,
                    'IsRead' => 0,
                    'Message' => $this->message,
                ]);

                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Ticket created successfully.',
                    "TotalCount" => 1,
                    'Data' => ['TicketData' => $Ticket_create]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }

    public function ShowAffiliateTickets(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));
            if ($UserId) {
                $support1 = SupportTicket::orderBy('UpdatedAt', 'desc')->with('tickettype', 'priortytype', 'userticket')->where('UserId', $UserId);
                if (isset($request->TicketTypeId) && $request->TicketTypeId != '') {
                    $support1->where('TicketTypeId', $request->TicketTypeId);
                }
                if (isset($request->Status) && $request->Status != '') {
                    $statusId = $request->Status;
                    $support1->whereHas('userticket', function ($qr) use ($statusId) {
                        $qr->where('TicketStatus', $statusId);
                    });
                }
                if (isset($request->PriorityId) && $request->PriorityId != '') {
                    $support1->where('PriorityId', $request->PriorityId);
                }
                if (isset($request->Search) && $request->Search != '') {
                    $search = $request->Search;
                    $support1->where('TicketTitle', 'like', '%' . $search . '%');
                }
                $support = $support1->get();
                if ($request->Timezone == "")
                    $TimeZoneOffSet = 0;
                else
                    $TimeZoneOffSet = $request->Timezone;
                $TicketList = [];
                foreach ($support as $value) {
                    if ($value['TicketStatus'] == 0) {
                        $TicketStatus = 'Open';
                    } else if ($value['TicketStatus'] == 1) {
                        $TicketStatus = 'Closed';
                    }
                    $d1 = date("m/d/Y  h:i:s", strtotime($value['CreatedAt']));
                    $d2 = date('m/d/Y h:i:s', time());
                    $s1 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$d1)));
                    $s2 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$d2)));

                    if (date("d-m-Y", strtotime($s2)) == date("d-m-Y", strtotime($s1))) {
                        $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $concate = 'Today ' . $dd1;
                    } else if (date('d-m-Y', strtotime("-1 day", strtotime($s2))) == date("d-m-Y", strtotime($s1))) {
                        $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $concate = 'Yesterday';
                    } else {
                        $dd1 = date("F d", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $concate = $dd1;
                    }

                    $tik_count = SupportManager::where('TicketId', $value['TicketId'])
                        ->where('FromUserId', 1)->where('IsRead', 0)->count();

                    $var1 = [
                        'TicketId' => $value['TicketId'],
                        'UserId' => $value['userticket']['UserId'],
                        'UserName' => $value['userticket']['FirstName'] . ' ' . $value['userticket']['LastName'],
                        'Email' => $value['userticket']['EmailId'],
                        'TicketSubject' => $value['TicketSubject'],
                        'TicketDescription' => $value['TicketDescription'],
                        'TicketDate' =>  $concate,
                        'IsReadCount' => $tik_count,
                        'TicketTitle' => $value['TicketTitle'],
                        'TicketTypeId' => $value['tickettype']['TicketTypeId'],
                        'TicketTypeTitle' => $value['tickettype']['TicketTypeTitle'],
                        'TicketTypeDescription' => $value['tickettype']['TicketTypeDescription'],
                        'PriorityId' => $value['priortytype']['PriorityId'],
                        'PriorityName' => $value['priortytype']['PriorityName'],
                        'TicketStatus' => $TicketStatus
                    ];
                    array_push($TicketList, $var1);
                }

                // $total_count = SupportManager::where('ToUserId',$UserId)->where('IsRead',0)->count();

                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Show Affiliate Tickets.',
                    "TotalCount" =>  $support1->count(),
                    'Data' => ['TicketData' => $TicketList, ]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }

    public function SendMessageByAffiliate(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));

            if ($UserId) {

                $validator = Validator::make($request->all(), [
                    'TicketId' => 'required',
                    'FromUserId' => 'required',
                    'Message' => 'required',
                ]);
                if ($validator->fails()) {
                    return response()->json([
                        'IsSuccess' => false,
                        'Message' => 'Something went wrong.',
                        "TotalCount" => count($validator->errors()),
                        "Data" => array('Error' => $validator->errors())
                    ], 200);
                }

                $TicketId = $request->TicketId;
                $FromUserId = $request->FromUserId;
                $Message = $request->Message;
                $ToUserId = 1;
                $send_create = SupportManager::create([
                    'TicketId' => $TicketId,
                    'FromUserId' => $FromUserId,
                    'ToUserId' => $ToUserId,
                    'Message' => $Message,
                    'IsReads' => 0,
                ]);
                SupportTicket::where('TicketId', $TicketId)->update(['UpdatedAt' => Carbon::now()]);

                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Message sending successfully.',
                    "TotalCount" => 1,
                    'Data' => ['SendingData' => $send_create]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }

    public function ShowMessageByAffiliate(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));

            if ($UserId) {
                SupportManager::where('TicketId', $request->TicketId)->where('ToUserId', $UserId)
                    ->update(['IsRead' => 1]);
                $support = SupportManager::where('TicketId', $request->TicketId)->get();
                if ($request->Timezone == "")
                    $TimeZoneOffSet = 0;
                else
                    $TimeZoneOffSet = $request->Timezone;

                $payment_request1 = [];
                foreach ($support as $value) {
                    if ($value['TicketStatus'] == 0) {
                        $TicketStatus = 'Open';
                    } else if ($value['TicketStatus'] == 1) {
                        $TicketStatus = 'Closed';
                    }
                    $d1 = date("m/d/Y  h:i:s", strtotime($value['CreatedAt']));
                    $d2 = date('m/d/Y h:i:s', time());

                    $s1 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$d1)));
                    $s2 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$d2)));

                    if (date("d-m-Y", strtotime($s2)) == date("d-m-Y", strtotime($s1))) {
                        $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $concate = $dd1 . ' | Today';
                    } else if (date('d-m-Y', strtotime("-1 day", strtotime($s2))) == date("d-m-Y", strtotime($s1))) {
                        $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $concate = $dd1 . ' | Yesterday';
                    } else {
                        $dd1 = date("F d", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $dd2 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $concate = $dd1 . ' | ' . $dd2;
                    }
                    $User = User::where('UserId', $value['FromUserId'])->first();
                    $var1 = [
                        'TicketId' => $value['TicketId'],
                        'FromUserId' => $value['FromUserId'],
                        'ToUserId' => $value['ToUserId'],
                        'Name' => $User['FirstName'] . " " . $User['LastName'],
                        'Message' => $value['Message'],
                        'IsRead' => $value['IsRead'],
                        'DateTime' => $concate,
                    ];
                    array_push($payment_request1, $var1);
                }
                $tik = SupportTicket::where('TicketId', $request->TicketId)->count();
                if ($tik == 0) {
                    $ticketstatus = NULL;
                } else {
                    $supportticket = SupportTicket::where('TicketId', $request->TicketId)->first();
                    $ticketstatus = $supportticket->TicketStatus;
                }

                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Show Affiliate Tickets.',
                    "TotalCount" =>  $support->count(),
                    'Data' => ['TicketData' => $payment_request1, 'TicketStatus' => $ticketstatus]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }
    
    public function GetUnreadMessageByAffiliate(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));

            if ($UserId) {
                $support = SupportManager::where('TicketId', $request->TicketId)->where('ToUserId', $UserId)->where('IsRead', 0)->get();
                SupportManager::where('TicketId', $request->TicketId)->where('ToUserId', $UserId)
                    ->update(['IsRead' => 1]);
                if ($request->Timezone == "")
                    $TimeZoneOffSet = 0;
                else
                    $TimeZoneOffSet = $request->Timezone;

                $payment_request1 = [];
                foreach ($support as $value) {
                    if ($value['TicketStatus'] == 0) {
                        $TicketStatus = 'Open';
                    } else if ($value['TicketStatus'] == 1) {
                        $TicketStatus = 'Closed';
                    }
                    $d1 = date("m/d/Y  h:i:s", strtotime($value['CreatedAt']));
                    $d2 = date('m/d/Y h:i:s', time());

                    $s1 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$d1)));
                    $s2 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$d2)));

                    if (date("d-m-Y", strtotime($s2)) == date("d-m-Y", strtotime($s1))) {
                        $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $concate = $dd1 . ' | Today';
                    } else if (date('d-m-Y', strtotime("-1 day", strtotime($s2))) == date("d-m-Y", strtotime($s1))) {
                        $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $concate = $dd1 . ' | Yesterday';
                    } else {
                        $dd1 = date("F d", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $dd2 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string)$value['CreatedAt'])));
                        $concate = $dd1 . ' | ' . $dd2;
                    }
                    $User = User::where('UserId', $value['FromUserId'])->first();
                    $var1 = [
                        'TicketId' => $value['TicketId'],
                        'FromUserId' => $value['FromUserId'],
                        'ToUserId' => $value['ToUserId'],
                        'Name' => $User['FirstName'] . " " . $User['LastName'],
                        'Message' => $value['Message'],
                        'IsRead' => $value['IsRead'],
                        'DateTime' => $concate,
                    ];
                    array_push($payment_request1, $var1);
                }
                $tik = SupportTicket::where('TicketId', $request->TicketId)->count();
                if ($tik == 0) {
                    $ticketstatus = NULL;
                } else {
                    $supportticket = SupportTicket::where('TicketId', $request->TicketId)->first();
                    $ticketstatus = $supportticket->TicketStatus;
                }

                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Show Affiliate Tickets.',
                    "TotalCount" =>  $support->count(),
                    'Data' => ['TicketData' => $payment_request1, 'TicketStatus' => $ticketstatus]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }
    
    public function GetUnreadMessageCountAffiliate(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));

            if ($UserId) {
                $support = SupportManager::where('ToUserId', $UserId)->where('IsRead', 0)->get(); 
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Show Affiliate Tickets.',
                    "TotalCount" =>  $support->count(),
                    'Data' => ['MessageCount' => $support->count()]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }

    public function UpdateTicketStatus(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));
            if ($UserId) {
                $support = SupportTicket::where('TicketId', $request->TicketId)->update([
                    'TicketStatus' => 1
                ]);
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Tickets Closed Successfully.',
                    "TotalCount" =>  1,
                    'Data' => ['TicketData' => $support]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }

    public function AffiliateSupportCount(Request $request)
    {
        try {
            $check = new UserToken();
            $UserId = $check->validToken($request->header('Token'));

            if ($UserId) {

                $total_count = SupportManager::where('ToUserId', $UserId)->where('IsRead', 0)->count();

                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Affiliate total support count.',
                    "TotalCount" =>  $total_count,
                    'Data' => ['SupportCount' => $total_count]
                ], 200);
            } else {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Token not found.',
                    "TotalCount" => 0,
                    'Data' => []
                ], 200);
            }
        } catch (exception $e) {
            $res = [
                'IsSuccess' => false,
                'Message' => $e,
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res);
    }
}
