<?php

namespace App\Http\Controllers;

use App\Jobs\CreateInvoicesJob;
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
             //return $request->all();
            $company_id = $request->company_id;
            $environment = $request->environment;
            $invoice = $request->invoice;
            $handler = fopen("invoice_request_" . date('d-m-Y') . ".txt", "a");

            fwrite($handler,json_encode($request->all()));
            fclose($handler);
            if(count($invoice)<1){
                return response()->json(['status'=>300,'message' => 'Request Missing Invoice Records']);
            }

            if($company_id && $environment){

                dispatch(new CreateInvoicesJob($request->all()));
                // Return a response to the original request
                return response()->json(['status'=>200,'message' => 'Invoice processing started']);
            }else{
                return response()->json(['status'=>300,'message' => 'Invalid Request body']);
            }



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
