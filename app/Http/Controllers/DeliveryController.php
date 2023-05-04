<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class DeliveryController extends Controller
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

    public function postDelivery(Request $request){
        try{

            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();
            // dd($company_id);
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }
            $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/itemfulfillment";
            $method = "POST";
            $received_data = $request->delivery;
           // return ($received_data);
            if($received_data != ''){
                $orders_created = [];
                $not_created =[];
                foreach ($received_data as $key=>$order){
                    $formatted_order = $this->generate_payload_for_netsuite($order,$request);
                    //return $formatted_order['message'];
                    if($formatted_order['status'] == 300 || $formatted_order['status'] ==202){
                        $not_created[$key]['delivery_reference']= $order['delivery_reference'];
                        $not_created[$key]['message']= $formatted_order['message'];
                    }else{
                        $data = json_encode($formatted_order['message']);
                        // return ($data);
                        $response = $this->netsuite_connector->callRestApi($url,$method,$data,$company_data,$environment);
                        //dd('jere');
                        if($response['statusCode'] != 200){
                            $not_created[$key]['delivery_reference']=$order['delivery_reference'];
                            $not_created[$key]['message']= $response['message'];
                            return response()->json(['statusCode'=>300,'message'=>$response['message']]);
                        }else{
                            $orders_created[] =$order['invoice_number'];
                            return response()->json(['statusCode'=>200,'message'=>$response['message']]);
                        }
                    }
                }
                return response()->json(['statusCode'=>200,'message'=>'Delivery created','created_delivery '=>($orders_created),'delivery_not_created'=>($not_created)]);
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
            $order_number = $oh['delivery_reference'];
            $items_data = $oh['item'];
            //return ['status'=>202,'message'=>$environment];
            $exists = $this->findDelivery($company_id,$environment,$order_number);


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
                $items[$i]['location']     = ['id'=> $om['warehouse_erp_id']];
              /*  $items[$i]['units']         =  $om['uom_erp_id'];*/
            }
            $payload = [
                'recordtype'        => 'delivery',
                'entity'            =>  ['id'  => $oh['customer_erp_id']],
                'custbody_nn_pa_posno' => $oh['delivery_reference'],
                'cseg_nn_branch'            => ['id'     => $oh['branch_id']],
                'status'              =>  "Shipped",
                'item'              => ['items'     => $items],
                'tranDate'          => date('Y-m-d')
            ];

            return ['status'=>200,'message'=> $payload];
        }catch(Exception $ex){
            return ['status'=>300,'message'=>$ex->getMessage()];
        }

    }

    public function findDelivery($company_id,$environment,$order_number){
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }

            $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/itemfulfillment?q=custbody_nn_pa_posno+CONTAIN+".$order_number;
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

}
