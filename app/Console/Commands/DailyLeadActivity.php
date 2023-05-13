<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\LeadActivity;
use App\LeadInfoFile;
use Illuminate\Support\Facades\Storage;
use App\ErrorLog;

class DailyLeadActivity extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'upload:DailyLeadActivity';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Daily Lead Activity Cron';


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
      ErrorLog::Create(['Request'=>'Auto Daily Lead Activity Cron', 'LogError'=>'Auto Daily Lead Activity Cron Test']);
      $count = 0;
      $ImportFiles = Storage::allFiles('upload/LeadActivity');
      foreach ($ImportFiles as $key => $value) {
        $newfile = 'storage/app/' . $value;
        $info = pathinfo($newfile);
        $ext = $info['extension'];
        $name = 'LeadActivity-' . time() . '.' . $ext;
        $newpath = '/import/LeadActivity/' . $name;
        Storage::move($value, $newpath); // move file upload folder to import
        $full_path = 'storage/app/import/LeadActivity/' . $name;
        // $full_path = storage_path('app/import/LeadInformation/'.$name);

        if ($ext == 'xlsx' || $ext == 'xlsb' || $ext == 'xlsm' || $ext == 'xls') {
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
              "Message" => 'Daily lead activity utility',
              "LeadFileFlage" => 2,
              "CreatedBy" => 1,
            ]);
            foreach ($data as $key => $val) {
              if ($val->account_id != '') {
                LeadActivity::Create([
                  'LeadFileInfo' => $LeadActivityFile->LeadFileInfo,
                  'LeadsActivityDate' => date('Y-m-d H:i:s', strtotime($val->date)),
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
          }
        }
      }
      $this->info('Successfully daily lead activity file uploaded. Total file:'. $count);
    } catch (exception $e) {
      ErrorLog::Create(['Request' => 'Auto Daily Lead Activity Cron', 'LogError' => $e]);
      $this->info('Exception: ' . $e);
    }
  }
}