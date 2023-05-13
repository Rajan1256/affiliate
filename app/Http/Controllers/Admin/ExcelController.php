<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Validator;
use DB;
use App\Lead;
use App\User; 
use App\CurrencyConvert;
use App\CurrencyRate;
use App\Campaign;
use App\CountryMaster;
use App\RevenueModelLog; 
use App\RevenueCpaPlanLog;
use App\RevenueCpaCountryLog;
use App\RevenueCpaTraderLog;
use App\UserRevenuePayment;
use App\RoyalRevenue;
use App\RoyalBalance;
use App\UserSubRevenue;
use App\UserBalance;
use App\UserToken;
use App\LeadActivity;
use App\LeadActivityFile;
use App\LeadInformation;
use App\UserRevenueType;
use App\LeadInfoFile;
use App\LeadStatusMaster;
use Maatwebsite\Excel\Facades\Excel;
use Laravel\Lumen\Routing\Controller as BaseController;

class ExcelController extends BaseController
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

  // Process Daily Lead Activity
  public function ProcessLeadActivity()
  {
    $processedData = 0;
    try {
      $lead_info = LeadActivity::where('ProcessStatus', 0)->take(1000)->get();
      foreach ($lead_info as $rw) {
        $LeadData = Lead::where('AccountId', $rw['AccountId'])->first();
        if ($LeadData) {
          $campgin_revenutype = Campaign::where('CampaignId', $LeadData['CampaignId'])->first();

          if ($campgin_revenutype) {
            $RevenueModelLog = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->orderBy('RevenueModelLogId', 'desc')->first();
            $User = User::find($campgin_revenutype->UserId);
            if ($RevenueModelLog) {
              try {
                $strdate = str_replace('/', '-', $rw['LeadsActivityDate']);
                $time = strtotime($strdate);
                if ($time) {
                  try {
                    $LeadsActivityDate = date('Y-m-d H:i:s', $time);
                  } catch (exception $e) {
                    $LeadsActivityDate = null;
                  }
                } else {
                  $LeadsActivityDate = null;
                }
              } catch (ParseException $e) {
                $LeadsActivityDate = null;
              }
              $LeadActivityData = LeadActivity::find($rw->LeadActivityId); 
              $LeadActivityData->ActualRevenueDate = $LeadsActivityDate;
              $LeadActivityData->save(); 
              if ($LeadsActivityDate != null) {
                $CurrencyRate = CurrencyRate::where('Status', 1)->where('Date', $LeadsActivityDate)->orderBy('CurrencyRateId', 'desc')->first();
                if ($CurrencyRate) {
                  $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                } else {
                  $CurrencyRate = CurrencyRate::where('Status', 1)->where('Date', '<', $LeadsActivityDate)->orderBy('CurrencyRateId', 'desc')->first();
                  if ($CurrencyRate) {
                    $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                  } else {
                    $CurrencyConvert = false;
                  }
                }
              } else {
                $CurrencyConvert = false;
              }
              if ($CurrencyConvert) {
                // CPL - Revenue type = 1
                if ($RevenueModelLog->RevenueTypeId == 1) {
                  $User = User::find($campgin_revenutype->UserId);
                  // Get RevenueModel Log Id List 
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  /*
                    Royal Revenue generate
                  */
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  $check_revenu_pay1 = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_revenu_pay1 == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;
                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw['LeadActivityId'],
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);
                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */
                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */
                }
                // C-CPL - Revenue type = 2
                if ($RevenueModelLog->RevenueTypeId == 2) {
                  // Get RevenueModel Log Id List 
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  /*
                    Royal Revenue generate
                  */
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  $check_revenu_pay2 = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_revenu_pay2 == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw['LeadActivityId'],
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */

                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */
                }
                // CPA - Revenue type = 3
                if ($RevenueModelLog->RevenueTypeId == 3) {
                  // Get RevenueModel Log Id List 
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  /*
                    Royal Revenue generate
                  */
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  $check_royal_revenu_pay = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_royal_revenu_pay == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw['LeadActivityId'],
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */

                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */
                }
                // C-CPA - Revenue type = 4
                else if ($RevenueModelLog->RevenueTypeId == 4) {
                  // return 'C-CPA';
                  // Get RevenueModel Log Id List
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  /*
                    Royal Revenue generate
                  */
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  $check_royal_revenu_pay = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_royal_revenu_pay == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */
                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */

                  if ($LeadData['IsConverted'] == 1) {
                    // check User Revenue Payment already assign
                    $check_revenu_pay4 = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                    if ($check_revenu_pay4 == null) {
                      // CurrencyConvert 
                      // $LeadsActivityDate = $LeadData['DateConverted'];
                      if ($LeadsActivityDate != null) {
                        $CurrencyRate = CurrencyRate::where('Status', 1)->where('Date', $LeadsActivityDate)->orderBy('CurrencyRateId', 'desc')->first();
                        if ($CurrencyRate) {
                          $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                        } else {
                          $CurrencyRate = CurrencyRate::where('Status', 1)->where('Date', '<', $LeadsActivityDate)->orderBy('CurrencyRateId', 'desc')->first();
                          if ($CurrencyRate) {
                            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                          } else {
                            $CurrencyConvert = false;
                          }
                        }
                      } else {
                        $CurrencyConvert = false;
                      }
                      // End. CurrencyConvert
                      if ($CurrencyConvert) {
                        // return $RevenueModelLogIds;
                        if ($RevenueModelLog['TradeType'] == 1) {
                          // return 'TradeType = 1';
                          if ($rw['VolumeTraded'] >= $RevenueModelLog['TradeValue']) {
                            // get Lead Information data
                            $LeadInformation = LeadInformation::where('LeadId', $LeadData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                            // get CountryId from Lead Information 
                            $CountryId = CountryMaster::where('CountryNameShortCode', $LeadInformation->Country)->first();
                            if ($CountryId) {
                              // get Revenue CPA Country group from log
                              $RevenueCpaCountryLog = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId->CountryId . ', RevenueCountrys)')->first();
                              // get all leads data of Lead Activity process status = 2 for count number of transactions of this country group
                              $Leads = Lead::with('LeadActivity')->whereHas('LeadActivity', function ($qr) {
                                $qr->where('ProcessStatus', 2);
                              })->where('CampaignId', $LeadData['CampaignId'])->where('IsConverted', 1)->get();

                              $count = 1;
                              // check number of coutry group revenue payment already assigned
                              foreach ($Leads as $LeadsData) {
                                foreach ($LeadsData['LeadActivity'] as $LeadActivity) {
                                  if ($LeadActivity['ProcessStatus'] == 2) {
                                    $UserRevenuePayment = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadActivity['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->get();
                                    foreach ($UserRevenuePayment as $value) {
                                      $RevenueModelLog = RevenueModelLog::find($value['RevenueModelLogId']);
                                      $LeadInformation2 = LeadInformation::where('LeadId', $LeadsData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                                      $CountryId2 = CountryMaster::where('CountryNameShortCode', $LeadInformation2->Country)->first();
                                      if ($CountryId2) {
                                        $RevenueCpaCountryLog1 = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId2->CountryId . ', RevenueCountrys)')->first();
                                        $CountryOld = explode(',', $RevenueCpaCountryLog1['RevenueCountrys']);
                                        $CountryNew = explode(',', $RevenueCpaCountryLog['RevenueCountrys']);
                                        $result = array_intersect($CountryOld, $CountryNew);
                                        // check new revenue country match with old payment
                                        if ($result) {
                                          $count++;
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                              // return  'count:-'.$count; die; 
                              $RevenueCpaTraderLog = RevenueCpaTraderLog::where('RevenueCpaCountryLogId', $RevenueCpaCountryLog->RevenueCpaCountryLogId)->get();
                              foreach ($RevenueCpaTraderLog as $value) {
                                // if Range Expression is -(in between two values)
                                if ($value['RangeExpression'] == 1) {
                                  if ($count >= $value['RangeFrom'] && $count <= $value['RangeTo']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die;  
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }

                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance) 
                                    {  
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save(); 
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                      'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::find($rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++; 
                                  }
                                }
                                // if Range Expression is >(Greater than value)
                                if ($value['RangeExpression'] == 3) {
                                  if ($count > $value['RangeFrom']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance) 
                                    { 
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save(); 
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                      'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::where('LeadActivityId', $rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                              }
                            }
                          }
                        } else if ($RevenueModelLog['TradeType'] == 2) {
                          // return "TradeType = 2";
                          if ($rw['DepositsUSD'] >= $RevenueModelLog['TradeValue']) {
                            // return 'TradeType 2'; die;
                            // get Lead Information data
                            $LeadInformation = LeadInformation::where('LeadId', $LeadData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                            // get CountryId from Lead Information 
                            $CountryId = CountryMaster::where('CountryNameShortCode', $LeadInformation->Country)->first();
                            if ($CountryId) {
                              // get Revenue CPA Country group from log
                              $RevenueCpaCountryLog = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId->CountryId . ', RevenueCountrys)')->first();
                              // get all leads data of Lead Activity process status = 2 for count number of transactions of this country group
                              $Leads = Lead::with('LeadActivity')->whereHas('LeadActivity', function ($qr) {
                                $qr->where('ProcessStatus', 2);
                              })->where('CampaignId', $LeadData['CampaignId'])->where('IsConverted', 1)->get();

                              $count = 1;
                              // check number of coutry group revenue payment already assigned
                              foreach ($Leads as $LeadsData) {
                                foreach ($LeadsData['LeadActivity'] as $LeadActivity) {
                                  if ($LeadActivity['ProcessStatus'] == 2) {
                                    $UserRevenuePayment = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadActivity['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->get();
                                    foreach ($UserRevenuePayment as $value) {
                                      $RevenueModelLog = RevenueModelLog::find($value['RevenueModelLogId']);
                                      $LeadInformation2 = LeadInformation::where('LeadId', $LeadsData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                                      $CountryId2 = CountryMaster::where('CountryNameShortCode', $LeadInformation2->Country)->first();
                                      if ($CountryId2) {
                                        $RevenueCpaCountryLog1 = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId2->CountryId . ', RevenueCountrys)')->first();
                                        $CountryOld = explode(',', $RevenueCpaCountryLog1['RevenueCountrys']);
                                        $CountryNew = explode(',', $RevenueCpaCountryLog['RevenueCountrys']);
                                        $result = array_intersect($CountryOld, $CountryNew);
                                        // check new revenue country match with old payment
                                        if ($result) {
                                          $count++;
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                              // return  $count; die; 
                              $RevenueCpaTraderLog = RevenueCpaTraderLog::where('RevenueCpaCountryLogId', $RevenueCpaCountryLog->RevenueCpaCountryLogId)->get();
                              foreach ($RevenueCpaTraderLog as $value) {
                                // if Range Expression is -(in between two values)
                                if ($value['RangeExpression'] == 1) {
                                  if ($count >= $value['RangeFrom'] && $count <= $value['RangeTo']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance)
                                    {  
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save(); 
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                        'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::find($rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                                // if Range Expression is >(Greater than value)
                                if ($value['RangeExpression'] == 3) {
                                  if ($count > $value['RangeFrom']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance) 
                                    { 
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save();
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                        'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::where('LeadActivityId', $rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                              }
                            }
                          }
                        } else if ($RevenueModelLog['TradeType'] == 3) {
                          // return "TradeType = 3";
                          if ($rw['NumberOfTransactions'] >= $RevenueModelLog['TradeValue']) {
                            // return 'TradeType 3'; die;
                            // get Lead Information data
                            $LeadInformation = LeadInformation::where('LeadId', $LeadData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                            // get CountryId from Lead Information 
                            $CountryId = CountryMaster::where('CountryNameShortCode', $LeadInformation->Country)->first();
                            if ($CountryId) {
                              // get Revenue CPA Country group from log
                              $RevenueCpaCountryLog = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId->CountryId . ', RevenueCountrys)')->first();
                              // get all leads data of Lead Activity process status = 2 for count number of transactions of this country group
                              $Leads = Lead::with('LeadActivity')->whereHas('LeadActivity', function ($qr) {
                                $qr->where('ProcessStatus', 2);
                              })->where('CampaignId', $LeadData['CampaignId'])->where('IsConverted', 1)->get();

                              $count = 1;
                              // check number of coutry group revenue payment already assigned
                              foreach ($Leads as $LeadsData) {
                                foreach ($LeadsData['LeadActivity'] as $LeadActivity) {
                                  if ($LeadActivity['ProcessStatus'] == 2) {
                                    $UserRevenuePayment = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadActivity['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->get();
                                    foreach ($UserRevenuePayment as $value) {
                                      $RevenueModelLog = RevenueModelLog::find($value['RevenueModelLogId']);
                                      $LeadInformation2 = LeadInformation::where('LeadId', $LeadsData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                                      $CountryId2 = CountryMaster::where('CountryNameShortCode', $LeadInformation2->Country)->first();
                                      if ($CountryId2) {
                                        $RevenueCpaCountryLog1 = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId2->CountryId . ', RevenueCountrys)')->first();
                                        $CountryOld = explode(',', $RevenueCpaCountryLog1['RevenueCountrys']);
                                        $CountryNew = explode(',', $RevenueCpaCountryLog['RevenueCountrys']);
                                        $result = array_intersect($CountryOld, $CountryNew);
                                        // check new revenue country match with old payment
                                        if ($result) {
                                          $count++;
                                        }
                                      }
                                    }
                                  }
                                }
                              }
                              // return  $count; die; 
                              $RevenueCpaTraderLog = RevenueCpaTraderLog::where('RevenueCpaCountryLogId', $RevenueCpaCountryLog->RevenueCpaCountryLogId)->get();
                              foreach ($RevenueCpaTraderLog as $value) {
                                // if Range Expression is -(in between two values)
                                if ($value['RangeExpression'] == 1) {
                                  if ($count >= $value['RangeFrom'] && $count <= $value['RangeTo']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance)
                                    {  
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save(); 
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                        'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::find($rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                                // if Range Expression is >(Greater than value)
                                if ($value['RangeExpression'] == 3) {
                                  if ($count > $value['RangeFrom']) {
                                    $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                    // return $RevenueCpaPlanLog; die; 
                                    if ($RevenueModelLog->CurrencyId == 1) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 2) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount;
                                      $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                    } else if ($RevenueModelLog->CurrencyId == 3) {
                                      $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                      $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                      $EURAmount = $RevenueCpaPlanLog->Amount;
                                    }
                                    $UserRevenuePayment = UserRevenuePayment::Create([
                                      'UserId' => $campgin_revenutype->UserId,
                                      'LeadId' => $LeadData['LeadId'],
                                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                                      'LeadActivityId' => $rw->LeadActivityId,
                                      'USDAmount' => $USDAmount,
                                      'AUDAmount' => $AUDAmount,
                                      'EURAmount' => $EURAmount,
                                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                      'ActualRevenueDate' => $LeadsActivityDate,
                                    ]);
                                    if ($this->revenue_auto_approve) {
                                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                    }
                                    /*$user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                    if($user_balance) 
                                    { 
                                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                      $user_balance->save();
                                    }
                                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                        'IsCompleted' => 1
                                    ]);*/
                                    LeadActivity::where('LeadActivityId', $rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
                                    if ($User->ParentId != null) {
                                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                    }
                                    // $processedData++;
                                  }
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
                // Revenue Share - Revenue type = 5
                else if ($RevenueModelLog->RevenueTypeId == 5) {
                  // Get RevenueModel Log Id List
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  /*
                    Royal Revenue generate
                  */
                  $check_royal_revenu_pay = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_royal_revenu_pay == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */
                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */

                  $AccountId = $rw['AccountId'];
                  $LeadActivity = LeadActivity::where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal)->where('AccountId', $AccountId)->where('ProcessStatus', 2)->count();  
                  /* $check_revenue_payment = UserRevenuePayment::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal, $AccountId) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal)->where('AccountId', $AccountId);
                  })->where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->first(); */
                  if ($LeadActivity == 0) {
                    // currency convert 
                    $USDAmount = $rw->RoyalCommissionUSD;
                    $AUDAmount = $rw->RoyalCommissionUSD * $CurrencyConvert->USDAUD;
                    $EURAmount = $rw->RoyalCommissionUSD * $CurrencyConvert->USDEUR;
                    // Spread amount
                    $USDAmount1 = $rw->RoyalSpreadUSD;
                    $AUDAmount1 = $rw->RoyalSpreadUSD * $CurrencyConvert->USDAUD;
                    $EURAmount1 = $rw->RoyalSpreadUSD * $CurrencyConvert->USDEUR;

                    $UserRevenuePayment = UserRevenuePayment::Create([
                      'UserId' => $campgin_revenutype->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $USDAmount * $RevenueModelLog->Percentage / 100,
                      'AUDAmount' => $AUDAmount * $RevenueModelLog->Percentage / 100,
                      'EURAmount' => $EURAmount * $RevenueModelLog->Percentage / 100,
                      'SpreadUSDAmount' => $USDAmount1 * $RevenueModelLog->Percentage / 100,
                      'SpreadAUDAmount' => $AUDAmount1 * $RevenueModelLog->Percentage / 100,
                      'SpreadEURAmount' => $EURAmount1 * $RevenueModelLog->Percentage / 100,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);
                    if ($this->revenue_auto_approve) {
                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                    }
                    // $Total_revenue = $RoyalCommissionUSD + $RoyalSpreadUSD;
                    /*if ($UserRevenuePayment->IsComplated == 0) 
                    {
                      $user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                      if ($user_balance != null) { 
                        $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
                        $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
                        $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
                        $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
                        $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
                        $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
                        $user_balance->save();
                      }
                      UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                          'IsCompleted' => 1
                      ]);
                      // $processedData++;
                    }*/
                    if ($User->ParentId != null) {
                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                    }
                  }
                }
                // FX Revenue Share - Revenue type = 6
                else if ($RevenueModelLog->RevenueTypeId == 6) {
                  // Get RevenueModel Log Id List
                  $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                  $PlatformLogin = $rw['PlatformLogin'];
                  $LeadsActivityDateRoyal = $rw['LeadsActivityDate'];
                  /*
                    Royal Revenue generate
                  */
                  $check_royal_revenu_pay = RoyalRevenue::with('LeadActivity')->whereHas('LeadActivity', function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal);
                  })->where('UserId', $User->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                  if ($check_royal_revenu_pay == null) {
                    /* Royal Revenue Calculation */
                    $RoyalCommissionAmount = $rw['RoyalCommissionUSD'];
                    $RoyalCommissionAUDAmount = $RoyalCommissionAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionEURAmount = $RoyalCommissionAmount * $CurrencyConvert->USDEUR;
                    // Royal Spread USD
                    $RoyalCommissionSpreadAmount = $rw['RoyalSpreadUSD'];
                    $RoyalCommissionSpreadAUDAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDAUD;
                    $RoyalCommissionSpreadEURAmount = $RoyalCommissionSpreadAmount * $CurrencyConvert->USDEUR;

                    $RoyalRevenuePayment = RoyalRevenue::Create([
                      'UserId' => $User->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $RoyalCommissionAmount,
                      'AUDAmount' => $RoyalCommissionAUDAmount,
                      'EURAmount' => $RoyalCommissionEURAmount,
                      'USDSpreadAmount' => $RoyalCommissionSpreadAmount,
                      'AUDSpreadAmount' => $RoyalCommissionSpreadAUDAmount,
                      'EURSpreadAmount' => $RoyalCommissionSpreadEURAmount,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);

                    // Royal Revenue in 3 currency
                    $RoyalCommissionUSD = $RoyalCommissionAmount + $RoyalCommissionSpreadAmount;
                    $RoyalCommissionAUD = $RoyalCommissionAUDAmount + $RoyalCommissionSpreadAUDAmount;
                    $RoyalCommissionEUR = $RoyalCommissionEURAmount + $RoyalCommissionSpreadEURAmount;
                    // Royal Revenue addition
                    $RoyalBalance = RoyalBalance::find(1);
                    $RoyalBalance->USDTotalRevenue = $RoyalBalance->USDTotalRevenue + $RoyalCommissionUSD;
                    $RoyalBalance->AUDTotalRevenue = $RoyalBalance->AUDTotalRevenue + $RoyalCommissionAUD;
                    $RoyalBalance->EURTotalRevenue = $RoyalBalance->EURTotalRevenue + $RoyalCommissionEUR;
                    $RoyalBalance->save();
                    /* End.Royal Revenue Calculation */
                    // $processedData++;
                  }
                  /*
                    End. Royal Revenue generate
                  */

                  $AccountId = $rw['AccountId'];
                  $LeadActivity = LeadActivity::where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal)->where('AccountId', $AccountId)->where('ProcessStatus', 2)->count();
                  /* $check_revenue_payment = UserRevenuePayment::with('LeadActivity')->whereHas('LeadActivity',  function ($leadAct) use ($PlatformLogin, $LeadsActivityDateRoyal, $AccountId) {
                    $leadAct->where('PlatformLogin', $PlatformLogin)->where('LeadsActivityDate', $LeadsActivityDateRoyal)->where('AccountId', $AccountId);
                  })->where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->first(); */
                  if ($LeadActivity == 0) {
                    // currency convert 
                    $USDAmount = $rw->AffCommissionUSD;
                    $AUDAmount = $rw->AffCommissionUSD * $CurrencyConvert->USDAUD;
                    $EURAmount = $rw->AffCommissionUSD * $CurrencyConvert->USDEUR;
                    // Spread amount
                    $USDAmount1 = $rw->AffSpreadUSD;
                    $AUDAmount1 = $rw->AffSpreadUSD * $CurrencyConvert->USDAUD;
                    $EURAmount1 = $rw->AffSpreadUSD * $CurrencyConvert->USDEUR;
                    $UserRevenuePayment = UserRevenuePayment::Create([
                      'UserId' => $campgin_revenutype->UserId,
                      'LeadId' => $LeadData['LeadId'],
                      'RevenueModelLogId' => $RevenueModelLog->RevenueModelLogId,
                      'LeadActivityId' => $rw->LeadActivityId,
                      'USDAmount' => $USDAmount,
                      'AUDAmount' => $AUDAmount,
                      'EURAmount' => $EURAmount,
                      'SpreadUSDAmount' => $USDAmount1,
                      'SpreadAUDAmount' => $AUDAmount1,
                      'SpreadEURAmount' => $EURAmount1,
                      'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                      'ActualRevenueDate' => $LeadsActivityDate,
                    ]);
                    if ($this->revenue_auto_approve) {
                      $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                    }
                    // $Total_revenue = $AffCommissionUSD + $AffSpreadUSD; // usd Total Revenue
                    /*if ($UserRevenuePayment->IsComplated == 0) {
                      $user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                      if ($user_balance != null) { 
                        $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
                        $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
                        $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;

                        $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
                        $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
                        $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
                        $user_balance->save();
                      }
                      UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                          'IsCompleted' => 1
                      ]);
                      // $processedData++;
                    }*/
                    if ($User->ParentId != null) {
                      $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                    }
                  }
                }
              }
            }
          }
        }
        LeadActivity::find($rw['LeadActivityId'])->update(['ProcessStatus' => 2]);
      }
      $remaining = LeadActivity::where('ProcessStatus', 0)->count();
      if ($remaining > 0) {
        $this->ProcessLeadActivity();
      }

      $Message = 'Data is processed successfully.';
      // $Message = 'Data is processed successfully. Total processed data '.count($lead_info).', total revenue generated '.$processedData.'.';
      // LeadInfoFile::where('LeadFileInfo', $fileid)->update([
      //   'Message'=> $Message
      // ]);
      return response()->json([
        'IsSuccess' => true,
        'Message' => $Message,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    } catch (exception $e) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    }
  }

  // call function ProcessLeadActivity()
  public function CallProcessLeadActivity(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          return $this->ProcessLeadActivity();
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => []
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
          'TotalCount' => 0,
          'Data' => []
        ], 200);
      }
    } catch (exception $e) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    }
  }

  // Process General Lead Info
  public function ProcessLeadInfo()
  {
    // $processedData = 0;
    try {
      $lead_info = LeadInformation::where('ProcessStatus', 0)->take(1000)->get();
      foreach ($lead_info as $rw) {
        $LeadStatus = LeadStatusMaster::where(['Status' => $rw['LeadStatus']])->first();
        $LeadStatusValid = LeadStatusMaster::where(['Status' => $rw['LeadStatus'], 'IsValid' => 1])->first();
        $LeadData = Lead::where('RefId', $rw['LeadId'])->first(); // get lead data from Refference Id 
        if ($LeadData) {
          try {
            $strdate = str_replace('/', '-', $rw['DateConverted']);
            $time = strtotime($strdate);
            if ($time) {
              try {
                $DateConverted = date('Y-m-d H:i:s', $time);
              } catch (exception $e) {
                $DateConverted = null;
              }
            } else {
              $DateConverted = null;
            }
          } catch (ParseException $e) {
            $DateConverted = null;
          }
          Lead::where('LeadId', $LeadData->LeadId)->update([
            'LeadStatus' => $rw['LeadStatus'],
            'IsActive' => $rw['IsActive'],
            'IsConverted' => $rw['IsConverted'],
            'DateConverted' => $DateConverted,
            'AccountId' => $rw['AccountId'],
            'SFAccountID' => $rw['SFAccountID'],
          ]);
          $LeadData = Lead::where('LeadId', $LeadData['LeadId'])->first(); // get lead data from 
          $campgin_revenutype = Campaign::find($LeadData['CampaignId']);
          if ($campgin_revenutype != null) {
            $revenu_model = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->orderBy('RevenueModelLogId', 'desc')->first();
            $User = User::where('UserId', $campgin_revenutype->UserId)->first();
            //return $revenu_model; die; 
            if ($revenu_model) {
              switch ($revenu_model->RevenueTypeId) {
                case '1':
                  if ($LeadStatus) {
                    $check_revenu_pay = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->first();
                    if ($check_revenu_pay == null) {
                      $LeadDate = date('Y-m-d H:i:s', strtotime($LeadData->CreatedAt));
                      $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $LeadDate)->orderBy('CurrencyRateId', 'desc')->first();
                      if ($CurrencyRate) {
                        $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                      } else {
                        $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $LeadDate)->orderBy('CurrencyRateId', 'desc')->first();
                        if ($CurrencyRate)
                          $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                        else
                          $CurrencyConvert = false;
                      }
                      if ($CurrencyConvert) {
                        // return "CurrencyConvert['CPL']"; die;
                        if ($revenu_model->CurrencyId == 1) {
                          $USDAmount = $revenu_model->Amount;
                          $AUDAmount = $revenu_model->Amount * $CurrencyConvert->USDAUD;
                          $EURAmount = $revenu_model->Amount * $CurrencyConvert->USDEUR;
                        } else if ($revenu_model->CurrencyId == 2) {
                          $USDAmount = $revenu_model->Amount * $CurrencyConvert->AUDUSD;
                          $AUDAmount = $revenu_model->Amount;
                          $EURAmount = $revenu_model->Amount * $CurrencyConvert->AUDEUR;
                        } else if ($revenu_model->CurrencyId == 3) {
                          $USDAmount = $revenu_model->Amount * $CurrencyConvert->EURUSD;
                          $AUDAmount = $revenu_model->Amount * $CurrencyConvert->EURAUD;
                          $EURAmount = $revenu_model->Amount;
                        }
                        $userpay1 = UserRevenuePayment::Create([
                          'UserId' => $campgin_revenutype->UserId,
                          'LeadId' => $LeadData['LeadId'],
                          'RevenueModelLogId' => $revenu_model->RevenueModelLogId,
                          'LeadInformationId' => $rw['LeadInformationId'],
                          'USDAmount' => $USDAmount,
                          'AUDAmount' => $AUDAmount,
                          'EURAmount' => $EURAmount,
                          'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                          'ActualRevenueDate' => $LeadData['CreatedAt'],
                        ]);
                        if ($this->revenue_auto_approve) {
                          $this->AffiliateRevenueAutoAccept($userpay1->UserRevenuePaymentId);
                        }
                        /*if($userpay1->IsComplated==0)
                        {
                          $user_balance = UserBalance::where('UserId',$campgin_revenutype->UserId)->first();
                          if($user_balance!=null) { 
                            $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $userpay1->USDAmount;
                            $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $userpay1->AUDAmount;
                            $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $userpay1->EURAmount;
                            $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $userpay1->USDAmount;
                            $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $userpay1->AUDAmount;
                            $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $userpay1->EURAmount;
                            $user_balance->save();
                          }
                          UserRevenuePayment::where('UserRevenuePaymentId',$userpay1->UserRevenuePaymentId)->update([
                              'IsCompleted'=>1
                          ]); 
                          // $processedData++;
                        }*/
                        LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
                        if ($User->ParentId != null) {
                          $this->getRevenueFromSubAffiliate($userpay1->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                        }
                      }
                    }
                  }
                  break;

                case '2':
                  if ($LeadStatusValid) {
                    $check_revenu_pay1 = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->first();
                    if ($check_revenu_pay1 == null) {
                      $LeadDate = date('Y-m-d H:i:s', strtotime($LeadData->CreatedAt));
                      $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $LeadDate)->orderBy('CurrencyRateId', 'desc')->first();
                      if ($CurrencyRate) {
                        $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                      } else {
                        $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $LeadDate)->orderBy('CurrencyRateId', 'desc')->first();
                        if ($CurrencyRate)
                          $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                        else
                          $CurrencyConvert = false;
                      }
                      if ($CurrencyConvert) {
                        // return "CurrencyConvert['C-CPL']"; die;
                        if ($revenu_model->CurrencyId == 1) {
                          $USDAmount = $revenu_model->Amount;
                          $AUDAmount = $revenu_model->Amount * $CurrencyConvert->USDAUD;
                          $EURAmount = $revenu_model->Amount * $CurrencyConvert->USDEUR;
                        } else if ($revenu_model->CurrencyId == 2) {
                          $USDAmount = $revenu_model->Amount * $CurrencyConvert->AUDUSD;
                          $AUDAmount = $revenu_model->Amount;
                          $EURAmount = $revenu_model->Amount * $CurrencyConvert->AUDEUR;
                        } else if ($revenu_model->CurrencyId == 3) {
                          $USDAmount = $revenu_model->Amount * $CurrencyConvert->EURUSD;
                          $AUDAmount = $revenu_model->Amount * $CurrencyConvert->EURAUD;
                          $EURAmount = $revenu_model->Amount;
                        }

                        $userpay = UserRevenuePayment::Create([
                          'UserId' => $campgin_revenutype->UserId,
                          'LeadId' => $LeadData['LeadId'],
                          'RevenueModelLogId' => $revenu_model->RevenueModelLogId,
                          'LeadInformationId' => $rw['LeadInformationId'],
                          'USDAmount' => $USDAmount,
                          'AUDAmount' => $AUDAmount,
                          'EURAmount' => $EURAmount,
                          'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                          'ActualRevenueDate' => $LeadData['CreatedAt'],
                        ]);
                        if ($this->revenue_auto_approve) {
                          $this->AffiliateRevenueAutoAccept($userpay->UserRevenuePaymentId);
                        }

                        /*if($userpay->IsComplated==0)
                        {
                          $user_balance = UserBalance::where('UserId',$campgin_revenutype->UserId)->first();
                          if($user_balance!=null) { 
                            $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $userpay->USDAmount;
                            $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $userpay->AUDAmount;
                            $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $userpay->EURAmount;
                            $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $userpay->USDAmount;
                            $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $userpay->AUDAmount;
                            $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $userpay->EURAmount;
                            $user_balance->save();
                          }
                          UserRevenuePayment::where('UserRevenuePaymentId',$userpay->UserRevenuePaymentId)->update([
                              'IsCompleted'=>1
                          ]);
                          // $processedData++;
                        }*/
                        LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
                        if ($User->ParentId != null) {
                          $this->getRevenueFromSubAffiliate($userpay->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                        }
                      }
                    }
                  }
                  break;

                case '3': 
                  if ($LeadData['IsConverted'] == 1 && $LeadData['DateConverted'] != NULL) {
                    $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $LeadData->DateConverted)->orderBy('CurrencyRateId', 'desc')->first();
                    if ($CurrencyRate) {
                      $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                    } else {
                      $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $LeadData->DateConverted)->orderBy('CurrencyRateId', 'desc')->first();
                      if ($CurrencyRate)
                        $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
                      else
                        $CurrencyConvert = false;
                    }
                    if ($CurrencyConvert) {
                      // Get RevenueModel Log Id List 
                      $RevenueModelLogIds = RevenueModelLog::where('RevenueModelId', $campgin_revenutype->RevenueModelId)->pluck('RevenueModelLogId');
                      // check User Revenue Payment already assign
                      $check_revenu_pay3 = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $LeadData['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->first();
                      if ($check_revenu_pay3 == null) {
                        // get Lead Information data
                        $LeadInformation = LeadInformation::where('LeadId', $LeadData['RefId'])->orderBy('LeadInformationId', 'desc')->first();
                        // get CountryId from Lead Information 
                        $CountryId = CountryMaster::where('CountryNameShortCode', $LeadInformation->Country)->first();
                        if ($CountryId) {
                          // get Revenue CPA Country group from log
                          $RevenueCpaCountryLog = RevenueCpaCountryLog::where('RevenueModelLogId', $revenu_model->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId->CountryId . ', RevenueCountrys)')->first();
                          // get all leads data of Lead Activity process status = 2 for count number of transactions of this country group
                          $Leads = Lead::with('LeadInformation')->whereHas(
                            'LeadInformation',
                            function ($qr) {
                              $qr->where('ProcessStatus', 2);
                            }
                          )->where('CampaignId', $LeadData['CampaignId'])->where('IsConverted', 1)->get();

                          $count = 1;
                          // check number of coutry group revenue payment already assigned
                          foreach ($Leads as $LeadsData) {
                            foreach ($LeadsData['LeadInformation'] as $LeadInformation) {
                              if ($LeadInformation['ProcessStatus'] == 2) {
                                $leadId = Lead::where('RefId', $LeadInformation['LeadId'])->first();
                                $UserRevenuePayment = UserRevenuePayment::where('UserId', $campgin_revenutype->UserId)->where('LeadId', $leadId['LeadId'])->whereIn('RevenueModelLogId', $RevenueModelLogIds)->get();
                                foreach ($UserRevenuePayment as $value) {
                                  $RevenueModelLog = RevenueModelLog::find($value['RevenueModelLogId']);
                                  $LeadInformation2 = LeadInformation::where('LeadId', $LeadInformation['LeadId'])->orderBy('LeadInformationId', 'desc')->first();
                                  $CountryId2 = CountryMaster::where('CountryNameShortCode', $LeadInformation2->Country)->first();
                                  if ($CountryId2) {
                                    $RevenueCpaCountryLog1 = RevenueCpaCountryLog::where('RevenueModelLogId', $RevenueModelLog->RevenueModelLogId)->whereRaw('FIND_IN_SET(' . $CountryId2->CountryId . ', RevenueCountrys)')->first();
                                    $CountryOld = explode(',', $RevenueCpaCountryLog1['RevenueCountrys']);
                                    $CountryNew = explode(',', $RevenueCpaCountryLog['RevenueCountrys']);
                                    $result = array_intersect($CountryOld, $CountryNew);
                                    // check new revenue country match with old payment
                                    if ($result) {
                                      $count++;
                                    }
                                  }
                                }
                              }
                            }
                          }
                          // return  'cpa-'.$count; die; 
                          $RevenueCpaTraderLog = RevenueCpaTraderLog::where('RevenueCpaCountryLogId', $RevenueCpaCountryLog->RevenueCpaCountryLogId)->get();
                          foreach ($RevenueCpaTraderLog as $value) {
                            // if Range Expression is -(in between two values)
                            if ($value['RangeExpression'] == 1) {
                              if ($count >= $value['RangeFrom'] && $count <= $value['RangeTo']) {
                                $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                if ($revenu_model->CurrencyId == 1) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                  $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                } else if ($revenu_model->CurrencyId == 2) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount;
                                  $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                } else if ($revenu_model->CurrencyId == 3) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                  $EURAmount = $RevenueCpaPlanLog->Amount;
                                }

                                $UserRevenuePayment = UserRevenuePayment::Create([
                                  'UserId' => $campgin_revenutype->UserId,
                                  'LeadId' => $LeadData['LeadId'],
                                  'RevenueModelLogId' => $revenu_model->RevenueModelLogId,
                                  'LeadInformationId' => $rw['LeadInformationId'],
                                  'USDAmount' => $USDAmount,
                                  'AUDAmount' => $AUDAmount,
                                  'EURAmount' => $EURAmount,
                                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                  'ActualRevenueDate' => $LeadData['DateConverted'],
                                ]);
                                if ($this->revenue_auto_approve) {
                                  $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                }
                                /*if($UserRevenuePayment->IsCompleted == 0) 
                                {
                                  $user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();
                                  if($user_balance) 
                                  {
                                    $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                    $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                    $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                    $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                    $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                    $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                    $user_balance->save(); 
                                  }
                                  UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                      'IsCompleted' => 1
                                  ]);
                                  // $processedData++;
                                }*/
                                LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
                                if ($User->ParentId != null) {
                                  $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                }
                              }
                            }
                            // if Range Expression is >(Greater than value)
                            if ($value['RangeExpression'] == 3) {
                              if ($count > $value['RangeFrom']) {
                                $RevenueCpaPlanLog = RevenueCpaPlanLog::where('RevenueCpaTraderLogId', $value['RevenueCpaTraderLogId'])->first();
                                // return $RevenueCpaPlanLog; die; 
                                if ($revenu_model->CurrencyId == 1) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDAUD;
                                  $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->USDEUR;
                                } else if ($revenu_model->CurrencyId == 2) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDUSD;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount;
                                  $EURAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->AUDEUR;
                                } else if ($revenu_model->CurrencyId == 3) {
                                  $USDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURUSD;
                                  $AUDAmount = $RevenueCpaPlanLog->Amount * $CurrencyConvert->EURAUD;
                                  $EURAmount = $RevenueCpaPlanLog->Amount;
                                }
                                $UserRevenuePayment = UserRevenuePayment::Create([
                                  'UserId' => $campgin_revenutype->UserId,
                                  'LeadId' => $LeadData['LeadId'],
                                  'RevenueModelLogId' => $revenu_model->RevenueModelLogId,
                                  'LeadInformationId' => $rw['LeadInformationId'],
                                  'USDAmount' => $USDAmount,
                                  'AUDAmount' => $AUDAmount,
                                  'EURAmount' => $EURAmount,
                                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                                  'ActualRevenueDate' => $LeadData['DateConverted'],
                                ]);
                                if ($this->revenue_auto_approve) {
                                  $this->AffiliateRevenueAutoAccept($UserRevenuePayment->UserRevenuePaymentId);
                                }
                                /*if($UserRevenuePayment->IsCompleted == 0) 
                                {
                                  $user_balance = UserBalance::where('UserId', $campgin_revenutype->UserId)->first();

                                  if($user_balance) { 
                                    $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                                    $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                                    $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                                    $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                                    $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                                    $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                                    $user_balance->save(); 
                                  }
                                  UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                                    'IsCompleted' => 1
                                  ]);
                                  // $processedData++;
                                }*/
                                LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
                                if ($User->ParentId != null) {
                                  $this->getRevenueFromSubAffiliate($UserRevenuePayment->UserRevenuePaymentId, $CurrencyConvert['CurrencyConvertId']);
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                  break;

                default:
                  break;
              }
            }
          }
        }
        LeadInformation::where('LeadInformationId', $rw['LeadInformationId'])->update(['ProcessStatus' => 2]);
      }
      $remaining = LeadInformation::where('ProcessStatus', 0)->count();
      if ($remaining > 0) {
        $this->ProcessLeadInfo();
      }

      $Message = 'Data is processed successfully.';
      /*$Message = 'Data is processed successfully. Total processed data '.count($lead_info).', total revenue generated '.$processedData.'.';
      $Message = 'Data is processed successfully. Total processed data '.$count.', total revenue generated '.$processedData.', duplicate data '.$exist.', new data '.$new.'.';
      LeadInfoFile::where('LeadFileInfo', $fileid)->update([
        'Message'=> $Message
      ]);*/
      return response()->json([
        'IsSuccess' => true,
        'Message' => $Message,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    } catch (exception $e) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    }
  }

  // call function ProcessLeadInfo()
  public function CallProcessLeadInfo(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          return $this->ProcessLeadInfo();
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            'TotalCount' => 0,
            'Data' => []
          ], 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
          'TotalCount' => 0,
          'Data' => []
        ], 200);
      }
    } catch (exception $e) {
      return response()->json([
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ], 200);
    }
  }

  // Sub-affiliate revenue generate
  public function GetRevenueFromSubAffiliate($UserRevenuePaymentId, $CurrencyConvertId)
  {
    $UserRevenuePayment =  UserRevenuePayment::find($UserRevenuePaymentId);
    $UserId = $UserRevenuePayment->UserId;  // dynamic
    $First_user = User::where('UserId', $UserId)->first();
    if ($First_user->ParentId != null) {
      $new_array = [];
      $parentUsers = DB::select("SELECT T2.UserId, T2.FirstName
                  FROM(SELECT @r AS _id, (SELECT @r := ParentId FROM users WHERE UserId = _id) AS parent_id, @l := @l + 1 AS lvl FROM (SELECT @r := $First_user->UserId, @l := 0) vars,users h
                  WHERE @r <> 0) T1
                  JOIN users T2 ON T1._id = T2.UserId
                  ORDER BY T1.lvl");

      $amt = $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;

      $SubAffiliateId = $First_user->UserId;  // parent user Id
      $parent_user = User::where('UserId', $SubAffiliateId)->first(); // parent user

      $CurrencyConvert = CurrencyConvert::find($CurrencyConvertId);

      foreach ($parentUsers as $rw) {
        if ($rw->UserId != $First_user->UserId) {
          $Get_revenue_Data = UserRevenueType::where('UserId', $rw->UserId)->where('RevenueTypeId', 8)->get();
          if ($Get_revenue_Data->count() >= 1) {
            foreach ($Get_revenue_Data as $Get_revenue_type) {
              $get_revenue_model = RevenueModelLog::where('RevenueModelId', $Get_revenue_type->RevenueModelId)->orderBy('RevenueModelLogId', 'desc')->first();

              if ($get_revenue_model != null) {                
                $amount = $amt * $get_revenue_model->Percentage / 100;
                $amt = $amount;
                $UserRevenuePaymentId = $UserRevenuePayment->UserRevenuePaymentId;

                $USDAmount = $amount;
                $AUDAmount = $amount * $CurrencyConvert->USDAUD;
                $EURAmount = $amount * $CurrencyConvert->USDEUR;

                $subrevenupayment = UserSubRevenue::create([
                  'UserId' => $rw->UserId,
                  'UserRevenuePaymentId' => $UserRevenuePaymentId,
                  'USDAmount' => $USDAmount,
                  'AUDAmount' => $AUDAmount,
                  'EURAmount' => $EURAmount,
                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                ]);
                // $user_balance = UserBalance::where('UserId',$subrevenupayment->UserId)->first();
                /*if($user_balance!=null) {     
                  $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $subrevenupayment->USDAmount;
                  $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $subrevenupayment->AUDAmount;
                  $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $subrevenupayment->EURAmount;
                  $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $subrevenupayment->USDAmount;
                  $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $subrevenupayment->AUDAmount;
                  $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $subrevenupayment->EURAmount;
                  $user_balance->save();
                }*/
                $userpay1 = UserRevenuePayment::create([
                  'UserId' => $subrevenupayment->UserId,
                  'LeadId' => $UserRevenuePayment->LeadId,
                  'RevenueModelLogId' => $get_revenue_model->RevenueModelLogId,
                  'UserSubRevenueId' => $subrevenupayment->UserSubRevenueId,
                  'USDAmount' => $subrevenupayment->USDAmount,
                  'AUDAmount' => $subrevenupayment->AUDAmount,
                  'EURAmount' => $subrevenupayment->EURAmount,
                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                  'ActualRevenueDate' => $UserRevenuePayment->ActualRevenueDate,
                ]);
                if ($this->revenue_auto_approve) {
                  $this->AffiliateRevenueAutoAccept($userpay1->UserRevenuePaymentId);
                }

                if ($get_revenue_model->Type == 1) {
                  // Debit from sub affiliate(From)
                  $subrevenupaymentdr = UserSubRevenue::create([
                    'UserId' => $SubAffiliateId,
                    'UserRevenuePaymentId' => $UserRevenuePaymentId,
                    'USDAmount' => -$USDAmount,
                    'AUDAmount' => -$AUDAmount,
                    'EURAmount' => -$EURAmount,
                    'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                  ]);
                  // $user_balance = UserBalance::where('UserId', $subrevenupaymentdr->UserId)->first();
                  /*if($user_balance != null) { 
                    $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $subrevenupaymentdr->USDAmount;
                    $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $subrevenupaymentdr->AUDAmount;
                    $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $subrevenupaymentdr->EURAmount;
                    $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $subrevenupaymentdr->USDAmount;
                    $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $subrevenupaymentdr->AUDAmount;
                    $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $subrevenupaymentdr->EURAmount;
                    $user_balance->save();
                  }*/

                  $userpay2 = UserRevenuePayment::create([
                    'UserId' => $subrevenupaymentdr->UserId,
                    'LeadId' => $UserRevenuePayment->LeadId,
                    'RevenueModelLogId' => $get_revenue_model->RevenueModelLogId,
                    'UserSubRevenueId' => $subrevenupaymentdr->UserSubRevenueId,
                    'USDAmount' => $subrevenupaymentdr->USDAmount,
                    'AUDAmount' => $subrevenupaymentdr->AUDAmount,
                    'EURAmount' => $subrevenupaymentdr->EURAmount,
                    'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                    'ActualRevenueDate' => $UserRevenuePayment->ActualRevenueDate,
                  ]);
                  if ($this->revenue_auto_approve) {
                    $this->AffiliateRevenueAutoAccept($userpay2->UserRevenuePaymentId);
                  }
                } else {
                  // debit from royal revenue(on top of)
                  $subrevenupaymentdr = RoyalRevenue::Create([
                    'UserRevenuePaymentId' => $UserRevenuePayment->UserRevenuePaymentId,
                    'UserId' => $rw->UserId,
                    'LeadId' => $UserRevenuePayment->LeadId,
                    'RevenueModelLogId' => $get_revenue_model->RevenueModelLogId,
                    'LeadActivityId' => $UserRevenuePayment->LeadActivityId,
                    'USDAmount' => -$USDAmount,
                    'AUDAmount' => -$AUDAmount,
                    'EURAmount' => -$EURAmount,
                    'USDSpreadAmount' => 0,
                    'AUDSpreadAmount' => 0,
                    'EURSpreadAmount' => 0,
                    'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                    'ActualRevenueDate' => $UserRevenuePayment->ActualRevenueDate,
                  ]);
                }
                $SubAffiliateId = $rw->UserId;
                $UserRevenuePayment = $userpay1;
              }
            }
          }
        }
      }
    }
  }

  // Auto revenue approve if set by admin
  public function AffiliateRevenueAutoAccept($UserRevenuePaymentId)
  {
    // return $request->all();
    // $UserRevenuePaymentId = $request->UserRevenuePaymentId;
    $UserRevenuePayment = UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePaymentId)->where('PaymentStatus', 0)->first();
    if ($UserRevenuePayment) {
      // update user balance
      $user_balance = UserBalance::where('UserId', $UserRevenuePayment->UserId)->first();
      if ($user_balance) {
        $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
        $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
        $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
        $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount + $UserRevenuePayment->SpreadUSDAmount;
        $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount + $UserRevenuePayment->SpreadAUDAmount;
        $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount + $UserRevenuePayment->SpreadEURAmount;
        $user_balance->save();
        UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
          'PaymentStatus' => 1
        ]);
      }
    }
  }

  /*
    Upload sheet
      1.General Lead Information &&
      2.Daily Lead Activity
  */
  public function UploadLeadActivitySheet(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          // default set 0
          $count = 0;
          $new = 0;
          $exist = 0;
          $InputData = json_decode($request->LeadFlag);
          // General Lead Information
          if ($InputData->flag == 1) {
            if ($request->hasFile('LeadActivity')) {
              // $validator = Validator::make($request->all(), [
              //   'LeadActivity' => 'required|mimes:xlsx,xlsb,xlsm,xls',
              // ]);
              $validator = Validator::make(
                [
                  'file'      => $request->file('LeadActivity'),
                  'extension' => strtolower($request->file('LeadActivity')->getClientOriginalExtension()),
                ],
                [
                  'file'          => 'required',
                  'extension'      => 'required|in:xlsx,xlsb,xlsm,xls',
                ]
              );

              if ($validator->fails()) {
                return response()->json([
                  'IsSuccess' => false,
                  'Message' => 'File validation error occurred. Please upload valid file format.',
                  "TotalCount" => count($validator->errors()),
                  "Data" => array('Error' => $validator->errors())
                ], 200);
              }

              $LeadActivity = $request->file('LeadActivity');
              $name = 'LeadInformation-' . '' . time() . '.' . $LeadActivity->getClientOriginalExtension();
              $destinationPath = storage_path('/app/import/LeadInformation');
              $LeadActivity->move($destinationPath, $name);
              $full_path = 'storage/app/import/LeadInformation/' . $name;
              $data = Excel::load($full_path)->formatDates(true)->get();
              $UploadFileKeys = (($data->first())->keys())->toArray();
              $keyArray = array(
                'lead_id',
                'lead_status',
                'country',
                'is_converted',
                'is_active',
                'date_converted',
                'account_id',
                'sf_account_id',
              );
              $arrayDiff = array_diff($keyArray, $UploadFileKeys);

              if (empty($arrayDiff)) {
                $LeadFile = LeadInfoFile::Create([
                  "FileName" => $name,
                  "LeadFileFlage" => $InputData->flag,
                  "CreatedBy" => $log_user->UserId,
                ]);
                $count = 0;
                foreach ($data as $key => $val) {
                  if ($val->lead_id != '') {
                    $arr  = array([
                      'LeadFileInfo' => $LeadFile->LeadFileInfo,
                      'LeadId' => $val->lead_id,
                      'LeadStatus' => $val->lead_status,
                      'Country' => $val->country,
                      'IsConverted' => $val->is_converted,
                      'IsActive' => $val->is_active,
                      'DateConverted' => $val->date_converted,
                      'AccountId' => $val->account_id,
                      'SFAccountID' => $val->sf_account_id,
                    ]);
                    $sheet = LeadInformation::insert($arr);
                    $count = $count + 1;
                  }
                }
                $fileid = $LeadFile->LeadFileInfo;
                return $this->ProcessLeadInfo();

                return response()->json([
                  'IsSuccess' => true,
                  'Message' => 'File uploaded successfully. Total data import ' . $count . '.',
                  'TotalCount' => $count,
                  "Data" => array('File' => $name)
                ], 200);
              } else {
                $mismatchKey = implode(',', $arrayDiff);
                return response()->json([
                  'IsSuccess' => false,
                  'Message' => 'Uploaded file key mismatch. Please check ' . $mismatchKey,
                  "TotalCount" => 0,
                  "Data" => array('File' => $name)
                ], 200);
              }
            } else {
              return response()->json([
                'IsSuccess' => false,
                'Message' => 'Please upload file.',
                "TotalCount" => 0,
                "Data" => array()
              ], 200);
            }
          }
          // Daily Lead Activity
          else if ($InputData->flag == 2) {
            if ($request->hasFile('LeadActivity')) {
              // $validator = Validator::make($request->all(), [
              //   'LeadActivity' => 'required|mimes:xlsx,xlsb,xlsm,xls',
              // ]);
              $validator = Validator::make(
                [
                  'file'      => $request->file('LeadActivity'),
                  'extension' => strtolower($request->file('LeadActivity')->getClientOriginalExtension()),
                ],
                [
                  'file'          => 'required',
                  'extension'      => 'required|in:xlsx,xlsb,xlsm,xls',
                ]
              );
              if ($validator->fails()) {
                return response()->json([
                  'IsSuccess' => false,
                  'Message' => 'File validation error occurred. Please upload valid file format.',
                  "TotalCount" => count($validator->errors()),
                  "Data" => array('Error' => $validator->errors())
                ], 200);
              }
              $LeadActivity = $request->file('LeadActivity');
              $name = 'LeadActivity-' . '' . time() . '.' . $LeadActivity->getClientOriginalExtension();
              $destinationPath = storage_path('/app/import/LeadActivity');
              $LeadActivity->move($destinationPath, $name);
              $full_path = 'storage/app/import/LeadActivity/' . $name;
              $data = Excel::load($full_path)->formatDates(true)->get();
              $UploadFileKeys = (($data->first())->keys())->toArray();
              $keyArray = array(
                'date',
                'account_id',
                'platform_login',
                'base_currency',
                'volume_traded',
                'number_of_transactions',
                'deposits_base_currency',
                'deposits_usd',
                'deposits_affiliate_currency',
                'royal_commission_base_currency',
                'royal_commission_usd',
                'royal_commission_affilliate_currency',
                'royal_spread_base_currency',
                'royal_spread_usd',
                'royal_spread_affiliate_currency',
                'affiliate_commission_base_currency',
                'affiliate_commission_usd',
                'affiliate_commission_affilliate_currency',
                'affiliate_spread_base_currency',
                'affiliate_spread_usd',
                'affiliate_spread_affiliate_currency',
              );
              $arrayDiff = array_diff($keyArray, $UploadFileKeys);
              if (empty($arrayDiff)) {
                $LeadActivityFile = LeadInfoFile::Create([
                  "FileName" => $name,
                  "LeadFileFlage" => $InputData->flag,
                  "CreatedBy" => $log_user->UserId,
                ]);
                foreach ($data as $key => $val) {
                  if ($val->account_id != '') {
                    $sheet = LeadActivity::Create([
                      'LeadFileInfo' => $LeadActivityFile->LeadFileInfo,
                      'LeadsActivityDate' => $val->date,
                      'AccountId' => $val->account_id,
                      'PlatformLogin' => $val->platform_login,
                      'BaseCurrency' => $val->base_currency,
                      'VolumeTraded' => $val->volume_traded,
                      'NumberOfTransactions' => $val->number_of_transactions,
                      'DepositsBaseCurrency' => $val->deposits_base_currency,
                      'DepositsUSD' => $val->deposits_usd,
                      'DepositsAffCur' => $val->deposits_affiliate_currency,
                      'RoyalCommissionBaseCur' => $val->royal_commission_base_currency,
                      'RoyalCommissionUSD' => $val->royal_commission_usd,
                      'RoyalCommissionAffCur' => $val->royal_commission_affilliate_currency,
                      'RoyalSpreadBaseCur' => $val->royal_spread_base_currency,
                      'RoyalSpreadUSD' => $val->royal_spread_usd,
                      'RoyalSpreadAffCur' => $val->royal_spread_affiliate_currency,
                      'AffCommissionBaseCur' => $val->affiliate_commission_base_currency,
                      'AffCommissionUSD' => $val->affiliate_commission_usd,
                      'AffCommissionAffCur' => $val->affiliate_commission_affilliate_currency,
                      'AffSpreadBaseCur' => $val->affiliate_spread_base_currency,
                      'AffSpreadUSD' => $val->affiliate_spread_usd,
                      'AffSpreadAffCur' => $val->affiliate_spread_affiliate_currency,
                    ]);
                    $count = $count + 1;
                  }
                }
                // $fileid = $LeadActivityFile->LeadFileInfo;
                return $this->ProcessLeadActivity();

                return response()->json([
                  'IsSuccess' => true,
                  'Message' => 'File uploaded successfully.',
                  'TotalCount' => $count,
                  "Data" => array('File' => $name)
                ], 200);
              } else {
                $mismatchKey = implode(',', $arrayDiff);
                return response()->json([
                  'IsSuccess' => false,
                  'Message' => 'Uploaded file key mismatch. Please check ' . $mismatchKey,
                  "TotalCount" => 0,
                  "Data" => array('File' => $name)
                ], 200);
              }
            } else {
              return response()->json([
                'IsSuccess' => false,
                'Message' => 'Please upload file.',
                "TotalCount" => 0,
                "Data" => array()
              ], 200);
            }
          } else {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Please input valid flag.',
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
          'Message' => 'Invalid Token.',
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

  // geting all list of general lead information files
  public function GetLeadInformationFiles(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $LeadInformationFile = LeadInfoFile::where('LeadFileFlage', 1)->orderBy('LeadFileInfo', 'desc')->get();
          $FilterData = [];
          if ($request->TimeZoneOffSet && $request->TimeZoneOffSet != '')
            $TimeZoneOffSet = $request->TimeZoneOffSet;
          else
            $TimeZoneOffSet = 0;
          foreach ($LeadInformationFile as $value) {
            $full_path = $this->storage_path . 'app/import/LeadInformation/' . $value['FileName'];
            $arrayFilter = [
              "Id" => $value['LeadFileInfo'],
              "FileName" => $value['FileName'],
            ];
            array_push($FilterData, $arrayFilter);
          }
          if (isset($request->FilterId) && $request->FilterId != '') {
            $LeadInformationFile = LeadInformationFile::where('LeadFileInfo', $request->FilterId)->get();
          }
          $files = [];
          foreach ($LeadInformationFile as $value) {
            $full_path = $this->storage_path . 'app/import/LeadInformation/' . $value['FileName'];
            $array = [
              "FileName" => $value['FileName'],
              "FilePath" => $full_path,
              'CreatedAt' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt'])))
            ];
            array_push($files, $array);
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Get list successfully.',
            'TotalCount' => 0,
            'Data' => array('FilterData' => $FilterData, 'List' => $files)
          ], 200);
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
          'Message' => 'Invalid token.',
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

  // geting all list of daily lead activity files
  public function GetLeadActivityFiles(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $LeadInformationFile = LeadInfoFile::where('LeadFileFlage', 2)->orderBy('LeadFileInfo', 'desc')->get();
          $FilterData = [];
          if ($request->TimeZoneOffSet && $request->TimeZoneOffSet != '')
            $TimeZoneOffSet = $request->TimeZoneOffSet;
          else
            $TimeZoneOffSet = 0;
          foreach ($LeadInformationFile as $value) {
            $full_path = $this->storage_path . 'app/import/LeadActivity/' . $value['FileName'];
            $arrayFilter = [
              "Id" => $value['LeadFileInfo'],
              "FileName" => $value['FileName'],
            ];
            array_push($FilterData, $arrayFilter);
          }
          if (isset($request->FilterId) && $request->FilterId != '') {
            $LeadInformationFile = LeadActivityFile::where('LeadFileInfo', $request->FilterId)->get();
          }
          $files = [];
          foreach ($LeadInformationFile as $value) {
            $full_path = $this->storage_path . 'app/import/LeadActivity/' . $value['FileName'];
            $array = [
              "FileName" => $value['FileName'],
              "FilePath" => $full_path,
              'CreatedAt' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt'])))
            ];
            array_push($files, $array);
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Get list successfully.',
            'TotalCount' => 0,
            'Data' => array('FilterData' => $FilterData, 'List' => $files)
          ], 200);
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
          'Message' => 'Invalid token.',
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

  // geting all list of daily lead activity files
  public function GetCurrencyRateFiles(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $LeadInformationFile = LeadInfoFile::where('LeadFileFlage', 3)->orderBy('LeadFileInfo', 'desc')->get();
          $FilterData = [];
          if ($request->TimeZoneOffSet && $request->TimeZoneOffSet != '')
            $TimeZoneOffSet = $request->TimeZoneOffSet;
          else
            $TimeZoneOffSet = 0;
          foreach ($LeadInformationFile as $value) {
            $full_path = $this->storage_path . 'app/import/CurrencyConvert/' . $value['FileName'];
            $arrayFilter = [
              "Id" => $value['LeadFileInfo'],
              "FileName" => $value['FileName'],
            ];
            array_push($FilterData, $arrayFilter);
          }
          if (isset($request->FilterId) && $request->FilterId != '') {
            $LeadInformationFile = LeadInfoFile::where('LeadFileInfo', $request->FilterId)->get();
          }
          $files = [];
          foreach ($LeadInformationFile as $value) {
            $full_path = $this->storage_path . 'app/import/CurrencyConvert/' . $value['FileName'];
            $array = [
              "FileName" => $value['FileName'],
              "FilePath" => $full_path,
              'CreatedAt' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt'])))
            ];
            array_push($files, $array);
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Get list successfully.',
            'TotalCount' => 0,
            'Data' => array('FilterData' => $FilterData, 'List' => $files)
          ], 200);
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
          'Message' => 'Invalid token.',
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

  // geting all list of daily lead activity files
  public function GetImportFiles(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('Token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $LeadInformationFile = LeadInfoFile::orderBy('LeadFileInfo', 'desc')->get();
          $FilterData = [];
          if ($request->TimeZoneOffSet && $request->TimeZoneOffSet != '')
            $TimeZoneOffSet = $request->TimeZoneOffSet;
          else
            $TimeZoneOffSet = 0;
          foreach ($LeadInformationFile as $value) {
            $arrayFilter = [
              "Id" => $value['LeadFileInfo'],
              "FileName" => $value['FileName'],
            ];
            array_push($FilterData, $arrayFilter);
          }
          if (isset($request->FilterId) && $request->FilterId != '') {
            $LeadInformationFile = LeadInfoFile::where('LeadFileInfo', $request->FilterId)->get();
          }
          $typeFilter = array(
            array("id" => 1, "Title" => "Lead Information"),
            array("id" => 2, "Title" => "Lead Activity"),
            array("id" => 3, "Title" => "Currency Conversion")
          );
          $files = [];
          foreach ($LeadInformationFile as $value) {
            if ($value['LeadFileFlage'] == 1) {
              $fileType = 'Lead Information';
              $full_path = $this->storage_path . 'app/import/LeadInformation/' . $value['FileName'];
            } else if ($value['LeadFileFlage'] == 2) {
              $fileType = 'Lead Activity';
              $full_path = $this->storage_path . 'app/import/LeadActivity/' . $value['FileName'];
            } else {
              $fileType = 'Currency Conversion';
              $full_path = $this->storage_path . 'app/import/CurrencyConvert/' . $value['FileName'];
            }
            $array = [
              "FileId" => $value['LeadFileInfo'],
              "FileName" => $value['FileName'],
              "FileType" => $fileType,
              "FilePath" => $full_path,
              'CreatedAt' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt'])))
            ];
            array_push($files, $array);
          }
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Get list successfully.',
            'TotalCount' => 0,
            'Data' => array('FilterData' => $FilterData, 'typeFilter' => $typeFilter, 'List' => $files)
          ], 200);
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
          'Message' => 'Invalid token.',
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
}
