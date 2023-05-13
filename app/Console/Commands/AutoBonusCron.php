<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\RevenueModel;
use App\RevenueModelLog;
use App\UserRevenuePayment;
use App\User;
use App\Lead;
use App\LeadActivity;
use App\UserBalance;
use App\UserBonus;
use DateTime;
use App\ErrorLog;

class AutoBonusCron extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'auto:bonus';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Auto Bonus Cron';

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
      ErrorLog::Create(['Request'=>'Auto Bonus Cron', 'LogError'=>'Auto Bonus Cron Test']);
      $ToDay = date('Y-m-d');
      $BufferDay = date('Y-m-d', strtotime('-5 day'));
      // $this->info('Total bonus generate ' . $BufferDay);
      // $UserBonusList = UserBonus::where('NextBonusDate', $ToDay)->where('Type', 1)->get();
      $UserBonusList = UserBonus::whereDate('NextBonusDate', '<=', $ToDay)->whereDate('NextBonusDate', '>=', $BufferDay)->where('Type', 1)->get();
      $count = 0;
      $this->info('Total bonus generate ' . count($UserBonusList));
      die;
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
      $this->info('Total bonus generate ' . $count);
    } catch (exception $e) {
      ErrorLog::Create(['Request'=>'Auto Bonus Cron', 'LogError'=>$e]);
      $this->info('Exception : ' . $e);
    }
  }
}
