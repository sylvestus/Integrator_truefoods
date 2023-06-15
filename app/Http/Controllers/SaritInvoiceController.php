<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class SaritInvoiceController extends Controller
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

    public function postSaritInvoice(Request $request)
    {
        try {
            // return $request->all();
            $company_id = $request->company_id;
            $environment = $request->environment;


            $company_data = CompanyMaster::where('id', $company_id)->first();
            // dd($company_id);


            $data = $request->all();
            $data_return = $data['invoice'][0];
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $invoice_number = $data_return['invoice_number'];
            $invoice = $this->findInvoice($company_id,$environment,$invoice_number);

            //$invoice = ['statusCode' => 300];
            if($invoice['message']->count > 0){
                return ['status'=>202,'message'=>'Invoice exists in netsuite'];
            } else {
                $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_invoice&deploy=customdeploy_create_invoice";
                $method = "POST";
                $data = "";
                $data = json_encode($data_return);
                $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

                if ($send_request['statusCode'] != 200) {
                    return $send_request;
                } else {
                    $data = $send_request['message'];
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }
            }

            return response()->json(['statusCode' => 404, 'response ' => "Orders missing", 'message' => 'Add at least one invoice and try again']);

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }
    public function findInvoice($company_id, $environment, $order_number)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/invoice?q=custbody_lms+CONTAIN+" .   $order_number;
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

    public function getInvoice($company_id,$environment,$invoice_number){
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
            $url = "https://".$account_number .".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_search_invoice&deploy=customdeploy_search_invoice&reference_number=".$invoice_number;
            $method = "GET";
            $data = "";
            $data = json_decode($data);
            $response = $this->netsuite_connector->callRestApi($url,$method,$data,$company_data,$environment);

            if($response['statusCode'] != 200){
                return $response;
            }else{
                $data  = $response['message'];
                return ['statusCode'=>200,'response'=>'Success','message'=>$data];
            }
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
        }
    }
    //creating invoice via script


}
