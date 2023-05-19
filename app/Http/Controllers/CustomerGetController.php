<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;

class CustomerGetController extends Controller
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

    public function getCustomerClass(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;
            /*$rep_id  = $request->rep_id;
            $customer_id  = $request->customer_id;
            $start_date  = $request->start_date;
            $end_date = $request->end_date;
            $invoice_number = $request->invoice_number;*/

            $company_data = CompanyMaster::where('id', $company_id)->first();


            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_get_customer_class&deploy=customdeploy_get_customer_class";
            //$url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_get_customer_class_updated&deploy=customdeploy_get_customer_cl";
            $method = "GET";
            $data = "";
            $data = json_decode($data);



            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function getCustomers(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;
            /*$rep_id  = $request->rep_id;
            $customer_id  = $request->customer_id;
            $start_date  = $request->start_date;
            $end_date = $request->end_date;
            $invoice_number = $request->invoice_number;*/

            $company_data = CompanyMaster::where('id', $company_id)->first();


            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_get_customers&deploy=customdeploy_get_customers";
            $method = "GET";
            $data = "";
            $data = json_decode($data);



            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

    public function searchCustomers($request,$email,$phone)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;
            /*$rep_id  = $request->rep_id;
            $customer_id  = $request->customer_id;
            $start_date  = $request->start_date;
            $end_date = $request->end_date;
            $invoice_number = $request->invoice_number;*/

            $company_data = CompanyMaster::where('id', $company_id)->first();


            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
           // $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_search_customer&deploy=customdeploy_search_customer?q=email=".$email;
            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_search_customer&deploy=customdeploy_search_customer&email=".$email.'&phone='.$phone;
            //dd($url);
            $method = "GET";
            $data = "";
            $data = json_decode($data);

            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
           // dd($send_request);
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }




}
