<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class ReturnsController extends Controller
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

    public function postReturnsOld(Request $request){
        try{

            $company_id = $request->company_id;
            $environment = $request->environment;
            return ($request->all());

            $company_data = CompanyMaster::where('id', $company_id)->first();
            // dd($company_id);
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }

            $data = $request->all();
            $data_return = $data['return'][0];
           // dd($data_return);
            $return_number = $data_return['return_number'];
            $invoice_number = $data_return['reference_number'];
            /*$exists = $this->findReturn($company_id,$environment,$return_number);

            if($exists['message']->count > 0){
                return ['status'=>202,'message'=>'Invoice exists in netsuite'];
            }*/
            //invoice id
            $invoice = $this->getInvoice($company_id,$environment,$invoice_number);
            // dd($invoice);
            if($invoice['statusCode'] !=200){
                return ['status'=>404,'message'=>'Invoice Missing in Netsuite'];
            }else{
                $invoice_id = $invoice['message'][0]->internalid;
            }

            $data_return['invoice_id']= $invoice_id;
           // dd($data_return);

            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_return&deploy=customdeploy_create_return";
            //dd($url);
            $method = "POST";
            $data = "";
            $data = json_encode($data_return);
            //dd($data);

            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            if($send_request['statusCode'] ==200){
                return response()->json(['statusCode'=>200,'message'=>$send_request['message']]);
            }else{
                return response()->json(['statusCode'=>300,'message'=>$send_request['message']]);
            }
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
        }
    }

    public function postReturns(Request $request){
        try{
            // return $request;
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
            $received_data = $request->return;

            foreach ($received_data as $payment){
                $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_return&deploy=customdeploy_create_return";
                $method = "POST";
                $data = "";
                $data = json_encode($payment);
                //return $url;
                $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
                // return ($send_request);
                if ($send_request['statusCode'] != 200) {
                    return $send_request;
                } else {
                    $data = $send_request['message'];

                    if($data->success){
                        return ['statusCode' => 200, 'response' => 'success', 'message' => $data];
                    }else{
                        return ['statusCode' => 300, 'response' => 'error', 'message' => $data];
                    }
                }

            }


        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
        }
    }

    public function findReturn($company_id, $environment, $order_number)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/returnAuthorization?q=custbody_nn_pa_posno+CONTAIN+".$order_number;
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

    public function  generate_payload_for_netsuite($oh,$rq){

        try{
            //dd($oh);
            $company_id = $rq->company_id;
            $environment = $rq->environment;
            $return_number = $oh['return_number'];
            $invoice_number = $oh['reference_number'];
            $items_data = $oh['item'];
            //return ['status'=>202,'message'=>$environment];
            $exists = $this->findReturn($company_id,$environment,$return_number);

            if($exists['message']->count > 0){
                return ['status'=>202,'message'=>'Invoice exists in netsuite'];
            }
            //invoice id
            $invoice = $this->getInvoice($company_id,$environment,$invoice_number);
           // dd($invoice);
            if($invoice['statusCode'] !=200){
                return ['status'=>404,'message'=>'Invoice Missing in Netsuite'];
            }else{
                $invoice_id = $invoice['message'][0]->internalid;
            }
            // dd($items_data);
            if($items_data ==''){
                return ['status'=>202,'message'=>'items missing'];
            }
            $items = [];
            foreach ($items_data as $i=>$om){
                //dd($om['item_id']);
                $item_id                    =  $om['item_id'];
                $items[$i]['item']          =  ['id'=>$item_id];
                $items[$i]['quantity']      =  floatval($om['quantity']);
                //$items[$i]['taxitem']      = (array('id'=>$om['tax_code']));
                $items[$i]['location']     = ['id'=> $om['warehouse_erp_id']];
              /*  $items[$i]['units']         =  $om['uom_erp_id'];*/
            }
            $payload = [
                'recordtype'        => 'returnauthorization', // Set the record type to 'returnauthorization' for returns
                'entity'            =>  ['id'        => $oh['customer_erp_id']],
                'custbody_nn_pa_posno'            => $oh['return_number'],
                'approvalstatus'            => ['id'        => '2'],
                'cseg_nn_branch'            => ['id'     => $oh['branch_id']],
                'memo'              =>  $oh["comments"],
                'item'              => ['items'     => $items],
                'tranDate'          => date('Y-m-d', strtotime($oh['created_at'])),
                'orderstatus'       => ['id' => '7'], // Set the order status to '7' for returns
                'trandate'          => date('Y-m-d', strtotime($oh['created_at'])),
                'tranid'            => $oh['return_number'], // Use the same transaction ID as the original invoice
                'createdfrom'       => ['id' => $invoice_id], // Reference the original invoice
                'customform'        => ['id' => '85'] // Set the custom form ID for returns (replace '2' with the appropriate form ID)
            ];

            return ['status'=>200,'message'=> $payload];
        }catch(Exception $ex){
            return ['status'=>300,'message'=>$ex->getMessage()];
        }

    }

    public function findReturnOld($company_id,$environment,$order_number){
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }

            $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/returnAuthorization?q=custbody_nn_pa_posno+CONTAIN+".$order_number;
            // return $url;
            $method = "GET";
            $data = "";
            $data = json_decode($data);
            $response = $this->netsuite_connector->callRestApi($url,$method,$data,$company_data,$environment);
            dd($response);
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


            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
            $url = "https://".$account_number .".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_invoice_search_custom_scrip&deploy=customdeploy_invoices_search_script_api&invoiceNumber=".$invoice_number."&startDate=".$start_date."&endDate=".$end_date."&customerId=".$customer_id."&repId=".$rep_id;
//            dd($url);
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


//call function
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

}
