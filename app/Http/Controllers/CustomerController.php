<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class CustomerController extends Controller
{
    public $netsuite_connector;
    public $customer_get;

    public function __construct()
    {
        $netsuite_connector = new NetsuiteConnectorController();
        $customer_get = new CustomerGetController();

        $this->netsuite_connector = $netsuite_connector;
        $this->customer_get = $customer_get;
    }
    public function postCustomers(Request $request)
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
            $url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/customer";
            $method = "POST";
            $received_data = $request->customer;
            // return ($received_data);
            if ($received_data != '') {
                $orders_created = [];
                $not_created = [];
                foreach ($received_data as $key => $customer) {
                    $formatted_order = $this->generate_payload_for_netsuite($customer, $request);
                   // return $formatted_order['message'];
                    if($formatted_order['status']== 404){
                        return response()->json(['statusCode' => 200, 'message' => $formatted_order['message']]);
                    }
                    else if ($formatted_order['status'] == 300 || $formatted_order['status'] == 202) {
                        $not_created[$key]['order_number'] = $customer['name'];
                        $not_created[$key]['message'] = $formatted_order['message'];

                    } else {
                        $data = json_encode($formatted_order['message']);
                         //return ($data);
                        $response = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
                       // dd($response);
                        if ($response['statusCode'] != 200) {
                            $not_created[$key]['order_number'] = $customer['name'];
                            $not_created[$key]['message'] = $response['message'];
                            return response()->json(['statusCode' => 300, 'message' => $response['message']]);
                        } else {
                            $orders_created[] = $customer['name'];
                            //dd($response);
                            $customer = $this->customer_get->searchCustomers($request,$customer['email'], $customer['phone']);
                            //dd($customer['message']);
                            return response()->json(['statusCode' => 200, 'message' => $customer['message']->results]);
                        }
                    }
                }
                return response()->json(['statusCode' => 200, 'message' => 'created ' . json_encode($orders_created), 'not_created' => ($not_created)]);
            }
            return response()->json(['statusCode' => 404, 'response ' => "Customer data missing", 'message' => 'Add at least one customer data and try again']);

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }


    public function generate_payload_for_netsuite($customer, $rq)
    {

        try {

            $company_id = $rq->company_id;
            $environment = $rq->environment;
            $phone  = $customer['phone'];
            $email  = $customer['email'];
            $exists = $this->customer_get->searchCustomers($rq, $email, $phone);
            $results = $exists['message']->results;
            //dd($exists);

            if($exists['statusCode']== 200 && !empty($results)){
                return ['status' => 404, 'message' => $results];
            }


            $rqrd = array(
                'recordtype' => 'customer',
                'subsidiary' => ['id' =>  $customer['subsidiary']],
                'category' => ['id' =>  $customer['category']],
                'companyname' => $customer['name'],
                'firstname' => $customer['name'],
                'lastname' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone']
                //'cseg_nn_branch' => ['id' =>  $customer['branch_id']]
                /*,

                'addressbook' => array(
                    array(
                        'defaultbilling' => true,
                        'defaultshipping' => true,
                        'addr1' => $customer['address'],
                        'city' => $customer['city'],
                    )
                )*/
            );

            return ['status' => 200, 'message' => $rqrd];
        } catch (Exception $ex) {
            return ['status' => 300, 'message' => $ex->getMessage()];
        }

    }
}
