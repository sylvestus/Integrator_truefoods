<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

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

    public function postInvoices(Request $request){
        try{

            $company_id = $request->company_id;
            $environment = $request->environment;
            //return ($company_id);

            $company_data = CompanyMaster::where('id', $company_id)->first();
            // dd($company_id);
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
            $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/invoice";
            $method = "POST";
            $received_data = $request->invoice;
           // return ($received_data);
            if($received_data != ''){
                $orders_created = [];
                $not_created =[];
                foreach ($received_data as $key=>$order){
                    $formatted_order = $this->generate_payload_for_netsuite($order,$request);
                    //return $formatted_order['message'];
                    if($formatted_order['status'] == 300 || $formatted_order['status'] ==202){
                        $not_created[$key]['invoice_number']= $order['invoice_number'];
                        $not_created[$key]['message']= $formatted_order['message'];
                    }else{
                        $data = json_encode($formatted_order['message']);
                        // return ($data);
                        $response = $this->netsuite_connector->callRestApi($url,$method,$data,$company_data,$environment);
                        //dd('jere');
                        if($response['statusCode'] != 200){
                            $not_created[$key]['invoice_number']=$order['invoice_number'];
                            $not_created[$key]['message']= $response['message'];
                            return response()->json(['statusCode'=>300,'message'=>$response['message']]);
                        }else{
                            $orders_created[] =$order['invoice_number'];
                            return response()->json(['statusCode'=>200,'message'=>$response['message']]);
                        }
                    }
                }
                return response()->json(['statusCode'=>200,'message'=>'Invoice created','created_invoice '=>($orders_created),'invoice_not_created'=>($not_created)]);
            }
            return response()->json(['statusCode'=>404,'response '=>"Invoice data missing",'message'=>'Add at least one order and try again']);

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
        }
    }

    public function  generate_payload_for_netsuite($oh,$rq){

        try{
            //dd($oh);
            $company_id = $rq->company_id;
            $environment = $rq->environment;
            $order_number = $oh['invoice_number'];
            $items_data = $oh['item'];
            //return ['status'=>202,'message'=>$environment];
            $exists = $this->findInvoice($company_id,$environment,$order_number);


            if($exists['message']->count > 0){
                return ['status'=>202,'message'=>'Invoice exists in netsuite'];
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
                $items[$i]['taxitem']      = (array('id'=>$om['tax_code']));
                $items[$i]['location']     = ['id'=> $om['warehouse_erp_id']];
              /*  $items[$i]['units']         =  $om['uom_erp_id'];*/
            }
            $payload = [
                'recordtype'        => 'invoice',
                'entity'            =>  ['id'        => $oh['customer_erp_id']],
                'custbody_nn_pa_posno'            => $oh['invoice_number'],
                'approvalstatus'            => ['id'        => '2'],
                'cseg_nn_branch'            => ['id'     => $oh['branch_id']],
                'memo'              =>  $oh["comments"],
                'item'              => ['items'     => $items],
                'tranDate'          => date('Y-m-d',strtotime($oh['created_at']))
            ];

            return ['status'=>200,'message'=> $payload];
        }catch(Exception $ex){
            return ['status'=>300,'message'=>$ex->getMessage()];
        }

    }

    public function findInvoice($company_id,$environment,$order_number){
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }

            $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/invoice?q=custbody_nn_pa_posno+CONTAIN+".$order_number;
            // return $url;
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

    public function updateInvoicePaymentStatus(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $invoice_number = $request->invoice_number;
            $environment = $request->environment;
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_update_payment_status&deploy=customdeploy_update_payment_status&invoice_number=" . $invoice_number;
            $method = "GET";
            $data = "";
            $data = json_decode($data);
            $response = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, 'sandbox');
            if ($response['statusCode'] != 200) {
                return $response;
            } else {
                $data = $response['message'];
                if ($data) {
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }
                return ['statusCode' => 404, 'response' => 'Success', 'message' => 'Sales Order not found'];

            }

            //call function
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }


    }

    public function updateAllSaleStatus(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $body = $request->sale_number;
            $sale_number = $body['number'];
            $record_type = $body['type'];
            $environment = $request->environment;
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_cancel_sale&deploy=customdeploy_cancel_sale&sale_number=" . $sale_number."&record_type=" . $record_type;
            $method = "GET";
            $data = "";
            $data = json_decode($data);
            $response = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, 'sandbox');
            if ($response['statusCode'] != 200) {
                return $response;
            } else {
                $data = $response['message'];
                if ($data) {
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }
                return ['statusCode' => 404, 'response' => 'Success', 'message' => 'Sales Order not found'];

            }

            //call function
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
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

    public function searchInvoices(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $order_number = $request->order_number;
            $environment = $request->environment;
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
            $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder?q=custbody_ordernum+CONTAIN+".$order_number;
            $method = "GET";
            $data = "";
            $data = json_decode($data);
            $response = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, 'sandbox');
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

    //creating invoice via script
    public function postInvoicesnew(Request $request){
        try{
            // return $request->all();
            $company_id = $request->company_id;
            $environment = $request->environment;
            foreach ($request->invoice as $invoice){
                $customer_id = $invoice['customer_erp_id'];
                $invoice_number = $invoice['invoice_number'];
                $discount_amount = $invoice['discount_amount'];
                $item = $invoice['item'];
                $invoice_date = date('Y-m-d',strtotime($invoice['created_at']));
                //$invoice_date = $invoice->date('Y-m-d');
            }

            //dd($company_id);

            $company_data = CompanyMaster::where('id', $company_id)->first();
            // dd($company_id);
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_invoice&deploy=customdeploy_create_invoice&invoice_date=".$invoice_date."&invoice_number=".$invoice_number."&discount_amount=".$discount_amount;
            //$url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_get_accounts&deploy=customdeployget_accounts";
            $method = "GET";
            //return $url;
            $data = "";
            $data = json_decode($data);
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
            }

            return response()->json(['statusCode'=>404,'response '=>"Orders missing",'message'=>'Add at least one order and try again']);

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
        }
    }

}
