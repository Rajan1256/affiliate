<?php

namespace App\Http\Controllers\User;
use Illuminate\Http\Request;
use Validator;
use App\UserBalance;
use App\UserPayment;
use App\UserPaymentRequest;
use App\PaymentType;
use App\User;
use App\UserBankDetail;
use App\UserToken; 
use App\CurrencyRate; 
use App\Lead; 
use App\CurrencyConvert;
use Laravel\Lumen\Routing\Controller as BaseController;

class PaymentController extends BaseController
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

  public function AddAffiliatePaymentRequest(Request $request)
  { 
      try{ 
          $check = new UserToken();
          $UserId = $check->validToken($request->header('Token'));

          if($UserId) {
              $validator = Validator::make($request->all(), [
                  'RequestAmount' => 'required',
                  'PaymentTypeId' => 'required',
                  'CurrencyId' => 'required',
              ]);
              if ($validator->fails()) {
                  return response()->json([
                      'IsSuccess' => false,
                      'Message' => 'Some field are required.',
                      "TotalCount" => count($validator->errors()),
                      "Data" => array('Error' => $validator->errors())
                  ], 200);
              }
              $UserBalance = UserBalance::where('UserId', $UserId)->first();
              $UserDetail = User::find($UserId);

              // $USDConvert = CurrencyConvert::find(1);
              // $AUDConvert = CurrencyConvert::find(2);
              // $EURConvert = CurrencyConvert::find(3);
              $ToDay = date('Y-m-d H:i:s');
              $CurrencyRate = CurrencyRate::where('Status',1)->whereDate('Date', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
              if($CurrencyRate){
              $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
              }
              else{
              $CurrencyRate = CurrencyRate::where('Status',1)->whereDate('Date', '<', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
              $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
              } 

              if($UserDetail->CurrencyId == 1){
                  $TotalRevenue = $UserBalance->USDTotalRevenue;
                  $Paid = $UserBalance->USDPaid;
                  $OutstandingRevenue = $UserBalance->USDOutstandingRevenue;
                  $TotalDuePayment = $UserBalance->USDTotalDuepayment;
              } else if($UserDetail->CurrencyId == 2){
                  $TotalRevenue = $UserBalance->AUDTotalRevenue;
                  $Paid = $UserBalance->AUDPaid;
                  $OutstandingRevenue = $UserBalance->AUDOutstandingRevenue;
                  $TotalDuePayment = $UserBalance->AUDTotalDuepayment;                    
              } else if($UserDetail->CurrencyId == 3){
                  $TotalRevenue = $UserBalance->EURTotalRevenue;
                  $Paid = $UserBalance->EURPaid;
                  $OutstandingRevenue = $UserBalance->EUROutstandingRevenue;
                  $TotalDuePayment = $UserBalance->EURTotalDuepayment;                    
              }

              if($UserId == $UserBalance->UserId)
              {
                  if($request->RequestAmount > $OutstandingRevenue){
                      return response()->json([
                          'IsSuccess' => false,
                          'Message' => 'Request amount is grater than outstanding revenue.',
                          "TotalCount" => 0,
                          'Data' => [ 'RequestData' => [] ]
                      ], 200);
                  }
                  $PaymentMethod = $request->PaymentTypeId;
                  $RequestAmount = $request->RequestAmount;
                  $CurrencyId = $request->CurrencyId; 

                  $request_create = UserPaymentRequest::create([
                      'UserId' => $UserId,
                      'PaymentTypeId' => $PaymentMethod,
                      'RequestAmount' => $RequestAmount,
                      'CurrencyId' => $CurrencyId,
                      'RemainingBalance' => $OutstandingRevenue,
                      'TotalDuePayment' => $TotalDuePayment,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                  ]);

                  if($UserDetail->CurrencyId == 1){
                      $UserBalance->USDTotalDuepayment = $UserBalance->USDTotalDuepayment+$RequestAmount;
                      $UserBalance->AUDTotalDuepayment = $UserBalance->AUDTotalDuepayment+$RequestAmount*$CurrencyConvert->USDAUD; 
                      $UserBalance->EURTotalDuepayment = $UserBalance->EURTotalDuepayment+$RequestAmount*$CurrencyConvert->USDEUR;
                  } else if($UserDetail->CurrencyId == 2){
                      $UserBalance->AUDTotalDuepayment = $UserBalance->AUDTotalDuepayment+$RequestAmount; 
                      $UserBalance->USDTotalDuepayment = $UserBalance->USDTotalDuepayment+$RequestAmount*$CurrencyConvert->AUDUSD;
                      $UserBalance->EURTotalDuepayment = $UserBalance->EURTotalDuepayment+$RequestAmount*$CurrencyConvert->AUDEUR;
                  } else if($UserDetail->CurrencyId == 3){
                      $UserBalance->EURTotalDuepayment = $UserBalance->EURTotalDuepayment+$RequestAmount;
                      $UserBalance->USDTotalDuepayment = $UserBalance->USDTotalDuepayment+$RequestAmount*$CurrencyConvert->EURUSD;
                      $UserBalance->AUDTotalDuepayment = $UserBalance->AUDTotalDuepayment+$RequestAmount*$CurrencyConvert->EURAUD;
                  }
                  $UserBalance->save();

                  return response()->json([
                      'IsSuccess' => true,
                      'Message' => 'Payment request added successfully.',
                      "TotalCount" => 1,
                      'Data' => [ 'RequestData' => $request_create]
                  ], 200);
              }
              else
              {
                  return response()->json([
                      'IsSuccess' => false,
                      'Message' => 'No revenue found for this user.',
                      "TotalCount" => 0,
                      'Data' => []
                  ], 200);
              }
          }else{                
              return response()->json([
                  'IsSuccess' => false,
                  'Message' => 'Invalid token.',
                  "TotalCount" => 0,
                  'Data' => []
              ], 200);
          }
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

  public function ShowAffiliatePaymentRequest(Request $request)
  { 
      try{
          $check = new UserToken();
          $UserId = $check->validToken($request->header('Token'));

          if($UserId)
          {
              $UserBalance = UserBalance::where('UserId',$UserId)->count();
              $UserDetail = User::find($UserId);
              if($UserBalance==0){
                  $array1 = [
                      'TotalRevenue'=>0,
                      'Paid'=>0,
                      'OutstandingRevenue'=>0,
                      'TotalDuepayment'=>0
                  ];

                  $res = [
                      'IsSuccess' => false,
                      'Message' => 'No revenue found.',
                      'TotalCount' => 0,
                      'Data' => array('AffiliateBalance'=>$array1, 'AffiliatePaymentData'=>0)
                  ];
                  return response()->json($res,200);
              }

              $all_request = UserPaymentRequest::with('userpayment')->where('UserId',$UserId)->orderBy('UserPaymentRequestId', 'desc')->get();
              $balance = UserPaymentRequest::where('UserId',$UserId)->sum('RequestAmount');
              $without_request = UserPayment::where('UserId',$UserId)->where('UserPaymentRequestId',NULL)->get();
              $user_revenue = UserBalance::where('UserId',$UserId)->first();
              if($UserDetail->CurrencyId == 1){
                  $TotalRevenue = $user_revenue->USDTotalRevenue;
                  $Paid = $user_revenue->USDPaid;
                  $OutstandingRevenue = $user_revenue->USDOutstandingRevenue;
                  $DueAmount = $user_revenue->USDTotalDuepayment;
              }else if($UserDetail->CurrencyId == 2){
                  $TotalRevenue = $user_revenue->AUDTotalRevenue;
                  $Paid = $user_revenue->AUDPaid;
                  $OutstandingRevenue = $user_revenue->AUDOutstandingRevenue;
                  $DueAmount = $user_revenue->AUDTotalDuepayment;
              }else if($UserDetail->CurrencyId == 3){
                  $TotalRevenue = $user_revenue->EURTotalRevenue;
                  $Paid = $user_revenue->EURPaid;
                  $OutstandingRevenue = $user_revenue->EUROutstandingRevenue;
                  $DueAmount = $user_revenue->EURTotalDuepayment;
              }
              $totalRequest = UserPaymentRequest::where(['UserId' =>$UserId, 'PaymentStatus' => 0])->count(); 
              if($totalRequest > 0){
                  $RequestAmount = UserPaymentRequest::where(['UserId' =>$UserId, 'PaymentStatus' => 0])->sum('RequestAmount');
                  if($OutstandingRevenue==0)
                      $DueAmount = 0;
                      else        
                  $DueAmount = $OutstandingRevenue-$RequestAmount;
              } else{
                  $DueAmount = $OutstandingRevenue;
              }

              $TimeZoneOffSet = $request->Timezone;
              if($TimeZoneOffSet=="")
                  $TimeZoneOffSet = 0;

              $payment_request1 = [];
              foreach ($all_request as $value) {
                  if($value['userpayment']['Attachment'] && $value['userpayment']['Attachment'] !='')
                      $Attachment = $this->storage_path.'app/payment/'.$value['userpayment']['Attachment'];
                  else
                      $Attachment = '';
                  $pay = PaymentType::where('PaymentTypeId',$value['PaymentTypeId'])->first();

                  if($value['PaymentStatus']==0)
                      $status='Request';
                  else if($value['PaymentStatus']==2)
                      $status='Received';
                  else if($value['PaymentStatus']==3)
                      $status='Declined';
                  $var1 = [
                      'Attachment' => $Attachment,
                      'RequestedAmount' => $value['RequestAmount'],
                      'CurrencyId'=>$value['userpayment']['CurrencyId'],
                      'RequestedDate' =>  date("d/m/Y H:i A",strtotime($TimeZoneOffSet." minutes", strtotime($value['CreatedAt']))),
                      'PaidAmount' => $value['userpayment']['PaymentAmount'],
                      'DueAmount' => round($value['RequestAmount']-$value['userpayment']['PaymentAmount'],4),
                      'PaidDate' => $value['userpayment']['CreatedAt']==""?"": date("d/m/Y H:i A",strtotime($TimeZoneOffSet." minutes", strtotime((string)$value['userpayment']['CreatedAt']))),
                      'PaymentMethod'=>$pay->PaymentTypeName,
                      'ActualPaymentDate'=> $value['userpayment']['DateOfPayment']==""?"":date("d/m/Y",strtotime($TimeZoneOffSet." minutes", strtotime((string)$value['userpayment']['DateOfPayment']))),
                      'Status' => $status,
                  ];
                  array_push($payment_request1, $var1);
              }
              $payment_request2 = [];
              foreach ($without_request as  $val) { 
                  $pay2 = PaymentType::where('PaymentTypeId',$val['PaymentTypeId'])->first();
                  if($val['Attachment'] && $val['Attachment'] !='')
                      $Attachment = $this->storage_path.'app/payment/'.$val['Attachment'];
                  else
                      $Attachment = '';
                  $var2 = [
                      'Attachment' => $Attachment,
                      'RequestedAmount' => '-',
                      'RequestedDate' => '-',
                      'CurrencyId'=>$val['CurrencyId'],
                      'PaidAmount' => $val['PaymentAmount'],
                      'PaidDate' => date("d/m/Y H:i A",strtotime($TimeZoneOffSet." minutes",strtotime($val['CreatedAt']))),
                      'PaymentMethod'=>$pay2->PaymentTypeName,
                      'ActualPaymentDate'=> $val['DateOfPayment']==""?"":date("d/m/Y",strtotime($TimeZoneOffSet." minutes", strtotime((string)$val['DateOfPayment']))),
                      'Status' => 'Received',
                  ];
                  array_push($payment_request2, $var2);
              }
              $combine_payment = array_merge($payment_request1,$payment_request2); 
              $array1 = [
                  'TotalRevenue'=> round($TotalRevenue, 2),
                  'Paid'=> round($Paid, 2),
                  'OutstandingRevenue'=> round($OutstandingRevenue,2), 
                  'TotalDuepayment'=> round($DueAmount,2)
              ]; 
              $res = [
                  'IsSuccess'=>true,
                  'Message'=>'List of affiliate request.',
                  'TotalCount' => count($combine_payment),
                  'Data' => array('AffiliateBalance'=>$array1, 'AffiliatePaymentData'=>$combine_payment)
              ];
          }
          else {
              $res = [
                  'IsSuccess'=>true,
                  'Message'=>'No request found',
                  'TotalCount' => 0,
                  'Data' => null
              ];
          }
          return response()->json($res,200);
      }
      catch(exception $e)
      {
          $res = [
              'IsSuccess'=>false,
              'Message'=>$e,
              'TotalCount' => 0,
              'Data' => null
          ];
          return response()->json($res,200);
      }
  }

  /*public function ShowAffiliateRevenue(Request $request)
  { 
    try{
        $check = new UserToken();
        $UserId = $check->validToken($request->header('Token'));

        if($UserId)
        {
            $UserBalance = UserBalance::where('UserId',$UserId)->count();
            $UserDetail = User::find($UserId);
            if($UserBalance==0){
                $array1 = [
                    'TotalRevenue'=>0,
                    'Paid'=>0,
                    'OutstandingRevenue'=>0,
                    'TotalDuepayment'=>0
                ];
                $res = [
                    'IsSuccess' => false,
                    'Message' => 'No revenue found.',
                    'TotalCount' => 0,
                    'Data' => array('AffiliateBalance'=>$array1, 'AffiliatePaymentData'=>0)
                ];
                return response()->json($res,200);
            } 
            $balance = UserPaymentRequest::where('UserId',$UserId)->sum('RequestAmount');
            $user_revenue = UserBalance::where('UserId',$UserId)->first();
            if($UserDetail->CurrencyId == 1){
                $TotalRevenue = $user_revenue->USDTotalRevenue;
                $Paid = $user_revenue->USDPaid;
                $OutstandingRevenue = $user_revenue->USDOutstandingRevenue;
                $DueAmount = $user_revenue->USDTotalDuepayment;
            }else if($UserDetail->CurrencyId == 2){
                $TotalRevenue = $user_revenue->AUDTotalRevenue;
                $Paid = $user_revenue->AUDPaid;
                $OutstandingRevenue = $user_revenue->AUDOutstandingRevenue;
                $DueAmount = $user_revenue->AUDTotalDuepayment;
            }else if($UserDetail->CurrencyId == 3){
                $TotalRevenue = $user_revenue->EURTotalRevenue;
                $Paid = $user_revenue->EURPaid;
                $OutstandingRevenue = $user_revenue->EUROutstandingRevenue;
                $DueAmount = $user_revenue->EURTotalDuepayment;
            }
            $totalRequest = UserPaymentRequest::where(['UserId' =>$UserId, 'PaymentStatus' => 0])->count(); 
            if($totalRequest > 0){
                $RequestAmount = UserPaymentRequest::where(['UserId' =>$UserId, 'PaymentStatus' => 0])->sum('RequestAmount');
                if($OutstandingRevenue==0)
                    $DueAmount = 0;
                    else        
                $DueAmount = $OutstandingRevenue-$RequestAmount;
            } else{
                $DueAmount = $OutstandingRevenue;
            }

            $TimeZoneOffSet = $request->Timezone;
            if($TimeZoneOffSet=="")
                $TimeZoneOffSet = 0;

            $array1 = [
                'TotalRevenue'=> round($TotalRevenue, 2),
                'Paid'=> round($Paid, 2),
                'OutstandingRevenue'=> round($OutstandingRevenue,2), 
                'TotalDuepayment'=> round($DueAmount,2)
            ]; 
            $res = [
                'IsSuccess' => true,
                'Message' => 'Affiliate revenue.',
                'TotalCount' => 0,
                'Data' => array(
                  'AffiliateBalance'=>$array1,
                )
            ];
        }
        else {
            $res = [
                'IsSuccess'=>true,
                'Message'=>'No request found',
                'TotalCount' => 0,
                'Data' => null
            ];
        }
        return response()->json($res,200);
    }
    catch(exception $e)
    {
        $res = [
            'IsSuccess'=>false,
            'Message'=>$e,
            'TotalCount' => 0,
            'Data' => null
        ];
        return response()->json($res,200);
    }
  }*/

  public function GetAffiliateBalanceDetail(Request $request)
  { 
    try{
      $check = new UserToken();
      $UserId = $check->validToken($request->header('Token'));

      if($UserId)
      {
        $UserDetails = User::find($UserId); 
        $user_revenue = UserBalance::with('user.Currency')->where('UserId',$UserId)->first();
        $bank_detail = UserBankDetail::with('payment')->where('UserId',$UserId)->first();

        if($bank_detail!=null) {
          $PaymentTypeId = $bank_detail['payment']['PaymentTypeId'];
          $PaymentName = $bank_detail['payment']['PaymentTypeName'];
        }
        else {
          $PaymentTypeId = null;
          $PaymentName = null;
        }
        if($user_revenue){   
          if($UserDetails->CurrencyId == 1){
              $TotalRevenue = $user_revenue->USDTotalRevenue;
              $Paid = $user_revenue->USDPaid;
              $OutstandingRevenue = $user_revenue->USDOutstandingRevenue;
          }else if($UserDetails->CurrencyId == 2){
              $TotalRevenue = $user_revenue->AUDTotalRevenue;
              $Paid = $user_revenue->AUDPaid;
              $OutstandingRevenue = $user_revenue->AUDOutstandingRevenue;
          }else if($UserDetails->CurrencyId == 3){
              $TotalRevenue = $user_revenue->EURTotalRevenue;
              $Paid = $user_revenue->EURPaid;
              $OutstandingRevenue = $user_revenue->EUROutstandingRevenue;
          }
          $totalRequest = UserPaymentRequest::where(['UserId' =>$UserId, 'PaymentStatus' => 0])->count();
          if($totalRequest > 0){
            $RequestAmount = UserPaymentRequest::where(['UserId' =>$UserId, 'PaymentStatus' => 0])->sum('RequestAmount');
            if($OutstandingRevenue==0)
              $DueAmount = 0;
            else
              $DueAmount = $OutstandingRevenue-$RequestAmount;
          }
          else{
            $DueAmount = $OutstandingRevenue;
          }
          $Leads = Lead::where('UserId', $UserId)->count();
          $Accounts = Lead::where('UserId', $UserId)->where('IsConverted', 1)->count();
          $array1 = [
            'TotalRevenue' => round($TotalRevenue, 2),
            'Paid' => round($Paid, 2),
            'OutstandingRevenue' => round($OutstandingRevenue, 2),
            'TotalDuepayment' => round($DueAmount, 2),
            'CurrencyId'=>$user_revenue['user']['currency']['CurrencyId'],
            'CurrencyName'=>$user_revenue['user']['currency']['CurrencyCode'],
            'PaymentTypeId'=>$PaymentTypeId,
            'PaymentName'=>$PaymentName,
            'Leads'=>$Leads,
            'Accounts'=>$Accounts,
          ];
        }else{
          $array1 = [
            'TotalRevenue'=>0,
            'Paid'=>0,
            'OutstandingRevenue'=>0,
            'TotalDuepayment'=>0,
            'CurrencyId'=>"",
            'CurrencyName'=>"",
            'PaymentTypeId'=>"",
            'PaymentName'=>""
          ]; 
        }
        $both = UserBankDetail::whereNotNull('BankName')->whereNotNull('AccountBeneficiary')
            ->whereNotNull('AccountNumber')->whereNotNull('BankBranch')
            ->whereNotNull('BankCity')
            ->whereNotNull('CountryId')
            ->whereNotNull('SwiftCode')->whereNotNull('IBANNumber')
            ->whereNotNull('ABANumber')->whereNotNull('BankCorrespondent')
            ->whereNotNull('VATNumber')->whereNotNull('MT4LoginNumber')->where('UserId',$UserId)->get();

        $Bank_Wire = UserBankDetail::whereNotNull('BankName')->whereNotNull('AccountBeneficiary')
            ->whereNotNull('AccountNumber')->whereNotNull('BankBranch')
            ->whereNotNull('BankCity')
            ->whereNotNull('CountryId')
            ->whereNotNull('SwiftCode')->whereNotNull('IBANNumber')
            ->whereNotNull('ABANumber')->whereNotNull('BankCorrespondent')
            ->whereNotNull('VATNumber')->where('UserId',$UserId)->get();

        $Account_deposite = UserBankDetail::whereNotNull('MT4LoginNumber')
            ->whereNotNull('VATNumber')->where('UserId',$UserId)->get();

        if($both->count()>=1)
        {
          $PaymentType = PaymentType::orderBy('PaymentTypeName')->get();
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Payment Type List',
            "TotalCount" => $PaymentType->count(),
            "Data" => array('AffiliateBalance'=>$array1,'PaymentType' => $PaymentType)
          ], 200);
        }

        else if($Bank_Wire->count()>=1)
        {
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Payment Type List',
            "TotalCount" => 1,
            "Data" => array(
              'AffiliateBalance'=>$array1,
              'PaymentType' => array([
                  'PaymentTypeId'=>1,
                  'PaymentTypeName'=>"Bank wire",
                  'PaymentTypeDescription'=>"",
                  'IsActive'=>1
              ])
            )
          ], 200);
        }
        else if($Account_deposite->count()>=1) {
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Payment Type List',
            "TotalCount" => 1,
            "Data" => array('AffiliateBalance'=>$array1,'PaymentType' => array([
              'PaymentTypeId'=>2,
              'PaymentTypeName'=>"Account deposit",
              'PaymentTypeDescription'=>"",
              'IsActive'=>1
            ]))
          ], 200);
        }
        else {
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Payment Type List',
            "TotalCount" => 0,
            'Data' => array(
              'AffiliateBalance'=>$array1,
              'PaymentType' => []
            )
          ], 200);
        }
      }
      else {
        $res = [
          'IsSuccess'=>true,
          'Message'=>'No request found',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res,200);
      }
    }
    catch(exception $e)
    {
        $res = [
            'IsSuccess'=>false,
            'Message'=>$e,
            'TotalCount' => 0,
            'Data' => null
        ];
        return response()->json($res,200);
    }
  }

  /*public function GetAffiliateBalanceList(Request $request)
  { 
      return $request->all();
      try {
          $check = new UserToken();
          $log_user = $check->validTokenAdmin($request->header('token'));
          if ($log_user) {
              if ($log_user->RoleId == 1) {
                  $affiliate_user = UserBalance::with('user.Currency')->get();
                  $alluser = [];
                  foreach ($affiliate_user as  $value) {
                      $var = [
                          'UserId' => $value['UserId'],
                          'AffiliateName' => $value['user']['FirstName'].' '.$value['user']['LastName'],
                          'EmailId' => $value['user']['EmailId'],
                          'Currency' => $value['user']['currency']['CurrencyCode'],
                          'RoyalRevenue'=>0,
                          'AffiliateRevenue'=>$value['TotalRevenue'],
                          'Paid' => $value['Paid'],
                          'OutstandingRevenue'=>$value['OutstandingRevenue'],
                      ];
                      array_push($alluser, $var);
                  }
                  return response()->json([
                      'IsSuccess' => false,
                      'Message' => 'Get affiliate royal balance.',
                      "TotalCount" => $affiliate_user->count(),
                      'Data' => ['AffiliateUser'=>$alluser]
                  ], 200);
                  return response()->json($res,200);
              }
              else
              {
                  return response()->json([
                      'IsSuccess' => false,
                      'Message' => 'You are not admin.',
                      "TotalCount" => 0,
                      'Data' => []
                  ], 200);
                  return response()->json($res,200);
              }
          }
          else {
              return response()->json([
                  'IsSuccess' => false,
                  'Message' => 'Invalid Token.',
                  "TotalCount" => 0,
                  'Data' => []
              ], 200);
              return response()->json($res,200);
          }
      }
      catch(exception $e)
      {
          $res = [
              'IsSuccess'=>false,
              'Message'=>$e,
              'TotalCount' => 0,
              'Data' => null
          ];
          return response()->json($res,200);
      }
  }*/ 

}