<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class CashSaleController extends Controller
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

   //creating cash sale via script
    public function postCashSale(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;
            foreach ($request->cash_sale as $sale) {
                $discount_amount = $sale['discount_amount'];
                $invoice_date = date('Y-m-d', strtotime($sale['created_at']));
            }

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            $data_return = $data['cash_sale'][0];
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $invoice_number = $data_return['reference_number'];
            //return $invoice_number;
            $invoice = $this->findCashSale($company_id,$environment,$invoice_number);
          // dd($invoice);



            if($invoice['message']->count>0){
                return ['statusCode'=>200,'message'=>'Sales exists in netsuite'];
            } else {
                $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_cash_sale&deploy=customdeploycreate_cash_sale";
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
            }
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

    public function findCashSale($company_id, $environment, $order_number)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/cashSale?q=custbody_nn_pa_posno+CONTAIN+" .$order_number;
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

}
