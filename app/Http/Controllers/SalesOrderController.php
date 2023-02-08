<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;

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
          $company_data = CompanyMaster::where('id', $company_id)->first();
          $url = "https://" . $company_data->account_number . "-SB1.suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder?q=custbody_ordernum+CONTAIN+".$order_number;
          $method = "GET";
          $data = "";
          $data = json_decode($data);
          $response = $this->netsuite_connector->callRestApi($url,$method,$data,$company_data,'sandbox');
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
           $company_id = $request->company_id;
           $environment = $request->environment;

           $company_data = CompanyMaster::where('id', $company_id)->first();
           $url = "https://" . $company_data->account_number . "-SB1.suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder";
           $method = "POST";
           $received_data = $request->orders;
           $formatted_order = $this->generate_order_for_netsuite($received_data);
           $data = json_decode($formatted_order);

           $response = $this->netsuite_connector->callRestApi($url,$method,$data,$company_data,'sandbox');
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

  public function  generate_order_for_netsuite($order){

        return $order;
  }
}
