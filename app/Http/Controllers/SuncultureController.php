<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class SuncultureController extends Controller
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
   //creating customer via script
    public function createSCCustomer(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            $data_return = $data['customer_data'];
           // return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }


            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rs_create_customer_recor&deploy=customdeploy_jn_rs_create_customer_recor";
            //$url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_cash_sale&deploy=customdeploycreate_cash_sale";
            $method = "POST";
            $data = json_encode($data_return);
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function createAMTCustomerAccount(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            $data_return = $data['account_data'];
            // return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }
            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rl_create_amt_cust_acc&deploy=customdeploy_jn_rl_create_amt_cust_acc";
            $method = "POST";
            $data = json_encode($data_return);
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function getRegions(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            $data_return = $data['filters'];
            // return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }


            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rs_get_regions&deploy=customdeploy_jn_rs_get_regions";
            $method = "POST";
            $data = json_encode($data_return);
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function createPayment(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            $data_return = $data['payment_data'];
            //return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rs_create_payment_record&deploy=customdeploy_jn_rs_create_payment_record";
            $method = "POST";
            $data = json_encode($data_return);
            //dd($data_return);
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            //return $send_request;
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function getPayments(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            $data_return = $data['filters'];
            // return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }


            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rs_search_payment_record&deploy=customdeploy_jn_rs_search_payment_record";
            $method = "POST";
            $data = json_encode($data_return);
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }





            if($invoice['message']->count>0){
                return ['statusCode'=>200,'message'=>'Sales exists in netsuite'];
            } else {

            }
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function getNsAccount(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            // return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }


            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rs_get_ns_accounts&deploy=customdeploy_jn_rs_get_ns_accounts";
            $method = "GET";
            $data = '';
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
               // dd($data);
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function createCreditNote(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            $data_return = $data['credit_note_data'];
            //return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            //$url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rl_create_credit_note&deploy=customdeploy_jn_rl_create_credit_note";
           // $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rl_create_grn&deploy=customdeploy_jn_rl_create_grn";
           // $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_dgg_create_customer_refund&deploy=customdeploy_dgg_create_customer_refund";
            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_dgg_create_customer_refund&deploy=customdeploy_dgg_create_customer_refund";
            $method = "GET";
            $data = json_encode($data_return);
            //dd($data_return);
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            //return $send_request;
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function getItems(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            //$data_return = $data['filters'];
            // return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

           // $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rl_get_items&deploy=customdeploy_jn_rl_get_items";
            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_rl_get_all_items&deploy=customdeploy_rl_get_all_items";
            $method = "GET";
            $data = '';
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function getItemLocationQty(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            //$data_return = $data['filters'];
            // return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rl_get_tem_location_qty&deploy=customdeploy_jn_rl_get_tem_location_qty";
            $method = "GET";
            $data = '';
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }


        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function getLocations(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            //$data_return = $data['filters'];
            // return $data_return;
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rl_get_locations&deploy=customdeploy_jn_rl_get_locations";
            $method = "GET";
            $data = '';
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                if($data->success){
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
                }
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

    public function findCashSale($company_id, $environment, $order_number)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/cashSale?q=custbody_nn_pa_posno+CONTAIN+" .$order_number;
            // return $url;
            $method = "GET";
            $data = "";
            $data = json_decode($data);
            $response = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            if ($response['statusCode'] != 200) {
                return $response;
            } else {
                $data = $response['message'];
                return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
            }
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

}
