<?php

namespace App\Jobs;

use App\Http\Controllers\NetsuiteConnectorController;
use App\Http\Controllers\SaritIGiftVoucherRedemptionController;
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

class CreateGiftVoucherRedemptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $requestData;
    protected $saritGiftVoucherRedemptionsController;
    protected $netsuite_connector;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $requestData)
    {
        $saritGiftVoucherRedemptionsController = new SaritIGiftVoucherRedemptionController();
        $netsuite_connector = new NetsuiteConnectorController();

        $this->requestData = $requestData;
        $this->saritGiftVoucherRedemptionsController =  $saritGiftVoucherRedemptionsController;
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

            foreach ($data['gift_voucher_redemption'] as $data_return){
                $redemption_number = isset($data_return['gift_voucher_redemption_number']) ? $data_return['gift_voucher_redemption_number'] : "Missing Gift Voucher Redemption Number";
                $lms_no = isset($data_return['lms_no']) ? $data_return['lms_no'] : "  ";

                try{
                    $redemption = $this->saritGiftVoucherRedemptionsController->findGiftVoucherRedemption($company_id,$environment,$redemption_number,$lms_no);
                    // dd($redemption);
                    if(!$redemption['message']){

                        $failed_redemptions [] = ['gift_voucher_redemption_number'=>$redemption_number,'message'=>'Check Gift Voucher Redemption or LMS NO provided on the Payload'];
                    }elseif($redemption['statusCode'] == 202) {
                        $existing_redemptions [] =  $redemption_number;

                    } else {
                        $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_gift_voucher_rd&deploy=customdeploy_create_gift_voucher_rd";
                        $method = "POST";
                        $data = "";
                        $data = json_encode($data_return);
                        $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

                        if ($send_request['statusCode'] != 200) {
                            $data = $send_request['message'];
                            $message = json_decode($data->error->message)->message;
                            $failed_redemptions [] = ['gift_voucher_redemption_number'=>$redemption_number,'message'=>$message];

                        } else {
                            $created_redemptions[] = $redemption_number;

                        }
                    }
                }catch (\Exception $ex){
                    $failed_redemptions [] = ['gift_voucher_redemption_number'=>$redemption_number,'message'=> $ex->getMessage() .' Line'.$ex->getLine()];
                }
            }
            $message = ['posted_successfully'=>$created_redemptions,
                'existing'=>$existing_redemptions,
                'failed'=>$failed_redemptions];

            $response  = ['status'=>200, 'response'=>'completed','message'=>$message];
        }catch (\Exception $ex){
            $response  = ['status'=>500, 'response'=>'failed','message'=>$ex->getMessage() .' Line'.$ex->getLine()];
        }

        $handler = fopen("gift_voucher_redemption_creation_response_sent" . date('d-m-Y') . ".txt", "a");
        fwrite($handler,json_encode($response));
        fclose($handler);
        //create a call to callback ul and return the response
        $callbackUrl = $this->requestData['callback_url'];
        $ch = curl_init($callbackUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseFromCallback = curl_exec($ch);
        curl_close($ch);


        //Http::post($callbackUrl, $response);
    }
}
