<?php

namespace App\Http\Controllers;

use App\Jobs\CreateGiftVoucherRedemptionsJob;
use App\Jobs\CreateRedemptionsJob;
use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class SaritIGiftVoucherRedemptionController extends Controller
{


    public function __invoke(Request $request)
    {
        //
    }

    public $netsuite_connector;

    public function __construct()
    {
        $netsuite_connector = new NetsuiteConnectorController();

        $this->netsuite_connector = $netsuite_connector;
    }

    public function postSaritGiftVoucherRedemptions(Request $request)
    {
        try {
            // return $request->all();
            $company_id = $request->company_id;
            $environment = $request->environment;
            $redemption = $request->gift_voucher_redemption;

            $handler = fopen("gift_voucher_redemption_request_" . date('d-m-Y') . ".txt", "a");

            fwrite($handler,json_encode($request->all()));
            fclose($handler);
            if(count($redemption)<1){
                return response()->json(['status'=>300,'message' => 'Request Missing gift voucher redemption data']);
            }

            if($company_id && $environment){

                dispatch(new CreateGiftVoucherRedemptionsJob($request->all()));
                // Return a response to the original request
                return response()->json(['status'=>200,'message' => 'Gift Voucher Redemption processing started']);
            }else{
                return response()->json(['status'=>300,'message' => 'Invalid Request body']);
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }


    public function findGiftVoucherRedemption($company_id, $environment, $order_number,$lms_no)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }
            //$url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/customtransaction_redemption?q=custbody_lms+CONTAIN+".$order_number;
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_search_gift_voucher_rd&deploy=customdeploy_search_gift_voucher_rd";
            $method = "POST";
            $redemption_number = ['gift_voucher_redemption_number' => $order_number,'lms_no'=>$lms_no];
            $data = json_encode($redemption_number);
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            //dd($send_request);
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];

                if($data->success){
                    return ['statusCode' => 202, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }

            }
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

}
