<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\CurrencyRate;
use App\LeadInfoFile;
use App\CurrencyConvert;
use Maatwebsite\Excel\Facades\Excel;
use App\ErrorLog;
use Illuminate\Support\Facades\Storage;

class CurrencyCron extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'df:currency';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Currency Convertion Cron';


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
      ErrorLog::Create(['Request' => 'Auto Currency Cron', 'LogError' => 'Auto Currency Cron Test']);
      $count = 0;
      $ImportFiles = Storage::allFiles('upload/CurrencyConvert');
      foreach ($ImportFiles as $key => $value) {
        $newfile = 'storage/app/' . $value;
        $info = pathinfo($newfile);
        $ext = $info['extension'];
        // $filename = $info['filename']; 
        $name = 'CurrencyConvert-' . '' . time() . '.' . $ext;
        $newpath = '/import/CurrencyConvert/' . $name;
        Storage::move($value, $newpath); // move file upload folder to import
        $full_path = 'storage/app/import/CurrencyConvert/' . $name;
        // $full_path = storage_path('app/import/LeadInformation/'.$name);

        if ($ext == 'xlsx' || $ext == 'xlsb' || $ext == 'xlsm' || $ext == 'xls') {
          $data = Excel::load($full_path)->formatDates(true)->get();
          $UploadFileKeys = (($data->first())->keys())->toArray();
          $keyArray = array(
            'audusd',
            'eurusd',
            'date',
          );
          $arrayDiff = array_diff($keyArray, $UploadFileKeys);
          if (empty($arrayDiff)) {
            $LeadFile = LeadInfoFile::Create([
              "FileName" => $name,
              "Message" => 'Currency convert utility',
              "LeadFileFlage" => 3,
              "CreatedBy" => 1,
            ]);
            $count = 0;
            foreach ($data as $key => $val) {
              if (isset($val->audusd) && $val->audusd != '' && isset($val->eurusd) && $val->eurusd != '') {
                $CurrencyRate = CurrencyRate::where('date', date('Y-m-d h:i:s', strtotime($val->date)))->first();
                if ($CurrencyRate == null) {
                  $NewRate = CurrencyRate::Create([
                    'LeadFileInfo' => $LeadFile->LeadFileInfo,
                    'AUDUSD' => $val->audusd,
                    'EURUSD' => $val->eurusd,
                    'Date' => date('Y-m-d h:i:s', strtotime($val->date)),
                    'Status' => 1,
                    "CreatedBy" => 1,
                  ]);
                  if ($NewRate) {
                    $AUDUSD = $NewRate->AUDUSD;
                    $EURUSD = $NewRate->EURUSD;
                    $USDAUD = round(1 / $AUDUSD, 10);
                    $EURAUD = round($EURUSD / $AUDUSD, 10);
                    $USDEUR = round(1 / $EURUSD, 10);
                    $AUDEUR = round(1 / $EURAUD, 10);
                    // CurrencyConvert Insert record
                    CurrencyConvert::insert(['CurrencyRateId' => $NewRate->CurrencyRateId, 'USDAUD' => $USDAUD, 'USDEUR' => $USDEUR, 'AUDUSD' => $AUDUSD, 'AUDEUR' => $AUDEUR, 'EURUSD' => $EURUSD, 'EURAUD' => $EURAUD]);
                  }
                  $count = $count + 1;
                }
              }
            }
          }
        }
      }
      $this->info('Currency updated successfully. Total import ' . $count . ' rate.');
    } catch (exception $e) {
      ErrorLog::Create(['Request' => 'Auto Currency Cron', 'LogError' => $e]);
      $this->info('Exception: ' . $e);
    }
  }
}
