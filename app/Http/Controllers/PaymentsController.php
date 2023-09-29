<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class PaymentsController extends Controller
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

    public function postPayments(Request $request){
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
            $received_data = $request->receipt;

            foreach ($received_data as $payment){
                $paymentExists = $this->findPayment($company_id, $environment, $payment['receipt_number']);
                if ($paymentExists['message']->count > 0) {
                    return ['statusCode' => 200, 'response' => 'Record Exists', 'message' => 'Payment Record Already Exists'];
                }else{
                    $invoiceController =  new InvoiceController();
                    $exists = $invoiceController->findInvoice($company_id, $environment, $payment['sale_number']);
                    if ($exists['message']->count > 0) {//if exists allow payment processing
                        $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_payment_from_pos&deploy=customdeploy_create_payment_from_pos";
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
                    }else{
                        return response()->json(['statusCode'=>500,'response '=>'Payment Not Created','message'=>'Invoice Data Missing']);
                    }
                }

            }


        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
        }
    }

    public function findPayment($company_id, $environment, $order_number)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/customerPayment?q=custbody_nn_pa_posno+CONTAIN+" .$order_number;
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
            $receipt_number = $oh['receipt_number'];
            $sale_number = $oh['sale_number'];

            //return ['status'=>202,'message'=>$environment];
//            $exists = $this->findPayment($company_id,$environment,$receipt_number);
//
//            if($exists['message']->count > 0){
//                return ['status'=>202,'message'=>'Payment exists in netsuite'];
//            }

            //get invoice netsuite internal id
            $invoice = new InvoiceController();
            $get_invoice_id = $invoice->getInvoice($company_id,$environment,$sale_number);
            if($get_invoice_id['statusCode']!=200){
                return  $get_invoice_id;
            }else{
                if(count($get_invoice_id['message'])>0){
                    $invoice_id =  $get_invoice_id['message'][0]->internalid;
                }else{
                    return  ['statusCode'=>404,'message'=>'Invoice not found'];
                }

            }

            if($oh['branch_id']==1){
                $account = "846";
            }else{
                $account = "845";
            }

            $items = [];
            //$items['doc']        = ['apply'=>true, 'id'=>$erp_id];
            $items['amount']     = floatval($oh['amount']);
            $payload = array(
                'recordtype'   => 'customerPayment',
                'account'      => ['id' => $account],
                'autoapply'    => 'false',
                //'apply'        => ['items' => [$items]],
                'currency'     => ['id' => 1],
                'customer'     => ['id' => $oh['customer_erp_id']],
                //'customform'   => ['id' => 70],
                'memo'         => "Payment from pos",
                'payment'      => floatval($oh['amount']),
                'paymentdate'  => date('Y-m-d\TH:i:s.00\Z', strtotime($oh['created_at'])),
                'tranDate'     => date('Y-m-d\TH:i:s.00\Z', strtotime($oh['created_at'])),
                'invoice'      => ['id' => $invoice_id] // Add the invoice ID here
            );

            return ['status'=>200,'message'=> $payload];
        }catch(Exception $ex){
            return ['status'=>300,'message'=>$ex->getMessage()];
        }

    }


    public function searchPayments(Request $request)
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

}
