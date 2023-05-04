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

    public function postCashSale(Request $request)
    {
        try {
            return $request;

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
            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/invoice";
            $method = "POST";
            $received_data = $request->sale;
            // return ($received_data);
            if ($received_data != '') {
                $orders_created = [];
                $not_created = [];
                foreach ($received_data as $key => $order) {
                    $formatted_order = $this->generate_payload_for_netsuite($order, $request);
                    //return $formatted_order['message'];
                    if ($formatted_order['status'] == 300 || $formatted_order['status'] == 202) {
                        $not_created[$key]['invoice_number'] = $order['invoice_number'];
                        $not_created[$key]['message'] = $formatted_order['message'];
                    } else {
                        $data = json_encode($formatted_order['message']);
                        // return ($data);
                        $response = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
                        //dd('jere');
                        if ($response['statusCode'] != 200) {
                            $not_created[$key]['invoice_number'] = $order['invoice_number'];
                            $not_created[$key]['message'] = $response['message'];
                            return response()->json(['statusCode' => 300, 'message' => $response['message']]);
                        } else {
                            $orders_created[] = $order['cash_sale_number'];
                            return response()->json(['statusCode' => 200, 'message' => $response['message']]);
                        }
                    }
                }
                return response()->json(['statusCode' => 200, 'message' => 'Cash Sale created', 'created_cash_sale ' => ($orders_created), 'cash_sale_not_created' => ($not_created)]);
            }
            return response()->json(['statusCode' => 404, 'response ' => "Cash Sale data missing", 'message' => 'Add at least one order and try again']);

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

    public function generate_payload_for_netsuite($oh, $rq)
    {

        try {
            //dd($oh);
            $company_id = $rq->company_id;
            $environment = $rq->environment;
            $order_number = $oh['invoice_number'];
            $items_data = $oh['item'];
            //return ['status'=>202,'message'=>$environment];
            $exists = $this->findCashSale($company_id, $environment, $order_number);


            if ($exists['message']->count > 0) {
                return ['status' => 202, 'message' => 'Cash Sale exists in netsuite'];
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
                /*  $items[$i]['units']         =  $om['uom_erp_id'];*/
            }
            $payload = [
                'recordtype' => 'invoice',
                'entity' => ['id' => $oh['customer_erp_id']],
                'custbody_nn_pa_posno' => $oh['invoice_number'],
                'approvalstatus' => ['id' => '2'],
                'cseg_nn_branch' => ['id' => $oh['branch_id']],
                'memo' => $oh["comments"],
                'item' => ['items' => $items],
                'tranDate' => date('Y-m-d', strtotime($oh['created_at']))
            ];

            return ['status' => 200, 'message' => $payload];
        } catch (Exception $ex) {
            return ['status' => 300, 'message' => $ex->getMessage()];
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

            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/invoice?q=custbody_nn_pa_posno+CONTAIN+" . $order_number;
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

    public function updateCashSaleStatus(Request $request)
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

}
