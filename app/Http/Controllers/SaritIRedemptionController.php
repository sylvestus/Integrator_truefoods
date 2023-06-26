<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class SaritIRedemptionController extends Controller
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

    public function postSaritRedemptions(Request $request)
    {
        try {
            // return $request->all();
            $company_id = $request->company_id;
            $environment = $request->environment;
            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            $data_return = $data['redemption'][0];
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $redemption_number = $data_return['redemption_number'];
            $lms_no = $data_return['lms_no'];
            $invoice = $this->findRedemption($company_id, $environment, $redemption_number,$lms_no);
            if ($invoice['statusCode'] == 202) {
                return ['status' => 202, 'message' => 'Redemption exists in netsuite'];
            } else if($invoice['statusCode'] == 200) {

                $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_redemptions&deploy=customdeploy_create_redemptions";
                $method = "POST";
                $data = json_encode($data_return);
                $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

                if ($send_request['statusCode'] != 200) {
                    return $send_request;
                } else {
                    $data = $send_request['message'];
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }
            } else {
                return $invoice;
            }

            return response()->json(['statusCode' => 404, 'response ' => "Orders missing", 'message' => 'Add at least one sale and try again']);

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

    public function findRedemption($company_id, $environment, $order_number,$lms_no)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }
            //$url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/customtransaction_redemption?q=custbody_lms+CONTAIN+".$order_number;
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_search_redemption&deploy=customdeploy_search_redemption";
            $method = "POST";
            $redemption_number = ['redemption_number' => $order_number,'lms_no'=>$lms_no];
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
