<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CompanyMaster;

class ItemReceiptController extends Controller
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


    public function postItemReceipt(Request $request)
    {
        
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;
            
           
            $company_data = CompanyMaster::where('id', $company_id)->first();
            $data = $request->all();
            //dd($data);
            
            $data_return = $data['item_fulfillment'];
           
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $reference_number = $data_return['item_receipt_reference_no'];
            //$reference_number = $data['transfer'][0]['order_number'];
            
            $reference = $this->findItemReceipt($company_id,$environment,$reference_number);
            //dd($reference);

            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_vw_rl_create_item_receipt&deploy=customdeploy_vw_rl_create_item_receipt";
                //sdd($url);            
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


            // if($reference['message']->count>0){
            //     return ['statusCode'=>200,'message'=>'Item Receipt exists in netsuite'];
            // }else{
            //     $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_vw_rl_create_item_receipt&deploy=customdeploy_vw_rl_create_item_receipt";
            //     //sdd($url);            
            //     $method = "POST";
            //     $data = json_encode($data_return);
            
            //     $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            //     //dd($send_request);
            //     if ($send_request['statusCode'] != 200) {
            //         return $send_request;
            //     } else {
            //         $data = $send_request['message'];
                
            //         if($data->success){
            //             return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
            //         }else{
            //             return ['statusCode' => 300, 'response' => 'Error', 'message' => $data];
            //         } 
            //     }
            // }
            
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }


    public function findItemReceipt($company_id, $environment, $order_number)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/itemreceipt?q=custbody_nn_pa_posno+CONTAIN+" .$order_number;
            $method = "GET";
            $data = "";
            $data = json_decode($data);
            $response = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            //dd($response);
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
