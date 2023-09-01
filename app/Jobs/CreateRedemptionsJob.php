<?php

namespace App\Jobs;

use App\Http\Controllers\NetsuiteConnectorController;
use App\Http\Controllers\SaritInvoiceController;
use App\Http\Controllers\SaritIRedemptionController;
use App\Models\CompanyMaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use PHPUnit\Exception;

class CreateRedemptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $requestData;
    protected $saritRedemptionsController;
    protected $netsuite_connector;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $requestData)
    {
        $saritRedemptionsController = new SaritIRedemptionController();
        $netsuite_connector = new NetsuiteConnectorController();

        $this->requestData = $requestData;
        $this->saritRedemptionsController =  $saritRedemptionsController;
        $this->netsuite_connector = $netsuite_connector;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $existing_redemptions = [];
        $created_redemptions = [];
        $failed_redemptions = [];
        $failed_message = [];
        try{
            $company_id = $this->requestData['company_id'];
            $environment =$this->requestData['environment'];

            $company_data = CompanyMaster::where('id', $company_id)->first();

            $data = $this->requestData;


            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            foreach ($data['redemption'] as $data_return){
                $redemption_number = $data_return['redemption_number'];
                $lms_no = $data_return['lms_no'];

                try{
                    $redemption = $this->saritRedemptionsController->findRedemption($company_id,$environment,$redemption_number,$lms_no);
                    // dd($redemption);
                    if(!$redemption['message']){

                        $failed_redemptions [] = ['redemption_number'=>$redemption_number,'message'=>'Something is wrong with this redemption number or lms No'];
                    }elseif($redemption['statusCode'] == 202) {
                        $existing_redemptions [] =  $redemption_number;

                    } else {
                        $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_redemptions&deploy=customdeploy_create_redemptions";
                        $method = "POST";
                        $data = "";
                        $data = json_encode($data_return);
                        $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

                        if ($send_request['statusCode'] != 200) {
                            $data = $send_request['message'];
                            $failed_redemptions [] = ['redemption_number'=>$redemption_number,'message'=>$data];

                        } else {
                            $created_redemptions[] = $redemption_number;

                        }
                    }
                }catch (Exception $ex){
                    $failed_redemptions [] = ['redemption_number'=>$redemption_number,'message'=> $ex->getMessage() .' Line'.$ex->getLine()];
                }
            }
            $message = ['posted_successfully'=>$created_redemptions,
                'existing'=>$existing_redemptions,
                'failed'=>$failed_redemptions];

            $response  = ['status'=>200, 'response'=>'completed','message'=>$message];
        }catch (\Exception $ex){
            $response  = ['status'=>500, 'response'=>'failed','message'=>$ex->getMessage() .' Line'.$ex->getLine()];
        }

        $handler = fopen("redemption_creation_response_sent" . date('d-m-Y') . ".txt", "a");
        fwrite($handler,json_encode($response));
        fclose($handler);
        //create a call to callback ul and return the response

        $callbackUrl = $this->requestData['callback_url'];
        Http::post($callbackUrl, $response);
    }
}
