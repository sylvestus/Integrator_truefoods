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
            $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/customrecord_pos";
            $method = "POST";
            $received_data = $request->receipt;
            // return ($received_data);
            if($received_data != ''){
                $orders_created = [];
                $not_created =[];
                foreach ($received_data as $key=>$receipt){
                    $formatted_order = $this->generate_payload_for_netsuite($receipt,$request);
                    //return $formatted_order['message'];
                    if($formatted_order['status'] == 300 || $formatted_order['status'] ==202){
                        $not_created[$key]['receipt_number'] = $receipt['receipt_number'];
                        $not_created[$key]['message']= $formatted_order['message'];
                    }elseif($formatted_order['status'] ==202){
                        $not_created[$key]['receipt_number'] = $receipt['receipt_number'];
                        $not_created[$key]['message']= $formatted_order['message'];
                    }else{
                        $data = json_encode($formatted_order['message']);
                        // return ($data);
                        $response = $this->netsuite_connector->callRestApi($url,$method,$data,$company_data,$environment);
                        //dd('jere');
                        if($response['statusCode'] != 200){
                            $not_created[$key]['receipt_number']= $receipt['receipt_number'];
                            $not_created[$key]['message']= $response['message'];
                            return response()->json(['statusCode'=>300,'message'=>$response['message']]);
                        }else{
                            $orders_created[] =$receipt['invoice_number'];
                            return response()->json(['statusCode'=>200,'message'=>$response['message']]);
                        }
                    }
                }
                return response()->json(['statusCode'=>200,'message'=>'Payment created','created_receipt '=>($orders_created),'receipt_not_created'=>($not_created)]);
            }
            return response()->json(['statusCode'=>404,'response '=>"Receipt data missing",'message'=>'Add at least one receipt and try again']);

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
            $order_number = $oh['receipt_number'];
            //return ['status'=>202,'message'=>$environment];
            $exists = $this->findPayment($company_id,$environment,$order_number);

            if($exists['message']->count > 0){
                return ['status'=>202,'message'=>'Payment exists in netsuite'];
            }
            // dd($items_data);

            $payload = [
                'recordtype'                        => 'customrecord_pos',
                'custrecord_paymentrefno'           => $oh['sale_number'],
                'custrecord_posreceipt'             => $oh['receipt_number'],
                'custrecord_billcustomer'           => $oh['customer_erp_id'],
                'custrecord_pdate'                  => date('Y-m-d\TH:i:s.00\Z', strtotime($oh['created_at'])),
                'cseg_nn_branch'                    => ['id' => $oh['branch_id']],
                'custrecord_billlocation'           => $oh['warehouse_erp_id'],
                'custrecord_receiptaccount'         => $oh['erp_account_id'],
                'custrecord_invoiceno'              => ['refName'=>$oh['sale_number']],
                'custrecord_paymentamount'          => $oh['amount']
            ];

            return ['status'=>200,'message'=> $payload];
        }catch(Exception $ex){
            return ['status'=>300,'message'=>$ex->getMessage()];
        }

    }
    public function findPayment($company_id,$environment,$order_number){
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
