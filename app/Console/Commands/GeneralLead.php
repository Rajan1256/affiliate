<?php

namespace App\Console\Commands;
use App\Http\Controllers\Controller;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\LeadInformation;
use App\LeadInfoFile;
use Illuminate\Support\Facades\Storage;
use App\ErrorLog;

class GeneralLead extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'upload:GeneralLeadInfo';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'General Lead Information Cron';


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
      ErrorLog::Create(['Request'=>'Auto General Lead Info Cron', 'LogError'=>'Auto General Lead Info Cron Test']);
      $count = 0;
      $ImportFiles = Storage::allFiles('upload/LeadInformation');
      foreach ($ImportFiles as $value) {
        $newfile = 'storage/app/' . $value;
        $info = pathinfo($newfile);
        $ext = $info['extension'];
        // $filename = $info['filename'];
        $name = 'LeadInformation-' . '' . time() . '.' . $ext;
        $newpath = '/import/LeadInformation/' . $name;
        Storage::move($value, $newpath); // move file upload folder to import
        $full_path = 'storage/app/import/LeadInformation/' . $name;
        // $full_path = storage_path('app/import/LeadInformation/'.$name);

        if ($ext == 'xlsx' || $ext == 'xlsb' || $ext == 'xlsm' || $ext == 'xls') {
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
              "Message" => 'General lead information utility',
              "LeadFileFlage" => 1,
              "CreatedBy" => 1,
            ]);
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
                LeadInformation::insert($arr);
                $count = $count + 1;
              }
            }
          }
        }
      }
      $this->info('Successfully general lead information file uploaded. Total file:'. $count); 
    } catch (exception $e) {
      ErrorLog::Create(['Request' => 'Auto General Lead Info Cron', 'LogError' => $e]);
      $this->info('Exception: ' . $e);
    }
  }
}
