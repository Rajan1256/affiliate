<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Lead;
use App\UserToken;
use Validator;
use App\CampaignAdList;
use Maatwebsite\Excel\Facades\Excel;
use Laravel\Lumen\Routing\Controller as BaseController;

class LeadController extends BaseController
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

  public function IntegrationLeadList(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        $LeadList = Lead::with('Ad.Brand', 'Affiliate');
        if (isset($request->Brand) && $request->Brand != '') {
          $Brand = $request->Brand;
          $LeadList->whereHas('Ad', function ($qr) use ($Brand) {
            $qr->whereHas('Brand', function ($qr2) use ($Brand) {
              $qr2->where('AdBrandId', $Brand);
            });
          });
        }
        if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
          $from = $request->DateForm;
          $to = $request->DateTo;
          $LeadList->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);
        }
        $LeadList = $LeadList->orderBy('LeadId', 'desc')->get();
        // return $LeadList; die;
        $TimeZoneOffSet = $request->TimeZoneOffSet;

        $LeadArray = [];
        foreach ($LeadList as $key => $value) {
          $Array = [
            'SysID' => $value['LeadId'],
            'RefId' => $value['RefId'],
            'Name' => $value['FirstName'] . ' ' . $value['LastName'],
            'AffId' => $value['UserId'],
            'AffiliateName' => $value['Affiliate']['FirstName'].' '.$value['Affiliate']['LastName'],
            'AccountId' => $value['AccountId'],
            'AdId' => $value['AdId'],
            'AdName' => $value['Ad']['Title'],
            'Brand' => $value['Ad']['brand']['Title'],
            'RegistrationDate' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
            'LastUpdated' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['UpdatedAt']))),
          ];
          array_push($LeadArray, $Array);
        }

        if ($request->IsExport) {
          Excel::create('LeadList', function ($excel) use ($LeadArray) {
            $excel->sheet('LeadList', function ($sheet) use ($LeadArray) {
              $sheet->fromArray($LeadArray);
            });
          })->store('xls', false, true);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export lead sheet successfully.',
            "TotalCount" => 1,
            'Data' => ['LeadList' => $this->storage_path . 'exports/LeadList.xls']
          ], 200);
        }

        return response()->json([
          'IsSuccess' => true,
          'Message' => 'Lead listed successfully.',
          "TotalCount" => $LeadList->count(),
          "Data" => array('ActiveLeadList' => $LeadArray)
        ], 200);
      } else {
        $res = [
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          'TotalCount' => 0,
          'Data' => []
        ];
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
    }
    return response()->json($res);
  }

  public function GenrateLead(Request $request)
  {
    // return $request->all();
    try {
      $RefId = $request->RefId;
      $AdId = decrypt($request->AdId);
      $CampaignId = decrypt($request->CampaignId);
      $FirstName = $request->FirstName;
      $LastName = $request->LastName;
      $Email = $request->Email;
      $Country = $request->Country;
      $PhoneNumber = $request->PhoneNumber;
      $LeadIPAddress = $request->LeadIPAddress;

      $validator = Validator::make($request->all(), [
        'RefId' => 'required|unique:leads',
      ]);
      if ($validator->fails()) {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'The ref id has already been taken.',
          "TotalCount" => count($validator->errors()),
          "Data" => array('Error' => $validator->errors())
        ], 200);
      }

      $user = CampaignAdList::where('CampaignId', $CampaignId)->where('AdId', $AdId)->count();
      if ($user >= 1) {
        $camp_user = CampaignAdList::where('CampaignId', $CampaignId)
          ->where('AdId', $AdId)
          ->first();
        $Lead_create = Lead::create([
          'RefId' => $RefId,
          'UserId' => $camp_user->UserId,
          'AdId' => $AdId,
          'CampaignId' => $CampaignId,
          'FirstName' => $FirstName,
          'LastName' => $LastName,
          'Email' => $Email,
          'Country' => $Country,
          'PhoneNumber' => $PhoneNumber,
          'LeadStatus' => 0,
          'IsActive' => 0,
          'IsConverted' => 0,
          'LeadIPAddress' => $LeadIPAddress
        ]);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'User Not Found.',
          "TotalCount" => 1,
          'Data' => []
        ], 200);
      }

      return response()->json([
        'IsSuccess' => true,
        'Message' => 'Lead Genrate successfully.',
        "TotalCount" => 1,
        'Data' => ['RequestData' => $Lead_create]
      ], 200);
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => null
      ];
      return response()->json($res);
    }
  }
}
