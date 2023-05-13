<?php

namespace App\Http\Controllers\Master;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\PaymentType;
use Illuminate\Http\Request;
use App\UserBankDetail;
use Laravel\Lumen\Routing\Controller as BaseController;

class PaymentTypeController extends BaseController
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
    }

    public function PaymentTypeList(Request $request)
    {
        try{

            $validator = Validator::make($request->all(), [
                'UserId' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'IsSuccess' => false,
                    'Message' => 'Some field are required.',
                    "TotalCount" => count($validator->errors()),
                    "Data" => array('Error' => $validator->errors())
                ], 200);
            }

            $userId = $request->UserId;

            $both = UserBankDetail::whereNotNull('BankName')->whereNotNull('AccountBeneficiary')
                ->whereNotNull('AccountNumber')->whereNotNull('BankBranch')
                ->whereNotNull('BankCity')
                ->whereNotNull('CountryId')
                ->whereNotNull('SwiftCode')->whereNotNull('IBANNumber')
                ->whereNotNull('ABANumber')->whereNotNull('BankCorrespondent')
                ->whereNotNull('VATNumber')->whereNotNull('MT4LoginNumber')->where('UserId',$userId)->get();


            $Bank_Wire = UserBankDetail::whereNotNull('BankName')->whereNotNull('AccountBeneficiary')
                ->whereNotNull('AccountNumber')->whereNotNull('BankBranch')
                ->whereNotNull('BankCity')
                ->whereNotNull('CountryId')
                ->whereNotNull('SwiftCode')->whereNotNull('IBANNumber')
                ->whereNotNull('ABANumber')->whereNotNull('BankCorrespondent')
                ->whereNotNull('VATNumber')->where('UserId',$userId)->get();

            $Account_deposite = UserBankDetail::whereNotNull('MT4LoginNumber')
                ->whereNotNull('VATNumber')->where('UserId',$userId)->get();




            if($both->count()>=1)
            {
                $PaymentType = PaymentType::orderBy('PaymentTypeName')->get();
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Payment Type List',
                    "TotalCount" => $PaymentType->count(),
                    "Data" => array('PaymentType' => $PaymentType)
                ], 200);
            }

            else if($Bank_Wire->count()>=1)
            {
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Payment Type List',
                    "TotalCount" => 1,
                    "Data" => array('PaymentType' =>array([
                        'PaymentTypeId'=>1,
                        'PaymentTypeName'=>"Bank wire",
                        'PaymentTypeDescription'=>"",
                        'IsActive'=>1
                    ]))
                ], 200);
            }
            elseif ($Account_deposite->count()>=1)
            {
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Payment Type List',
                    "TotalCount" => 1,
                    "Data" => array('PaymentType' => array([
                        'PaymentTypeId'=>2,
                        'PaymentTypeName'=>"Account deposit",
                        'PaymentTypeDescription'=>"",
                        'IsActive'=>1
                    ]))
                ], 200);
            }

            else
            {
                return response()->json([
                    'IsSuccess' => true,
                    'Message' => 'Payment Type List',
                    "TotalCount" => 0,
                    "Data" => array('PaymentType' => [])
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
}