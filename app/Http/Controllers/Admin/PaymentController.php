<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\UserBalance;
use App\RoyalBalance;
use App\RoyalRevenue;
use App\UserPayment;
use App\UserBankDetail;
use App\UserPaymentRequest;
use App\UserRevenuePayment;
use App\UserSubRevenue;
use App\CurrencyConvert;
use App\CurrencyRate;
use App\PaymentType;
use App\User;
use App\UserToken;
use App\Ad;
use Maatwebsite\Excel\Facades\Excel;
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
    $this->revenue_auto_approve = env('REVENUE_AUTO_APPROVE', true);
    $this->storage_path = getenv('STORAGE_URL');
  }

  public function GetAffiliateBalanceList(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $alluser = [];
          // user list for filter
          $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('AdminVerified', 1)->where('IsEnabled', 1)->where('IsDeleted', 1)->where('RoleId', 3)->orderBy('UserId', 'desc')->get();
          $UserList = [];
          foreach ($User as $value) {
            $check_balance = UserBalance::where('UserId', $value['UserId'])->count();
            if ($check_balance == 1) {
              $UserBalance = UserBalance::where('UserId', $value['UserId'])->first();
              $UserDetail = User::find($value['UserId']);
              if ($UserDetail->CurrencyId == 1) {
                $OutstandingRevenue = $UserBalance->USDOutstandingRevenue;
              } else if ($UserDetail->CurrencyId == 2) {
                $OutstandingRevenue = $UserBalance->AUDOutstandingRevenue;
              } else if ($UserDetail->CurrencyId == 3) {
                $OutstandingRevenue = $UserBalance->EUROutstandingRevenue;
              }
              $outstanding = $OutstandingRevenue;
              $arr = [
                "UserId" => $value['UserId'],
                "AffiliateName" => $value['FirstName'] . ' ' . $value['LastName'],
                "EmailId" => $value['EmailId'],
                "OutStandingAmount" => $outstanding
              ];
              array_push($UserList, $arr);
            }
          }
          // End. User list for filter
          if ($request->UserId) {
            $affiliate_user = User::with('UserBalance', 'currency', 'BankDetail.payment')->where('UserId', $request->UserId)->where('IsDeleted', 1)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('RoleId', 3)->get();
          } else {
            $affiliate_user = User::with('UserBalance', 'currency', 'BankDetail.payment')->where('IsDeleted', 1)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('RoleId', 3)->orderBy('UserId', 'desc')->get();
          }

          // If date filter apply
          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to = $request->DateTo;
            // $UserList->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to);

            foreach ($affiliate_user as  $value) {
              if ($request->CurrencyId == "") {
                $CurrencyCode = $value['currency']['CurrencyCode'];
                if ($value->CurrencyId == 1) {
                  $AmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDAmount');
                  $SpreadAmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('SpreadUSDAmount');
                  $tot_rev = $AmountRev + $SpreadAmountRev;
                  $paid = UserPayment::where('UserId', $value['UserId'])->whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('USDAmount');
                  // Calculation.?
                  $out_stand = $value['UserBalance']['USDOutstandingRevenue'];
                  $USDAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('USDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDAmount');
                  $USDSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('USDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDSpreadAmount');
                  $RoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
                } else if ($value->CurrencyId == 2) {
                  $AmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDAmount');
                  $SpreadAmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('SpreadAUDAmount');
                  $tot_rev = $AmountRev + $SpreadAmountRev;

                  $paid = UserPayment::where('UserId', $value['UserId'])->whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('AUDAmount');
                  // Calculation.?
                  $out_stand = $value['UserBalance']['AUDOutstandingRevenue'];
                  $AUDAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('AUDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDAmount');
                  $AUDSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('AUDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDSpreadAmount');
                  $RoyalRevenue = round($AUDAmount + $AUDSpreadAmount, 2);
                } else if ($value->CurrencyId == 3) {
                  $AmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURAmount');
                  $SpreadAmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('SpreadEURAmount');
                  $tot_rev = $AmountRev + $SpreadAmountRev;

                  $paid = UserPayment::where('UserId', $value['UserId'])->whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('EURAmount');
                  // Calculation.?
                  $out_stand = $value['UserBalance']['EUROutstandingRevenue'];
                  $EURAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('EURAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURAmount');
                  $EURSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('EURSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURSpreadAmount');
                  $RoyalRevenue = round($EURAmount + $EURSpreadAmount, 2);
                }
              } else if ($request->CurrencyId == 1) {
                $CurrencyCode = "USD";
                $AmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDAmount');
                $SpreadAmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('SpreadUSDAmount');
                $tot_rev = $AmountRev + $SpreadAmountRev;
                $paid = UserPayment::where('UserId', $value['UserId'])->whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('USDAmount');
                // Calculation.?
                $out_stand = $value['UserBalance']['USDOutstandingRevenue'];
                $USDAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('USDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDAmount');
                $USDSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('USDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDSpreadAmount');
                $RoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
              } else if ($request->CurrencyId == 2) {
                $CurrencyCode = "AUD";
                $AmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDAmount');
                $SpreadAmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('SpreadAUDAmount');
                $tot_rev = $AmountRev + $SpreadAmountRev;
                $paid = UserPayment::where('UserId', $value['UserId'])->whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('AUDAmount');
                // Calculation.?
                $out_stand = $value['UserBalance']['AUDOutstandingRevenue'];
                $AUDAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('AUDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDAmount');
                $AUDSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('AUDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDSpreadAmount');
                $RoyalRevenue = round($AUDAmount + $AUDSpreadAmount, 2);
              } else if ($request->CurrencyId == 3) {
                $CurrencyCode = "EUR";
                $AmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURAmount');
                $SpreadAmountRev = UserRevenuePayment::where('UserId', $value['UserId'])->where('PaymentStatus', 1)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('SpreadEURAmount');
                $tot_rev = $AmountRev + $SpreadAmountRev;
                $paid = UserPayment::where('UserId', $value['UserId'])->whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('EURAmount');
                // Calculation.?
                $out_stand = $value['UserBalance']['EUROutstandingRevenue'];
                $EURAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('EURAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURAmount');
                $EURSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('EURSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURSpreadAmount');
                $RoyalRevenue = round($EURAmount + $EURSpreadAmount, 2);
              }
              $affName = $value['FirstName'] . ' ' . $value['LastName'];
              if (strlen($affName) > 15) {
                $affName = substr($affName, 0, 13) . "..";
              } else {
                $affName = $affName;
              }
              $var = [
                'UserId' => $value['UserId'],
                'AffiliateName' => $affName,
                'EmailId' => $value['EmailId'],
                'CurrencyName' => $CurrencyCode,
                'CurrencyId' => $value['currency']['CurrencyId'],
                'RoyalRevenue' => $RoyalRevenue,
                'PaymentTypeId' => $value['BankDetail']['payment']['PaymentTypeId'],
                'PaymentTypeName' => $value['BankDetail']['payment']['PaymentTypeName'],
                'AffiliateRevenue' => round($tot_rev, 2),
                'Paid' => round($paid, 2),
                'OutstandingRevenue' => round($out_stand, 2),
              ];
              array_push($alluser, $var);
            }
            $RoyalBalance = RoyalBalance::find(1);
            if ($request->UserId) {
              $UserBalance = UserBalance::where('UserId', $request->UserId)->first();
              if ($UserBalance) {
                if ($request->CurrencyId == "") {
                  $USDAmount = RoyalRevenue::where('UserId', $request->UserId)->where('USDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDAmount');
                  $USDSpreadAmount = RoyalRevenue::where('UserId', $request->UserId)->where('USDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDSpreadAmount');
                  $TotalRoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
                  $TotalAffilaiteRevenue = $tot_rev;
                  $TotalAffilaitePaidRevenue = $paid;
                  $TotalAffilaiteOutstandingRevenue = $UserBalance->USDOutstandingRevenue;
                  $TotalAffilaiteDuepayment = $UserBalance->USDTotalDuepayment;
                } else if ($request->CurrencyId == 1) {
                  $USDAmount = RoyalRevenue::where('UserId', $request->UserId)->where('USDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDAmount');
                  $USDSpreadAmount = RoyalRevenue::where('UserId', $request->UserId)->where('USDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDSpreadAmount');
                  $TotalRoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
                  $TotalAffilaiteRevenue = $tot_rev;
                  $TotalAffilaitePaidRevenue = $paid;
                  $TotalAffilaiteOutstandingRevenue = $UserBalance->USDOutstandingRevenue;
                  $TotalAffilaiteDuepayment = $UserBalance->USDTotalDuepayment;
                } else if ($request->CurrencyId == 2) {
                  $AUDAmount = RoyalRevenue::where('UserId', $request->UserId)->where('AUDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDAmount');
                  $AUDSpreadAmount = RoyalRevenue::where('UserId', $request->UserId)->where('AUDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDSpreadAmount');
                  $TotalRoyalRevenue = round($AUDAmount + $AUDSpreadAmount, 2);
                  $TotalAffilaiteRevenue = $tot_rev;
                  $TotalAffilaitePaidRevenue = $paid;
                  $TotalAffilaiteOutstandingRevenue = $UserBalance->AUDOutstandingRevenue;
                  $TotalAffilaiteDuepayment = $UserBalance->AUDTotalDuepayment;
                } else if ($request->CurrencyId == 3) {
                  $EURAmount = RoyalRevenue::where('UserId', $request->UserId)->where('EURAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURAmount');
                  $EURSpreadAmount = RoyalRevenue::where('UserId', $request->UserId)->where('EURSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURSpreadAmount');
                  $TotalRoyalRevenue = round($EURAmount + $EURSpreadAmount, 2);
                  $TotalAffilaiteRevenue = $tot_rev;
                  $TotalAffilaitePaidRevenue = $paid;
                  $TotalAffilaiteOutstandingRevenue = $UserBalance->EUROutstandingRevenue;
                  $TotalAffilaiteDuepayment = $UserBalance->EURTotalDuepayment;
                }
              } else {
                return response()->json([
                  'IsSuccess' => true,
                  'Message' => 'Get affiliate royal balance.',
                  "TotalCount" => $affiliate_user->count(),
                  'Data' => ['AffiliateBalance' => $alluser, 'UserList' => $UserList, 'TotalBalance' => []]
                ], 200);
              }
            } else {
              if ($request->CurrencyId == "") {
                $USDAmount = RoyalRevenue::where('USDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDAmount');
                $USDSpreadAmount = RoyalRevenue::where('USDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDSpreadAmount');
                $TotalRoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
                // $TotalAffilaiteRevenue = UserBalance::sum('USDTotalRevenue');
                $AmountRev = UserRevenuePayment::whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1)->sum('USDAmount');
                $SpreadAmountRev = UserRevenuePayment::whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1)->sum('SpreadUSDAmount');
                $TotalAffilaiteRevenue = $AmountRev + $SpreadAmountRev;
                // $TotalAffilaitePaidRevenue = UserBalance::sum('USDPaid');
                $TotalAffilaitePaidRevenue = UserPayment::whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('USDAmount');
                $TotalAffilaiteOutstandingRevenue = UserBalance::sum('USDOutstandingRevenue');
                $TotalAffilaiteDuepayment = UserBalance::sum('USDTotalDuepayment');
              } else if ($request->CurrencyId == 1) {
                $USDAmount = RoyalRevenue::where('USDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDAmount');
                $USDSpreadAmount = RoyalRevenue::where('USDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('USDSpreadAmount');
                $TotalRoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
                // $TotalAffilaiteRevenue = UserBalance::sum('USDTotalRevenue');
                $AmountRev = UserRevenuePayment::whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1)->sum('USDAmount');
                $SpreadAmountRev = UserRevenuePayment::whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1)->sum('SpreadUSDAmount');
                $TotalAffilaiteRevenue = $AmountRev + $SpreadAmountRev;
                // $TotalAffilaitePaidRevenue = UserBalance::sum('USDPaid');
                $TotalAffilaitePaidRevenue = UserPayment::whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('USDAmount');
                $TotalAffilaiteOutstandingRevenue = UserBalance::sum('USDOutstandingRevenue');
                $TotalAffilaiteDuepayment = UserBalance::sum('USDTotalDuepayment');
              } else if ($request->CurrencyId == 2) {
                $AUDAmount = RoyalRevenue::where('AUDAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDAmount');
                $AUDSpreadAmount = RoyalRevenue::where('AUDSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('AUDSpreadAmount');
                $TotalRoyalRevenue = round($AUDAmount + $AUDSpreadAmount, 2);
                // $TotalAffilaiteRevenue = UserBalance::sum('AUDTotalRevenue'); 
                $AmountRev = UserRevenuePayment::whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1)->sum('AUDAmount');
                $SpreadAmountRev = UserRevenuePayment::whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1)->sum('SpreadAUDAmount');
                $TotalAffilaiteRevenue = $AmountRev + $SpreadAmountRev;
                // $TotalAffilaitePaidRevenue = UserBalance::sum('AUDPaid');
                $TotalAffilaitePaidRevenue = UserPayment::whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('AUDAmount');
                $TotalAffilaiteOutstandingRevenue = UserBalance::sum('AUDOutstandingRevenue');
                $TotalAffilaiteDuepayment = UserBalance::sum('AUDTotalDuepayment');
              } else if ($request->CurrencyId == 3) {
                $EURAmount = RoyalRevenue::where('EURAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURAmount');
                $EURSpreadAmount = RoyalRevenue::where('EURSpreadAmount', '>=', 0)->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->sum('EURSpreadAmount');
                $TotalRoyalRevenue = round($EURAmount + $EURSpreadAmount, 2);
                // $TotalAffilaiteRevenue = UserBalance::sum('EURTotalRevenue');
                $AmountRev = UserRevenuePayment::whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1)->sum('EURAmount');
                $SpreadAmountRev = UserRevenuePayment::whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to)->where('PaymentStatus', 1)->sum('SpreadEURAmount');
                $TotalAffilaiteRevenue = $AmountRev + $SpreadAmountRev;
                // $TotalAffilaitePaidRevenue = UserBalance::sum('EURPaid');
                $TotalAffilaitePaidRevenue = UserPayment::whereDate('DateOfPayment', '>=', $from)->whereDate('DateOfPayment', '<=', $to)->sum('EURAmount');
                $TotalAffilaiteOutstandingRevenue = UserBalance::sum('EUROutstandingRevenue');
                $TotalAffilaiteDuepayment = UserBalance::sum('EURTotalDuepayment');
              }
            }
          }
          // End. If date filter apply
          else { 
            foreach ($affiliate_user as  $value) {
              if ($request->CurrencyId == "") {
                $CurrencyCode = $value['currency']['CurrencyCode'];
                if ($value->CurrencyId == 1) {
                  $tot_rev = $value['UserBalance']['USDTotalRevenue'];
                  $paid = $value['UserBalance']['USDPaid'];
                  $out_stand = $value['UserBalance']['USDOutstandingRevenue'];
                  $USDAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('USDAmount', '>=', 0)->sum('USDAmount');
                  $USDSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('USDSpreadAmount', '>=', 0)->sum('USDSpreadAmount');
                  $RoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
                } else if ($value->CurrencyId == 2) {
                  $tot_rev = $value['UserBalance']['AUDTotalRevenue'];
                  $paid = $value['UserBalance']['AUDPaid'];
                  $out_stand = $value['UserBalance']['AUDOutstandingRevenue'];
                  $AUDAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('AUDAmount', '>=', 0)->sum('AUDAmount');
                  $AUDSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('AUDSpreadAmount', '>=', 0)->sum('AUDSpreadAmount');
                  $RoyalRevenue = round($AUDAmount + $AUDSpreadAmount, 2);
                } else if ($value->CurrencyId == 3) {
                  $tot_rev = $value['UserBalance']['EURTotalRevenue'];
                  $paid = $value['UserBalance']['EURPaid'];
                  $out_stand = $value['UserBalance']['EUROutstandingRevenue'];
                  $EURAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('EURAmount', '>=', 0)->sum('EURAmount');
                  $EURSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('EURSpreadAmount', '>=', 0)->sum('EURSpreadAmount');
                  $RoyalRevenue = round($EURAmount + $EURSpreadAmount, 2);
                }
              } else if ($request->CurrencyId == 1) {
                $CurrencyCode = "USD";
                $tot_rev = $value['UserBalance']['USDTotalRevenue'];
                $paid = $value['UserBalance']['USDPaid'];
                $out_stand = $value['UserBalance']['USDOutstandingRevenue'];
                $USDAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('USDAmount', '>=', 0)->sum('USDAmount');
                $USDSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('USDSpreadAmount', '>=', 0)->sum('USDSpreadAmount');
                $RoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
              } else if ($request->CurrencyId == 2) {
                $CurrencyCode = "AUD";
                $tot_rev = $value['UserBalance']['AUDTotalRevenue'];
                $paid = $value['UserBalance']['AUDPaid'];
                $out_stand = $value['UserBalance']['AUDOutstandingRevenue'];
                $AUDAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('AUDAmount', '>=', 0)->sum('AUDAmount');
                $AUDSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('AUDSpreadAmount', '>=', 0)->sum('AUDSpreadAmount');
                $RoyalRevenue = round($AUDAmount + $AUDSpreadAmount, 2);
              } else if ($request->CurrencyId == 3) {
                $CurrencyCode = "EUR";
                $tot_rev = $value['UserBalance']['EURTotalRevenue'];
                $paid = $value['UserBalance']['EURPaid'];
                $out_stand = $value['UserBalance']['EUROutstandingRevenue'];
                $EURAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('EURAmount', '>=', 0)->sum('EURAmount');
                $EURSpreadAmount = RoyalRevenue::where('UserId', $value['UserId'])->where('EURSpreadAmount', '>=', 0)->sum('EURSpreadAmount');
                $RoyalRevenue = round($EURAmount + $EURSpreadAmount, 2);
              }
              $var = [
                'UserId' => $value['UserId'],
                'AffiliateName' => $value['FirstName'] . ' ' . $value['LastName'],
                'EmailId' => $value['EmailId'],
                'CurrencyName' => $CurrencyCode,
                'CurrencyId' => $value['currency']['CurrencyId'],
                'RoyalRevenue' => $RoyalRevenue,
                'PaymentTypeId' => $value['BankDetail']['payment']['PaymentTypeId'],
                'PaymentTypeName' => $value['BankDetail']['payment']['PaymentTypeName'],
                'AffiliateRevenue' => round($tot_rev, 2),
                'Paid' => round($paid, 2),
                'OutstandingRevenue' => round($out_stand, 2),
              ];
              array_push($alluser, $var);
            }
            $RoyalBalance = RoyalBalance::find(1);
            if ($request->UserId) {
              $UserBalance = UserBalance::where('UserId', $request->UserId)->first();
              if ($UserBalance) {
                if ($request->CurrencyId == "") {
                  $USDAmount = RoyalRevenue::where('UserId', $request->UserId)->where('USDAmount', '>=', 0)->sum('USDAmount');
                  $USDSpreadAmount = RoyalRevenue::where('UserId', $request->UserId)->where('USDSpreadAmount', '>=', 0)->sum('USDSpreadAmount');
                  $TotalRoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
                  $TotalAffilaiteRevenue = $UserBalance->USDTotalRevenue;
                  $TotalAffilaitePaidRevenue = $UserBalance->USDPaid;
                  $TotalAffilaiteOutstandingRevenue = $UserBalance->USDOutstandingRevenue;
                  $TotalAffilaiteDuepayment = $UserBalance->USDTotalDuepayment;
                } else if ($request->CurrencyId == 1) {
                  $USDAmount = RoyalRevenue::where('UserId', $request->UserId)->where('USDAmount', '>=', 0)->sum('USDAmount');
                  $USDSpreadAmount = RoyalRevenue::where('UserId', $request->UserId)->where('USDSpreadAmount', '>=', 0)->sum('USDSpreadAmount');
                  $TotalRoyalRevenue = round($USDAmount + $USDSpreadAmount, 2);
                  $TotalAffilaiteRevenue = $UserBalance->USDTotalRevenue;
                  $TotalAffilaitePaidRevenue = $UserBalance->USDPaid;
                  $TotalAffilaiteOutstandingRevenue = $UserBalance->USDOutstandingRevenue;
                  $TotalAffilaiteDuepayment = $UserBalance->USDTotalDuepayment;
                } else if ($request->CurrencyId == 2) {
                  $AUDAmount = RoyalRevenue::where('UserId', $request->UserId)->where('AUDAmount', '>=', 0)->sum('AUDAmount');
                  $AUDSpreadAmount = RoyalRevenue::where('UserId', $request->UserId)->where('AUDSpreadAmount', '>=', 0)->sum('AUDSpreadAmount');
                  $TotalRoyalRevenue = round($AUDAmount + $AUDSpreadAmount, 2);
                  $TotalAffilaiteRevenue = $UserBalance->AUDTotalRevenue;
                  $TotalAffilaitePaidRevenue = $UserBalance->AUDPaid;
                  $TotalAffilaiteOutstandingRevenue = $UserBalance->AUDOutstandingRevenue;
                  $TotalAffilaiteDuepayment = $UserBalance->AUDTotalDuepayment;
                } else if ($request->CurrencyId == 3) {
                  $EURAmount = RoyalRevenue::where('UserId', $request->UserId)->where('EURAmount', '>=', 0)->sum('EURAmount');
                  $EURSpreadAmount = RoyalRevenue::where('UserId', $request->UserId)->where('EURSpreadAmount', '>=', 0)->sum('EURSpreadAmount');
                  $TotalRoyalRevenue = round($EURAmount + $EURSpreadAmount, 2);
                  $TotalAffilaiteRevenue = $UserBalance->EURTotalRevenue;
                  $TotalAffilaitePaidRevenue = $UserBalance->EURPaid;
                  $TotalAffilaiteOutstandingRevenue = $UserBalance->EUROutstandingRevenue;
                  $TotalAffilaiteDuepayment = $UserBalance->EURTotalDuepayment;
                }
              } else {
                return response()->json([
                  'IsSuccess' => true,
                  'Message' => 'Get affiliate royal balance.',
                  "TotalCount" => $affiliate_user->count(),
                  'Data' => ['AffiliateBalance' => $alluser, 'UserList' => $UserList, 'TotalBalance' => []]
                ], 200);
              }
            } else {
              if ($request->CurrencyId == "") {
                $TotalRoyalRevenue = round($RoyalBalance->USDTotalRevenue, 2);
                $TotalAffilaiteRevenue = UserBalance::sum('USDTotalRevenue');
                $TotalAffilaitePaidRevenue = UserBalance::sum('USDPaid');
                $TotalAffilaiteOutstandingRevenue = UserBalance::sum('USDOutstandingRevenue');
                $TotalAffilaiteDuepayment = UserBalance::sum('USDTotalDuepayment');
              } else if ($request->CurrencyId == 1) {
                $TotalRoyalRevenue = round($RoyalBalance->USDTotalRevenue, 2);
                $TotalAffilaiteRevenue = UserBalance::sum('USDTotalRevenue');
                $TotalAffilaitePaidRevenue = UserBalance::sum('USDPaid');
                $TotalAffilaiteOutstandingRevenue = UserBalance::sum('USDOutstandingRevenue');
                $TotalAffilaiteDuepayment = UserBalance::sum('USDTotalDuepayment');
              } else if ($request->CurrencyId == 2) {
                $TotalRoyalRevenue = round($RoyalBalance->AUDTotalRevenue, 2);
                $TotalAffilaiteRevenue = UserBalance::sum('AUDTotalRevenue');
                $TotalAffilaitePaidRevenue = UserBalance::sum('AUDPaid');
                $TotalAffilaiteOutstandingRevenue = UserBalance::sum('AUDOutstandingRevenue');
                $TotalAffilaiteDuepayment = UserBalance::sum('AUDTotalDuepayment');
              } else if ($request->CurrencyId == 3) {
                $TotalRoyalRevenue = round($RoyalBalance->EURTotalRevenue, 2);
                $TotalAffilaiteRevenue = UserBalance::sum('EURTotalRevenue');
                $TotalAffilaitePaidRevenue = UserBalance::sum('EURPaid');
                $TotalAffilaiteOutstandingRevenue = UserBalance::sum('EUROutstandingRevenue');
                $TotalAffilaiteDuepayment = UserBalance::sum('EURTotalDuepayment');
              }
            }
          }
          $Balance = [
            "TotalRoyalRevenue" => $TotalRoyalRevenue,
            "TotalAffilaiteRevenue" => round($TotalAffilaiteRevenue, 2),
            "TotalAffilaitePaidRevenue" => round($TotalAffilaitePaidRevenue, 2),
            "TotalAffilaiteOutstandingRevenue" => round($TotalAffilaiteOutstandingRevenue, 2),
            "TotalAffilaiteDuepayment" => round($TotalAffilaiteDuepayment, 2),
          ];
          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Get affiliate royal balance.',
            "TotalCount" => $affiliate_user->count(),
            'Data' => ['AffiliateBalance' => $alluser, 'UserList' => $UserList, 'TotalBalance' => $Balance]
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
          'Message' => 'Invalid Token.',
          "TotalCount" => 0,
          'Data' => []
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }

  public function AdminAffiliateRevenueList(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          // return $request->all();
          $UserRevenuePayments = UserRevenuePayment::with(['Affiliate', 'LeadDetail.Ad.Brand', 'LeadDetail.Ad.Type', 'LeadDetail.Ad.size', 'LeadDetail.Ad.Language', 'LeadActivity', 'LeadInformation', 'UserSubRevenue', 'UserBonus.RevenueModel', 'RevenueModelLog.RevenueModel.Revenue'])->where(function($query){
            return $query->whereNull('UserSubRevenueId')->orWhere('USDAmount', '>=', 0);
          })->orderBy('UserRevenuePaymentId', 'desc');

          if (isset($request->AffiliateId)) {
            $AffiliateId = $request->AffiliateId;
            if ($AffiliateId != '') {
              $UserRevenuePayments->where('UserId', $AffiliateId);
            }
          }
          if (isset($request->RevenueTypeId) && $request->RevenueTypeId != '') {
            $RevenueType = $request->RevenueTypeId;
            if ($RevenueType != '') {
              $UserRevenuePayments->whereHas('RevenueModelLog.RevenueModel.Revenue', function ($qr) use ($RevenueType) {
                $qr->where('RevenueTypeId', $RevenueType);
              });
            }
            if ($RevenueType == 7) {
              $AffiliateId = '';
              if (isset($request->AffiliateId)) {
                $AffiliateId = $request->AffiliateId; 
              }
              $UserRevenuePayments->orWhere(function ($qr) use($AffiliateId) {
                $qr->where('UserBonusId', '!=', '');
                if($AffiliateId != ''){
                  $qr->where('UserId', $AffiliateId);
                }
              });
            }
          }
          $TimeZoneOffSet = $request->TimeZoneOffSet;
          if ($TimeZoneOffSet == '')
            $TimeZoneOffSet = 0;
          // date filter
          if (isset($request->DateFrom) && $request->DateFrom != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateFrom;
            $to = $request->DateTo;
            $UserRevenuePayments->whereDate('ActualRevenueDate', '>=', $from)->whereDate('ActualRevenueDate', '<=', $to);
          }
          $UserRevenuePaymentData = $UserRevenuePayments->get();
          // return $UserRevenuePaymentData;
          $arrayList = [];
          $arrayListExport = [];
          $TotalAmount = 0;
          $TotalSpread = 0;
          $TotalRevenue = 0;

          foreach ($UserRevenuePaymentData as $UserRevenuePayment) {
            // $UserSubRevenueDeduct = UserSubRevenue::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->where('UserId', $UserRevenuePayment->UserId);

            $UserSubRevenue = UserSubRevenue::with('UserSubRevenuePayment.RevenueModelLog')->where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->where('UserId', $UserRevenuePayment->UserId)->first();
            $percentage = 0;
            if ($UserSubRevenue)
              $percentage = $UserSubRevenue['UserSubRevenuePayment']['RevenueModelLog']['Percentage'];

            if ($request->CurrencyId == 1) {
              $Amount = round($UserRevenuePayment['USDAmount'], 4);
              $Spread = round($UserRevenuePayment['SpreadUSDAmount'], 4);
              // $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
            } elseif ($request->CurrencyId == 2) {
              $Amount = round($UserRevenuePayment['AUDAmount'], 4);
              $Spread = round($UserRevenuePayment['SpreadAUDAmount'], 4);
              // $Deduct = $UserSubRevenueDeduct->sum('AUDAmount');
            } elseif ($request->CurrencyId == 3) {
              $Amount = round($UserRevenuePayment['EURAmount'], 4);
              $Spread = round($UserRevenuePayment['SpreadEURAmount'], 4);
              // $Deduct = $UserSubRevenueDeduct->sum('EURAmount');
            } else {
              $Amount = round($UserRevenuePayment['USDAmount'], 4);
              $Spread = round($UserRevenuePayment['SpreadUSDAmount'], 4);
              // $Deduct = $UserSubRevenueDeduct->sum('USDAmount');
            }
            $AmountDeduct = 0;
            $SpreadDeduct = 0;
            if ($UserSubRevenue) {
              $AmountDeduct = $Amount * $percentage / 100;
              $SpreadDeduct = $Spread * $percentage / 100;
            }
            $Amount = round($Amount - $AmountDeduct, 4);
            $Spread = round($Spread - $SpreadDeduct, 4);
            $TotalRevenue = $Amount + $Spread;

            if ($UserRevenuePayment['UserBonusId']) {
              $array = [
                'UserRevenuePaymentId' => $UserRevenuePayment['UserRevenuePaymentId'],
                'UserId' => $UserRevenuePayment['UserId'],
                'Affiliate' => $UserRevenuePayment['Affiliate']['FirstName'] . ' ' . $UserRevenuePayment['Affiliate']['LastName'],
                'LeadId' => '',
                'AdId' => '',
                'AdTitle' => '',
                'AdBrand' => '',
                'AdType' => '',
                'AdSize' => '',
                'AdLanguage' => '',
                'RevenueModelLogId' => '',
                'RevenueModelName' => $UserRevenuePayment['UserBonus']['RevenueModel']['RevenueModelName'],
                'Comment' => $UserRevenuePayment['UserBonus']['Comment'],
                'RevenueTypeName' => '' . $UserRevenuePayment['UserBonus'] == 0 ? 'Bonus(Manual)' : 'Bonus(Auto)',
                'UserBonusId' => $UserRevenuePayment['UserBonusId'],
                'UserSubRevenueId' => '',
                'LeadInformationId' => '',
                'LeadActivityId' => '',
                'Amount' => $Amount,
                'Spread' => $Spread,
                'Total' => $TotalRevenue,
                // 'SpreadAmount' => round($UserRevenuePayment['SpreadUSDAmount'],8),
                'CurrencyConvertId' => $UserRevenuePayment['CurrencyConvertId'],
                'PaymentStatus' => $UserRevenuePayment['PaymentStatus'],
                'ActualRevenueDate' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['ActualRevenueDate']))),
                'CreatedAt' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
              ];
              // Export data
              $arrayExport = [
                'Affiliate' => $UserRevenuePayment['Affiliate']['FirstName'] . ' ' . $UserRevenuePayment['Affiliate']['LastName'],
                'Effective Date' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['ActualRevenueDate']))),
                'Insertion Date' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
                'Revenue Type' => '' . $UserRevenuePayment['UserBonus'] == 0 ? 'Bonus(Manual)' : 'Bonus(Auto)',
                'Revenue' => $Amount,
                'Spread' => $Spread,
                'Total' => $TotalRevenue,
                'Brand' => '',
                'Ad' => '',
                'Ad Type' => '',
                'Ad Size' => '',
                'Revenue Model' => $UserRevenuePayment['UserBonus']['RevenueModel']['RevenueModelName'],
                'Comment' => $UserRevenuePayment['UserBonus']['Comment'],
              ];
            } else {
              $array = [
                'UserRevenuePaymentId' => $UserRevenuePayment['UserRevenuePaymentId'],
                'UserId' => $UserRevenuePayment['UserId'],
                'Affiliate' => $UserRevenuePayment['Affiliate']['FirstName'] . ' ' . $UserRevenuePayment['Affiliate']['LastName'],
                'LeadId' => $UserRevenuePayment['LeadId'],
                'AdId' => $UserRevenuePayment['LeadDetail']['AdId'],
                'AdTitle' => $UserRevenuePayment['LeadDetail']['Ad']['Title'],
                'AdBrand' => $UserRevenuePayment['LeadDetail']['Ad']['Brand']['Title'],
                'AdType' => $UserRevenuePayment['LeadDetail']['Ad']['Type']['Title'],
                'AdSize' => $UserRevenuePayment['LeadDetail']['Ad']['Size']['Width'] . '*' . $UserRevenuePayment['LeadDetail']['Ad']['Size']['Height'],
                'AdLanguage' => $UserRevenuePayment['LeadDetail']['Ad']['Language']['LanguageName'],
                'RevenueModelLogId' => $UserRevenuePayment['RevenueModelLogId'],
                'RevenueModelName' => $UserRevenuePayment['RevenueModelLog']['RevenueModelName'],
                'Comment' => '',
                'RevenueTypeName' => $UserRevenuePayment['RevenueModelLog']['RevenueModel']['Revenue']['RevenueTypeName'],
                'UserBonusId' => $UserRevenuePayment['UserBonusId'],
                'UserSubRevenueId' => $UserRevenuePayment['UserSubRevenueId'],
                'LeadInformationId' => $UserRevenuePayment['LeadInformationId'],
                'LeadActivityId' => $UserRevenuePayment['LeadActivityId'],
                'Amount' => $Amount,
                'Spread' => $Spread,
                'Total' => $TotalRevenue,
                // 'SpreadAmount' => round($UserRevenuePayment['SpreadUSDAmount'],8),
                'CurrencyConvertId' => $UserRevenuePayment['CurrencyConvertId'],
                'PaymentStatus' => $UserRevenuePayment['PaymentStatus'],
                'ActualRevenueDate' => date('d/m/Y h:i A', strtotime($UserRevenuePayment['ActualRevenueDate'])),
                'CreatedAt' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
              ];
              // Export data
              $arrayExport = [
                'Affiliate' => $UserRevenuePayment['Affiliate']['FirstName'] . ' ' . $UserRevenuePayment['Affiliate']['LastName'],
                'Effective Date' => date('d/m/Y h:i A', strtotime($UserRevenuePayment['ActualRevenueDate'])),
                'Insertion Date' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
                'Revenue Type' => $UserRevenuePayment['RevenueModelLog']['RevenueModel']['Revenue']['RevenueTypeName'],
                'Revenue' => $Amount,
                'Spread' => $Spread,
                'Total' => $TotalRevenue,
                'Brand' => $UserRevenuePayment['LeadDetail']['Ad']['Brand']['Title'],
                'Ad' => $UserRevenuePayment['LeadDetail']['Ad']['Title'],
                'Ad Type' => $UserRevenuePayment['LeadDetail']['Ad']['Type']['Title'],
                'Ad Size' => $UserRevenuePayment['LeadDetail']['Ad']['Size']['Width'] . '*' . $UserRevenuePayment['LeadDetail']['Ad']['Size']['Height'],
                'Revenue Model' => $UserRevenuePayment['RevenueModelLog']['RevenueModelName'],
                'Comment' => '',
              ];
            }
            array_push($arrayList, $array);
            array_push($arrayListExport, $arrayExport);
            $TotalAmount = $TotalAmount  + $Amount;
            $TotalSpread = $TotalSpread  + $Spread;
          }

          // Export data
          $TotalArrayExport = [
            'Affiliate' => '',
            'Effective Date' => '',
            'Insertion Date' => '',
            'Revenue Type' => '',
            'Revenue' => $TotalAmount,
            'Spread' => $TotalSpread,
            'Total' => $TotalAmount + $TotalSpread,
            'Brand' => '',
            'Ad' => '',
            'Ad Type' => '',
            'Ad Size' => '',
            'Revenue Model' => '',
            'Comment' => '',
          ];
          array_push($arrayListExport, $TotalArrayExport);

          // Export report in xls file
          if ($request->IsExport) {
            Excel::create('AdminAffiliateRevenueList', function ($excel) use ($arrayListExport) {
              $excel->sheet('AdminAffiliateRevenueList', function ($sheet) use ($arrayListExport) {
                $sheet->fromArray($arrayListExport);
              });
            })->store('xls', false, true);

            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Export admin affiliate revenue list successfully.',
              "TotalCount" => 1,
              'Data' => ['AdminAffiliateRevenueList' => $this->storage_path . 'exports/AdminAffiliateRevenueList.xls'],
            ], 200);
          }

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Revenue list.',
            "TotalCount" => count($UserRevenuePaymentData),
            'Data' => array('RevenueList' => $arrayList)
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
          'Message' => 'Invalid Token.',
          "TotalCount" => 0,
          'Data' => []
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }

  public function AdminAffiliateRevenueDetails(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          // return $request->all();
          $UserRevenuePayment = UserRevenuePayment::with(['Affiliate', 'LeadDetail.Ad.Brand', 'LeadDetail.Ad.Type', 'LeadDetail.Ad.size', 'LeadDetail.Ad.Language', 'LeadActivity.LeadFile', 'LeadInformation.LeadFile', 'CurrencyConvert.CurrencyRate.ConversionFile', 'UserSubRevenue',  'UserBonus.RevenueModel', 'RevenueModelLog.RevenueModel.Revenue'])->where('UserRevenuePaymentId', $request->UserRevenuePaymentId)->first();
          if ($UserRevenuePayment) {
            $TimeZoneOffSet = $request->TimeZoneOffSet;
            if ($TimeZoneOffSet == '')
              $TimeZoneOffSet = 0;
            $arrayList = [];
            if ($UserRevenuePayment['LeadInformation']) {
              $LeadFileName = $UserRevenuePayment['LeadInformation']['LeadFile']['FileName'];
              $LeadFileLink = $this->storage_path . 'app/import/LeadInformation/' . $LeadFileName;
              $LeadFileType = "Lead Information";
            } else if ($UserRevenuePayment['LeadActivity']) {
              $LeadFileName = $UserRevenuePayment['LeadActivity']['LeadFile']['FileName'];
              $LeadFileLink = $this->storage_path . 'app/import/LeadActivity/' . $LeadFileName;
              $LeadFileType = "Lead Activity";
            } else {
              $LeadFileName = "";
              $LeadFileLink = "";
              $LeadFileType = "";
            }
            if ($UserRevenuePayment['CurrencyConvert']) {
              $ConFileName = $UserRevenuePayment['CurrencyConvert']['CurrencyRate']['ConversionFile']['FileName'];
              $ConFileLink = $this->storage_path . 'app/import/CurrencyConvert/' . $ConFileName;
            } else {
              $ConFileName = "";
              $ConFileLink = "";
            }
            $PaymentStatus = $UserRevenuePayment['PaymentStatus'];
            if ($PaymentStatus == 0)
              $PaymentStatusSub = 'Hold';
            else if ($PaymentStatus == 1)
              $PaymentStatusSub = 'Approved';
            else if ($PaymentStatusSub == 2)
              $PaymentStatusSub = 'Rejected';
            else
              $PaymentStatusSub = 'Hold';
            // return $UserRevenuePayment;

            if ($UserRevenuePayment['UserBonusId']) {
              $array = [
                'UserRevenuePaymentId' => $UserRevenuePayment['UserRevenuePaymentId'],
                'UserId' => $UserRevenuePayment['UserId'],
                'Affiliate' => $UserRevenuePayment['Affiliate']['FirstName'] . ' ' . $UserRevenuePayment['Affiliate']['LastName'],
                'LeadId' => '',
                'AccountId' => '',
                'RefId' => '',
                'LeadFileName' => $LeadFileName,
                'LeadFileLink' => $LeadFileLink,
                'LeadFileType' => $LeadFileType,
                'AdId' => '',
                'AdTitle' => '',
                'AdBrand' => '',
                'AdType' => '',
                'AdSize' => '',
                'AdLanguage' => '',
                'RevenueModelLogId' => '',
                'RevenueTypeId' => $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'],
                'RevenueModelName' => $UserRevenuePayment['UserBonus']['RevenueModelLog']['RevenueModelName'],
                'RevenueTypeName' => '' . $UserRevenuePayment['UserBonus'] == 0 ? 'Bonus(Manual)' : 'Bonus(Auto)',
                'UserBonusId' => $UserRevenuePayment['UserBonusId'],
                'UserSubRevenueId' => '',
                'ParentRevenue' => '',
                'LeadInformationId' => '',
                'LeadActivityId' => '',
                'USDAmount' => round($UserRevenuePayment['USDAmount'], 8),
                'USDAmountSpread' => round($UserRevenuePayment['SpreadUSDAmount'], 8),
                'USDAmountTotal' => round($UserRevenuePayment['USDAmount'], 8) + round($UserRevenuePayment['SpreadUSDAmount'], 8),
                'AUDAmount' => round($UserRevenuePayment['AUDAmount'], 8),
                'AUDAmountSpread' => round($UserRevenuePayment['SpreadAUDAmount'], 8),
                'AUDAmountTotal' => round($UserRevenuePayment['AUDAmount'], 8) + round($UserRevenuePayment['SpreadAUDAmount'], 8),
                'EURAmount' => round($UserRevenuePayment['EURAmount'], 8),
                'EURAmountSpread' => round($UserRevenuePayment['SpreadEURAmount'], 8),
                'EURAmountTotal' => round($UserRevenuePayment['EURAmount'], 8) + round($UserRevenuePayment['SpreadEURAmount'], 8),
                'CurrencyConvertId' => $UserRevenuePayment['CurrencyConvertId'],
                'CurrencyRateId' => $UserRevenuePayment['CurrencyRateId'],
                'CurrencyRateDate' => date('d/m/Y h:i A', strtotime($UserRevenuePayment['CurrencyConvert']['CurrencyRate']['Date'])),
                'ConversionFile' => $ConFileName,
                'ConversionFileLink' => $ConFileLink,
                'AUDUSD' => $UserRevenuePayment['CurrencyConvert']['CurrencyRate']['AUDUSD'],
                'EURUSD' => $UserRevenuePayment['CurrencyConvert']['CurrencyRate']['EURUSD'],
                'PaymentStatus' => $UserRevenuePayment['PaymentStatus'],
                'PaymentStatusName' => $PaymentStatusSub,
                'ActualRevenueDate' => date('d/m/Y h:i A', strtotime($UserRevenuePayment['ActualRevenueDate'])),
                'CreatedAt' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
              ];
            } else { 
              $UserSubRevenue = UserSubRevenue::with('UserSubRevenuePayment.RevenueModelLog')->where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->where('UserId', $UserRevenuePayment->UserId)->first();
              $percentage = 0;
              $subAffiliate = '';
              if ($UserSubRevenue){
                $percentage = $UserSubRevenue['UserSubRevenuePayment']['RevenueModelLog']['Percentage'];
                $subAffiliate = ''; 
              }
              $ParentRevenueDetails = '';
              if ($UserRevenuePayment['UserSubRevenueId']) {
                $ParentRevenueDetails = UserSubRevenue::with('UserSubRevenuePayment.RevenueModelLog','UserSubRevenuePayment.Affiliate')->where('UserSubRevenueId', $UserRevenuePayment->UserSubRevenueId)->first();
              }

              $array = [
                'UserRevenuePaymentId' => $UserRevenuePayment['UserRevenuePaymentId'],
                'UserId' => $UserRevenuePayment['UserId'],
                'Affiliate' => $UserRevenuePayment['Affiliate']['FirstName'] . ' ' . $UserRevenuePayment['Affiliate']['LastName'],
                'LeadId' => $UserRevenuePayment['LeadId'],
                'AccountId' => $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 3 || $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 4 || $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 5 || $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 6 ? $UserRevenuePayment['LeadDetail']['AccountId'] : '',
                'RefId' => $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 1 || $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 2 ? $UserRevenuePayment['LeadDetail']['RefId'] : '',
                'LeadFileName' => $LeadFileName,
                'LeadFileLink' => $LeadFileLink,
                'LeadFileType' => $LeadFileType,
                'AdId' => $UserRevenuePayment['LeadDetail']['AdId'],
                'AdTitle' => $UserRevenuePayment['LeadDetail']['Ad']['Title'],
                'AdBrand' => $UserRevenuePayment['LeadDetail']['Ad']['Brand']['Title'],
                'AdType' => $UserRevenuePayment['LeadDetail']['Ad']['Type']['Title'],
                'AdSize' => $UserRevenuePayment['LeadDetail']['Ad']['Size']['Width'] . '*' . $UserRevenuePayment['LeadDetail']['Ad']['Size']['Height'],
                'AdLanguage' => $UserRevenuePayment['LeadDetail']['Ad']['Language']['LanguageName'],
                'RevenueModelLogId' => $UserRevenuePayment['RevenueModelLogId'],
                'RevenueTypeId' => $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'],
                'RevenueModelName' => $UserRevenuePayment['RevenueModelLog']['RevenueModelName'],
                'RevenueTypeName' => $UserRevenuePayment['RevenueModelLog']['RevenueModel']['Revenue']['RevenueTypeName'],
                'UserBonusId' => $UserRevenuePayment['UserBonusId'],
                'UserSubRevenueId' => $UserRevenuePayment['UserSubRevenueId'],
                'ParentRevenue' => $ParentRevenueDetails,
                'LeadInformationId' => $UserRevenuePayment['LeadInformationId'],
                'LeadActivityId' => $UserRevenuePayment['LeadActivityId'],
                'USDAmount' => round($UserRevenuePayment['USDAmount'] - ($UserRevenuePayment['USDAmount'] * $percentage / 100), 8),
                'USDAmountSpread' => round($UserRevenuePayment['SpreadUSDAmount'] - ($UserRevenuePayment['SpreadUSDAmount'] * $percentage / 100), 8),
                'USDAmountTotal' => round($UserRevenuePayment['USDAmount'] - ($UserRevenuePayment['USDAmount'] * $percentage / 100), 8) + round($UserRevenuePayment['SpreadUSDAmount'] - ($UserRevenuePayment['SpreadUSDAmount'] * $percentage / 100), 8),
                'AUDAmount' => round($UserRevenuePayment['AUDAmount'] - ($UserRevenuePayment['AUDAmount'] * $percentage / 100), 8),
                'AUDAmountSpread' => round($UserRevenuePayment['SpreadAUDAmount'] - ($UserRevenuePayment['SpreadAUDAmount'] * $percentage / 100), 8),
                'AUDAmountTotal' => round($UserRevenuePayment['AUDAmount'] - ($UserRevenuePayment['AUDAmount'] * $percentage / 100), 8) + round($UserRevenuePayment['SpreadAUDAmount'] - ($UserRevenuePayment[''] * $percentage / 100), 8),
                'EURAmount' => round($UserRevenuePayment['EURAmount'] - ($UserRevenuePayment['EURAmount'] * $percentage / 100), 8),
                'EURAmountSpread' => round($UserRevenuePayment['SpreadEURAmount'] - ($UserRevenuePayment['SpreadEURAmount'] * $percentage / 100), 8),
                'EURAmountTotal' => round($UserRevenuePayment['EURAmount'] - ($UserRevenuePayment['EURAmount'] * $percentage / 100), 8) + round($UserRevenuePayment['SpreadEURAmount'] - ($UserRevenuePayment['SpreadEURAmount'] * $percentage / 100), 8),
                'CurrencyConvertId' => $UserRevenuePayment['CurrencyConvertId'],
                'CurrencyRateId' => $UserRevenuePayment['CurrencyRateId'],
                'CurrencyRateDate' => date('d/m/Y h:i A', strtotime($UserRevenuePayment['CurrencyConvert']['CurrencyRate']['Date'])),
                'ConversionFile' => $ConFileName,
                'ConversionFileLink' => $ConFileLink,
                'AUDUSD' => $UserRevenuePayment['CurrencyConvert']['CurrencyRate']['AUDUSD'],
                'EURUSD' => $UserRevenuePayment['CurrencyConvert']['CurrencyRate']['EURUSD'],
                'PaymentStatus' => $UserRevenuePayment['PaymentStatus'],
                'PaymentStatusName' => $PaymentStatusSub,
                'ActualRevenueDate' => date('d/m/Y h:i A', strtotime($UserRevenuePayment['ActualRevenueDate'])),
                'CreatedAt' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
              ];

              // sub revenue get
              $UserSubRevenue = UserSubRevenue::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->where('USDAmount', '>', 0)->count();
              if ($UserSubRevenue > 0) {
                $UserSubRevenueGet = UserSubRevenue::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->where('USDAmount', '>', 0)->get();
                // return $UserSubRevenueGet;
                foreach ($UserSubRevenueGet as $value) {
                  $UserRevenuePayment = UserRevenuePayment::where('UserSubRevenueId', $value->UserSubRevenueId)->first();
                  if ($UserRevenuePayment) {
                    $PaymentStatus = $UserRevenuePayment['PaymentStatus'];
                    if ($PaymentStatus == 0)
                      $PaymentStatusSub = 'Hold';
                    else if ($PaymentStatus == 1)
                      $PaymentStatusSub = 'Approved';
                    else if ($PaymentStatus == 2)
                      $PaymentStatusSub = 'Rejected';
                    else
                      $PaymentStatusSub = 'Hold';

                    $arraySub = [
                      'UserRevenuePaymentId' => $UserRevenuePayment['UserRevenuePaymentId'],
                      'UserId' => $UserRevenuePayment['UserId'],
                      'Affiliate' => $UserRevenuePayment['Affiliate']['FirstName'] . ' ' . $UserRevenuePayment['Affiliate']['LastName'],
                      'LeadId' => $UserRevenuePayment['LeadId'],
                      'AccountId' => $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 3 || $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 4 || $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 5 || $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 6 ? $UserRevenuePayment['LeadDetail']['AccountId'] : '',
                      'RefId' => $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 1 || $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'] == 2 ? $UserRevenuePayment['LeadDetail']['RefId'] : '',
                      'AdId' => $UserRevenuePayment['LeadDetail']['AdId'],
                      'AdTitle' => $UserRevenuePayment['LeadDetail']['Ad']['Title'],
                      'AdBrand' => $UserRevenuePayment['LeadDetail']['Ad']['Brand']['Title'],
                      'AdType' => $UserRevenuePayment['LeadDetail']['Ad']['Type']['Title'],
                      'AdSize' => $UserRevenuePayment['LeadDetail']['Ad']['Size']['Width'] . '*' . $UserRevenuePayment['LeadDetail']['Ad']['Size']['Height'],
                      'AdLanguage' => $UserRevenuePayment['LeadDetail']['Ad']['Language']['LanguageName'],
                      'RevenueModelLogId' => $UserRevenuePayment['RevenueModelLogId'],
                      'RevenueTypeId' => $UserRevenuePayment['RevenueModelLog']['RevenueTypeId'],
                      'RevenueModelName' => $UserRevenuePayment['RevenueModelLog']['RevenueModelName'],
                      'RevenueTypeName' => $UserRevenuePayment['RevenueModelLog']['RevenueModel']['Revenue']['RevenueTypeName'],
                      'UserBonusId' => $UserRevenuePayment['UserBonusId'],
                      'UserSubRevenueId' => $UserRevenuePayment['UserSubRevenueId'],
                      'LeadInformationId' => $UserRevenuePayment['LeadInformationId'],
                      'LeadActivityId' => $UserRevenuePayment['LeadActivityId'],
                      'USDAmount' => round($UserRevenuePayment['USDAmount'], 8),
                      'USDAmountSpread' => round($UserRevenuePayment['SpreadUSDAmount'], 8),
                      'USDAmountTotal' => round($UserRevenuePayment['USDAmount'], 8) + round($UserRevenuePayment['SpreadUSDAmount'], 8),
                      'AUDAmount' => round($UserRevenuePayment['AUDAmount'], 8),
                      'AUDAmountSpread' => round($UserRevenuePayment['SpreadAUDAmount'], 8),
                      'AUDAmountTotal' => round($UserRevenuePayment['AUDAmount'], 8) + round($UserRevenuePayment['SpreadAUDAmount'], 8),
                      'EURAmount' => round($UserRevenuePayment['EURAmount'], 8),
                      'EURAmountSpread' => round($UserRevenuePayment['SpreadEURAmount'], 8),
                      'EURAmountTotal' => round($UserRevenuePayment['EURAmount'], 8) + round($UserRevenuePayment['SpreadEURAmount'], 8),
                      'CurrencyConvertId' => $UserRevenuePayment['CurrencyConvertId'],
                      'PaymentStatus' => $UserRevenuePayment['PaymentStatus'],
                      'PaymentStatusName' => $PaymentStatusSub,
                      'ActualRevenueDate' => date('d/m/Y h:i A', strtotime($UserRevenuePayment['ActualRevenueDate'])),
                      'CreatedAt' => date("d/m/Y h:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserRevenuePayment['CreatedAt']))),
                    ];
                    array_push($arrayList, $arraySub);
                  }
                }
              }
              // End. Sub revenue get
            }
            $array['SubRevenue'] = $arrayList;
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Revenue details.',
              "TotalCount" => 1,
              'Data' => array('UserRevenue' => $array)
            ], 200);
          } else {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Revenue details not found.',
              "TotalCount" => 0,
              'Data' => []
            ], 200);
          }
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
          'Message' => 'Invalid Token.',
          "TotalCount" => 0,
          'Data' => []
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }

  public function AdminAffiliateRevenueAcceptReject(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          // return $request->all();
          if ($request->PaymentStatus == 1) { // Approve payment 
            // return 'approve';
            $UserRevenuePaymentId = $request->UserRevenuePaymentId;
            $UserRevenuePayment = UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePaymentId)->where('PaymentStatus', 0)->first();
            if ($UserRevenuePayment) {
              // update user balance
              $user_balance = UserBalance::where('UserId', $UserRevenuePayment->UserId)->first();
              if ($user_balance) {
                $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                $user_balance->save();
                UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                  'PaymentStatus' => 1
                ]);
              }

              // sub revenue get
              $UserSubRevenue = UserSubRevenue::where('UserRevenuePaymentId', $UserRevenuePaymentId)->count();
              if ($UserSubRevenue > 0) {
                $UserSubRevenueGet = UserSubRevenue::where('UserRevenuePaymentId', $UserRevenuePaymentId)->get();
                // return 'sub Revenue';
                foreach ($UserSubRevenueGet as $value) {
                  $UserRevenuePayment = UserRevenuePayment::where('UserSubRevenueId', $value->UserSubRevenueId)->first();
                  if ($UserRevenuePayment) {
                    // update user balance 
                    $user_balance = UserBalance::where('UserId', $UserRevenuePayment->UserId)->first();
                    if ($user_balance) {
                      $user_balance->USDTotalRevenue = $user_balance->USDTotalRevenue + $UserRevenuePayment->USDAmount;
                      $user_balance->AUDTotalRevenue = $user_balance->AUDTotalRevenue + $UserRevenuePayment->AUDAmount;
                      $user_balance->EURTotalRevenue = $user_balance->EURTotalRevenue + $UserRevenuePayment->EURAmount;
                      $user_balance->USDOutstandingRevenue = $user_balance->USDOutstandingRevenue + $UserRevenuePayment->USDAmount;
                      $user_balance->AUDOutstandingRevenue = $user_balance->AUDOutstandingRevenue + $UserRevenuePayment->AUDAmount;
                      $user_balance->EUROutstandingRevenue = $user_balance->EUROutstandingRevenue + $UserRevenuePayment->EURAmount;
                      $user_balance->save();
                      UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                        'PaymentStatus' => 1
                      ]);
                    }
                  }
                }
              }
              // return 'successfully';
              return response()->json([
                'IsSuccess' => true,
                'Message' => 'Revenue approved successfully.',
                "TotalCount" => 1,
                'Data' => []
              ], 200);
            } else {
              return response()->json([
                'IsSuccess' => false,
                'Message' => 'Invalid revenue id. Revenue details not found.',
                "TotalCount" => 0,
                'Data' => []
              ], 200);
            }
          } else if ($request->PaymentStatus == 2) { // Reject payment
            $UserRevenuePaymentId = $request->UserRevenuePaymentId;
            $UserRevenuePayment = UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePaymentId)->where('PaymentStatus', 0)->first();
            if ($UserRevenuePayment) {
              UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                'PaymentStatus' => 2
              ]);
              // sub revenue get
              $UserSubRevenue = UserSubRevenue::where('UserRevenuePaymentId', $UserRevenuePaymentId)->count();
              if ($UserSubRevenue > 0) {
                $UserSubRevenueGet = UserSubRevenue::where('UserRevenuePaymentId', $UserRevenuePaymentId)->get();
                // return 'sub Revenue';
                foreach ($UserSubRevenueGet as $value) {
                  $UserRevenuePayment = UserRevenuePayment::where('UserSubRevenueId', $value->UserSubRevenueId)->first();
                  if ($UserRevenuePayment) {
                    // update user balance
                    UserRevenuePayment::where('UserRevenuePaymentId', $UserRevenuePayment->UserRevenuePaymentId)->update([
                      'PaymentStatus' => 2
                    ]);
                  }
                }
              }
              // return 'successfully';
              return response()->json([
                'IsSuccess' => true,
                'Message' => 'Revenue rejected successfully.',
                "TotalCount" => 1,
                'Data' => []
              ], 200);
            } else {
              return response()->json([
                'IsSuccess' => false,
                'Message' => 'Invalid revenue id. Revenue details not found.',
                "TotalCount" => 0,
                'Data' => []
              ], 200);
            }
          } else {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Invalid payment status.',
              "TotalCount" => 0,
              'Data' => []
            ], 200);
          }
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
          'Message' => 'Invalid Token.',
          "TotalCount" => 0,
          'Data' => []
        ], 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }

  public function GetAdminAffiliatePaymentRequest(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $affiliaterequest = UserPaymentRequest::orderBy('UserPaymentRequestId', 'DESC')->with('userrequest.Currency', 'Pay')->where('PaymentStatus', 0);
          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to = $request->DateTo;
            $affiliaterequest->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to)->get();
          }
          if (isset($request->UserId) && $request->UserId != '') {
            $User = $request->UserId;
            $affiliaterequest->where('UserId', $User)->get();
          }
          /*if(isset($request->CurrencyId) && $request->CurrencyId != '') {
              $CurrencyId = $request->CurrencyId;
              $affiliaterequest->where('CurrencyId',$CurrencyId)->get();
            }*/
          $TimeZoneOffSet = $request->TimeZoneOffSet;
          if ($TimeZoneOffSet == '')
            $TimeZoneOffSet = 0;

          $us = $affiliaterequest->get();
          $allrequest = [];

          $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('AdminVerified', 1)->where('IsEnabled', 1)->where('IsDeleted', 1)->where('RoleId', 3)->get();
          $UserList = [];
          foreach ($User as $value) {
            $arr = [
              "UserId" => $value['UserId'],
              "AffiliateName" => $value['FirstName'] . ' ' . $value['LastName'],
              "EmailId" => $value['EmailId']
            ];
            array_push($UserList, $arr);
          }
          // $USDConvert = CurrencyConvert::find(1);
          // $AUDConvert = CurrencyConvert::find(2);
          // $EURConvert = CurrencyConvert::find(3);
          $ToDay = date('Y-m-d');
          $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
          if ($CurrencyRate) {
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          } else {
            $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          }

          foreach ($us as  $value) {
            if ($request->CurrencyId == "") {
              $req_amt = $value['RequestAmount'];
              $rem_amt = $value['RemainingBalance'];
              $CurrencyCode = $value['userrequest']['currency']['CurrencyCode'];
            } else if ($request->CurrencyId == 1) {
              if ($request->CurrencyId == $value['CurrencyId']) {
                $req_amt = $value['RequestAmount'];
                $rem_amt = $value['RemainingBalance'];
              } else if ($value['CurrencyId'] == 2) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->AUDUSD;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->AUDUSD;
              } else if ($value['CurrencyId'] == 3) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->EURUSD;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->EURUSD;
              }
              $CurrencyCode = 'USD';
            } else if ($request->CurrencyId == 2) {
              if ($request->CurrencyId == $value['CurrencyId']) {
                $req_amt = $value['RequestAmount'];
                $rem_amt = $value['RemainingBalance'];
              } else if ($value['CurrencyId'] == 1) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->USDAUD;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->USDAUD;
              } else if ($value['CurrencyId'] == 3) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->EURAUD;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->EURAUD;
              }
              $CurrencyCode = 'AUD';
            } else if ($request->CurrencyId == 3) {
              if ($request->CurrencyId == $value['CurrencyId']) {
                $req_amt = $value['RequestAmount'];
                $rem_amt = $value['RemainingBalance'];
              } else if ($value['CurrencyId'] == 1) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->USDEUR;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->USDEUR;
              } else if ($value['CurrencyId'] == 2) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->AUDEUR;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->AUDEUR;
              }
              $CurrencyCode = 'EUR';
            }
            // $req_amt = $value['RequestAmount'];
            // $rem_amt = $value['RemainingBalance'];
            $var = [
              'UserId' => $value['userrequest']['UserId'],
              'RequestBy' => $value['userrequest']['FirstName'] . ' ' . $value['userrequest']['LastName'],
              'PaymentRequestId' => $value['UserPaymentRequestId'],
              'EmailId' => $value['userrequest']['EmailId'],
              'CurrencyId' => $value['userrequest']['currency']['CurrencyId'],
              'CurrencyCode' => $CurrencyCode,
              'PaymentTypeId' => $value['pay']['PaymentTypeId'],
              'PaymentMethod' => $value['pay']['PaymentTypeName'],
              'RequestAmount' => round($req_amt, 4),
              'DueAmount' => $rem_amt,
              'RequestDate' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
              // 'Status'=>$value['PaymentStatus']==2?'Received':'Request'
            ];
            array_push($allrequest, $var);
          }
          $res = [
            'IsSuccess' => true,
            'Message' => 'Show All Affiliate Request',
            'TotalCount' => $us->count(),
            'Data' => ['RequestData' => $allrequest, 'UserList' => $UserList]
          ];
          return response()->json($res, 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
        return response()->json($res, 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
          "TotalCount" => 0,
          'Data' => []
        ], 200);
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

  public function GetAdminAffiliatePaymentDeclined(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $affiliaterequest = UserPaymentRequest::orderBy('UserPaymentRequestId', 'DESC')->with('userrequest.Currency', 'Pay')->where('PaymentStatus', 3);
          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to = $request->DateTo;
            $affiliaterequest->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to)->get();
          }
          if (isset($request->UserId) && $request->UserId != '') {
            $User = $request->UserId;
            $affiliaterequest->where('UserId', $User)->get();
          }
          /*if(isset($request->CurrencyId) && $request->CurrencyId != '') {
              $CurrencyId = $request->CurrencyId;
              $affiliaterequest->where('CurrencyId',$CurrencyId)->get();
            }*/
          $TimeZoneOffSet = $request->TimeZoneOffSet;
          if ($TimeZoneOffSet == '')
            $TimeZoneOffSet = 0;

          $us = $affiliaterequest->get();
          $allrequest = [];

          $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('AdminVerified', 1)->where('IsEnabled', 1)->where('IsDeleted', 1)->where('RoleId', 3)->get();
          $UserList = [];
          foreach ($User as $value) {
            $arr = [
              "UserId" => $value['UserId'],
              "AffiliateName" => $value['FirstName'] . ' ' . $value['LastName'],
              "EmailId" => $value['EmailId']
            ];
            array_push($UserList, $arr);
          }
          // $USDConvert = CurrencyConvert::find(1);
          // $AUDConvert = CurrencyConvert::find(2);
          // $EURConvert = CurrencyConvert::find(3);
          $ToDay = date('Y-m-d');
          $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
          if ($CurrencyRate) {
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          } else {
            $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          }

          foreach ($us as  $value) {
            if ($request->CurrencyId == "") {
              $req_amt = $value['RequestAmount'];
              $rem_amt = $value['RemainingBalance'];
              $CurrencyCode = $value['userrequest']['currency']['CurrencyCode'];
            } else if ($request->CurrencyId == 1) {
              if ($request->CurrencyId == $value['CurrencyId']) {
                $req_amt = $value['RequestAmount'];
                $rem_amt = $value['RemainingBalance'];
              } else if ($value['CurrencyId'] == 2) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->AUDUSD;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->AUDUSD;
              } else if ($value['CurrencyId'] == 3) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->EURUSD;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->EURUSD;
              }
              $CurrencyCode = 'USD';
            } else if ($request->CurrencyId == 2) {
              if ($request->CurrencyId == $value['CurrencyId']) {
                $req_amt = $value['RequestAmount'];
                $rem_amt = $value['RemainingBalance'];
              } else if ($value['CurrencyId'] == 1) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->USDAUD;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->USDAUD;
              } else if ($value['CurrencyId'] == 3) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->EURAUD;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->EURAUD;
              }
              $CurrencyCode = 'AUD';
            } else if ($request->CurrencyId == 3) {
              if ($request->CurrencyId == $value['CurrencyId']) {
                $req_amt = $value['RequestAmount'];
                $rem_amt = $value['RemainingBalance'];
              } else if ($value['CurrencyId'] == 1) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->USDEUR;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->USDEUR;
              } else if ($value['CurrencyId'] == 2) {
                $req_amt = $value['RequestAmount'] * $CurrencyConvert->AUDEUR;
                $rem_amt = $value['RemainingBalance'] * $CurrencyConvert->AUDEUR;
              }
              $CurrencyCode = 'EUR';
            }
            // $req_amt = $value['RequestAmount'];
            // $rem_amt = $value['RemainingBalance'];
            $var = [
              'UserId' => $value['userrequest']['UserId'],
              'RequestBy' => $value['userrequest']['FirstName'] . ' ' . $value['userrequest']['LastName'],
              'PaymentRequestId' => $value['UserPaymentRequestId'],
              'EmailId' => $value['userrequest']['EmailId'],
              'CurrencyId' => $value['userrequest']['currency']['CurrencyId'],
              'CurrencyCode' => $CurrencyCode,
              'PaymentTypeId' => $value['pay']['PaymentTypeId'],
              'PaymentMethod' => $value['pay']['PaymentTypeName'],
              'RequestAmount' => round($req_amt, 4),
              'DueAmount' => $rem_amt,
              'RequestDate' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
              // 'Status'=>$value['PaymentStatus']==2?'Received':'Request'
            ];
            array_push($allrequest, $var);
          }
          $res = [
            'IsSuccess' => true,
            'Message' => 'Show All Affiliate Request',
            'TotalCount' => $us->count(),
            'Data' => ['RequestData' => $allrequest, 'UserList' => $UserList]
          ];
          return response()->json($res, 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
        return response()->json($res, 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
          "TotalCount" => 0,
          'Data' => []
        ], 200);
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

  public function GetAdminAffiliatePaymentHistory(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $affiliaterequest = UserPayment::with('userdata.Currency', 'Paytype', 'paymentrequest');
          if (isset($request->DateForm) && $request->DateForm != '' && isset($request->DateTo) && $request->DateTo != '') {
            $from = $request->DateForm;
            $to = $request->DateTo;
            $affiliaterequest->whereDate('CreatedAt', '>=', $from)->whereDate('CreatedAt', '<=', $to)->get();
          }
          if (isset($request->UserId) && $request->UserId != '') {
            $User = $request->UserId;
            $affiliaterequest->where('UserId', $User)
              ->get();
          }
          // if(isset($request->CurrencyId) && $request->CurrencyId != '') {
          //    $CurrencyId = $request->CurrencyId;
          //    $affiliaterequest->where('CurrencyId',$CurrencyId)
          //        ->get();
          // }
          $TimeZoneOffSet = $request->TimeZoneOffSet;
          if ($TimeZoneOffSet == '')
            $TimeZoneOffSet = 0;
          $us = $affiliaterequest->orderBy('UserPaymentId', 'desc')->get();
          $allrequest = [];
          // $USDConvert = CurrencyConvert::find(1);
          // $AUDConvert = CurrencyConvert::find(2);
          // $EURConvert = CurrencyConvert::find(3);            
          $ToDay = date('Y-m-d');
          $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
          if ($CurrencyRate) {
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          } else {
            $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          }

          $User = User::select('UserId', 'FirstName', 'LastName', 'EmailId')->where('AdminVerified', 1)->where('IsEnabled', 1)->where('IsDeleted', 1)->where('RoleId', 3)->get();

          $UserList = [];
          foreach ($User as $value) {
            $arr = [
              "UserId" => $value['UserId'],
              "AffiliateName" => $value['FirstName'] . ' ' . $value['LastName'],
              "EmailId" => $value['EmailId']
            ];
            array_push($UserList, $arr);
          }

          foreach ($us as  $value) {
            if ($request->CurrencyId == "") {
              $req_amt = $value['paymentrequest']['RequestAmount'];
              if ($value['paymentrequest']['RequestAmount'] == null)
                $DueAmount = 0;
              else
                $DueAmount = $value['paymentrequest']['RequestAmount'] - $value['PaymentAmount'];
              $PaymentAmount = $value['PaymentAmount'];
              $rem_amt = $value['RemainingBalance'];
              $CurrencyCode = $value['userdata']['currency']['CurrencyCode'];
            } else if ($request->CurrencyId == 1) {
              if ($request->CurrencyId == $value['CurrencyId']) {
                $req_amt = $value['paymentrequest']['RequestAmount'];
                $PaymentAmount = $value['PaymentAmount'];
                if ($value['paymentrequest']['RequestAmount'] == null)
                  $DueAmount = 0;
                else
                  $DueAmount = $value['paymentrequest']['RequestAmount'] - $value['PaymentAmount'];
              } else if ($value['CurrencyId'] == 2) {
                $req_amt = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->AUDUSD;
                $PaymentAmount = $value['PaymentAmount'] * $CurrencyConvert->AUDUSD;
                if ($value['paymentrequest']['RequestAmount'] == null)
                  $DueAmount = 0;
                else
                  $DueAmount = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->AUDUSD - $value['PaymentAmount'] * $CurrencyConvert->AUDUSD;
              } else if ($value['CurrencyId'] == 3) {
                $req_amt = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->EURUSD;
                $PaymentAmount = $value['PaymentAmount'] * $CurrencyConvert->EURUSD;
                if ($value['paymentrequest']['RequestAmount'] == null)
                  $DueAmount = 0;
                else
                  $DueAmount = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->EURUSD - $value['PaymentAmount'] * $CurrencyConvert->EURUSD;
              }
              $CurrencyCode = 'USD';
            } else if ($request->CurrencyId == 2) {
              if ($request->CurrencyId == $value['CurrencyId']) {
                $req_amt = $value['paymentrequest']['RequestAmount'];
                $PaymentAmount = $value['PaymentAmount'];
                if ($value['paymentrequest']['RequestAmount'] == null)
                  $DueAmount = 0;
                else
                  $DueAmount = $value['paymentrequest']['RequestAmount'] - $value['PaymentAmount'];
              } else if ($value['CurrencyId'] == 1) {
                $req_amt = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->USDAUD;
                $PaymentAmount = $value['PaymentAmount'] * $CurrencyConvert->USDAUD;
                if ($value['paymentrequest']['RequestAmount'] == null)
                  $DueAmount = 0;
                else
                  $DueAmount = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->USDAUD - $value['PaymentAmount'] * $CurrencyConvert->USDAUD;
              } else if ($value['CurrencyId'] == 3) {
                $req_amt = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->EURAUD;
                $PaymentAmount = $value['PaymentAmount'] * $CurrencyConvert->EURAUD;
                if ($value['paymentrequest']['RequestAmount'] == null)
                  $DueAmount = 0;
                else
                  $DueAmount = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->EURAUD - $value['PaymentAmount'] * $CurrencyConvert->EURAUD;
              }
              $CurrencyCode = 'AUD';
            } else if ($request->CurrencyId == 3) {
              if ($request->CurrencyId == $value['CurrencyId']) {
                $req_amt = $value['paymentrequest']['RequestAmount'];
                $PaymentAmount = $value['PaymentAmount'];
                if ($value['paymentrequest']['RequestAmount'] == null)
                  $DueAmount = 0;
                else
                  $DueAmount = $value['paymentrequest']['RequestAmount'] - $value['PaymentAmount'];
              } else if ($value['CurrencyId'] == 1) {
                $req_amt = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->USDEUR;
                $PaymentAmount = $value['PaymentAmount'] * $CurrencyConvert->USDEUR;
                if ($value['paymentrequest']['RequestAmount'] == null)
                  $DueAmount = 0;
                else
                  $DueAmount = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->USDEUR - $value['PaymentAmount'] * $CurrencyConvert->USDEUR;
              } else if ($value['CurrencyId'] == 2) {
                $req_amt = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->AUDEUR;
                $PaymentAmount = $value['PaymentAmount'] * $CurrencyConvert->AUDEUR;
                if ($value['paymentrequest']['RequestAmount'] == null)
                  $DueAmount = 0;
                else
                  $DueAmount = $value['paymentrequest']['RequestAmount'] * $CurrencyConvert->AUDEUR - $value['PaymentAmount'] * $CurrencyConvert->AUDEUR;
              }
              $CurrencyCode = 'EUR';
            }

            $sumRequestAmount = UserPaymentRequest::where('UserId', $value['userdata']['UserId'])->where('PaymentStatus', 0)->sum('RequestAmount');
            if ($value['Attachment'] && $value['Attachment'] != '')
              $Attachment = $this->storage_path . 'app/payment/' . $value['Attachment'];
            else
              $Attachment = '';

            $var = [
              'UserPaymentId' => $value['UserPaymentId'],
              'UserId' => $value['userdata']['UserId'],
              'AffiliateName' => $value['userdata']['FirstName'] . ' ' . $value['userdata']['LastName'],
              'EmailId' => $value['userdata']['EmailId'],
              'CurrencyId' => $value['userdata']['currency']['CurrencyId'],
              'CurrencyName' => $CurrencyCode,
              'Amount' => round($PaymentAmount, 2),
              'PaymentTypeId' => $value['paytype']['PaymentTypeId'],
              'PaymentTypeName' => $value['paytype']['PaymentTypeName'],
              'RequestAmount' => round($req_amt, 2),
              'DueAmount' => round($DueAmount, 2),
              'RequestDate' => (string) $value['paymentrequest']['CreatedAt'] == "" ? "" : date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['paymentrequest']['CreatedAt']))),
              'PaidAmount' => round($PaymentAmount, 2),
              'PaidDate' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($value['CreatedAt']))),
              'PaymentActualDate' => date('d/m/Y h:i A', strtotime($TimeZoneOffSet . " minutes", strtotime($value['DateOfPayment']))),
              'Attachment' => $Attachment,
              'Comment' => $value['Comments'],
            ];
            array_push($allrequest, $var);
          }

          $res = [
            'IsSuccess' => true,
            'Message' => 'Show all affiliate payment history.',
            'TotalCount' => $us->count(),
            'Data' => ['PaymentData' => $allrequest, 'UserList' => $UserList]
          ];

          return response()->json($res, 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
        }
        return response()->json($res, 200);
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Invalid Token.',
          "TotalCount" => 0,
          'Data' => []
        ], 200);
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

  public function GetPaymentDetailsByUserPaymentId(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $UserPayment = UserPayment::with('userdata.Currency', 'Paytype', 'paymentrequest')->where('UserPaymentId', $request->UserPaymentId)->first();
          $TimeZoneOffSet = $request->Timezone;
          if ($TimeZoneOffSet == '')
            $TimeZoneOffSet = 0;

          if ($UserPayment) {
            $sumRequestAmount = UserPaymentRequest::where('UserId', $UserPayment['userdata']['UserId'])->where('PaymentStatus', 0)->sum('RequestAmount');
            if ($UserPayment['Attachment'] && $UserPayment['Attachment'] != '')
              $Attachment = $this->storage_path . 'app/payment/' . $UserPayment['Attachment'];
            else
              $Attachment = '';

            $UserBalance = UserBalance::where('UserId', $UserPayment['userdata']['UserId'])->first();
            $UserDetail = User::find($UserPayment['userdata']['UserId']);
            if ($UserDetail->CurrencyId == 1) {
              $OutstandingRevenue = $UserBalance->USDOutstandingRevenue;
            } else if ($UserDetail->CurrencyId == 2) {
              $OutstandingRevenue = $UserBalance->AUDOutstandingRevenue;
            } else if ($UserDetail->CurrencyId == 3) {
              $OutstandingRevenue = $UserBalance->EUROutstandingRevenue;
            }
            $var = [
              'UserPaymentId' => $UserPayment['UserPaymentId'],
              'UserId' => $UserPayment['userdata']['UserId'],
              'AffiliateName' => $UserPayment['userdata']['FirstName'] . ' ' . $UserPayment['userdata']['LastName'],
              'EmailId' => $UserPayment['userdata']['EmailId'],
              'CurrencyId' => $UserPayment['userdata']['currency']['CurrencyId'],
              'CurrencyName' => $UserPayment['userdata']['currency']['CurrencyCode'],
              'Amount' => $UserPayment['PaymentAmount'],
              'PaymentTypeId' => $UserPayment['paytype']['PaymentTypeId'],
              'PaymentTypeName' => $UserPayment['paytype']['PaymentTypeName'],
              'RequestAmount' => $UserPayment['paymentrequest']['RequestAmount'],
              'DueAmount' => $UserPayment['paymentrequest']['RequestAmount'] - $UserPayment['PaymentAmount'],
              'RequestDate' => (string) $UserPayment['paymentrequest']['CreatedAt'] == "" ? "" : date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserPayment['paymentrequest']['CreatedAt']))),
              'PaidAmount' => $UserPayment['PaymentAmount'],
              'PaidDate' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime($UserPayment['CreatedAt']))),
              'PaymentActualDate' => $UserPayment['DateOfPayment'],
              'Attachment' => $Attachment,
              'Comment' => $UserPayment['Comments'],
              'OutstandingRevenue' => $OutstandingRevenue
            ];

            $res = [
              'IsSuccess' => true,
              'Message' => 'Affiliate payment history.',
              'TotalCount' => 1,
              'Data' => ['PaymentData' => $var]
            ];
            return response()->json($res, 200);
          } else {
            $res = [
              'IsSuccess' => false,
              'Message' => 'Not found affiliate payment history.',
              'TotalCount' => 1,
              'Data' => ['PaymentData' => []]
            ];
            return response()->json($res, 200);
          }
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

  public function CreatePayment(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $InputData = json_decode($request->AddPaymentData);
          $UserId = $InputData->UserId;
          $UserPaymentRequestId = $InputData->UserPaymentRequestId;
          $UserPaymentAmount = $InputData->PaymentAmount;
          $RejectRequestPaymentIds = $InputData->RejectRequestPaymentIds;
          $PaymentDate = $InputData->PaymentDate;
          $PaymentTypeId = $InputData->PaymentTypeId;
          $CurrencyId = $InputData->CurrencyId;
          $Comment = $InputData->Comment;

          // $USDConvert = CurrencyConvert::find(1);
          // $AUDConvert = CurrencyConvert::find(2);
          // $EURConvert = CurrencyConvert::find(3);

          // $ToDay = date('Y-m-d');
          $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $PaymentDate)->orderBy('CurrencyRateId', 'desc')->first();
          if ($CurrencyRate) {
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          } else {
            $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $PaymentDate)->orderBy('CurrencyRateId', 'desc')->first();
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          }

          if ($CurrencyId == 1) {
            $USDAmount = $UserPaymentAmount;
            $AUDAmount = $UserPaymentAmount * $CurrencyConvert->USDAUD;
            $EURAmount = $UserPaymentAmount * $CurrencyConvert->USDEUR;
          } else if ($CurrencyId == 2) {
            $AUDAmount = $UserPaymentAmount;
            $USDAmount = $UserPaymentAmount * $CurrencyConvert->AUDUSD;
            $EURAmount = $UserPaymentAmount * $CurrencyConvert->AUDEUR;
          } else if ($CurrencyId == 3) {
            $EURAmount = $UserPaymentAmount;
            $AUDAmount = $UserPaymentAmount * $CurrencyConvert->EURAUD;
            $USDAmount = $UserPaymentAmount * $CurrencyConvert->EURUSD;
          }

          if (isset($InputData->UserPaymentId) && $InputData->UserPaymentId != '') {
            // update payment
            $UserPayment = UserPayment::find($InputData->UserPaymentId);
            if ($UserPayment) {
              $UserPaymentUSDAmount = $UserPayment->USDAmount;
              $UserPaymentAUDAmount = $UserPayment->AUDAmount;
              $UserPaymentEURAmount = $UserPayment->EURAmount;
              $UserBalance = UserBalance::where('UserId', $UserPayment->UserId)->first();
              $UserDetail = User::find($UserPayment->UserId);
              if ($UserDetail->CurrencyId == 1) {
                $TotalRevenue = $UserBalance->USDTotalRevenue;
                $Paid = $UserBalance->USDPaid;
                $OutstandingRevenue = $UserBalance->USDOutstandingRevenue;
                $TotalDuePayment = $UserBalance->USDTotalDuepayment;
              } else if ($UserDetail->CurrencyId == 2) {
                $TotalRevenue = $UserBalance->AUDTotalRevenue;
                $Paid = $UserBalance->AUDPaid;
                $OutstandingRevenue = $UserBalance->AUDOutstandingRevenue;
                $TotalDuePayment = $UserBalance->AUDTotalDuepayment;
              } else if ($UserDetail->CurrencyId == 3) {
                $TotalRevenue = $UserBalance->EURTotalRevenue;
                $Paid = $UserBalance->EURPaid;
                $OutstandingRevenue = $UserBalance->EUROutstandingRevenue;
                $TotalDuePayment = $UserBalance->EURTotalDuepayment;
              }
              $totalRequest = UserPaymentRequest::where(['UserId' => $UserPayment->UserId, 'PaymentStatus' => 0])->count();
              if ($totalRequest > 0) {
                $RequestAmount = UserPaymentRequest::where(['UserId' => $UserPayment->UserId, 'PaymentStatus' => 0])->sum('RequestAmount');
                $DueAmount = $OutstandingRevenue - $RequestAmount;
              } else {
                $DueAmount = $OutstandingRevenue;
              }

              if ($UserPaymentAmount == $UserPayment->PaymentAmount) {
                // no need to update payment if not change payment amount, update other info
                if ($request->hasFile('Attachment')) {
                  $image = $request->file('Attachment');
                  $PaymentFileName = 'Payment-' . time() . '.' . $image->getClientOriginalExtension();
                  $destinationPath = storage_path('/app/payment');
                  $image->move($destinationPath, $PaymentFileName);
                } else {
                  $PaymentFileName = $UserPayment->Attachment;
                }
                $UserPayment->DateOfPayment = $PaymentDate;
                $UserPayment->Attachment = $PaymentFileName;
                $UserPayment->Comments = $Comment;
                $UserPayment->UpdatedBy = $log_user->UserId;
                $UserPayment->save();
                return response()->json([
                  'IsSuccess' => true,
                  'Message' => 'Payment updated successfully.',
                  "TotalCount" => 1,
                  'Data' => ['PaymentData' => $UserPayment]
                ], 200);
              } else if ($UserPaymentAmount < $UserPayment->PaymentAmount) {
                // return 'Done, if($UserPaymentAmount < $UserPayment->PaymentAmount)'; die;
                // update payment+user balance if(new amount < old amount, Payment Amount < User request amount )
                // update user balance
                if ($UserDetail->CurrencyId == 1) {
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + ($UserPaymentAmount - $UserPayment->PaymentAmount);
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - ($UserPaymentAmount - $UserPayment->PaymentAmount);
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + ($AUDAmount - $UserPaymentAUDAmount);
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - ($AUDAmount - $UserPaymentAUDAmount);
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + ($EURAmount - $UserPaymentEURAmount);
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - ($EURAmount - $UserPaymentEURAmount);
                } else if ($UserDetail->CurrencyId == 2) {
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + ($UserPaymentAmount - $UserPayment->PaymentAmount);
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - ($UserPaymentAmount - $UserPayment->PaymentAmount);
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + ($USDAmount - $UserPaymentUSDAmount);
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - ($USDAmount - $UserPaymentUSDAmount);
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + ($EURAmount - $UserPaymentEURAmount);
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - ($EURAmount - $UserPaymentEURAmount);
                } else if ($UserDetail->CurrencyId == 3) {
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + ($UserPaymentAmount - $UserPayment->PaymentAmount);
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - ($UserPaymentAmount - $UserPayment->PaymentAmount);
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + ($USDAmount - $UserPaymentUSDAmount);
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - ($USDAmount - $UserPaymentUSDAmount);
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + ($AUDAmount - $UserPaymentAUDAmount);
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - ($AUDAmount - $UserPaymentAUDAmount);
                }
                $UserBalance->save();

                if ($request->hasFile('Attachment')) {
                  $image = $request->file('Attachment');
                  $PaymentFileName = 'Payment-' . time() . '.' . $image->getClientOriginalExtension();
                  $destinationPath = storage_path('/app/payment');
                  $image->move($destinationPath, $PaymentFileName);
                } else {
                  $PaymentFileName = $UserPayment->Attachment;
                }
                // update user payment
                $UserPayment->PaymentAmount = $UserPaymentAmount;
                $UserPayment->USDAmount = $USDAmount;
                $UserPayment->EURAmount = $EURAmount;
                $UserPayment->AUDAmount = $AUDAmount;
                $UserPayment->DateOfPayment = $PaymentDate;
                $UserPayment->Attachment = $PaymentFileName;
                $UserPayment->Comments = $Comment;
                $UserPayment->UpdatedBy = $log_user->UserId;
                $UserPayment->save();

                return response()->json([
                  'IsSuccess' => true,
                  'Message' => 'Payment updated successfully.',
                  "TotalCount" => 1,
                  'Data' => ['PaymentData' => $UserPayment]
                ], 200);
              } else {
                if (isset($UserPayment->UserPaymentRequestId) && $UserPayment->UserPaymentRequestId != '') {
                  $UserPaymentRequest = UserPaymentRequest::find($UserPayment->UserPaymentRequestId);
                  if ($UserPaymentAmount > $UserPaymentRequest->RequestAmount) {
                    return response()->json([
                      'IsSuccess' => false,
                      'Message' => 'Payment amount is greater than request amount.',
                      "TotalCount" => 0,
                      'Data' => []
                    ], 200);
                  }
                }
                if ($UserPaymentAmount <= $DueAmount + $UserPayment->PaymentAmount) {
                  // return 'update payment if($UserPaymentAmount > $UserPayment->PaymentAmount)'; die;
                  // update payment+user balance if(new amount < old amount)
                  // update user balance
                  if ($UserDetail->CurrencyId == 1) {
                    // USD
                    $UserBalance->USDPaid = $UserBalance->USDPaid + ($UserPaymentAmount - $UserPayment->PaymentAmount);
                    $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - ($UserPaymentAmount - $UserPayment->PaymentAmount);
                    // AUD
                    $UserBalance->AUDPaid = $UserBalance->AUDPaid + ($AUDAmount - $UserPaymentAUDAmount);
                    $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - ($AUDAmount - $UserPaymentAUDAmount);
                    // EUR
                    $UserBalance->EURPaid = $UserBalance->EURPaid + ($EURAmount - $UserPaymentEURAmount);
                    $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - ($EURAmount - $UserPaymentEURAmount);
                  } else if ($UserDetail->CurrencyId == 2) {
                    // AUD
                    $UserBalance->AUDPaid = $UserBalance->AUDPaid + ($UserPaymentAmount - $UserPayment->PaymentAmount);
                    $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - ($UserPaymentAmount - $UserPayment->PaymentAmount);
                    // USD
                    $UserBalance->USDPaid = $UserBalance->USDPaid + ($USDAmount - $UserPaymentUSDAmount);
                    $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - ($USDAmount - $UserPaymentUSDAmount);
                    // EUR
                    $UserBalance->EURPaid = $UserBalance->EURPaid + ($EURAmount - $UserPaymentEURAmount);
                    $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - ($EURAmount - $UserPaymentEURAmount);
                  } else if ($UserDetail->CurrencyId == 3) {
                    // EUR
                    $UserBalance->EURPaid = $UserBalance->EURPaid + ($UserPaymentAmount - $UserPayment->PaymentAmount);
                    $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - ($UserPaymentAmount - $UserPayment->PaymentAmount);
                    // USD
                    $UserBalance->USDPaid = $UserBalance->USDPaid + ($USDAmount - $UserPaymentUSDAmount);
                    $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - ($USDAmount - $UserPaymentUSDAmount);
                    // AUD
                    $UserBalance->AUDPaid = $UserBalance->AUDPaid + ($AUDAmount - $UserPaymentAUDAmount);
                    $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - ($AUDAmount - $UserPaymentAUDAmount);
                  }
                  $UserBalance->save();

                  if ($request->hasFile('Attachment')) {
                    $image = $request->file('Attachment');
                    $PaymentFileName = 'Payment-' . time() . '.' . $image->getClientOriginalExtension();
                    $destinationPath = storage_path('/app/payment');
                    $image->move($destinationPath, $PaymentFileName);
                  } else {
                    $PaymentFileName = $UserPayment->Attachment;
                  }
                  // update user payment
                  $UserPayment->PaymentAmount = $UserPaymentAmount;
                  $UserPayment->USDAmount = $USDAmount;
                  $UserPayment->EURAmount = $EURAmount;
                  $UserPayment->AUDAmount = $AUDAmount;
                  $UserPayment->DateOfPayment = $PaymentDate;
                  $UserPayment->Attachment = $PaymentFileName;
                  $UserPayment->Comments = $Comment;
                  $UserPayment->UpdatedBy = $log_user->UserId;
                  $UserPayment->save();

                  return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Payment updated successfully.',
                    "TotalCount" => 1,
                    'Data' => ['PaymentData' => $UserPayment]
                  ], 200);
                } else {
                  return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Payment is greater than remaining balance.',
                    "TotalCount" => 0,
                    'Data' => []
                  ], 200);
                }
              }
            } else {
              return response()->json([
                'IsSuccess' => false,
                'Message' => 'User payment not found.',
                "TotalCount" => 0,
                'Data' => []
              ], 200);
            }
          } else if (isset($RejectRequestPaymentIds) && $RejectRequestPaymentIds != '' && $RejectRequestPaymentIds != 0) {

            if ($request->hasFile('Attachment')) {
              $image = $request->file('Attachment');
              $PaymentFileName = 'Payment-' . time() . '.' . $image->getClientOriginalExtension();
              $destinationPath = storage_path('/app/payment');
              $image->move($destinationPath, $PaymentFileName);
            } else {
              $PaymentFileName = "";
            }

            $UserBalance = UserBalance::where('UserId', $UserId)->first();
            $UserDetail = User::find($UserId);
            if ($UserDetail->CurrencyId == 1) {
              $TotalRevenue = $UserBalance->USDTotalRevenue;
              $Paid = $UserBalance->USDPaid;
              $OutstandingRevenue = $UserBalance->USDOutstandingRevenue;
              $TotalDuePayment = $UserBalance->USDTotalDuepayment;
            } else if ($UserDetail->CurrencyId == 2) {
              $TotalRevenue = $UserBalance->AUDTotalRevenue;
              $Paid = $UserBalance->AUDPaid;
              $OutstandingRevenue = $UserBalance->AUDOutstandingRevenue;
              $TotalDuePayment = $UserBalance->AUDTotalDuepayment;
            } else if ($UserDetail->CurrencyId == 3) {
              $TotalRevenue = $UserBalance->EURTotalRevenue;
              $Paid = $UserBalance->EURPaid;
              $OutstandingRevenue = $UserBalance->EUROutstandingRevenue;
              $TotalDuePayment = $UserBalance->EURTotalDuepayment;
            }

            if ($UserBalance) {
              $UserDetail = User::find($UserId);
              if ($RejectRequestPaymentIds) {
                if ($UserDetail->CurrencyId == 1) {
                  $remaingbalance = $UserBalance->USDOutstandingRevenue - $UserPaymentAmount;
                } else if ($UserDetail->CurrencyId == 2) {
                  $remaingbalance = $UserBalance->AUDOutstandingRevenue - $UserPaymentAmount;
                } else if ($UserDetail->CurrencyId == 3) {
                  $remaingbalance = $UserBalance->EUROutstandingRevenue - $UserPaymentAmount;
                }
                $paymentCreate = UserPayment::create([
                  'UserId' => $UserId,
                  'InitialBalance' => $OutstandingRevenue,
                  'PaymentAmount' => $UserPaymentAmount,
                  'RemaingBalance' => $remaingbalance,
                  'USDAmount' => $USDAmount,
                  'EURAmount' => $EURAmount,
                  'AUDAmount' => $AUDAmount,
                  'DateOfPayment' => $PaymentDate,
                  'PaymentTypeId' => $PaymentTypeId,
                  'CurrencyId' => $CurrencyId,
                  'Attachment' => $PaymentFileName,
                  'Comments' => $Comment,
                  'CreatedBy' => $log_user->UserId,
                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                ]);

                foreach (explode(",", $RejectRequestPaymentIds) as $rw) {
                  UserPaymentRequest::where('UserPaymentRequestId', $rw)->update([
                    'PaymentStatus' => 3
                  ]);
                }
                if ($UserDetail->CurrencyId == 1) {
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + $UserPaymentAmount;
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - $UserPaymentAmount;
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + $AUDAmount;
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - $AUDAmount;
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + $EURAmount;
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - $EURAmount;
                } else if ($UserDetail->CurrencyId == 2) {
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + $UserPaymentAmount;
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - $UserPaymentAmount;
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + $USDAmount;
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - $USDAmount;
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + $EURAmount;
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - $EURAmount;
                } else if ($UserDetail->CurrencyId == 3) {
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + $UserPaymentAmount;
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - $UserPaymentAmount;
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + $AUDAmount;
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - $AUDAmount;
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + $USDAmount;
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - $USDAmount;
                }
                $UserBalance->save();

                return response()->json([
                  'IsSuccess' => true,
                  'Message' => 'Payment Done successfully.',
                  "TotalCount" => $paymentCreate->count(),
                  'Data' => ['PaymentData' => $paymentCreate]
                ], 200);
              }
            }
          } else {
            // Create payment
            if (isset($UserPaymentRequestId) && $UserPaymentRequestId != '' && $UserPaymentRequestId != 0) {
              $UserPaymentRequest = UserPaymentRequest::where('UserPaymentRequestId', $UserPaymentRequestId)->where('PaymentStatus', 0)->first();
              if ($UserPaymentRequest) {
                if ($CurrencyId == 1) {
                  $UserPaymentRequestUSDAmount = $UserPaymentRequest->RequestAmount;
                  $UserPaymentRequestAUDAmount = $UserPaymentRequest->RequestAmount * $CurrencyConvert->USDAUD;
                  $UserPaymentRequestEURAmount = $UserPaymentRequest->RequestAmount * $CurrencyConvert->USDEUR;
                } else if ($CurrencyId == 2) {
                  $UserPaymentRequestAUDAmount = $UserPaymentAmount;
                  $UserPaymentRequestUSDAmount = $UserPaymentRequest->RequestAmount * $CurrencyConvert->AUDUSD;
                  $UserPaymentRequestEURAmount = $UserPaymentRequest->RequestAmount * $CurrencyConvert->AUDEUR;
                } else if ($CurrencyId == 3) {
                  $UserPaymentRequestEURAmount = $UserPaymentAmount;
                  $UserPaymentRequestUSDAmount = $UserPaymentRequest->RequestAmount * $CurrencyConvert->EURUSD;
                  $UserPaymentRequestAUDAmount = $UserPaymentRequest->RequestAmount * $CurrencyConvert->EURAUD;
                }

                if ($UserPaymentAmount > $UserPaymentRequest->RequestAmount) {
                  return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'User payment amount is grater than request amount.',
                    "TotalCount" => 0,
                    'Data' => ['PaymentData' => []]
                  ], 200);
                }
              } else {
                return response()->json([
                  'IsSuccess' => false,
                  'Message' => 'User payment request not found.',
                  "TotalCount" => 0,
                  'Data' => array('PaymentData' => [])
                ], 200);
              }
            }

            if ($request->hasFile('Attachment')) {
              $image = $request->file('Attachment');
              $PaymentFileName = 'Payment-' . time() . '.' . $image->getClientOriginalExtension();
              $destinationPath = storage_path('/app/payment');
              $image->move($destinationPath, $PaymentFileName);
            } else {
              $PaymentFileName = "";
            }

            $UserBalance = UserBalance::where('UserId', $UserId)->first();
            $UserDetail = User::find($UserId);
            if ($UserDetail->CurrencyId == 1) {
              $TotalRevenue = $UserBalance->USDTotalRevenue;
              $Paid = $UserBalance->USDPaid;
              $OutstandingRevenue = $UserBalance->USDOutstandingRevenue;
              $TotalDuePayment = $UserBalance->USDTotalDuepayment;
            } else if ($UserDetail->CurrencyId == 2) {
              $TotalRevenue = $UserBalance->AUDTotalRevenue;
              $Paid = $UserBalance->AUDPaid;
              $OutstandingRevenue = $UserBalance->AUDOutstandingRevenue;
              $TotalDuePayment = $UserBalance->AUDTotalDuepayment;
            } else if ($UserDetail->CurrencyId == 3) {
              $TotalRevenue = $UserBalance->EURTotalRevenue;
              $Paid = $UserBalance->EURPaid;
              $OutstandingRevenue = $UserBalance->EUROutstandingRevenue;
              $TotalDuePayment = $UserBalance->EURTotalDuepayment;
            }
            if ($UserBalance) {
              if (isset($UserPaymentRequestId) && $UserPaymentRequestId != '' && $UserPaymentRequestId != 0) {
                $remaingbalance = $OutstandingRevenue - $UserPaymentAmount;
                $payment_create = UserPayment::create([
                  'UserId' => $UserId,
                  'UserPaymentRequestId' => $UserPaymentRequest->UserPaymentRequestId,
                  'InitialBalance' => $UserPaymentRequest->RemainingBalance,
                  'PaymentAmount' => $UserPaymentAmount,
                  'RemaingBalance' => $remaingbalance,
                  'USDAmount' => $USDAmount,
                  'EURAmount' => $EURAmount,
                  'AUDAmount' => $AUDAmount,
                  'DateOfPayment' => $PaymentDate,
                  'PaymentTypeId' => $PaymentTypeId,
                  'CurrencyId' => $CurrencyId,
                  'Attachment' => $PaymentFileName,
                  'Comments' => $Comment,
                  'CreatedBy' => $log_user->UserId,
                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                ]);

                if ($UserDetail->CurrencyId == 1) {
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + $UserPaymentAmount;
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - $UserPaymentAmount;
                  $UserBalance->USDTotalDuepayment = $UserBalance->USDTotalDuepayment - $UserPaymentRequest->RequestAmount;
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + $AUDAmount;
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - $AUDAmount;
                  $UserBalance->AUDTotalDuepayment = $UserBalance->AUDTotalDuepayment - $UserPaymentRequestAUDAmount;
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + $EURAmount;
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - $EURAmount;
                  $UserBalance->EURTotalDuepayment = $UserBalance->EURTotalDuepayment - $UserPaymentRequestEURAmount;
                } else if ($UserDetail->CurrencyId == 2) {
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + $UserPaymentAmount;
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - $UserPaymentAmount;
                  $UserBalance->AUDTotalDuepayment = $UserBalance->AUDTotalDuepayment - $UserPaymentRequest->RequestAmount;
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + $USDAmount;
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - $EURAmount;
                  $UserBalance->USDTotalDuepayment = $UserBalance->USDTotalDuepayment - $UserPaymentRequestUSDAmount;
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + $EURAmount;
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - $EURAmount;
                  $UserBalance->EURTotalDuepayment = $UserBalance->EURTotalDuepayment - $UserPaymentRequestEURAmount;
                } else if ($UserDetail->CurrencyId == 3) {
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + $UserPaymentAmount;
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - $UserPaymentAmount;
                  $UserBalance->EURTotalDuepayment = $UserBalance->EURTotalDuepayment - $UserPaymentRequest->RequestAmount;
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + $USDAmount;
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - $EURAmount;
                  $UserBalance->USDTotalDuepayment = $UserBalance->USDTotalDuepayment - $UserPaymentRequestUSDAmount;
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + $AUDAmount;
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - $AUDAmount;
                  $UserBalance->AUDTotalDuepayment = $UserBalance->AUDTotalDuepayment - $UserPaymentRequestAUDAmount;
                }
                $UserBalance->save();

                // $UserPaymentRequest->DueAmount = $UserPaymentRequest->RequestAmount-$UserPaymentAmount;
                $UserPaymentRequest->PaymentStatus = 2;
                $UserPaymentRequest->CreatedAt = (string) $UserPaymentRequest->CreatedAt;
                $UserPaymentRequest->save();

                return response()->json([
                  'IsSuccess' => true,
                  'Message' => 'Request Payment Done successfully.',
                  "TotalCount" => $payment_create->count(),
                  'Data' => ['PaymentData' => $payment_create]
                ], 200);
              } else {
                $remaingbalance = $OutstandingRevenue - $UserPaymentAmount;
                $paymentCreate = UserPayment::create([
                  'UserId' => $UserId,
                  'InitialBalance' => $OutstandingRevenue,
                  'PaymentAmount' => $UserPaymentAmount,
                  'RemaingBalance' => $remaingbalance,
                  'USDAmount' => $USDAmount,
                  'EURAmount' => $EURAmount,
                  'AUDAmount' => $AUDAmount,
                  'DateOfPayment' => $PaymentDate,
                  'PaymentTypeId' => $PaymentTypeId,
                  'CurrencyId' => $CurrencyId,
                  'Attachment' => $PaymentFileName,
                  'Comments' => $Comment,
                  'CreatedBy' => $log_user->UserId,
                  'CurrencyConvertId' => $CurrencyConvert['CurrencyConvertId'],
                ]);
                if ($UserDetail->CurrencyId == 1) {
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + $UserPaymentAmount;
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - $UserPaymentAmount;
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + $AUDAmount;
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - $AUDAmount;
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + $EURAmount;
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - $EURAmount;
                } else if ($UserDetail->CurrencyId == 2) {
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + $UserPaymentAmount;
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - $UserPaymentAmount;
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + $USDAmount;
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - $USDAmount;
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + $EURAmount;
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - $EURAmount;
                } else if ($UserDetail->CurrencyId == 3) {
                  // EUR
                  $UserBalance->EURPaid = $UserBalance->EURPaid + $UserPaymentAmount;
                  $UserBalance->EUROutstandingRevenue = $UserBalance->EUROutstandingRevenue - $UserPaymentAmount;
                  // USD
                  $UserBalance->USDPaid = $UserBalance->USDPaid + $USDAmount;
                  $UserBalance->USDOutstandingRevenue = $UserBalance->USDOutstandingRevenue - $USDAmount;
                  // AUD
                  $UserBalance->AUDPaid = $UserBalance->AUDPaid + $AUDAmount;
                  $UserBalance->AUDOutstandingRevenue = $UserBalance->AUDOutstandingRevenue - $AUDAmount;
                }
                $UserBalance->save();

                return response()->json([
                  'IsSuccess' => true,
                  'Message' => 'Payment done successfully.',
                  "TotalCount" => 1,
                  'Data' => ['PaymentData' => $paymentCreate]
                ], 200);
              }
            } else {
              return response()->json([
                'IsSuccess' => false,
                'Message' => 'User balance not found.',
                "TotalCount" => 0,
                'Data' => []
              ], 200);
            }
          }
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
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }

  public function ExportAdminPaymentDetails(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {

          if ($request->UserId == "") {
            $affiliate_user = User::with('UserBalance', 'currency', 'BankDetail.payment')->where('IsDeleted', 1)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('RoleId', 3)->get();
          } else {
            $affiliate_user = User::with('UserBalance', 'currency', 'BankDetail.payment')->where('IsDeleted', 1)->where('EmailVerified', 1)->where('AdminVerified', 1)->where('RoleId', 3)->where('UserId', $request->UserId)->get();
          }


          $data = [];
          // $USDConvert = CurrencyConvert::find(1);
          // $AUDConvert = CurrencyConvert::find(2);
          // $EURConvert = CurrencyConvert::find(3);                    
          $ToDay = date('Y-m-d');
          $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
          if ($CurrencyRate) {
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          } else {
            $CurrencyRate = CurrencyRate::where('Status', 1)->whereDate('Date', '<', $ToDay)->orderBy('CurrencyRateId', 'desc')->first();
            $CurrencyConvert = CurrencyConvert::where('CurrencyRateId', $CurrencyRate->CurrencyRateId)->first();
          }

          foreach ($affiliate_user as  $value) {

            if ($request->CurrencyId == "") {
              $tot_rev = $value['UserBalance']['TotalRevenue'];
              $paid = $value['UserBalance']['Paid'];
              $out_stand = $value['UserBalance']['OutstandingRevenue'];
            } else if ($request->CurrencyId == 1) {
              if ($request->CurrencyId == $value['currency']['CurrencyId']) {
                $tot_rev = $value['UserBalance']['TotalRevenue'];
                $paid = $value['UserBalance']['Paid'];
                $out_stand = $value['UserBalance']['OutstandingRevenue'];
              } else if ($value['currency']['CurrencyId'] == 2) {

                $tot_rev = $value['UserBalance']['TotalRevenue'] * $CurrencyConvert->AUDUSD;
                $paid = $value['UserBalance']['Paid'] * $CurrencyConvert->AUDUSD;
                $out_stand = $value['UserBalance']['OutstandingRevenue'] * $CurrencyConvert->AUDUSD;
              } else if ($value['currency']['CurrencyId'] == 3) {
                $tot_rev = $value['UserBalance']['TotalRevenue'] * $CurrencyConvert->EURUSD;
                $paid = $value['UserBalance']['Paid'] * $CurrencyConvert->EURUSD;
                $out_stand = $value['UserBalance']['OutstandingRevenue'] * $CurrencyConvert->EURUSD;
              }
            } else if ($request->CurrencyId == 2) {

              if ($request->CurrencyId == $value['currency']['CurrencyId']) {
                $tot_rev = $value['UserBalance']['TotalRevenue'];
                $paid = $value['UserBalance']['Paid'];
                $out_stand = $value['UserBalance']['OutstandingRevenue'];
              } else if ($value['currency']['CurrencyId'] == 1) {
                $tot_rev = $value['UserBalance']['TotalRevenue'] * $CurrencyConvert->USDAUD;
                $paid = $value['UserBalance']['Paid'] * $CurrencyConvert->USDAUD;
                $out_stand = $value['UserBalance']['OutstandingRevenue'] * $CurrencyConvert->USDAUD;
              } else if ($value['currency']['CurrencyId'] == 3) {
                $tot_rev = $value['UserBalance']['TotalRevenue'] * $CurrencyConvert->EURAUD;
                $paid = $value['UserBalance']['Paid'] * $CurrencyConvert->EURAUD;
                $out_stand = $value['UserBalance']['OutstandingRevenue'] * $CurrencyConvert->EURAUD;
              }
            } else if ($request->CurrencyId == 3) {

              if ($request->CurrencyId == $value['currency']['CurrencyId']) {
                $tot_rev = $value['UserBalance']['TotalRevenue'];
                $paid = $value['UserBalance']['Paid'];
                $out_stand = $value['UserBalance']['OutstandingRevenue'];
              } else if ($value['currency']['CurrencyId'] == 1) {
                $tot_rev = $value['UserBalance']['TotalRevenue'] * $CurrencyConvert->USDEUR;
                $paid = $value['UserBalance']['Paid'] * $CurrencyConvert->USDEUR;
                $out_stand = $value['UserBalance']['OutstandingRevenue'] * $CurrencyConvert->USDEUR;
              } else if ($value['currency']['CurrencyId'] == 2) {
                $tot_rev = $value['UserBalance']['TotalRevenue'] * $CurrencyConvert->AUDEUR;
                $paid = $value['UserBalance']['Paid'] * $CurrencyConvert->AUDEUR;
                $out_stand = $value['UserBalance']['OutstandingRevenue'] * $CurrencyConvert->AUDEUR;
              }
            }
            $var = [
              // 'UserId' => $value['UserId'],
              'AffiliateName' => $value['FirstName'] . ' ' . $value['LastName'],
              'EmailId' => $value['EmailId'],
              'CurrencyName' => $value['currency']['CurrencyCode'],
              // 'CurrencyId' => $value['currency']['CurrencyId'],
              'RoyalRevenue' => 0,
              // 'PaymentTypeId' => $value['BankDetail']['payment']['PaymentTypeId'],
              'PaymentTypeName' => $value['BankDetail']['payment']['PaymentTypeName'],
              'AffiliateRevenue' => $tot_rev,
              'Paid' => $paid,
              'OutstandingRevenue' => $out_stand,
            ];


            array_push($data, $var);
          }

          Excel::create('AffiliateBillingAndPayments', function ($excel) use ($data) {
            $excel->sheet('AffiliateBillingAndPayments', function ($sheet) use ($data) {
              $sheet->fromArray($data);
            });
          })->store('xls', false, true);

          return response()->json([
            'IsSuccess' => true,
            'Message' => 'Export affiliate royal balance sheet successfully.',
            "TotalCount" => 1,
            'Data' => ['BalanceExcel' => 'http://differenzuat.com/affiliate_api/storage/exports/AffiliateBillingAndPayments.xls']
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
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }

  public function GetSingleAffiliatePaymentRequest(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $both = UserBankDetail::whereNotNull('BankName')->whereNotNull('AccountBeneficiary')
            ->whereNotNull('AccountNumber')->whereNotNull('BankBranch')
            ->whereNotNull('BankCity')
            ->whereNotNull('CountryId')
            ->whereNotNull('SwiftCode')->whereNotNull('IBANNumber')
            ->whereNotNull('ABANumber')->whereNotNull('BankCorrespondent')
            ->whereNotNull('VATNumber')->whereNotNull('MT4LoginNumber')->where('UserId', $request->UserId)->get();
          $Bank_Wire = UserBankDetail::whereNotNull('BankName')->whereNotNull('AccountBeneficiary')
            ->whereNotNull('AccountNumber')->whereNotNull('BankBranch')
            ->whereNotNull('BankCity')
            ->whereNotNull('CountryId')
            ->whereNotNull('SwiftCode')->whereNotNull('IBANNumber')
            ->whereNotNull('ABANumber')->whereNotNull('BankCorrespondent')
            ->whereNotNull('VATNumber')->where('UserId', $request->UserId)->get();
          $Account_deposite = UserBankDetail::whereNotNull('MT4LoginNumber')
            ->whereNotNull('VATNumber')->where('UserId', $request->UserId)->get();
          $new_user = UserBankDetail::where('UserId', $request->UserId)->first();
          $paymentrequestcount = UserPaymentRequest::where('UserId', $request->UserId)->where('PaymentStatus', 0)->count();
          if ($request->UserId == "") {
            if ($both->count() >= 1) {
              $b_array = [];
              $PaymentData = PaymentType::orderBy('PaymentTypeName')->get();

              foreach ($PaymentData as $rw) {
                if ($new_user->PaymentTypeId == $rw['PaymentTypeId'])
                  $chk = 1;
                else
                  $chk = 0;
                $var = [
                  'PaymentTypeId' => $rw['PaymentTypeId'],
                  'PaymentTypeName' => $rw['PaymentTypeName'],
                  'PaymentTypeDescription' => $rw['PaymentTypeDescription'],
                  'IsActive' => $rw['IsActive'],
                  'IsSelected' => $chk
                ];

                array_push($b_array, $var);
              }
              $PaymentType = array('PaymentRequest' => '', 'PaymentType' => $b_array);
            } else if ($Bank_Wire->count() >= 1) {
              $PaymentType = array('PaymentRequest' => '', 'PaymentType' => array([
                'PaymentTypeId' => 1,
                'PaymentTypeName' => "Bank wire",
                'PaymentTypeDescription' => "",
                'IsActive' => 1,
                'IsSelected' => 1
              ]));
            } elseif ($Account_deposite->count() >= 1) {
              $PaymentType = array('PaymentRequest' => '', 'PaymentType' => array(
                [
                  'PaymentTypeId' => 2,
                  'PaymentTypeName' => "Account deposit",
                  'PaymentTypeDescription' => "",
                  'IsActive' => 1,
                  'IsSelected' => 1
                ]
              ));
            } else {
              $PaymentType = array('PaymentRequest' => '', 'PaymentType' => []);
            }
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'User not record Found.',
              "TotalCount" => 0,
              'Data' => $PaymentType
            ], 200);
          } else if ($paymentrequestcount >= 1) {
            $TimeZoneOffSet = $request->Timezone;
            if ($TimeZoneOffSet == '')
              $TimeZoneOffSet = 0;

            $request_payment = UserPaymentRequest::where('UserId', $request->UserId)->where('PaymentStatus', 0)->get();
            $payment_req = [];

            foreach ($request_payment as $value) {
              $paymentMethod = PaymentType::where('PaymentTypeId', $value['PaymentTypeId'])->first();
              $val = [
                'RequestId' => $value['UserPaymentRequestId'],
                'PaymentTypeId' => $paymentMethod->PaymentTypeId,
                'PaymentTypeName' => $paymentMethod->PaymentTypeName,
                'RequestAmount' => $value['RequestAmount'],
                'RequestDate' => date("d/m/Y H:i A", strtotime($TimeZoneOffSet . " minutes", strtotime((string) $value['CreatedAt'])))
              ];

              array_push($payment_req, $val);
            }

            if ($both->count() >= 1) {
              $b_array = [];
              $PaymentData = PaymentType::orderBy('PaymentTypeName')->get();
              foreach ($PaymentData as $rw) {
                if ($new_user->PaymentTypeId == $rw['PaymentTypeId'])
                  $chk = 1;
                else
                  $chk = 0;
                $var = [
                  'PaymentTypeId' => $rw['PaymentTypeId'],
                  'PaymentTypeName' => $rw['PaymentTypeName'],
                  'PaymentTypeDescription' => $rw['PaymentTypeDescription'],
                  'IsActive' => $rw['IsActive'],
                  'IsSelected' => $chk
                ];

                array_push($b_array, $var);
              }
              $PaymentType = array('PaymentRequest' => $payment_req, 'PaymentType' => $b_array);
            } else if ($Bank_Wire->count() >= 1) {
              $PaymentType = array('PaymentRequest' => $payment_req, 'PaymentType' => array([
                'PaymentTypeId' => 1,
                'PaymentTypeName' => "Bank wire",
                'PaymentTypeDescription' => "",
                'IsActive' => 1,
                'IsSelected' => 1
              ]));
            } elseif ($Account_deposite->count() >= 1) {
              $PaymentType = array('PaymentRequest' => $payment_req, 'PaymentType' => array(
                [
                  'PaymentTypeId' => 2,
                  'PaymentTypeName' => "Account deposit",
                  'PaymentTypeDescription' => "",
                  'IsActive' => 1,
                  'IsSelected' => 1
                ]
              ));
            } else {
              $PaymentType = array('PaymentRequest' => '', 'PaymentType' => []);
            }
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'No Request Found.',
              "TotalCount" => 0,
              'Data' => $PaymentType
            ], 200);
          } else {
            if ($both->count() >= 1) {
              $b_array = [];
              $PaymentData = PaymentType::orderBy('PaymentTypeName')->get();
              foreach ($PaymentData as $rw) {
                if ($new_user->PaymentTypeId == $rw['PaymentTypeId'])
                  $chk = 1;
                else
                  $chk = 0;
                $var = [
                  'PaymentTypeId' => $rw['PaymentTypeId'],
                  'PaymentTypeName' => $rw['PaymentTypeName'],
                  'PaymentTypeDescription' => $rw['PaymentTypeDescription'],
                  'IsActive' => $rw['IsActive'],
                  'IsSelected' => $chk
                ];

                array_push($b_array, $var);
              }
              $PaymentType =  array('PaymentRequest' => '', 'PaymentType' => $b_array);
            } else if ($Bank_Wire->count() >= 1) {
              $PaymentType = array('PaymentRequest' => '', 'PaymentType' => array([
                'PaymentTypeId' => 1,
                'PaymentTypeName' => "Bank wire",
                'PaymentTypeDescription' => "",
                'IsActive' => 1,
                'IsSelected' => 1
              ]));
            } elseif ($Account_deposite->count() >= 1) {
              $PaymentType = array('PaymentRequest' => '', 'PaymentType' => array(
                [
                  'PaymentTypeId' => 2,
                  'PaymentTypeName' => "Account deposit",
                  'PaymentTypeDescription' => "",
                  'IsActive' => 1,
                  'IsSelected' => 1
                ]
              ));
            } else {
              $PaymentType = array('PaymentRequest' => '', 'PaymentType' => []);
            }
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'No Request Found.',
              "TotalCount" => 0,
              'Data' => $PaymentType
            ], 200);
          }
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
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }

  public function RejectPaymentRequest(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {

          if ($request->PaymentRequestId == "") {
            return response()->json([
              'IsSuccess' => false,
              'Message' => 'Payment request not found.',
              "TotalCount" => 0,
              'Data' => []
            ], 200);
          } else {

            $paymentrequest = UserPaymentRequest::where('UserPaymentRequestId', $request->PaymentRequestId)->update([
              'PaymentStatus' => 3
            ]);
            return response()->json([
              'IsSuccess' => true,
              'Message' => 'Payment request declined successfully.',
              "TotalCount" => 1,
              'Data' => []
            ], 200);
          }
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
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }

  public function GetAffiliateBalanceFromUserId(Request $request)
  {
    try {
      $check = new UserToken();
      $log_user = $check->validTokenAdmin($request->header('token'));
      if ($log_user) {
        if ($log_user->RoleId == 1 || $log_user->RoleId == 2) {
          $UserId = $request->UserId;
          if ($UserId) {
            $UserBalanceCount = UserBalance::where('UserId', $UserId)->count();
            if ($UserBalanceCount == 0) {
              $array1 = [
                'TotalRevenue' => 0,
                'Paid' => 0,
                'OutstandingRevenue' => 0,
                'TotalDuepayment' => 0
              ];
              $res = [
                'IsSuccess' => false,
                'Message' => 'No revenue found.',
                'TotalCount' => 0,
                'Data' => array('AffiliateBalance' => $array1)
              ];
              return response()->json($res, 200);
            }

            $UserBalance = UserBalance::with('user.Currency')->where('UserId', $UserId)->first();
            $UserDetail = User::find($UserId);
            if ($UserDetail->CurrencyId == 1) {
              $TotalRevenue = $UserBalance->USDTotalRevenue;
              $Paid = $UserBalance->USDPaid;
              $OutstandingRevenue = $UserBalance->USDOutstandingRevenue;
              $TotalDuePayment = $UserBalance->USDTotalDuepayment;
            } else if ($UserDetail->CurrencyId == 2) {
              $TotalRevenue = $UserBalance->AUDTotalRevenue;
              $Paid = $UserBalance->AUDPaid;
              $OutstandingRevenue = $UserBalance->AUDOutstandingRevenue;
              $TotalDuePayment = $UserBalance->AUDTotalDuepayment;
            } else if ($UserDetail->CurrencyId == 3) {
              $TotalRevenue = $UserBalance->EURTotalRevenue;
              $Paid = $UserBalance->EURPaid;
              $OutstandingRevenue = $UserBalance->EUROutstandingRevenue;
              $TotalDuePayment = $UserBalance->EURTotalDuepayment;
            }

            $totalRequest = UserPaymentRequest::where(['UserId' => $UserId, 'PaymentStatus' => 0])->count();
            if ($totalRequest > 0) {
              $RequestAmount = UserPaymentRequest::where(['UserId' => $UserId, 'PaymentStatus' => 0])->sum('RequestAmount');
              if ($OutstandingRevenue == 0)
                $DueAmount = 0;
              else
                $DueAmount = $OutstandingRevenue - $RequestAmount;
            } else {
              $DueAmount = $OutstandingRevenue;
            }

            $array1 = [
              'TotalRevenue' => $TotalRevenue,
              'Paid' => $Paid,
              'OutstandingRevenue' => $OutstandingRevenue,
              'TotalDuepayment' => $DueAmount,
              'CurrencyId' => $UserBalance['user']['currency']['CurrencyId'],
              'CurrencyName' => $UserBalance['user']['currency']['CurrencyCode']
            ];
            $res = [
              'IsSuccess' => true,
              'Message' => 'List of affiliate request.',
              'TotalCount' => 1,
              'Data' => array('AffiliateBalance' => $array1)
            ];
          } else {
            $res = [
              'IsSuccess' => true,
              'Message' => 'No request found',
              'TotalCount' => 0,
              'Data' => null
            ];
          }
          return response()->json($res, 200);
        } else {
          return response()->json([
            'IsSuccess' => false,
            'Message' => 'You are not admin.',
            "TotalCount" => 0,
            'Data' => []
          ], 200);
          return response()->json($res, 200);
        }
      } else {
        return response()->json([
          'IsSuccess' => false,
          'Message' => 'Token not found.',
          "TotalCount" => 0,
          'Data' => []
        ], 200);
        return response()->json($res, 200);
      }
    } catch (exception $e) {
      $res = [
        'IsSuccess' => false,
        'Message' => $e,
        'TotalCount' => 0,
        'Data' => []
      ];
      return response()->json($res, 200);
    }
  }
}
