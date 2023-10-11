<?php

namespace App\Http\Controllers;

use App\Jobs\CreateInvoicesJob;
use App\Models\CompanyMaster;
use Illuminate\Http\Request;
use phpseclib3\Net\SFTP;
use PHPUnit\Exception;

class IBLEFTController extends Controller
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

    public function getFiles(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;


            $company_data = CompanyMaster::where('id', $company_id)->first();


            if($environment == 'sandbox'){
                $account_number = $company_data->account_number.'-sb1';
            }else{
                $account_number = $company_data->account_number;
            }

            $url = "https://".$account_number.".restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=customscript_restlet_export_eft&deploy=customdeploy_export_eft";

            $method = "GET";
            $data = "";
            $data = json_decode($data);



            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);
            if ($send_request['statusCode'] != 200) {
                return $send_request;
            } else {
                $data = $send_request['message'];
                return ['statusCode' => 200, 'response' => 'Success', 'message' => $data];
            }

        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()]);
        }
    }

    public function getFileFromNetsuite(Request $request)
    {
        $file_name = $request->file_name;
        $file_contents = $request->file_contents;
        $handle  = fopen('newFile.txt','w+');
        fwrite($handle, $file_contents);
        fclose($handle);


        // Replace "\r\n" with new lines
        $file_contents = str_replace("\r\n", "\n", $file_contents);

        // Specify the directory where you want to save the file
        $directory = './eft';

        // Check if the directory exists, and create it if it doesn't
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Create and write the file
        $file_path = './eft/'. $file_name.'.txt';
        file_put_contents($file_path, $file_contents);
        $command = "C:\MCBCheckSum\ConsoleLnHashCheckSum.exe $file_path";
        $checksum = shell_exec($command);
        $sendToSFTP = $this->sendChecksumToSFTP($checksum,$file_name);

        return $sendToSFTP;
    }

    public function sendChecksumToSFTP($checksum,$file_name) {
        $sftp = new SFTP('192.225.166.247', 2224);

        $username = 'NetSuiteSFTP';
        $password = 'N3t$u298kr4!fp34'; // or provide the key path and passphrase if using key-based authentication
        $uploadDirectory = '';

        if (!$sftp->login($username, $password)) {
            // Login failed
            return response()->json(['message' => 'SFTP login failed']);
        }

        // Upload the checksum to the SFTP server
        $remoteFileName = $file_name.'_checksum.txt'; // Name of the remote file
        $remoteFilePath =  './' . $remoteFileName;

        if ($sftp->put($remoteFilePath, $checksum)) {
            // Upload successful
            return response()->json(['message' => 'Checksum uploaded successfully']);
        } else {
            // Upload failed
            return response()->json(['message' => 'Checksum upload failed']);
        }
    }
}
