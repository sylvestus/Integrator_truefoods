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

    public function searchSalesOrder(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $order_number = $request->order_number;
            $environment = $request->environment;
            if ($order_number == '') {
                return response()->json(['statusCode' => 404, 'response' => 'Order Number Missing',
                    'message' => 'Add order number to search']);
            }
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }
            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder?q=custbody_ordernum+CONTAIN+" . $order_number;
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


            //call function
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }

    }

    public function postSalesOrder(Request $request)
    {
        try {

            $company_id = $request->company_id;
            $environment = $request->environment;
            //return ($company_id);

            $company_data = CompanyMaster::where('id', $company_id)->first();
            // dd($company_id);
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }
            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder";
            $method = "POST";
            $received_data = $request->invoice;
            // return ($received_data);
            if ($received_data != '') {
                $orders_created = [];
                $not_created = [];
                foreach ($received_data as $key => $order) {
                    $formatted_order = $this->generate_payload_for_netsuite($order, $request);
                    //return $formatted_order['message'];
                    if ($formatted_order['status'] == 300 || $formatted_order['status'] == 202) {
                        $not_created[$key]['order_number'] = $order['order_number'];
                        $not_created[$key]['message'] = $formatted_order['message'];

                    } else {
                        $data = json_encode($formatted_order['message']);
                        // return ($data);
                        $response = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
                        //dd('jere');
                        if ($response['statusCode'] != 200) {
                            $not_created[$key]['order_number'] = $order['order_number'];
                            $not_created[$key]['message'] = $response['message'];
                            return response()->json(['statusCode' => 300, 'message' => $response['message']]);
                        } else {
                            $orders_created[] = $order['order_number'];
                            return response()->json(['statusCode' => 200, 'message' => $response['message']]);
                        }
                    }
                }
                return response()->json(['statusCode' => 200, 'message' => 'created_invoice ' . json_encode($orders_created), 'invoice_not_created' => ($not_created)]);
            }
            return response()->json(['statusCode' => 404, 'response ' => "Sales Order data missing", 'message' => 'Add at least one order and try again']);

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

    public function generate_payload_for_netsuite($oh, $rq)
    {

        try {

            $company_id = $rq->company_id;
            $environment = $rq->environment;
            $order_number = $oh['order_number'];
            $items_data = $oh['item'];
            //return ['status'=>202,'message'=>$environment];
            $exists = $this->findOrder($company_id, $environment, $order_number);
            //dd($exists);
            if ($exists['message']->count > 0) {
                return ['status' => 202, 'message' => 'Order exists in netsuite'];
            }
            // dd($items_data);
            if ($items_data == '') {
                return ['status' => 202, 'message' => 'items missing'];
            }
            $items = [];
            foreach ($items_data as $i => $om) {
                //dd($om['item_id']);
                $item_id = $om['item_id'];
                $items[$i]['item'] = ['id' => $item_id];
                $items[$i]['quantity'] = floatval($om['quantity']);
                $items[$i]['taxitem'] = (array('id' => $om['tax_code']));
                $items[$i]['location'] = ['id' => $om['warehouse_erp_id']];
                //$items[$i]['units']         = $om['uom_erp_id'];
            }

            $rqrd = array(
                'recordtype' => 'salesorder',
                'memo' => $oh['comments'],
                'entity' => ['id' => $oh['customer_erp_id']],
                'custbody_nn_pa_posno' => $oh['order_number'],
                'orderstatus' => 'B',
                'cseg_nn_branch' => ['id' => $oh['branch_id']],
                'tranDate' => date('Y-m-d\TH:i:s.00\Z', strtotime($oh['created_at'])),
                'item' => (['items' => $items]),
                'custbody_widget_link' => $oh['widget_link'] ?? null
                //'location'                  =>  (['id'=>  $oh['warehouse_erp_id']])
            );
            return ['status' => 200, 'message' => $rqrd];
        } catch (Exception $ex) {
            return ['status' => 300, 'message' => $ex->getMessage()];
        }

    }

    public function transformSalesOrder(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $order_number = $request->sales_order_number;
            $environment = $request->environment;
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_transform_so_to_invoice&deploy=customdeploy_transform_so_to_invoice&order_number=" . $order_number;
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

    public function findOrder($company_id, $environment, $order_number)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/salesOrder?q=custbody_nn_pa_posno+CONTAIN+" . $order_number;
            // return $url;
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
