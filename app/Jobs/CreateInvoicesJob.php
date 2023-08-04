<?php

namespace App\Jobs;

use App\Http\Controllers\NetsuiteConnectorController;
use App\Http\Controllers\SaritInvoiceController;
use App\Models\CompanyMaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class CreateInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $requestData;
    protected $saritInvoiceController;
    protected $netsuite_connector;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $requestData)
    {
        $saritInvoiceController = new SaritInvoiceController();
        $netsuite_connector = new NetsuiteConnectorController();

        $this->requestData = $requestData;
        $this->saritInvoiceController =  $saritInvoiceController;
        $this->netsuite_connector = $netsuite_connector;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
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
            $existing_invoices = [];
            $created_invoices = [];
            $failed_invoices = [];
            $failed_message = [];
            foreach ($data['invoice'] as $data_return){
                $invoice_number = $data_return['invoice_number'];
                $invoice = $this->saritInvoiceController->findInvoice($company_id,$environment,$invoice_number);

                if($invoice['message']->count > 0){
                    $existing_invoices =  $invoice_number;
                } else {
                    $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_create_invoice&deploy=customdeploy_create_invoice";
                    $method = "POST";
                    $data = "";
                    $data = json_encode($data_return);
                    $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

                    if ($send_request['statusCode'] != 200) {
                        $data = $send_request['message'];
                        $failed_invoices = ['invoice_number'=>$invoice_number,'message'=>$data];

                    } else {
                        $created_invoices = $invoice_number;

                    }
                }
            }
            $message = ['posted_successfully'=>$created_invoices,
                'existing'=>$existing_invoices,
                'failed'=>$failed_invoices];

            $response  = ['status'=>200, 'response'=>'completed','message'=>$message];
        }catch (\Exception $ex){
            $response  = ['status'=>500, 'response'=>'failed','message'=>$ex->getMessage()];
        }

        $handler = fopen("response_sent_to_url_" . $this->requestData['callback_url'] . "_" . date('d-m-Y') . ".txt", "a");
        fwrite($handler,json_encode($response));
        fclose($handler);
        //create a call to callback ul and return the response

        $callbackUrl = $this->requestData['callback_url'];
        Http::post($callbackUrl, $response);
    }
}
