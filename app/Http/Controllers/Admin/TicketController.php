<?php

namespace App\Http\Controllers\Admin;

use Validator;
use App\UserToken;
use App\SupportManager;
use App\SupportTicket;
use Illuminate\Http\Request;
use App\User;
use Carbon\Carbon;
use Laravel\Lumen\Routing\Controller as BaseController;

class TicketController extends BaseController
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
    $this->storage_path = getenv('STORAGE_URL');
  }

  public function AdminShowAllTickets(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validTokenAdmin($request->header('token'));

      if ($UserId) {
        if ($UserId->RoleId == 1 || $UserId->RoleId == 2) {
          $support1 = SupportTicket::orderBy('UpdatedAt', 'desc')->with('tickettype', 'priortytype', 'userticket');
          if (isset($request->TicketTypeId) && $request->TicketTypeId != '') {
            $support1->where('TicketTypeId', $request->TicketTypeId);
          }
          if (isset($request->Status) && $request->Status != '') {
            $support1->where('TicketStatus', $request->Status);
          }
          if (isset($request->PriorityId) && $request->PriorityId != '') {
            $support1->where('PriorityId', $request->PriorityId);
          }
          if (isset($request->Search) && $request->Search != '') {
            $search = $request->Search;
            $support1->where('TicketTitle', 'like', '%' . $search . '%');
          }
          $support = $support1->get();
          if ($request->Timezone == "") {
            $TimeZoneOffSet = 0;
          } else {
            $TimeZoneOffSet = $request->Timezone;
          }

          $payment_request = [];
          foreach ($support as $value) {
            if ($value['TicketStatus'] == 0) {
              $TicketStatus = 'Open';
            } else if ($value['TicketStatus'] == 1) {
              $TicketStatus = 'Closed';
            }
            $d1 = date("m/d/Y  h:i:s", strtotime($value['CreatedAt']));
            $d2 = date('m/d/Y h:i:s', time());

            $s1 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $d1)));
            $s2 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $d2)));

            if (date("d-m-Y", strtotime($s2)) == date("d-m-Y", strtotime($s1))) {
              $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $value['CreatedAt'])));
              $concate = 'Today ' . date("h:i A", strtotime($dd1));
            } else if (date('d-m-Y', strtotime("-1 day", strtotime($s2))) == date("d-m-Y", strtotime($s1))) {
              $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $value['CreatedAt'])));
              $concate = 'Yesterday';
            } else {
              $dd1 = date("F d", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $value['CreatedAt'])));
              $concate = $dd1;
            }
            $tik_count = SupportManager::where('TicketId', $value['TicketId'])
              ->where('ToUserId', 1)->where('IsRead', 0)->count();
            $var1 = [
              'TicketId' => $value['TicketId'],
              'UserId' => $value['userticket']['UserId'],
              'UserName' => $value['userticket']['FirstName'] . ' ' . $value['userticket']['LastName'],
              'Email' => $value['userticket']['EmailId'],
              'TicketSubject' => $value['TicketSubject'],
              'TicketDescription' => $value['TicketDescription'],
              'TicketDate' => $concate,
              'IsReadCount' => $tik_count,
              'TicketTitle' => $value['TicketTitle'],
              'TicketTypeId' => $value['tickettype']['TicketTypeId'],
              'TicketTypeTitle' => $value['tickettype']['TicketTypeTitle'],
              'TicketTypeDescription' => $value['tickettype']['TicketTypeDescription'],
              'PriorityId' => $value['priortytype']['PriorityId'],
              'PriorityName' => $value['priortytype']['PriorityName'],
              'TicketStatus' => $TicketStatus
            ];
            array_push($payment_request, $var1);
          }

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Show Affiliate Tickets.',
            "TotalCount" => $support1->count(),
            'Data' => ['TicketData' => $payment_request]
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
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

  public function AdminShowTicketMessages(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $SupportTicket = SupportTicket::where('TicketId', $request->TicketId)->first();
          if ($SupportTicket) {
            $SupportMessages = SupportManager::where('TicketId', $request->TicketId)->get();
            if ($request->Timezone == "")
              $TimeZoneOffSet = 0;
            else
              $TimeZoneOffSet = $request->Timezone;

            $payment_request1 = [];
            foreach ($SupportMessages as $value) {

              if ($value['TicketStatus'] == 0)
                $TicketStatus = 'Open';
              else if ($value['TicketStatus'] == 1)
                $TicketStatus = 'Closed';
              $d1 = date("m/d/Y  h:i:s", strtotime($value['CreatedAt']));
              $d2 = date('m/d/Y h:i:s', time());

              $s1 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $d1)));
              $s2 = date("d-m-Y  h:i:s", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $d2)));

              if (date("d-m-Y", strtotime($s2)) == date("d-m-Y", strtotime($s1))) {
                $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $value['CreatedAt'])));
                $concate = $dd1 . ' | Today';
              } else if (date('d-m-Y', strtotime("-1 day", strtotime($s2))) == date("d-m-Y", strtotime($s1))) {
                $dd1 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $value['CreatedAt'])));
                $concate = $dd1 . ' | Yesterday';
              } else {
                $dd1 = date("F d", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $value['CreatedAt'])));
                $dd2 = date("h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $value['CreatedAt'])));
                $concate = $dd1 . ' | ' . $dd2;
              }
              $var1 = [
                'TicketId' => $value['TicketId'],
                'FromUserId' => $value['FromUserId'],
                'ToUserId' => $value['ToUserId'],
                //  'Name'=> $User==""?null:$User['FirstName']." ".$User['LastName'],
                'Message' => $value['Message'],
                'IsRead' => $value['IsRead'],
                'DateTime' => $concate,
              ];
              array_push($payment_request1, $var1);
            }
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Show Tickets Messages.',
              "TotalCount" =>  $SupportMessages->count(),
              'Data' => ['TicketData' => $payment_request1]
            ], 200);
          } else {
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Ticket not found.',
              "TotalCount" =>  0,
              'Data' => ['TicketData' => []]
            ], 200);
          }
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Token not found.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
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

  public function SendMessageByAdmin(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $validator = Validator::make($request->all(), [
            'TicketId' => 'required',
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
          $new_tik = SupportTicket::where('TicketId', $request->TicketId)->first();
          $TicketId = $request->TicketId;
          $Message = $request->Message;
          $ToUserId = $new_tik->UserId;
          $send_create = SupportManager::create([
            'TicketId' => $TicketId,
            'FromUserId' => 1,
            'ToUserId' => $ToUserId,
            'Message' => $Message,
            'IsRead' => 0,
          ]);
          SupportTicket::where('TicketId', $TicketId)->update(['UpdatedAt' => Carbon::now()]);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Send messages to affiliate successfully.',
            "TotalCount" =>  $send_create->count(),
            'Data' => ['SendData' => $send_create]
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
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

        $log_user = User::where('UserId', $UserId)->first();

        if ($log_user->RoleId == 1) {


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
            'Message' => 'You are not admin.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
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

  public function AdminSupportCount(Request $request)
  {
    try {
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));
      if ($UserId) {
        $log_user = User::where('UserId', $UserId)->first();
        if ($log_user->RoleId == 1) {
          $total_count = SupportManager::where('ToUserId', $UserId)->where('IsRead', 0)->count();
          return response()->json([
            'IsSuccess' => true,
            'Message' => ' Admin total support count.',
            "TotalCount" =>  1,
            'Data' => ['SupportCount' => $total_count]
          ], 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
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
