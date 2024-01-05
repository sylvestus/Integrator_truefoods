<?php

namespace App\Http\Controllers;

use App\Jobs\CreateJournalEntryJob;
use App\Jobs\CreateRedemptionsJob;
use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class MoneyPointJournalEntryController extends Controller
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

    public function postMoneyPointJournalEntry(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;
            $redemption = $request->journal;

            $handler = fopen("mp_journal_request_" . date('d-m-Y') . ".txt", "a");

            fwrite($handler,json_encode($request->all()));
            fclose($handler);
            if(count($redemption)<1){
                return response()->json(['status'=>300,'message' => 'Request Missing Journal Values']);
            }

            if($company_id && $environment){

                dispatch(new CreateJournalEntryJob($request->all()));
                // Return a response to the original request
                return response()->json(['status'=>200,'message' => 'Journal processing started']);
            }else{
                return response()->json(['status'=>300,'message' => 'Invalid Request body']);
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

    public function postSaritRedemptionsOld(Request $request)
    {
        try {
            // return $request->all();
            $company_id = $request->company_id;
            $environment = $request->environment;
            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $request->all();
            $data_return = $data['redemption'][0];
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $redemption_number = $data_return['redemption_number'];
            $lms_no = $data_return['lms_no'];
            $invoice = $this->findRedemption($company_id, $environment, $redemption_number,$lms_no);
            if ($invoice['statusCode'] == 202) {
                return ['status' => 202, 'message' => 'Redemption exists in netsuite'];
            } else if($invoice['statusCode'] == 200) {

                $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_redemptions&deploy=customdeploy_create_redemptions";
                $method = "POST";
                $data = json_encode($data_return);
                $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

                if ($send_request['statusCode'] != 200) {
                    return $send_request;
                } else {
                    $data = $send_request['message'];
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }
            } else {
                return $invoice;
            }

            return response()->json(['statusCode' => 404, 'response ' => "Orders missing", 'message' => 'Add at least one sale and try again']);

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

    public function findJournalEntry($company_id, $environment, $order_number,$lms_no)
    {
        try {
            $company_data = CompanyMaster::where('id', $company_id)->first();
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }
            //$url = "https://" . $account_number . ".suitetalk.api.netsuite.com/services/rest/record/v1/customtransaction_redemption?q=custbody_lms+CONTAIN+".$order_number;
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_jn_rl_search_jea&deploy=customdeploy_jn_rl_search_je";
            $method = "POST";
            $redemption_number = ['reference_number' => $order_number];
            $data = json_encode($redemption_number);
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            //dd($send_request);
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];

                if($data->success){
                    return ['statusCode' => 202, 'response' => 'Success', 'message' => $data];
                }else{
                    return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
                }

            }
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

}
