<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Validator;
use DateTime;
use App\RevenueType;
use App\RevenueModel;
use App\RevenueModelLog;
use App\RevenueCpaPlan;
use App\RevenueCpaPlanLog;
use App\RevenueCpaCountry;
use App\RevenueCpaCountryLog;
use App\RevenueCpaTrader;
use App\RevenueCpaTraderLog;
use App\UserRevenuePayment;
use App\User;
use App\Lead;
use App\LeadActivity;
use App\UserBalance;
use App\UserToken;
use App\CountryMaster;
use App\RevenueBonusUser;
use App\UserBonus;
use App\UserRevenueType;
use App\CurrencyConvert;
use App\CurrencyRate;
use App\UserRevenue;
use Laravel\Lumen\Routing\Controller as BaseController;

class RevenueController extends BaseController
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
    $this->revenue_auto_approve = env('REVENUE_AUTO_APPROVE', true);
    $this->storage_path = getenv('STORAGE_URL');
  }

  public function AddRevenueOptionModel(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));

      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $validator = validator::make($request->all(), [
            'RevenueModelName' => 'required',
            'RevenueTypeId' => 'required',
          ]);
          if ($validator->fails()) {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Something went wrong.',
              "TotalCount" => count($validator->errors()),
              "Data" => array('Error' => $validator->errors())
            ], 200);
          }
          $ToDay = date('Y-m-d');
          $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
          if ($CurrencyRate) {
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          } else {
            $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          }

          // CPL And Conditional-CPL 
          if ($request->RevenueTypeId == 1 || $request->RevenueTypeId == 2) {
            if ($request->CurrencyId == 1) {
              $USDAmount = $request->Amount;
              $AUDAmount = $request->Amount * $CurrencyConvert->USDAUD;
              $EURAmount = $request->Amount * $CurrencyConvert->USDEUR;
            } else if ($request->CurrencyId == 2) {
              $AUDAmount = $request->Amount;
              $USDAmount = $request->Amount * $CurrencyConvert->AUDUSD;
              $EURAmount = $request->Amount * $CurrencyConvert->AUDEUR;
            } else if ($request->CurrencyId == 3) {
              $EURAmount = $request->Amount;
              $AUDAmount = $request->Amount * $CurrencyConvert->EURAUD;
              $USDAmount = $request->Amount * $CurrencyConvert->EURUSD;
            }
            $RevenueModel = RevenueModel::create([
              'RevenueModelName' => $request->RevenueModelName,
              'RevenueTypeId' => $request->RevenueTypeId,
              'CurrencyId' => $request->CurrencyId,
              'Amount' => $request->Amount,
              'USDAmount' => $USDAmount,
              'AUDAmount' => $AUDAmount,
              'EURAmount' => $EURAmount,
              'Comment' => $request->Comment,
              'CreatedBy' => $log_user->UserId,
              'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
            ]);
            // create log table. use at time of revenue generate ds
            RevenueModelLog::create($RevenueModel->toArray());
          }
          // CPA And Conditional-CPA 
          if ($request->RevenueTypeId == 3 || $request->RevenueTypeId == 4) {
            if ($request->RevenueTypeId == 4) {
              $TradeType = $request->TradeType;
              $TradeValue = $request->TradeValue;
            } else {
              $TradeType = NULL;
              $TradeValue = NULL;
            }
            $RevenueModel = RevenueModel::create([
              'RevenueModelName' => $request->RevenueModelName,
              'RevenueTypeId' => $request->RevenueTypeId,
              'CurrencyId' => $request->CurrencyId,
              'TradeType' => $TradeType,
              'TradeValue' => $TradeValue,
              'Comment' => $request->Comment,
              'CreatedBy' => $log_user->UserId,
              'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
            ]);
            $RevenueModelId = $RevenueModel->RevenueModelId;
            // create log table. use at time of revenue generate
            $RevenueModelLog = RevenueModelLog::create($RevenueModel->toArray());

            foreach ($request->RevenueCpaPlans['CountrySelect'] as $value) {
              $RevenueCpaCountry = RevenueCpaCountry::create([
                'RevenueModelId' => $RevenueModelId,
                'RevenueCountrys' => $value['SelectedCountryList'],
              ]);
              $RevenueCpaCountryLog = RevenueCpaCountryLog::create([
                'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                'RevenueCountrys' => $value['SelectedCountryList'],
              ]);
              foreach ($value['CountryRangeSelect'] as $value) {
                if ($request->CurrencyId == 1) {
                  $USDAmount = $value['Value'];
                  $AUDAmount = $value['Value'] * $CurrencyConvert->USDAUD;
                  $EURAmount = $value['Value'] * $CurrencyConvert->USDEUR;
                } else if ($request->CurrencyId == 2) {
                  $USDAmount = $value['Value'] * $CurrencyConvert->AUDUSD;
                  $AUDAmount = $value['Value'];
                  $EURAmount = $value['Value'] * $CurrencyConvert->AUDEUR;
                } else if ($request->CurrencyId == 3) {
                  $USDAmount = $value['Value'] * $CurrencyConvert->EURUSD;
                  $AUDAmount = $value['Value'] * $CurrencyConvert->EURAUD;
                  $EURAmount = $value['Value'];
                }
                if ($value['Operation'] == '-') {
                  $Operation = 1;
                } else if ($value['Operation'] == '<') {
                  $Operation = 2;
                } else if ($value['Operation'] == '>') {
                  $Operation = 3;
                }
                $RevenueCpaTrader = RevenueCpaTrader::create([
                  'RevenueModelId' => $RevenueModelId,
                  'RevenueCpaCountryId' => $RevenueCpaCountry->RevenueCpaCountryId,
                  'RangeFrom' => $value['StartRange'],
                  'RangeExpression' => $Operation,
                  'RangeTo' => $value['EndRange']
                ]);
                $RevenueCpaTraderLog = RevenueCpaTraderLog::create([
                  'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                  'RevenueCpaCountryLogId' => $RevenueCpaCountryLog->RevenueCpaCountryLogId,
                  'RangeFrom' => $value['StartRange'],
                  'RangeExpression' => $Operation,
                  'RangeTo' => $value['EndRange']
                ]);

                RevenueCpaPlan::create([
                  'RevenueModelId' => $RevenueModelId,
                  'RevenueCpaCountryId' => $RevenueCpaCountry->RevenueCpaCountryId,
                  'RevenueCpaTraderId' => $RevenueCpaTrader->RevenueCpaTraderId,
                  'Amount' => $value['Value'],
                  'USDAmount' => $USDAmount,
                  'AUDAmount' => $AUDAmount,
                  'EURAmount' => $EURAmount,
                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                ]);
                RevenueCpaPlanLog::create([
                  'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                  'RevenueCpaCountryLogId' => $RevenueCpaCountryLog->RevenueCpaCountryLogId,
                  'RevenueCpaTraderLogId' => $RevenueCpaTraderLog->RevenueCpaTraderLogId,
                  'Amount' => $value['Value'],
                  'USDAmount' => $USDAmount,
                  'AUDAmount' => $AUDAmount,
                  'EURAmount' => $EURAmount,
                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                ]);
              }
            }
          }
          // Revenue Share
          if ($request->RevenueTypeId == 5) {
            $RevenueModel = RevenueModel::create([
              'RevenueModelName' => $request->RevenueModelName,
              'RevenueTypeId' => $request->RevenueTypeId,
              'Percentage' => $request->Percentage,
              'Comment' => $request->Comment,
              'CreatedBy' => $log_user->UserId,
              'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
            ]);
            // create log table. use at time of revenue generate
            RevenueModelLog::create($RevenueModel->toArray());
          }
          // FX Revenue Share
          if ($request->RevenueTypeId == 6) {
            $RevenueModel = RevenueModel::create([
              'RevenueModelName' => $request->RevenueModelName,
              'RevenueTypeId' => $request->RevenueTypeId,
              'CurrencyId' => $request->CurrencyId,
              'Amount' => $request->Amount,
              'Rebate' => $request->Rebate,
              'ReferenceDeal' => $request->ReferenceDeal,
              'Comment' => $request->Comment,
              'CreatedBy' => $log_user->UserId,
              'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
            ]);
            // create log table. use at time of revenue generate
            RevenueModelLog::create($RevenueModel->toArray());
          }
          // Bonus Revenue
          if ($request->RevenueTypeId == 7) {
            if ($request->CurrencyId == 1) {
              $USDAmount = $request->Amount;
              $AUDAmount = $request->Amount * $CurrencyConvert->USDAUD;
              $EURAmount = $request->Amount * $CurrencyConvert->USDEUR;
            } else if ($request->CurrencyId == 2) {
              $USDAmount = $request->Amount * $CurrencyConvert->AUDUSD;
              $AUDAmount = $request->Amount;
              $EURAmount = $request->Amount * $CurrencyConvert->AUDEUR;
            } else if ($request->CurrencyId == 3) {
              $USDAmount = $request->Amount * $CurrencyConvert->EURUSD;
              $AUDAmount = $request->Amount * $CurrencyConvert->EURAUD;
              $EURAmount = $request->Amount;
            }
            // TradeType
            if ($request->TradeType == 1) {
              $TotalAccTradeVol = $request->BonusConditionValue;
              $TotalIntroducedAcc = null;
            } else {
              $TotalAccTradeVol = null;
              $TotalIntroducedAcc = $request->BonusConditionValue;
            }
            $RevenueModel = RevenueModel::create([
              'RevenueTypeId' => $request->RevenueTypeId,
              'RevenueModelName' => $request->RevenueModelName,
              'TradeType' => $request->TradeType,
              'CurrencyId' => $request->CurrencyId,
              'Amount' => $request->Amount,
              'USDAmount' => $USDAmount,
              'AUDAmount' => $AUDAmount,
              'EURAmount' => $EURAmount,
              'Schedule' => $request->Schedule,
              'TotalAccTradeVol' => $TotalAccTradeVol,
              'TotalIntroducedAcc' => $TotalIntroducedAcc,
              'Comment' => $request->Comment,
              'CreatedBy' => $log_user->UserId,
              'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
            ]);
            // create log table. use at time of revenue generate
            RevenueModelLog::create($RevenueModel->toArray());
          }
          // Sub-Affiliate
          if ($request->RevenueTypeId == 8) {
            $RevenueModel = RevenueModel::create([
              'RevenueTypeId' => $request->RevenueTypeId,
              'RevenueModelName' => $request->RevenueModelName,
              'Percentage' => $request->Percentage,
              'Type' => $request->Type,
              'Comment' => $request->Comment,
              'CreatedBy' => $log_user->UserId,
              'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
            ]);
            // create log table. use at time of revenue generate
            RevenueModelLog::create($RevenueModel->toArray());
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Revenue model added successfully.',
            'TotalCount' => 0,
            'Data' => null
          ], 200);
        }
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ], 200);
    }
  }

  public function ViewRevenueOptionModel(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $validator = Validator::make($request->all(), [
          'RevenueModelId' => 'required',
        ]);
        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $RevenueModel = RevenueModel::with('Revenue', 'Currency')->find($request->RevenueModelId);
          if ($request->TimeZoneOffSet && $request->TimeZoneOffSet != '')
            $TimeZoneOffSet = $request->TimeZoneOffSet;
          else
            $TimeZoneOffSet = 0;

          if ($RevenueModel) {
            if ($RevenueModel->RevenueTypeId == 3 || $RevenueModel->RevenueTypeId == 4) {
              $RevenueModelId = $request->RevenueModelId;
              $RevenueModel = RevenueModel::with('RevenueCpaPlan', 'Revenue', 'Currency')->find($request->RevenueModelId);
              $RevenueModel->whereHas('RevenueCpaPlan', function ($qr) use ($RevenueModelId) {
                $qr->where('RevenueModelId', $RevenueModelId);
              })->get();

              $RevenueCpaCountry = RevenueCpaCountry::with('RevenueCpaTrader')->where('RevenueModelId',  $RevenueModel['RevenueModelId'])->get();

              $returnArray = [];
              foreach ($RevenueCpaCountry as $valueCountry) {
                $RevenueCpaTrader = [];
                $CountrysArr = explode(",", $valueCountry['RevenueCountrys']);
                $CountryMaster = CountryMaster::whereIn('CountryId', $CountrysArr)->get();

                foreach ($valueCountry['RevenueCpaTrader'] as $value) {
                  $RevenueCpaPlan = RevenueCpaPlan::where('RevenueCpaCountryId', $value['RevenueCpaCountryId'])->where('RevenueCpaTraderId', $value['RevenueCpaTraderId'])->first();
                  $arr = [
                    "RevenueCpaTraderId" => $value['RevenueCpaTraderId'],
                    "RevenueModelId" => $value['RevenueModelId'],
                    "RevenueCpaCountryId" => $value['RevenueCpaCountryId'],
                    "RangeFrom" => $value['RangeFrom'],
                    "RangeExpression" => $value['RangeExpression'],
                    "RangeTo" => $value['RangeTo'],
                    "Value" => $RevenueCpaPlan->Amount,
                  ];
                  array_push($RevenueCpaTrader, $arr);
                }
                $arr = [
                  "RevenueCpaCountryId" => $valueCountry['RevenueCpaCountryId'],
                  "RevenueModelId" => $valueCountry['RevenueModelId'],
                  "RevenueCountryIds" => $valueCountry['RevenueCountrys'],
                  "RevenueCountrys" => $CountryMaster,
                  "RevenueCpaTrader" => $RevenueCpaTrader
                ];
                array_push($returnArray, $arr);
              }
              $RevenueCpaTraders = RevenueCpaTrader::where('RevenueModelId', $RevenueModelId)->groupBy('RangeFrom')->get();

              // get user list assigned this model
              $selectedUsers = UserRevenueType::with('User')->where('RevenueModelId', $RevenueModel->RevenueModelId)->orderBy('UserId')->get();
              $UserArrList = [];
              foreach ($selectedUsers as $value) {
                $arr = [
                  "UserId" => $value['user']['UserId'],
                  "Name" => $value['user']['FirstName'] . ' ' . $value['user']['LastName'],
                  "EmailId" => $value['user']['EmailId'],
                  "CreatedAt" =>  date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
                ];
                array_push($UserArrList, $arr);
              }

              $RevenueModelDetails = [
                'RevenueModelId' => $RevenueModel['RevenueModelId'],
                'RevenueModelName' => $RevenueModel['RevenueModelName'],
                'RevenueTypeId' => $RevenueModel['Revenue']['RevenueTypeId'],
                'RevenueTypeName' => $RevenueModel['Revenue']['RevenueTypeName'],
                'CurrencyId' => $RevenueModel['CurrencyId'],
                'Currency' => $RevenueModel['Currency']['CurrencyCode'],
                'Amount' => $RevenueModel['Amount'],
                'Percentage' => $RevenueModel['Percentage'],
                'Rebate' => $RevenueModel['Rebate'],
                'TradeType' => $RevenueModel['TradeType'],
                'TradeValue' => $RevenueModel['TradeValue'],
                'Schedule' => $RevenueModel['Schedule'],
                'TotalAccTradeVol' => $RevenueModel['TotalAccTradeVol'],
                'TotalIntroducedAcc' => $RevenueModel['TotalIntroducedAcc'],
                'Type' => $RevenueModel['Type'],
                'IsActive' => $RevenueModel['IsActive'],
                'Comment' => $RevenueModel['Comment'],
                'RevenueCpaPlan' => $returnArray,
                'RevenueCpaTraders' => $RevenueCpaTraders,
                'UserList' => $UserArrList
              ];

              $res = [
                'IsSuccess' => true,
                'Message' => 'View revenue model.',
                'TotalCount' => $RevenueModel->count(),
                'Data' => array('RevenueModelDetails' => $RevenueModelDetails)
              ];
              return response()->json($res, 200);
            } else if ($RevenueModel->RevenueTypeId == 7) {
              // get user list assigned this model
              $selectedUsers = UserRevenueType::with('User')->where('RevenueModelId', $RevenueModel->RevenueModelId)->orderBy('UserId')->get();
              $UserArrList = [];
              foreach ($selectedUsers as $value) {
                $arr = [
                  "UserId" => $value['user']['UserId'],
                  "Name" => $value['user']['FirstName'] . ' ' . $value['user']['LastName'],
                  "EmailId" => $value['user']['EmailId'],
                  "CreatedAt" => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
                ];
                array_push($UserArrList, $arr);
              }
              $RevenueModelDetails = [
                'RevenueModelId' => $RevenueModel['RevenueModelId'],
                'RevenueModelName' => $RevenueModel['RevenueModelName'],
                'RevenueTypeId' => $RevenueModel['Revenue']['RevenueTypeId'],
                'RevenueTypeName' => $RevenueModel['Revenue']['RevenueTypeName'],
                'CurrencyId' => $RevenueModel['CurrencyId'],
                'Currency' => $RevenueModel['Currency']['CurrencyCode'],
                'Amount' => $RevenueModel['Amount'],
                'Percentage' => $RevenueModel['Percentage'],
                'Rebate' => $RevenueModel['Rebate'],
                'TradeType' => $RevenueModel['TradeType'],
                'Schedule' => $RevenueModel['Schedule'],
                'TotalAccTradeVol' => $RevenueModel['TotalAccTradeVol'],
                'TotalIntroducedAcc' => $RevenueModel['TotalIntroducedAcc'],
                'Type' => $RevenueModel['Type'],
                'IsActive' => $RevenueModel['IsActive'],
                'Comment' => $RevenueModel['Comment'],
                'UserList' => $UserArrList
              ];
              $res = [
                'IsSuccess' => true,
                'Message' => 'View revenue model.',
                'TotalCount' => $RevenueModel->count(),
                'Data' => array('RevenueModelDetails' => $RevenueModelDetails)
              ];
              return response()->json($res, 200);
            } else {
              $selectedUsers = UserRevenueType::with('User')->where('RevenueModelId', $RevenueModel->RevenueModelId)->orderBy('UserId')->get();
              $UserArrList = [];
              foreach ($selectedUsers as $value) {
                $arr = [
                  "UserId" => $value['user']['UserId'],
                  "Name" => $value['user']['FirstName'] . ' ' . $value['user']['LastName'],
                  "EmailId" => $value['user']['EmailId'],
                  "CreatedAt" => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
                ];
                array_push($UserArrList, $arr);
              }
              $RevenueModelDetails = [
                'RevenueModelId' => $RevenueModel['RevenueModelId'],
                'RevenueModelName' => $RevenueModel['RevenueModelName'],
                'RevenueTypeId' => $RevenueModel['Revenue']['RevenueTypeId'],
                'RevenueTypeName' => $RevenueModel['Revenue']['RevenueTypeName'],
                'CurrencyId' => $RevenueModel['CurrencyId'],
                'Currency' => $RevenueModel['Currency']['CurrencyCode'],
                'Amount' => $RevenueModel['Amount'],
                'Percentage' => $RevenueModel['Percentage'],
                'Rebate' => $RevenueModel['Rebate'],
                'TradeType' => $RevenueModel['TradeType'],
                'Schedule' => $RevenueModel['Schedule'],
                'TotalAccTradeVol' => $RevenueModel['TotalAccTradeVol'],
                'TotalIntroducedAcc' => $RevenueModel['TotalIntroducedAcc'],
                'Type' => $RevenueModel['Type'],
                'ReferenceDeal' => $RevenueModel['ReferenceDeal'],
                'IsActive' => $RevenueModel['IsActive'],
                'Comment' => $RevenueModel['Comment'],
                'UserList' => $UserArrList
              ];
              $res = [
                'IsSuccess' => true,
                'Message' => 'View revenue model.',
                'TotalCount' => $RevenueModel->count(),
                'Data' => array('RevenueModelDetails' => $RevenueModelDetails)
              ];
              return response()->json($res, 200);
            }
          } else {
            $res = [
              'IsSuccess' => true,
              'Message' => 'Revenue model not found.',
              'TotalCount' => 0,
              'Data' => []
            ];
            return response()->json($res, 200);
          }
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
    }
    return response()->json($res, 200);
  }

  public function ViewRevenueOptionModelV2(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $validator = Validator::make($request->all(), [
          'RevenueModelId' => 'required',
        ]);
        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }

        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $RevenueModel = RevenueModel::with('Revenue', 'Currency')->find($request->RevenueModelId);
          if ($RevenueModel) {
            if ($RevenueModel->RevenueTypeId == 3 || $RevenueModel->RevenueTypeId == 4) {
              $RevenueModelId = $request->RevenueModelId;
              $RevenueModel = RevenueModel::with('RevenueCpaPlan', 'Revenue', 'Currency')->find($request->RevenueModelId);
              $RevenueModel->whereHas('RevenueCpaPlan', function ($qr) use ($RevenueModelId) {
                $qr->where('RevenueModelId', $RevenueModelId);
              })->get();

              $RevenueCpaCountry = RevenueCpaCountry::with('RevenueCpaTrader')->where('RevenueModelId',  $RevenueModel['RevenueModelId'])->get();

              $returnArray = [];
              foreach ($RevenueCpaCountry as $valueCountry) {
                $RevenueCpaTrader = [];
                $CountrysArr = explode(",", $valueCountry['RevenueCountrys']);
                $CountryMaster = CountryMaster::whereIn('CountryId', $CountrysArr)->get();
                foreach ($valueCountry['RevenueCpaTrader'] as $value) {
                  $RevenueCpaPlan = RevenueCpaPlan::where('RevenueCpaCountryId', $value['RevenueCpaCountryId'])->where('RevenueCpaTraderId', $value['RevenueCpaTraderId'])->first();
                  $arr = [
                    "RevenueCpaTraderId" => $value['RevenueCpaTraderId'],
                    "RevenueModelId" => $value['RevenueModelId'],
                    "RevenueCpaCountryId" => $value['RevenueCpaCountryId'],
                    "RangeFrom" => $value['RangeFrom'],
                    "RangeExpression" => $value['RangeExpression'],
                    "RangeTo" => $value['RangeTo'],
                    "Value" => $RevenueCpaPlan->Amount,
                  ];
                  array_push($RevenueCpaTrader, $arr);
                }
                $arr = [
                  "RevenueCpaCountryId" => $valueCountry['RevenueCpaCountryId'],
                  "RevenueModelId" => $valueCountry['RevenueModelId'],
                  "RevenueCountryIds" => $valueCountry['RevenueCountrys'],
                  "RevenueCountrys" => $CountryMaster,
                  "RevenueCpaTrader" => $RevenueCpaTrader
                ];
                array_push($returnArray, $arr);
              }
              $newArray = [];
              foreach ($returnArray as $valueCnt) {
                foreach ($valueCnt['RevenueCpaTrader'] as $valueTrader) {
                  $arr = [
                    'RevenueCpaCountryId' => $valueCnt['RevenueCpaCountryId'],
                    'RevenueCpaTraderId' => $valueTrader['RevenueCpaTraderId'],
                    'Amount' => $valueTrader['Value']
                  ];
                  array_push($newArray, $arr);
                }
              }

              $RevenueCpaCountries = RevenueCpaCountry::where('RevenueModelId', $RevenueModelId)->get();
              $RevenueCpaTraders = RevenueCpaTrader::where('RevenueModelId', $RevenueModelId)->groupBy('RangeFrom')->get();

              $RevenueModelDetails = [
                'RevenueModelId' => $RevenueModel['RevenueModelId'],
                'RevenueModelName' => $RevenueModel['RevenueModelName'],
                'RevenueTypeId' => $RevenueModel['Revenue']['RevenueTypeId'],
                'RevenueTypeName' => $RevenueModel['Revenue']['RevenueTypeName'],
                'CurrencyId' => $RevenueModel['CurrencyId'],
                'Currency' => $RevenueModel['Currency']['CurrencyCode'],
                'Amount' => $RevenueModel['Amount'],
                'Percentage' => $RevenueModel['Percentage'],
                'Rebate' => $RevenueModel['Rebate'],
                'Schedule' => $RevenueModel['Schedule'],
                'TotalAccTradeVol' => $RevenueModel['TotalAccTradeVol'],
                'TotalIntroducedAcc' => $RevenueModel['TotalIntroducedAcc'],
                'Type' => $RevenueModel['Type'],
                'IsActive' => $RevenueModel['IsActive'],
                'RevenueCpaPlan' => $returnArray,
                // 'RevenueCpaCountries' => $RevenueCpaCountries,
                'RevenueCpaTraders' => $RevenueCpaTraders,
                // 'RevenueCpaPlanDetails' => $newArray,
              ];

              $res = [
                'IsSuccess' => true,
                'Message' => 'View revenue model.',
                'TotalCount' => $RevenueModel->count(),
                'Data' => array('RevenueModelDetails' => $RevenueModelDetails)
              ];
              return response()->json($res, 200);
            } else if ($RevenueModel->RevenueTypeId == 7) {
              $RevenueBonusUser = RevenueBonusUser::with('Users')->where('RevenueModelId', $request->RevenueModelId)->get();
              $UserIds = [];
              foreach ($RevenueBonusUser as $value) {
                $arr = [
                  "Name" => $value['users']['FirstName'] . ' ' . $value['users']['LastName'],
                  "UserId" => $value['UserId']
                ];
                array_push($UserIds, $arr);
              }
              $RevenueModelDetails = [
                'RevenueModelId' => $RevenueModel['RevenueModelId'],
                'RevenueModelName' => $RevenueModel['RevenueModelName'],
                'RevenueTypeId' => $RevenueModel['Revenue']['RevenueTypeId'],
                'RevenueTypeName' => $RevenueModel['Revenue']['RevenueTypeName'],
                'CurrencyId' => $RevenueModel['CurrencyId'],
                'Currency' => $RevenueModel['Currency']['CurrencyCode'],
                'Amount' => $RevenueModel['Amount'],
                'Percentage' => $RevenueModel['Percentage'],
                'Rebate' => $RevenueModel['Rebate'],
                'TradeType' => $RevenueModel['TradeType'],
                'Schedule' => $RevenueModel['Schedule'],
                'TotalAccTradeVol' => $RevenueModel['TotalAccTradeVol'],
                'TotalIntroducedAcc' => $RevenueModel['TotalIntroducedAcc'],
                'Type' => $RevenueModel['Type'],
                'IsActive' => $RevenueModel['IsActive']
              ];
              $res = [
                'IsSuccess' => true,
                'Message' => 'View revenue model.',
                'TotalCount' => $RevenueModel->count(),
                'Data' => array('RevenueModelDetails' => $RevenueModelDetails)
              ];
              return response()->json($res, 200);
            } else {
              $RevenueModelDetails = [
                'RevenueModelId' => $RevenueModel['RevenueModelId'],
                'RevenueModelName' => $RevenueModel['RevenueModelName'],
                'RevenueTypeId' => $RevenueModel['Revenue']['RevenueTypeId'],
                'RevenueTypeName' => $RevenueModel['Revenue']['RevenueTypeName'],
                'CurrencyId' => $RevenueModel['CurrencyId'],
                'Currency' => $RevenueModel['Currency']['CurrencyCode'],
                'Amount' => $RevenueModel['Amount'],
                'Percentage' => $RevenueModel['Percentage'],
                'Rebate' => $RevenueModel['Rebate'],
                'Schedule' => $RevenueModel['Schedule'],
                'TotalAccTradeVol' => $RevenueModel['TotalAccTradeVol'],
                'TotalIntroducedAcc' => $RevenueModel['TotalIntroducedAcc'],
                'Type' => $RevenueModel['Type'],
                'IsActive' => $RevenueModel['IsActive']
              ];
              $res = [
                'IsSuccess' => true,
                'Message' => 'View revenue model.',
                'TotalCount' => $RevenueModel->count(),
                'Data' => array('RevenueModelDetails' => $RevenueModelDetails)
              ];
              return response()->json($res, 200);
            }
          } else {
            $res = [
              'IsSuccess' => true,
              'Message' => 'Revenue model not found.',
              'TotalCount' => 0,
              'Data' => []
            ];
            return response()->json($res, 200);
          }
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
    }
    return response()->json($res, 200);
  }

  public function EditRevenueOptionModel(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $validator = Validator::make($request->all(), [
          'RevenueModelId' => 'required',
        ]);

        if ($validator->fails()) {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'Something went wrong.',
            "TotalCount" => count($validator->errors()),
            "Data" => array('Error' => $validator->errors())
          ], 200);
        }

        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $RevenueModel = RevenueModel::find($request->RevenueModelId);
          if ($RevenueModel) {
            if ($RevenueModel->RevenueTypeId == 3 || $RevenueModel->RevenueTypeId == 4) {
              $RevenueModelId = $request->RevenueModelId;
              $RevenueModel = RevenueModel::with('RevenueCpaPlan', 'Revenue', 'Currency')->find($request->RevenueModelId);
              $RevenueModel->whereHas('RevenueCpaPlan', function ($qr) use ($RevenueModelId) {
                $qr->where('RevenueModelId', $RevenueModelId);
              })->get();

              $RevenueCpaCountry = RevenueCpaCountry::with('RevenueCpaTrader')->where('RevenueModelId',  $RevenueModel['RevenueModelId'])->get();

              $returnArray = [];
              foreach ($RevenueCpaCountry as $valueCountry) {
                $RevenueCpaTrader = [];
                $CountrysArr = explode(",", $valueCountry['RevenueCountrys']);
                $CountryMaster = CountryMaster::whereIn('CountryId', $CountrysArr)->get();
                foreach ($valueCountry['RevenueCpaTrader'] as $value) {
                  $RevenueCpaPlan = RevenueCpaPlan::where('RevenueCpaCountryId', $value['RevenueCpaCountryId'])->where('RevenueCpaTraderId', $value['RevenueCpaTraderId'])->first();
                  if ($value['RangeExpression'] == '1') {
                    $RangeExpression = '-';
                  } else if ($value['RangeExpression'] == '2') {
                    $RangeExpression = '<';
                  } else {
                    $RangeExpression = '>';
                  }
                  $arr = [
                    "RevenueCpaTraderId" => $value['RevenueCpaTraderId'],
                    "RevenueModelId" => $value['RevenueModelId'],
                    "RevenueCpaCountryId" => $value['RevenueCpaCountryId'],
                    "StartRange" => $value['RangeFrom'],
                    "Operation" => $RangeExpression,
                    "EndRange" => $value['RangeTo'],
                    "Value" => $RevenueCpaPlan->Amount,
                  ];
                  array_push($RevenueCpaTrader, $arr);
                }
                $arr = [
                  "RevenueCpaCountryId" => $valueCountry['RevenueCpaCountryId'],
                  "RevenueModelId" => $valueCountry['RevenueModelId'],
                  "SelectedCountryList" => $valueCountry['RevenueCountrys'],
                  "RevenueCountrys" => $CountryMaster,
                  "CountryRangeSelect" => $RevenueCpaTrader
                ];
                array_push($returnArray, $arr);
              }

              $RevenueModelDetails = [
                'RevenueModelId' => $RevenueModel['RevenueModelId'],
                'RevenueModelName' => $RevenueModel['RevenueModelName'],
                'RevenueTypeId' => $RevenueModel['Revenue']['RevenueTypeId'],
                'RevenueTypeName' => $RevenueModel['Revenue']['RevenueTypeName'],
                'CurrencyId' => $RevenueModel['CurrencyId'],
                'Currency' => $RevenueModel['Currency']['CurrencyCode'],
                'Amount' => $RevenueModel['Amount'],
                'Percentage' => $RevenueModel['Percentage'],
                'Rebate' => $RevenueModel['Rebate'],
                'TradeType' => $RevenueModel['TradeType'],
                'TradeValue' => $RevenueModel['TradeValue'],
                'Schedule' => $RevenueModel['Schedule'],
                'TotalAccTradeVol' => $RevenueModel['TotalAccTradeVol'],
                'TotalIntroducedAcc' => $RevenueModel['TotalIntroducedAcc'],
                'Type' => $RevenueModel['Type'],
                'IsActive' => $RevenueModel['IsActive'],
                'Comment' => $RevenueModel['Comment'],
                'RevenueCpaPlan' => $returnArray,
              ];

              $res = [
                'IsSuccess' => true,
                'Message' => 'Edit revenue model.',
                'TotalCount' => 1,
                'Data' => array('RevenueModelDetails' => $RevenueModelDetails)
              ];
              return response()->json($res, 200);
            } else if ($RevenueModel->RevenueTypeId == 7) {
              $RevenueModelDetails = [
                'RevenueModelId' => $RevenueModel['RevenueModelId'],
                'RevenueModelName' => $RevenueModel['RevenueModelName'],
                'RevenueTypeId' => $RevenueModel['Revenue']['RevenueTypeId'],
                'RevenueTypeName' => $RevenueModel['Revenue']['RevenueTypeName'],
                'CurrencyId' => $RevenueModel['CurrencyId'],
                'Currency' => $RevenueModel['Currency']['CurrencyCode'],
                'Amount' => $RevenueModel['Amount'],
                'Percentage' => $RevenueModel['Percentage'],
                'Rebate' => $RevenueModel['Rebate'],
                'TradeType' => $RevenueModel['TradeType'],
                'Schedule' => $RevenueModel['Schedule'],
                'TotalAccTradeVol' => $RevenueModel['TotalAccTradeVol'],
                'TotalIntroducedAcc' => $RevenueModel['TotalIntroducedAcc'],
                'Type' => $RevenueModel['Type'],
                'IsActive' => $RevenueModel['IsActive'],
                'Comment' => $RevenueModel['Comment']
              ];
              $res = [
                'IsSuccess' => true,
                'Message' => 'Edit revenue model.',
                'TotalCount' => $RevenueModel->count(),
                'Data' => array('RevenueModelDetails' => $RevenueModelDetails)
              ];
              return response()->json($res, 200);
            }
            $res = [
              'IsSuccess' => true,
              'Message' => 'Edit revenue model.',
              'TotalCount' => $RevenueModel->count(),
              'Data' => array('RevenueModelDetails' => $RevenueModel)
            ];
            return response()->json($res, 200);
          } else {
            $res = [
              'IsSuccess' => false,
              'Message' => 'Revenue model not found.',
              'TotalCount' => 0,
              'Data' => []
            ];
            return response()->json($res, 200);
          }
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
    }
    return response()->json($res, 200);
  }

  public function UpdateRevenueOptionModel(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));

      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $validator = validator::make($request->all(), [
            'RevenueModelId' => 'required',
            'RevenueModelName' => 'required',
            'RevenueTypeId' => 'required',
          ]);
          if ($validator->fails()) {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Something went wrong.',
              "TotalCount" => count($validator->errors()),
              "Data" => array('Error' => $validator->errors())
            ], 200);
          }
          /*$USDConvert = CurrencyConvert::find(1);
          $AUDConvert = CurrencyConvert::find(2);
          $EURConvert = CurrencyConvert::find(3); */
          $ToDay = date('Y-m-d');
          $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
          if ($CurrencyRate) {
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          } else {
            $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          }
          // CPL And Conditional-CPL 
          if ($request->RevenueTypeId == 1 || $request->RevenueTypeId == 2) {
            if ($request->CurrencyId == 1) {
              $USDAmount = $request->Amount;
              $AUDAmount = $request->Amount * $CurrencyConvert->USDAUD;
              $EURAmount = $request->Amount * $CurrencyConvert->USDEUR;
            } else if ($request->CurrencyId == 2) {
              $AUDAmount = $request->Amount;
              $USDAmount = $request->Amount * $CurrencyConvert->AUDUSD;
              $EURAmount = $request->Amount * $CurrencyConvert->AUDEUR;
            } else if ($request->CurrencyId == 3) {
              $EURAmount = $request->Amount;
              $AUDAmount = $request->Amount * $CurrencyConvert->EURAUD;
              $USDAmount = $request->Amount * $CurrencyConvert->EURUSD;
            }
            $RevenueModel = RevenueModel::find($request->RevenueModelId);
            $RevenueModel->RevenueModelName = $request->RevenueModelName;
            $RevenueModel->RevenueTypeId = $request->RevenueTypeId;
            $RevenueModel->CurrencyId = $request->CurrencyId;
            $RevenueModel->Amount = $request->Amount;
            $RevenueModel->USDAmount = $USDAmount;
            $RevenueModel->AUDAmount = $AUDAmount;
            $RevenueModel->EURAmount = $EURAmount;
            $RevenueModel->Comment = $request->Comment;
            $RevenueModel->UpdatedBy = $log_user->UserId;
            $RevenueModel->CurrencyConvertId = $CurrencyConvert['CurrencyConvertId'];
            $RevenueModel->save();
            // create log table. use at time of revenue generate
            RevenueModelLog::create($RevenueModel->toArray());

            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Revenue model updated successfully.',
              'TotalCount' => 0,
              'Data' => null
            ], 200);
          }
          // CPA And Conditional-CPA
          if ($request->RevenueTypeId == 3 || $request->RevenueTypeId == 4) {
            if ($request->RevenueTypeId == 4) {
              $TradeType = $request->TradeType;
              $TradeValue = $request->TradeValue;
            } else {
              $TradeType = NULL;
              $TradeValue = NULL;
            }
            $RevenueModel = RevenueModel::find($request->RevenueModelId);
            if ($RevenueModel) {
              $RevenueModel->RevenueModelName = $request->RevenueModelName;
              $RevenueModel->RevenueTypeId = $request->RevenueTypeId;
              $RevenueModel->CurrencyId = $request->CurrencyId;
              $RevenueModel->TradeType = $TradeType;
              $RevenueModel->TradeValue = $TradeValue;
              $RevenueModel->Comment = $request->Comment;
              $RevenueModel->UpdatedBy = $log_user->UserId;
              $RevenueModel->CurrencyConvertId = $CurrencyConvert['CurrencyConvertId'];
              $RevenueModel->save();
              // create log table. use at time of revenue generate
              $RevenueModelLog = RevenueModelLog::create($RevenueModel->toArray());
              $RevenueModelId = $RevenueModel->RevenueModelId;
              RevenueCpaCountry::where('RevenueModelId', $RevenueModelId)->delete();
              RevenueCpaTrader::where('RevenueModelId', $RevenueModelId)->delete();
              RevenueCpaPlan::where('RevenueModelId', $RevenueModelId)->delete();
              foreach ($request->RevenueCpaPlans['CountrySelect'] as $value) {
                $RevenueCpaCountry = RevenueCpaCountry::create([
                  'RevenueModelId' => $RevenueModelId,
                  'RevenueCountrys' => $value['SelectedCountryList'],
                ]);
                $RevenueCpaCountryLog = RevenueCpaCountryLog::create([
                  'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                  'RevenueCountrys' => $value['SelectedCountryList'],
                ]);
                foreach ($value['CountryRangeSelect'] as $value) {
                  if ($request->CurrencyId == 1) {
                    $USDAmount = $value['Value'];
                    $AUDAmount = $value['Value'] * $CurrencyConvert->USDAUD;
                    $EURAmount = $value['Value'] * $CurrencyConvert->USDEUR;
                  } else if ($request->CurrencyId == 2) {
                    $USDAmount = $value['Value'] * $CurrencyConvert->AUDUSD;
                    $AUDAmount = $value['Value'];
                    $EURAmount = $value['Value'] * $CurrencyConvert->AUDEUR;
                  } else if ($request->CurrencyId == 3) {
                    $USDAmount = $value['Value'] * $CurrencyConvert->EURUSD;
                    $AUDAmount = $value['Value'] * $CurrencyConvert->EURAUD;
                    $EURAmount = $value['Value'];
                  }
                  if ($value['Operation'] == '-') {
                    $Operation = 1;
                  } else if ($value['Operation'] == '<') {
                    $Operation = 2;
                  } else if ($value['Operation'] == '>') {
                    $Operation = 3;
                  }
                  $RevenueCpaTrader = RevenueCpaTrader::create([
                    'RevenueModelId' => $RevenueModelId,
                    'RevenueCpaCountryId' => $RevenueCpaCountry->RevenueCpaCountryId,
                    'RangeFrom' => $value['StartRange'],
                    'RangeExpression' => $Operation,
                    'RangeTo' => $value['EndRange']
                  ]);

                  $RevenueCpaTraderLog = RevenueCpaTraderLog::create([
                    'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                    'RevenueCpaCountryLogId' => $RevenueCpaCountryLog->RevenueCpaCountryLogId,
                    'RangeFrom' => $value['StartRange'],
                    'RangeExpression' => $Operation,
                    'RangeTo' => $value['EndRange']
                  ]);

                  RevenueCpaPlan::create([
                    'RevenueModelId' => $RevenueModelId,
                    'RevenueCpaCountryId' => $RevenueCpaCountry->RevenueCpaCountryId,
                    'RevenueCpaTraderId' => $RevenueCpaTrader->RevenueCpaTraderId,
                    'Amount' => $value['Value'],
                    'USDAmount' => $USDAmount,
                    'AUDAmount' => $AUDAmount,
                    'EURAmount' => $EURAmount,
                    'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                  ]);

                  RevenueCpaPlanLog::create([
                    'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                    'RevenueCpaCountryLogId' => $RevenueCpaCountryLog->RevenueCpaCountryLogId,
                    'RevenueCpaTraderLogId' => $RevenueCpaTraderLog->RevenueCpaTraderLogId,
                    'Amount' => $value['Value'],
                    'USDAmount' => $USDAmount,
                    'AUDAmount' => $AUDAmount,
                    'EURAmount' => $EURAmount,
                    'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                  ]);
                }
              }
              return response()->json([
                'IsSuccess' => true,
                'Message' => 'Revenue model updated successfully.',
                'TotalCount' => 0,
                'Data' => null
              ], 200);
            } else {
              return response()->json([
                'IsSuccess' => true,
                'Message' => 'Revenue model not found.',
                'TotalCount' => 0,
                'Data' => null
              ], 200);
            }
          }
          // Revenue Share
          else if ($request->RevenueTypeId == 5) {
            $RevenueModel = RevenueModel::find($request->RevenueModelId);
            $RevenueModel->RevenueModelName = $request->RevenueModelName;
            $RevenueModel->RevenueTypeId = $request->RevenueTypeId;
            $RevenueModel->Percentage = $request->Percentage;
            $RevenueModel->Comment = $request->Comment;
            $RevenueModel->UpdatedBy = $log_user->UserId;
            $RevenueModel->CurrencyConvertId = $CurrencyConvert['CurrencyConvertId'];
            $RevenueModel->save();
            // create log table. use at time of revenue generate
            RevenueModelLog::create($RevenueModel->toArray());
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Revenue model updated successfully.',
              'TotalCount' => 0,
              'Data' => null
            ], 200);
          }
          // FX Revenue Share
          else if ($request->RevenueTypeId == 6) {
            $RevenueModel = RevenueModel::find($request->RevenueModelId);
            $RevenueModel->RevenueModelName = $request->RevenueModelName;
            $RevenueModel->RevenueTypeId = $request->RevenueTypeId;
            $RevenueModel->Amount = $request->Amount;
            $RevenueModel->Rebate = $request->Rebate;
            $RevenueModel->ReferenceDeal = $request->ReferenceDeal;
            $RevenueModel->Comment = $request->Comment;
            $RevenueModel->UpdatedBy = $log_user->UserId;
            $RevenueModel->CurrencyConvertId = $CurrencyConvert['CurrencyConvertId'];
            $RevenueModel->save();
            // create log table. use at time of revenue generate
            RevenueModelLog::create($RevenueModel->toArray());
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Revenue model updated successfully.',
              'TotalCount' => 0,
              'Data' => null
            ], 200);
          }
          // Bonus Revenue
          else if ($request->RevenueTypeId == 7) {
            if ($request->CurrencyId == 1) {
              $USDAmount = $request->Amount;
              $AUDAmount = $request->Amount * $CurrencyConvert->USDAUD;
              $EURAmount = $request->Amount * $CurrencyConvert->USDEUR;
            } else if ($request->CurrencyId == 2) {
              $USDAmount = $request->Amount * $CurrencyConvert->AUDUSD;
              $AUDAmount = $request->Amount;
              $EURAmount = $request->Amount * $CurrencyConvert->AUDEUR;
            } else if ($request->CurrencyId == 3) {
              $USDAmount = $request->Amount * $CurrencyConvert->EURUSD;
              $AUDAmount = $request->Amount * $CurrencyConvert->EURAUD;
              $EURAmount = $request->Amount;
            }
            // TradeType
            if ($request->TradeType == 1) {
              $TotalAccTradeVol = $request->BonusConditionValue;
              $TotalIntroducedAcc = NULL;
            } else {
              $TotalAccTradeVol = NULL;
              $TotalIntroducedAcc = $request->BonusConditionValue;
            }
            $RevenueModel = RevenueModel::find($request->RevenueModelId);
            $RevenueModel->RevenueTypeId = $request->RevenueTypeId;
            $RevenueModel->RevenueModelName = $request->RevenueModelName;
            $RevenueModel->Amount = $request->Amount;
            $RevenueModel->USDAmount = $USDAmount;
            $RevenueModel->AUDAmount = $AUDAmount;
            $RevenueModel->EURAmount = $EURAmount;
            $RevenueModel->TradeType = $request->TradeType;
            // $RevenueModel->Schedule = $request->Schedule;
            $RevenueModel->TotalAccTradeVol = $TotalAccTradeVol;
            $RevenueModel->TotalIntroducedAcc = $TotalIntroducedAcc;
            $RevenueModel->Comment = $request->Comment;
            $RevenueModel->UpdatedBy = $log_user->UserId;
            $RevenueModel->CurrencyConvertId = $CurrencyConvert['CurrencyConvertId'];
            $RevenueModel->save();
            // create log table. use at time of revenue generate
            RevenueModelLog::create($RevenueModel->toArray());

            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Revenue model updated successfully.',
              'TotalCount' => 0,
              'Data' => null
            ], 200);
          }
          // Sub-Affiliate
          else if ($request->RevenueTypeId == 8) {
            $RevenueModel = RevenueModel::find($request->RevenueModelId);
            $RevenueModel->RevenueTypeId = $request->RevenueTypeId;
            $RevenueModel->RevenueModelName = $request->RevenueModelName;
            $RevenueModel->Percentage = $request->Percentage;
            $RevenueModel->Type = $request->Type;
            $RevenueModel->Comment = $request->Comment;
            $RevenueModel->UpdatedBy = $log_user->UserId;
            $RevenueModel->CurrencyConvertId = $CurrencyConvert['CurrencyConvertId'];
            $RevenueModel->save();
            // create log table. use at time of revenue generate
            RevenueModelLog::create($RevenueModel->toArray());
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Revenue model updated successfully.',
              'TotalCount' => 0,
              'Data' => null
            ], 200);
          } else {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Something went wrong.',
              'TotalCount' => 0,
              'Data' => null
            ], 200);
          }
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => null
        ], 200);
      }
    } catch (exception $e) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ], 200);
    }
  }

  public function ListRevenueOptionModel(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $RevenueModel = RevenueModel::with('Revenue', 'Currency')->where('IsActive', 1)->orderBy('RevenueModelId', 'desc');
          if (isset($request->RevenueTypeId) && $request->RevenueTypeId != '') {
            $RevenueTypeId = $request->RevenueTypeId;
            $RevenueModel->where('RevenueTypeId', $RevenueTypeId);
          }
          if (isset($request->CurrencyId) && $request->CurrencyId != '') {
            $CurrencyId = $request->CurrencyId;
            $RevenueModel->where('CurrencyId', $CurrencyId);
          }
          $RevenueModel = $RevenueModel->get();
          $RevenueModelArray = [];
          foreach ($RevenueModel as $value) {
            $UserRevenueType = UserRevenueType::where('RevenueModelId', $value['RevenueModelId'])->count();
            if ($UserRevenueType == 0)
              $IsDeletable = true;
            else
              $IsDeletable = false;
            if ($value['RevenueTypeId'] == 7) {
              $UserBonus = UserBonus::where('RevenueModelId', $value['RevenueModelId'])->where('Type', 1)->count();
              if ($UserBonus == 0)
                $IsDeletable = true;
              else
                $IsDeletable = false;
            }


            $revenuname = $value['Revenue']['RevenueTypeName'];
            // Details C-CPA
            if ($value['RevenueTypeId'] == 4) {
              if ($value['TradeType'] == 1)
                $TradeType = 'Number of lots';
              else if ($value['TradeType'] == 2)
                $TradeType = 'Total deposit';
              else
                $TradeType = 'Number of transaction';
              $Details = '<b>' . $TradeType . '</b>:' . $value['TradeValue'];
            }
            // Details Fx Reve. Share
            else if ($value['RevenueTypeId'] == 6) {
              $Details = '<b>Spread</b>:' . $value['Rebate'] . '<br>' . '<b>Reference Deal</b>:' . $value['ReferenceDeal'];
            }
            // Details Bonus
            else if ($value['RevenueTypeId'] == 7) {
              if ($value['Schedule'] == 1)
                $Schedule = 'Yearly';
              else if ($value['Schedule'] == 2)
                $Schedule = 'Monthly';
              else
                $Schedule = 'Inception';
              if ($value['TradeType'] == 1)
                $Details = '<b>Schedule</b>: ' . $Schedule . '<br><b>Total Account Trade Volume</b>: ' . $value['TotalAccTradeVol'];
              else
                $Details = '<b>Schedule</b>: ' . $Schedule . '<br><b>Total Introduced Account</b>: ' . $value['TotalIntroducedAcc'];
            }
            // Details Sub Aff.
            else if ($value['RevenueTypeId'] == 8) {
              if ($value['Type'] == 1)
                $Type = 'From';
              else
                $Type = 'On Top Of';
              $Details = '<b>' . $Type . '</b>:' . $value['Percentage'];
            } else {
              $Details = '';
            }

            $RevenueModelDetails = [
              'RevenueModelId' => $value['RevenueModelId'],
              'RevenueModelName' => $value['RevenueModelName'],
              'RevenueTypeId' => $value['Revenue']['RevenueTypeId'],
              'RevenueTypeName' => $revenuname,
              'Details' => $Details,
              'CurrencyId' => $value['CurrencyId'],
              'Currency' => $value['Currency']['CurrencyCode'],
              'Value' => (($value['RevenueTypeId'] == 1) || ($value['RevenueTypeId'] == 2) || ($value['RevenueTypeId'] == 3) || ($value['RevenueTypeId'] == 4) || ($value['RevenueTypeId'] == 6) || ($value['RevenueTypeId'] == 7)) ? $value['Amount'] : $value['Percentage'],
              'Schedule' => $value['Schedule'],
              'TotalAccTradeVol' => $value['TotalAccTradeVol'],
              'TotalIntroducedAcc' => $value['TotalIntroducedAcc'],
              'Type' => $value['Type'],
              'IsActive' => $value['IsActive'],
              'IsDeletable' => $IsDeletable
            ];
            array_push($RevenueModelArray, $RevenueModelDetails);
          }

          $res = [
            'IsSuccess' => true,
            'Message' => 'List revenue model.',
            'TotalCount' => $RevenueModel->count(),
            'Data' => array('RevenueModelDetails' => $RevenueModelArray)
          ];
          return response()->json($res, 200);
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
    }
    return response()->json($res, 200);
  }

  // same as App\Console\Commands\AutoBonusCron.php
  public function AffiliateAutoBonusGenerate()
  {
    try {
      $ToDay = date('Y-m-d');
      $UserBonusList = UserBonus::where('NextBonusDate', $ToDay)->where('Type', 1)->get();
      $count = 0;
      foreach ($UserBonusList as $UserBonus) {
        $UserDetails = User::with('UserRevenueType')->whereHas('UserRevenueType', function ($qr) {
          $qr->where('RevenueTypeId', 7);
        })->where('IsDeleted', 1)->where('IsEnabled', 1)->where('RoleId', 3)->where('UserId', $UserBonus->UserId)->first();
        if ($UserDetails) {
          if ($UserBonus->RevenueModelId == $UserDetails['UserRevenueType']['RevenueModelId']) {
            $RevenueModelId = $UserDetails['UserRevenueType']['RevenueModelId'];
            $RevenueModelLog = RevenueModelLog::where('RevenueModelId', $RevenueModelId)->orderBy('RevenueModelLogId', 'desc')->first();
            if ($RevenueModelLog) {
              // $user = User::where('UserId', $UserDetails['UserId'])->where('IsEnabled', 1)->where('IsDeleted', 1)->first();
              // if ($user) {
              // $UserRegisterDate = date('Y-m-d',strtotime($user->CreatedAt));
              $RevenueModel = RevenueModel::where('RevenueModelId', $RevenueModelId)->first();
              // Inception
              if ($RevenueModel->Schedule == 3) {
                // $user_bonus = UserBonus::where('UserId', $UserDetails['UserId'])->where('RevenueModelId', $RevenueModelId)->where('Type', 1)->where('NextBonusDate', null)->first(); 
                $NextBonusDate = date('Y-m-d', strtotime($ToDay . '+1 day'));
                // return $ToDay.'+'.$timestampend; 
                $totalAcc = Lead::where('UserId', $UserDetails['UserId'])->where('IsConverted', 1)->count();
                $leadArr = Lead::where('UserId', $UserDetails['UserId'])->pluck('AccountId');
                $LeadActivityCount = LeadActivity::whereIn('AccountId', $leadArr)->sum('VolumeTraded');
                if ($RevenueModel->TradeType == 1) {
                  // return 'TradeType==1/'.$LeadActivityCount.'-'.$RevenueModel->TotalAccTradeVol;
                  if ($LeadActivityCount >= $RevenueModel->TotalAccTradeVol) {
                    UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                    $UserBonus = UserBonus::create([
                      "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                      "UserId" => $UserDetails['UserId'],
                      "CurrencyId" => $RevenueModelLog->CurrencyId,
                      "USDAmount" => $RevenueModelLog->USDAmount,
                      "AUDAmount" => $RevenueModelLog->AUDAmount,
                      "EURAmount" => $RevenueModelLog->EURAmount,
                      "TotalAccTradeVol" => $LeadActivityCount,
                      "Type" => 1,
                      "NextBonusDate" => null,
                      "Comment" => 'Automated generate'
                    ]);
                    $user_balance = UserBalance::where('UserId', $UserDetails['UserId'])->first();
                    if ($user_balance) {
                      // USD
                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $RevenueModelLog->USDAmount;
                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $RevenueModelLog->USDAmount;
                      // AUD
                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $RevenueModelLog->AUDAmount;
                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $RevenueModelLog->AUDAmount;
                      // EUR
                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $RevenueModelLog->EURAmount;
                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $RevenueModelLog->EURAmount;
                      // update user balance
                      $user_balance->save();

                      $UserRevenuePayment = UserRevenuePayment::Create([
                        'UserId' => $UserDetails['UserId'],
                        'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                        'UserBonusId' => $UserBonus->UserBonusId,
                        'USDAmount' => $RevenueModelLog->USDAmount,
                        'AUDAmount' => $RevenueModelLog->AUDAmount,
                        'EURAmount' => $RevenueModelLog->EURAmount,
                        'PaymentStatus' => 1,
                        'CurrencyConvertId' => $RevenueModelLog->CurrencyConvertId,
                        'ActualRevenueDate' => date('Y-m-d h:i:s'),
                      ]);
                      $count = $count + 1;
                    }
                  } else {
                    // default generate 0 revenue and update NextBonusDate 
                    $UserBonus = UserBonus::where('UserBonusId', $UserBonus->UserBonusId)->update(["TotalAccTradeVol" => $LeadActivityCount, "NextBonusDate" => $NextBonusDate, "Comment" => 'Automated generate default 0 inception bonus, update Next Bonus Date']);
                  }
                } else if ($RevenueModel->TradeType == 2) {
                  // return 'TradeType==2/'.$totalAcc.' >= '.$RevenueModel->TotalIntroducedAcc; die;
                  if ($totalAcc >= $RevenueModel->TotalIntroducedAcc) {
                    UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                    $UserBonus = UserBonus::create([
                      "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                      "UserId" => $UserDetails['UserId'],
                      "CurrencyId" => $RevenueModelLog->CurrencyId,
                      "USDAmount" => $RevenueModelLog->USDAmount,
                      "AUDAmount" => $RevenueModelLog->AUDAmount,
                      "EURAmount" => $RevenueModelLog->EURAmount,
                      "TotalIntroducedAcc" => $totalAcc,
                      "Type" => 1,
                      "Comment" => 'Automated generate'
                    ]);
                    $user_balance = UserBalance::where('UserId', $UserDetails['UserId'])->first();
                    if ($user_balance) {
                      // USD
                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $RevenueModelLog->USDAmount;
                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $RevenueModelLog->USDAmount;
                      // AUD
                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $RevenueModelLog->AUDAmount;
                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $RevenueModelLog->AUDAmount;
                      // EUR
                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $RevenueModelLog->EURAmount;
                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $RevenueModelLog->EURAmount;
                      // update user balance
                      $user_balance->save();

                      $UserRevenuePayment = UserRevenuePayment::Create([
                        'UserId' => $UserDetails['UserId'],
                        'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                        'UserBonusId' => $UserBonus->UserBonusId,
                        'USDAmount' => $RevenueModelLog->USDAmount,
                        'AUDAmount' => $RevenueModelLog->AUDAmount,
                        'EURAmount' => $RevenueModelLog->EURAmount,
                        'PaymentStatus' => 1,
                        'CurrencyConvertId' => $RevenueModelLog->CurrencyConvertId,
                      ]);
                      $count = $count + 1;
                    }
                  } else {
                    // default generate 0 revenue and update NextBonusDate 
                    $UserBonus = UserBonus::where('UserBonusId', $UserBonus->UserBonusId)->update(["TotalIntroducedAcc" => $totalAcc, "NextBonusDate" => $NextBonusDate, "Comment" => 'Automated generate default 0 inception bonus, update Next Bonus Date']);
                  }
                }
              }
              // Monthly
              else if ($RevenueModel->Schedule == 2) {
                // return 'Monthly'; 
                $timeStampStart = date('Y-m-d', strtotime($ToDay . '-1 month'));
                $NextBonusDate = date('Y-m-d', strtotime($ToDay . '+1 month'));
                $totalAcc = Lead::where('UserId', $UserDetails['UserId'])->where('IsConverted', 1)->whereDate('CreatedAt', '>=', $timeStampStart)->whereDate('CreatedAt', '<=', $ToDay)->count();
                $leadArr = Lead::where('UserId', $UserDetails['UserId'])->pluck('AccountId');
                $LeadActivityCount = LeadActivity::whereIn('AccountId', $leadArr)->whereDate('CreatedAt', '>=', $timeStampStart)->whereDate('CreatedAt', '<=', $ToDay)->sum('VolumeTraded');
                if ($RevenueModel->TradeType == 1) {
                  // return 'TradeType==1/'.$LeadActivityCount.'-'.$RevenueModel->TotalAccTradeVol;
                  if ($LeadActivityCount >= $RevenueModel->TotalAccTradeVol) {
                    UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                    $UserBonus = UserBonus::create([
                      "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                      "UserId" => $UserDetails['UserId'],
                      "CurrencyId" => $RevenueModelLog->CurrencyId,
                      "USDAmount" => $RevenueModelLog->USDAmount,
                      "AUDAmount" => $RevenueModelLog->AUDAmount,
                      "EURAmount" => $RevenueModelLog->EURAmount,
                      "TotalAccTradeVol" => $LeadActivityCount,
                      "Type" => 1,
                      "NextBonusDate" => $NextBonusDate,
                      "Comment" => 'Automated generate monthly bonus'
                    ]);
                    $user_balance = UserBalance::where('UserId', $UserDetails['UserId'])->first();
                    if ($user_balance) {
                      // USD
                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $RevenueModelLog->USDAmount;
                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $RevenueModelLog->USDAmount;
                      // AUD
                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $RevenueModelLog->AUDAmount;
                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $RevenueModelLog->AUDAmount;
                      // EUR
                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $RevenueModelLog->EURAmount;
                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $RevenueModelLog->EURAmount;
                      // update user balance
                      $user_balance->save();

                      $UserRevenuePayment = UserRevenuePayment::Create([
                        'UserId' => $UserDetails['UserId'],
                        'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                        'UserBonusId' => $UserBonus->UserBonusId,
                        'USDAmount' => $RevenueModelLog->USDAmount,
                        'AUDAmount' => $RevenueModelLog->AUDAmount,
                        'EURAmount' => $RevenueModelLog->EURAmount,
                        'PaymentStatus' => 1,
                        'CurrencyConvertId' => $RevenueModelLog->CurrencyConvertId,
                      ]);
                      $count = $count + 1;
                    }
                  } else {
                    // default mothly generate 0 revenue and update NextBonusDate
                    if ($LeadActivityCount == $UserBonus->TotalAccTradeVol) {
                      $UserBonus = UserBonus::where('UserBonusId', $UserBonus->UserBonusId)->update(["TotalAccTradeVol" => $LeadActivityCount, "NextBonusDate" => $NextBonusDate, "Comment" => 'Automated generate default 0 monthly bonus, update Next Bonus Date']);
                    } else {
                      UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                      UserBonus::create([
                        "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                        "UserId" => $UserDetails['UserId'],
                        "TotalAccTradeVol" => $LeadActivityCount,
                        "Type" => 1,
                        "NextBonusDate" => $NextBonusDate,
                        "Comment" => 'Automated generate default 0 monthly bonus, update Next Bonus Date'
                      ]);
                    }
                  }
                } else if ($RevenueModel->TradeType == 2) {
                  // return 'TradeType==2/'.$totalAcc.'-'.$RevenueModel->TotalIntroducedAcc;
                  if ($totalAcc >= $RevenueModel->TotalIntroducedAcc) {
                    UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                    $UserBonus = UserBonus::create([
                      "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                      "UserId" => $UserDetails['UserId'],
                      "CurrencyId" => $RevenueModelLog->CurrencyId,
                      "USDAmount" => $RevenueModelLog->USDAmount,
                      "AUDAmount" => $RevenueModelLog->AUDAmount,
                      "EURAmount" => $RevenueModelLog->EURAmount,
                      "TotalIntroducedAcc" => $totalAcc,
                      "Type" => 1,
                      "NextBonusDate" => $NextBonusDate,
                      "Comment" => 'Automated generate'
                    ]);
                    $user_balance = UserBalance::where('UserId', $UserDetails['UserId'])->first();
                    if ($user_balance) {
                      // USD
                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $RevenueModelLog->USDAmount;
                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $RevenueModelLog->USDAmount;
                      // AUD
                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $RevenueModelLog->AUDAmount;
                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $RevenueModelLog->AUDAmount;
                      // EUR
                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $RevenueModelLog->EURAmount;
                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $RevenueModelLog->EURAmount;
                      // update user balance
                      $user_balance->save();

                      $UserRevenuePayment = UserRevenuePayment::Create([
                        'UserId' => $UserDetails['UserId'],
                        'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                        'UserBonusId' => $UserBonus->UserBonusId,
                        'USDAmount' => $RevenueModelLog->USDAmount,
                        'AUDAmount' => $RevenueModelLog->AUDAmount,
                        'EURAmount' => $RevenueModelLog->EURAmount,
                        'PaymentStatus' => 1,
                        'CurrencyConvertId' => $RevenueModelLog->CurrencyConvertId,
                      ]);
                      $count = $count + 1;
                    }
                  } else {
                    // default mothly generate 0 revenue and update NextBonusDate
                    if ($LeadActivityCount == $UserBonus->TotalAccTradeVol) {
                      $UserBonus = UserBonus::where('UserBonusId', $UserBonus->UserBonusId)->update(["TotalIntroducedAcc" => $totalAcc, "NextBonusDate" => $NextBonusDate, "Comment" => 'Automated generate default 0 mothly bonus, update Next Bonus Date']);
                    } else {
                      UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                      UserBonus::create([
                        "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                        "UserId" => $UserDetails['UserId'],
                        "TotalIntroducedAcc" => $totalAcc,
                        "Type" => 1,
                        "NextBonusDate" => $NextBonusDate,
                        "Comment" => 'Automated generate default 0 mothly bonus, update Next Bonus Date'
                      ]);
                    }
                  }
                }
              }
              // Yearly
              else {
                // return 'Yearly';
                $timeStampStart = date('Y-m-d', strtotime($ToDay . '-1 year'));
                $NextBonusDate = date('Y-m-d', strtotime($ToDay . '+1 year'));
                $totalAcc = Lead::where('UserId', $UserDetails['UserId'])->where('IsConverted', 1)->whereDate('CreatedAt', '>=', $timeStampStart)->whereDate('CreatedAt', '<=', $ToDay)->count();
                $leadArr = Lead::where('UserId', $UserDetails['UserId'])->pluck('AccountId');
                $LeadActivityCount = LeadActivity::whereIn('AccountId', $leadArr)->whereDate('CreatedAt', '>=', $timeStampStart)->whereDate('CreatedAt', '<=', $ToDay)->sum('VolumeTraded');
                if ($RevenueModel->TradeType == 1) {
                  // return 'TradeType==1/'.$LeadActivityCount.'-'.$RevenueModel->TotalAccTradeVol; die;
                  if ($LeadActivityCount >= $RevenueModel->TotalAccTradeVol) {
                    UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                    $UserBonus = UserBonus::create([
                      "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                      "UserId" => $UserDetails['UserId'],
                      "CurrencyId" => $RevenueModelLog->CurrencyId,
                      "USDAmount" => $RevenueModelLog->USDAmount,
                      "AUDAmount" => $RevenueModelLog->AUDAmount,
                      "EURAmount" => $RevenueModelLog->EURAmount,
                      "TotalAccTradeVol" => $LeadActivityCount,
                      "Type" => 1,
                      "NextBonusDate" => $NextBonusDate,
                      "Comment" => 'Automated generate yearly bonus'
                    ]);
                    $user_balance = UserBalance::where('UserId', $UserDetails['UserId'])->first();
                    if ($user_balance) {
                      // USD
                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $RevenueModelLog->USDAmount;
                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $RevenueModelLog->USDAmount;
                      // AUD
                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $RevenueModelLog->AUDAmount;
                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $RevenueModelLog->AUDAmount;
                      // EUR
                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $RevenueModelLog->EURAmount;
                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $RevenueModelLog->EURAmount;
                      // update user balance
                      $user_balance->save();

                      $UserRevenuePayment = UserRevenuePayment::Create([
                        'UserId' => $UserDetails['UserId'],
                        'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                        'UserBonusId' => $UserBonus->UserBonusId,
                        'USDAmount' => $RevenueModelLog->USDAmount,
                        'AUDAmount' => $RevenueModelLog->AUDAmount,
                        'EURAmount' => $RevenueModelLog->EURAmount,
                        'PaymentStatus' => 1,
                        'CurrencyConvertId' => $RevenueModelLog->CurrencyConvertId,
                      ]);
                      $count = $count + 1;
                    }
                  } else {
                    // default Yearly generate 0 revenue and update NextBonusDate
                    if ($LeadActivityCount == $UserBonus->TotalAccTradeVol) {
                      $UserBonus = UserBonus::where('UserBonusId', $UserBonus->UserBonusId)->update(["TotalAccTradeVol" => $LeadActivityCount, "NextBonusDate" => $NextBonusDate, "Comment" => 'Automated generate default 0 yearly bonus, update Next Bonus Date']);
                    } else {
                      UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                      UserBonus::create([
                        "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                        "UserId" => $UserDetails['UserId'],
                        "TotalAccTradeVol" => $LeadActivityCount,
                        "Type" => 1,
                        "NextBonusDate" => $NextBonusDate,
                        "Comment" => 'Automated generate default 0 yearly bonus, update Next Bonus Date'
                      ]);
                    }
                  }
                } else if ($RevenueModel->TradeType == 2) {
                  // return 'TradeType==2/'.$totalAcc.'-'.$RevenueModel->TotalIntroducedAcc; die;
                  if ($totalAcc >= $RevenueModel->TotalIntroducedAcc) {
                    // echo 'if'; die;
                    UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                    $UserBonus = UserBonus::create([
                      "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                      "UserId" => $UserDetails['UserId'],
                      "CurrencyId" => $RevenueModelLog->CurrencyId,
                      "USDAmount" => $RevenueModelLog->USDAmount,
                      "AUDAmount" => $RevenueModelLog->AUDAmount,
                      "EURAmount" => $RevenueModelLog->EURAmount,
                      "TotalIntroducedAcc" => $totalAcc,
                      "Type" => 1,
                      "NextBonusDate" => $NextBonusDate,
                      "Comment" => 'Automated generate yearly bonus'
                    ]);
                    $user_balance = UserBalance::where('UserId', $UserDetails['UserId'])->first();
                    if ($user_balance) {
                      // USD
                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $RevenueModelLog->USDAmount;
                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $RevenueModelLog->USDAmount;
                      // AUD
                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $RevenueModelLog->AUDAmount;
                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $RevenueModelLog->AUDAmount;
                      // EUR
                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $RevenueModelLog->EURAmount;
                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $RevenueModelLog->EURAmount;
                      // update user balance
                      $user_balance->save();

                      $UserRevenuePayment = UserRevenuePayment::Create([
                        'UserId' => $UserDetails['UserId'],
                        'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                        'UserBonusId' => $UserBonus->UserBonusId,
                        'USDAmount' => $RevenueModelLog->USDAmount,
                        'AUDAmount' => $RevenueModelLog->AUDAmount,
                        'EURAmount' => $RevenueModelLog->EURAmount,
                        'PaymentStatus' => 1,
                        'CurrencyConvertId' => $RevenueModelLog->CurrencyConvertId
                      ]);
                      $count = $count + 1;
                    }
                  } else {
                    // default Yearly generate 0 revenue and update NextBonusDate
                    if ($LeadActivityCount == $UserBonus->TotalAccTradeVol) {
                      $UserBonus = UserBonus::where('UserBonusId', $UserBonus->UserBonusId)->update(["TotalIntroducedAcc" => $totalAcc, "NextBonusDate" => $NextBonusDate, "Comment" => 'Automated generate default 0 yearly bonus, update Next Bonus Date']);
                    } else {
                      UserBonus::where('UserId', $UserDetails['UserId'])->update(['NextBonusDate' => null]);
                      UserBonus::create([
                        "RevenueModelId" => $RevenueModelLog->RevenueModelId,
                        "UserId" => $UserDetails['UserId'],
                        "TotalIntroducedAcc" => $totalAcc,
                        "Type" => 1,
                        "NextBonusDate" => $NextBonusDate,
                        "Comment" => 'Automated generate default 0 yearly bonus, update Next Bonus Date'
                      ]);
                    }
                  }
                }
              }
              // End. Yearly
              // }
            }
          }
        }
      }
      // return true
      $res = [
        'IsSuccess' => true,
        'Message' => 'Total Revenue generate ' . $count,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res, 200);
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res, 200);
    }
  }

  public function AffiliateManualBonusAssign(Request $request)
  {
    // return $request->all();
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $UserDetails = User::find($request->UserId);
          if ($UserDetails) {
            /*$USDConvert = CurrencyConvert::find(1);
            $AUDConvert = CurrencyConvert::find(2);
            $EURConvert = CurrencyConvert::find(3);*/
            $ToDay = date('Y-m-d');
            $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
            if ($CurrencyRate) {
              $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
            } else {
              $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
              $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
            }

            if ($UserDetails->CurrencyId == 1) {
              $USDAmount = $request->Amount;
              $AUDAmount = $request->Amount * $CurrencyConvert->USDAUD;
              $EURAmount = $request->Amount * $CurrencyConvert->USDEUR;
            } else if ($UserDetails->CurrencyId == 2) {
              $AUDAmount = $request->Amount;
              $USDAmount = $request->Amount * $CurrencyConvert->AUDUSD;
              $EURAmount = $request->Amount * $CurrencyConvert->AUDEUR;
            } else if ($UserDetails->CurrencyId == 3) {
              $EURAmount = $request->Amount;
              $AUDAmount = $request->Amount * $CurrencyConvert->EURAUD;
              $USDAmount = $request->Amount * $CurrencyConvert->EURUSD;
            }

            $UserBonus = UserBonus::create([
              "UserId" => $UserDetails->UserId,
              "CurrencyId" => $UserDetails->CurrencyId,
              "USDAmount" => $USDAmount,
              "AUDAmount" => $AUDAmount,
              "EURAmount" => $EURAmount,
              "Type" => 0,
              "Comment" => $request->Comment
            ]);
            $user_balance = UserBalance::where('UserId', $UserDetails->UserId)->first();
            if ($user_balance) {
              // USD 
              $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $USDAmount;
              $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $USDAmount;
              // AUD
              $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $AUDAmount;
              $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $AUDAmount;
              // EUR
              $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $EURAmount;
              $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $EURAmount;
              $user_balance->save();

              $UserRevenuePayment = UserRevenuePayment::Create([
                'UserId' => $UserDetails->UserId,
                'UserBonusId' => $UserBonus->UserBonusId,
                'USDAmount' => $USDAmount,
                'AUDAmount' => $AUDAmount,
                'EURAmount' => $EURAmount,
                'PaymentStatus' => 1,
                'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                'ActualRevenueDate' => date('Y-m-d h:i:s'),
              ]);
            }
            $res = [
              'IsSuccess' => true,
              'Message' => 'Bonus assigned successfully.',
              'TotalCount' => 0,
              'Data' => null
            ];
            return response()->json($res, 200);
          } else {
            $res = [
              'IsSuccess' => false,
              'Message' => 'User not found.',
              'TotalCount' => 0,
              'Data' => null
            ];
            return response()->json($res, 200);
          }
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
    }
    return response()->json($res, 200);
  }

  public function GetAffiliateListForRevenueModel(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $RevenueModel = RevenueModel::find($request->RevenueModelId);
          if ($RevenueModel) {
            if ($RevenueModel->RevenueTypeId == 7) {
              $selectedUsers = UserRevenueType::where('RevenueTypeId', $RevenueModel->RevenueTypeId)->orderBy('UserId')->pluck('UserId');
              $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('CurrencyId', $RevenueModel->CurrencyId)->where('AdminVerified', 1)->where('IsEnabled', 1)->where('IsDeleted', 1)->whereNotIn('UserId', $selectedUsers)->where('RoleId', 3)->get();
              $arrList = [];
              foreach ($User as $value) {
                $arr = [
                  "UserId" => $value['UserId'],
                  "Name" => $value['FirstName'] . ' ' . $value['LastName'],
                  "EmailId" => $value['EmailId']
                ];
                array_push($arrList, $arr);
              }
              $res = [
                'IsSuccess' => true,
                'Message' => 'User list.',
                'TotalCount' => $User->count(),
                'Data' => array('UserList' => $arrList)
              ];
              return response()->json($res, 200);
            } else if ($RevenueModel->RevenueTypeId == 5 || $RevenueModel->RevenueTypeId == 6) {
              $selectedUsers = UserRevenueType::where('RevenueTypeId', $RevenueModel->RevenueTypeId)->orderBy('UserId')->pluck('UserId');
              $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('AdminVerified', 1)->where('IsEnabled', 1)->where('IsDeleted', 1)->whereNotIn('UserId', $selectedUsers)->where('RoleId', 3)->get();
              $arrList = [];
              foreach ($User as $value) {
                $arr = [
                  "UserId" => $value['UserId'],
                  "Name" => $value['FirstName'] . ' ' . $value['LastName'],
                  "EmailId" => $value['EmailId']
                ];
                array_push($arrList, $arr);
              }
              $res = [
                'IsSuccess' => true,
                'Message' => 'User list.',
                'TotalCount' => $User->count(),
                'Data' => array('UserList' => $arrList)
              ];
              return response()->json($res, 200);
            } else if ($RevenueModel->RevenueTypeId == 8) {
              $selectedUsers = UserRevenueType::where('RevenueTypeId', $RevenueModel->RevenueTypeId)->orderBy('UserId')->pluck('UserId');
              $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('IsAllowSubAffiliate', 1)->where('AdminVerified', 1)->where('IsEnabled', 1)->where('IsDeleted', 1)->whereNotIn('UserId', $selectedUsers)->where('RoleId', 3)->get();
              $arrList = [];
              foreach ($User as $value) {
                $arr = [
                  "UserId" => $value['UserId'],
                  "Name" => $value['FirstName'] . ' ' . $value['LastName'],
                  "EmailId" => $value['EmailId']
                ];
                array_push($arrList, $arr);
              }
              $res = [
                'IsSuccess' => true,
                'Message' => 'User list.',
                'TotalCount' => $User->count(),
                'Data' => array('UserList' => $arrList)
              ];
              return response()->json($res, 200);
            } else {
              $selectedUsers = UserRevenueType::where('RevenueTypeId', $RevenueModel->RevenueTypeId)->orderBy('UserId')->pluck('UserId');
              $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('CurrencyId', $RevenueModel->CurrencyId)->where('AdminVerified', 1)->where('IsEnabled', 1)->where('IsDeleted', 1)->whereNotIn('UserId', $selectedUsers)->where('RoleId', 3)->get();

              $arrList = [];
              foreach ($User as $value) {
                $arr = [
                  "UserId" => $value['UserId'],
                  "Name" => $value['FirstName'] . ' ' . $value['LastName'],
                  "EmailId" => $value['EmailId']
                ];
                array_push($arrList, $arr);
              }
              $res = [
                'IsSuccess' => true,
                'Message' => 'User list.',
                'TotalCount' => $User->count(),
                'Data' => array('UserList' => $arrList)
              ];
              return response()->json($res, 200);
            }
          } else {
            $res = [
              'IsSuccess' => false,
              'Message' => 'Revenue model not found.',
              'TotalCount' => 0,
              'Data' => []
            ];
            return response()->json($res, 200);
          }
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
    }
    return response()->json($res, 200);
  }

  public function AssignRevenueModelToAffiliates(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $RevenueModel = RevenueModel::find($request->RevenueModelId);
          if ($RevenueModel) {
            if ($RevenueModel->RevenueTypeId == 7) {
              foreach ($request->Users as $value) {
                UserRevenueType::create([
                  'UserId' => $value,
                  'RevenueTypeId' => $RevenueModel->RevenueTypeId,
                  'RevenueModelId' => $RevenueModel->RevenueModelId,
                  'CreatedBy' => $log_user->UserId
                ]);
                // Auto bonus update date
                $UserBonus = UserBonus::where('UserId', $value)->orderBy('UserBonusId', 'desc')->first();
                $RevenueModel = RevenueModel::where('RevenueModelId', $request->RevenueModelId)->first();
                // Inception
                if ($RevenueModel->Schedule == 3) {
                  $NextBonusDate = new DateTime('tomorrow');
                  $NextBonusDate->format('Y-m-d');
                }
                // Monthly
                else if ($RevenueModel->Schedule == 2) {
                  $NextBonusDate = new DateTime('today');
                  $NextBonusDate->modify('+1 month');
                  $NextBonusDate->format('Y-m-d');
                }
                // Yearly
                else {
                  $NextBonusDate = new DateTime('today');
                  $NextBonusDate->modify('+1 year');
                  $NextBonusDate->format('Y-m-d');
                }
                if ($UserBonus) {
                  // update NextBonusDate if bonus revenue model change/update
                  if ($UserBonus->RevenueModelId != $request->RevenueModelId) {
                    UserBonus::where('UserId', $value)->update(['NextBonusDate' => null]);
                    $UserBonus = UserBonus::create([
                      "RevenueModelId" => $RevenueModel->RevenueModelId,
                      "UserId" => $value,
                      "NextBonusDate" => $NextBonusDate,
                      "Type" => 1,
                    ]);
                  }
                } else {
                  $UserBonus = UserBonus::create([
                    "RevenueModelId" => $RevenueModel->RevenueModelId,
                    "UserId" => $value,
                    "NextBonusDate" => $NextBonusDate,
                    "Type" => 1,
                  ]);
                }
                // End. Auto bonus update date
              }
              $res = [
                'IsSuccess' => true,
                'Message' => 'User revenue bonus assigned successfully.',
                'TotalCount' => 0,
                'Data' => []
              ];
              return response()->json($res, 200);
            } else {
              foreach ($request->Users as $value) {
                UserRevenueType::create([
                  'UserId' => $value,
                  'RevenueTypeId' => $RevenueModel->RevenueTypeId,
                  'RevenueModelId' => $RevenueModel->RevenueModelId,
                  'CreatedBy' => $log_user->UserId
                ]);
              }
              $res = [
                'IsSuccess' => true,
                'Message' => 'User assigned successfully.',
                'TotalCount' => 0,
                'Data' => []
              ];
              return response()->json($res, 200);
            }
          } else {
            $res = [
              'IsSuccess' => false,
              'Message' => 'Revenue model not found.',
              'TotalCount' => 0,
              'Data' => []
            ];
            return response()->json($res, 200);
          }
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
    }
    return response()->json($res, 200);
  }

  public function DeleteRevenueModel(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $RevenueModel = RevenueModel::where('RevenueModelId', $request->RevenueModelId)->first();
          if ($RevenueModel) {
            if ($RevenueModel->RevenueTypeId == 7) {
              $UserBonus = UserBonus::where('RevenueModelId', $request->RevenueModelId)->where('Type', 1)->count();
              if ($UserBonus > 0) {
                $res = [
                  'IsSuccess' => false,
                  'Message' => 'Revenue model not deletable.',
                  'TotalCount' => 0,
                  'Data' => []
                ];
                return response()->json($res, 200);
              }
            }
            $UserRevenueType = UserRevenueType::where('RevenueModelId', $request->RevenueModelId)->count();
            if ($UserRevenueType == 0) {
              RevenueModel::where('RevenueModelId', $request->RevenueModelId)->update(['IsActive' => 0]);
              $res = [
                'IsSuccess' => true,
                'Message' => 'Revenue model delete successfully.',
                'TotalCount' => 0,
                'Data' => []
              ];
              return response()->json($res, 200);
            } else {
              $res = [
                'IsSuccess' => false,
                'Message' => 'Revenue model not deletable.',
                'TotalCount' => 0,
                'Data' => []
              ];
              return response()->json($res, 200);
            }
          } else {
            $res = [
              'IsSuccess' => false,
              'Message' => 'Revenue model not found.',
              'TotalCount' => 0,
              'Data' => []
            ];
            return response()->json($res, 200);
          }
          if ($UserRevenueType == 0)
            $IsDeletable = true;
          else
            $IsDeletable = false;
        } else {
          $res = [
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => null
          ];
          return response()->json($res, 200);
        }
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Invalid token.',
          'TotalCount' => 0,
          'Data' => null
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res, 200);
    }
  }
}
