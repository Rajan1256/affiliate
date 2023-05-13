<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\RevenueModelLog;
use App\UserRevenuePayment;
use App\User;
use App\Lead;
use App\LeadActivity;
use App\LeadStatusMaster;
use App\Campaign;
use App\CurrencyConvert;
use App\CurrencyRate;
use App\RoyalRevenue;
use App\LeadInformation;
use App\ErrorLog;

class AutoRevenueCron extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'auto:revenue';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Auto Revenue Cron';


  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return mixed
   */
  public function handle()
  {
    try {
      ErrorLog::Create(['Request' => 'Auto Revenue Cron', 'LogError' => 'Auto Revenue Cron Test']);
      $lead_info = LeadInformation::where('ProcessStatus', 0)->get();
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
                  // return 'cpa'; die;
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

      $lead_info = LeadActivity::where('ProcessStatus', 0)->get();
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
      // return true 
      $this->info('Revenue generated successfully');
    } catch (exception $e) {
      ErrorLog::Create(['Request' => 'Auto Revenue Cron', 'LogError' => $e]);
      $this->info('Exception : ' . $e);
    }
  }
}
