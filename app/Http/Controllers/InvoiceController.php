<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;

class InvoiceController extends Controller
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


    public function getInvoices(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;
            $rep_id  = $request->rep_id;
            $customer_id  = $request->customer_id;
            $start_date  = $request->start_date;
            $end_date = $request->end_date;
            $invoice_number = $request->invoice_number;

            $company_data = CompanyMaster::where('id', $company_id)->first();
            $url = "https://" . $company_data->account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_invoice_search_custom_scrip&deploy=customdeploy_invoices_search_script_api&invoiceNumber=".$invoice_number."&startDate=".$start_date."&endDate=".$end_date."&customerId=".$customer_id."&repId=".$rep_id;
            $method = "GET";
            $data = "";
            $data = json_decode($data);


            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, 'production');
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
            }


//call function
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }


    public function searchInvoices(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $order_number = $request->order_number;

            $company_data = CompanyMaster::where('id', $company_id)->first();
            $url = "https://" . $company_data->account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder?q=custbody_ordernum+CONTAIN+".$order_number;
            $method = "GET";
            $data = "";
            $data = json_decode($data);
            $response = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, 'production');
            if ($response['statusCode'] != 200) {
                return $response;
            } else {
                $data = $response['message'];
                return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
            }


            //call function
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }


    }

}
