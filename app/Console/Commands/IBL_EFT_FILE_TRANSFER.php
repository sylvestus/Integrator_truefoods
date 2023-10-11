<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IBL_EFT_FILE_TRANSFER extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eft:transfer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5ODg5MjdlMC1lY2JmLTQ5ZWQtODA5Yy0yNmI1ZDRlYTM3ZjEiLCJqdGkiOiJlMzRiM2JhODNiNWVjNzhhNDhmYzc4NzdjN2U4NTZjZGE5MGJmZGUyZjFlYjQ2ZTkyYjFlM2FiZjYwNzYwZjFhYTI2YzUyOTEyM2VmNTIxNiIsImlhdCI6MTY5NzAyNzQ1OC4yODMwOTcsIm5iZiI6MTY5NzAyNzQ1OC4yODMxMTQsImV4cCI6MTcyODY0OTg1OC4yNjcwNDcsInN1YiI6IjgiLCJzY29wZXMiOltdfQ.IshkBSyXQ0ThyonNCd55LkERlW7B-VGMDT-VvwdT8yZUMyXsrMTzpkwxOWoibRn4_w0hzL5VNbXOoKEh2yJZ2mujEhrxbd3ESbJDBdR8ZOsCh_cHyHVpKmF0kT_3HW44EREWJXqQNr22SwRmwRneuG3itfRylhHo6ycHc034am2zYppPUSXBOPx0nIhHn2HEpHcUjMT4vEw1NaLkwAJ2dr1xdrmwUc4rKVxbrj1-vtcNyCA6fBU6ZUoSCrWc6FPES6wBZp9TYKma3d_rnG9BPqk7Ir1kJbTMPZAtutkP2xCN61PYPVPaSoCpHT3OBl2Z0ec6y9wxc_JYpT3JQAPnoLId2aXivTYv3-rTVl6mitQ8yBWwcGBMm5vMT7OHsOSIVm04rt7jKpz_KzBiHN5GPNTx58_db4Ka9YpcGb-R9GfyM2IGEJwQlohpLBe9TIlRhjLxb0QQoUb_N-R37A1arLhMoo8RgO8taaV3R8my_o5tOpv8h77Mb-dHmuhskYKjNIvJ3uDi5lkhaCr6zLWO6o_cNaXA843SuqYcKc9ZFYsRb3cp42PA0ShurtxSPjwFdyzYqNeQiA0s2Zh2jEb7tjMwQOOhc6kN-fBJUxHnsIxUeaLAv8ix4j-O2Iu6NT58MOpUJ_iH1Z9o3Q8jNO7oSt-Wq2C4wL__sNFYoT381Is'; // Replace with your actual access token
        $requestBody = [
            'environment' => 'sandbox',
            'company_id' => 7,
        ];

        $response = Http::withToken($token)
            ->post('http://nsintegrator.dynamicsserv.com:8800/api/ibl-fetch-files', $requestBody);

        dd($response);
        $result = $response->json(); // Assuming the API response is in JSON format



    }
}
