<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class SalesOrderController extends Controller
{
    public $netsuite_connector;

    public function __construct()
    {
        $netsuite_connector = new NetsuiteConnectorController();

        $this->netsuite_connector = $netsuite_connector;
    }

    public function __invoke(Request $request)
    {
        //
    }

  public function searchSalesOrder(Request $request){
      try {
          $company_id = $request->company_id;
          $order_number = $request->order_number;
          $environment = $request->environment;
          if($order_number == ''){
              return response()->json(['statusCode' => 404, 'response' => 'Order Number Missing',
                  'message' => 'Add order number to search']);
          }
          $company_data = CompanyMaster::where('id', $company_id)->first();
          if($environment == 'sandbox'){
              $account_number = $company_data->account_number.'-sb1';
          }else{
              $account_number = $company_data->account_number;
          }
          $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder?q=custbody_ordernum+CONTAIN+".$order_number;
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


          //call function
      } catch (\Exception $ex) {
          return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
              'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
      }

  }
  public function postSalesOrder(Request $request){
       try{
           //return 'jeremy';
           $company_id = $request->company_id;
           $environment = $request->environment;


           $company_data = CompanyMaster::where('id', $company_id)->first();

           if($environment == 'sandbox'){
               $account_number = $company_data->account_number.'-sb1';
           }else{
               $account_number = $company_data->account_number;
           }
           $url = "https://".$account_number .".suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder";
           $method = "POST";
           $received_data = $request->orders;
           if($received_data != ''){
               $orders_created = [];
               $not_created =[];
               foreach ($received_data as $key=>$order){
                   $formatted_order = $this->generate_order_for_netsuite($order);
                   //return $formatted_order['message'];
                   if($formatted_order['status'] ==300){
                       $not_created[$key]['order_number']=$order->order_number;
                       $not_created[$key]['message']= $formatted_order['message'];
                   }elseif($formatted_order['status'] ==202){
                       $not_created[$key]['order_number']=$order->order_number;
                       $not_created[$key]['message']= $formatted_order['message'];
                   }else{
                       $data = json_encode($formatted_order['message']);
                       // return ($data);
                       $response = $this->netsuite_connector->callRestApi($url,$method,$data,$company_data,$environment);
                       if($response['statusCode'] != 200){
                           $not_created[$key]['order_number']=$order['order_number'];
                           $not_created[$key]['message']= $response['message'];
                       }else{
                           $orders_created[] =$order->order_number;
                       }
                   }
               }
               return response()->json(['statusCode'=>200,'Orders Created '=>json_encode($orders_created),'Orders Not Created '=>($not_created)]);
           }
           return response()->json(['statusCode'=>404,'response '=>"Orders missing",'message'=>'Add at least one order and try again']);

       } catch (\Exception $ex) {
           return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
               'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
       }
  }

  public function  generate_order_for_netsuite($oh){

        try{

            $items_data = $oh['item'];
           // dd($items_data);
            if($items_data ==''){
                return ['status'=>202,'message'=>'items missing'];
            }
            $items = [];
            foreach ($items_data as $i=>$om){
                //dd($om['item_id']);
                $item_id =$om['item_id'];
                $items[$i]['item']          = ['id'=>$item_id];
                $items[$i]['quantity']      = floatval($om['quantity']);
                $items[$i]['units']         = $om['uom_erp_id'];
            }

            $rqrd = array(
                'recordtype'                =>  'salesorder',
                'class'                     =>  (array('refName'=>'Mesora Supermarket Limited')),
                'otherRefNum'               =>  $oh['lpo_number'],
                'memo'                      =>  $oh['comments'],
                'custbody_ordernum'         =>  $oh['order_number'],
                'custbody_orderbooker'      =>  $oh['created_by_erp_id'],
                'custbody_comment'          =>  'Order From Integrator',
                'tranDate'                  =>  date('Y-m-d\TH:i:s.00\Z',strtotime($oh['created_at'])),
                'discountItem'              =>  '',
                //'cseg_supplier'             =>  ['id'=>0],
                'custbody_custname'         =>  $oh['customer_name'],
                'discountAmount'            =>  $oh['discount_amount'],
                'entity'                    =>  ['id'=>  $oh['customer_erp_id']],
                'item'                      =>  (['items'=>  $items]),
                'location'                  =>  (['id'=>  $oh['warehouse_erp_id']])
            );
            return ['status'=>200,'message'=> $rqrd];
        }catch(Exception $ex){
              return ['status'=>300,'message'=>$ex->getMessage()];
        }

  }
}
