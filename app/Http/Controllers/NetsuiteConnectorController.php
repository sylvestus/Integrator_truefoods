<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use mysql_xdevapi\Exception;

class NetsuiteConnectorController extends Controller
{



    public function __invoke(Request $request)
    {
        //
    }


    public function callRestApi($url,$method,$data,$company_data,$environment){
        try{


            $oauth_nonce = md5(mt_rand());
            $oauth_timestamp = time();
            $oauth_signature_method = 'HMAC-SHA256';
            $oauth_version = "1.0";


            $company_master = $company_data;
            $account_number = $company_master->account_number;

           // dd($company_master);
            if($environment == 'sandbox'){
                $account = $account_number.'_SB1';
                $consumerKey = $company_master->staging_consumerKey;
                $tokenId = $company_master->staging_tokenId;
                $consumerSecret = $company_master->staging_consumerSecret;
                $tokenSecret = $company_master->staging_tokenSecret;
                //dd($tokenSecret);
            }else{
                $account = $account_number;
                $consumerKey = $company_master->consumerKey;
                $tokenId = $company_master->tokenId;
                $consumerSecret = $company_master->consumerSecret;
                $tokenSecret = $company_master->tokenSecret;
            }


            // generate Signature
            $baseString = $this->restletBaseString($method,
                $url,
                $consumerKey,
                $tokenId,
                $oauth_nonce,
                $oauth_timestamp,
                $oauth_version,
                $oauth_signature_method,null);



            $key = rawurlencode($consumerSecret) .'&'. rawurlencode($tokenSecret);

            $signature = base64_encode(hash_hmac('sha256', $baseString, $key, true));
            //dd($signature);

            // GENERATE HEADER TO PASS IN CURL
            $header = 'Authorization: OAuth '
                .'realm="' .rawurlencode($account) .'", '
                .'oauth_consumer_key="' .rawurlencode($consumerKey) .'", '
                .'oauth_token="' .rawurlencode($tokenId) .'", '
                .'oauth_nonce="' .rawurlencode($oauth_nonce) .'", '
                .'oauth_timestamp="' .rawurlencode($oauth_timestamp) .'", '
                .'oauth_signature_method="' .rawurlencode($oauth_signature_method) .'", '
                .'oauth_version="' .rawurlencode($oauth_version) .'", '
                .'oauth_signature="' .rawurlencode($signature) .'"';

           // $header = 'Authorization: OAuth realm="7569482_SB1",oauth_consumer_key="15f24cfbd171c23df88897a9592d8a1938836f0bd24576e6a23e80b275bf8923",oauth_token="51bd5707e5c8e74efe810bcc362ec575e72a597bcdbd5681ea4a32d7ad527d15",oauth_signature_method="HMAC-SHA256",oauth_timestamp="1675842972",oauth_nonce="L1Z5cNUDg0c",oauth_version="1.0",oauth_signature="RpC%2F5asl3OyLbJQdaDXLsLNz0UbztpmYkphZdjOZoYw%3D"';


            return  $this->callCurl($header,$url,$data,$method);
        }catch (\Exception $ex){
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
        }

    }

    public function callCurl($header,$url,$data,$method){
        try{
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYHOST=>false,
                CURLOPT_SSL_VERIFYPEER=>false,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    $header,
                    "content-type: application/json",
                    "Prefer: transient"
                ),
            ));
            $response = curl_exec($curl);

            //dd($response);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);


            if($httpcode !=204 && $httpcode!=200 ){
                return (['statusCode'=>$httpcode,'response'=>'Something Went Wrong','message'=>json_decode($response)]);
            }else{
                return (array("statusCode"=>200,"response"=>'Success','message'=>json_decode($response)));

            }
        }catch (\Exception $ex){
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
        }

    }

    public function restletBaseString($httpMethod, $url, $consumerKey, $tokenKey, $nonce, $timestamp, $version, $signatureMethod, $postParams){
        //http method must be upper case
        $baseString = strtoupper($httpMethod) .'&';

        //include url without parameters, schema and hostname must be lower case
        if (strpos($url, '?')){
            $baseUrl = substr($url, 0, strpos($url, '?'));
            $getParams = substr($url, strpos($url, '?') + 1);
        } else {
            $baseUrl = $url;
            $getParams = "";
        }
        $hostname = strtolower(substr($baseUrl, 0,  strpos($baseUrl, '/', 10)));
        $path = substr($baseUrl, strpos($baseUrl, '/', 10));
        $baseUrl = $hostname . $path;
        $baseString .= rawurlencode($baseUrl) .'&';

        //all oauth and get params. First they are decoded, next alphabetically sorted, next each key and values is encoded and finally whole parameters are encoded
        $params = array();
        $params['oauth_consumer_key'] = array($consumerKey);
        $params['oauth_token'] = array($tokenKey);
        $params['oauth_nonce'] = array($nonce);
        $params['oauth_timestamp'] = array($timestamp);
        $params['oauth_signature_method'] = array($signatureMethod);
        $params['oauth_version'] = array($version);

        foreach (explode('&', $getParams ."&". $postParams) as $param) {
            $parsed = explode('=', $param);
            if ($parsed[0] != "") {
                $value = isset($parsed[1]) ? urldecode($parsed[1]): "";
                if (isset($params[urldecode($parsed[0])])) {
                    array_push($params[urldecode($parsed[0])], $value);
                } else {
                    $params[urldecode($parsed[0])] = array($value);
                }
            }
        }

        //all parameters must be alphabetically sorted
        ksort($params);

        $paramString = "";
        foreach ($params as $key => $valueArray){
            //all values must be alphabetically sorted
            sort($valueArray);
            foreach ($valueArray as $value){
                $paramString .= rawurlencode($key) . '='. rawurlencode($value) .'&';
            }
        }
        $paramString = substr($paramString, 0, -1);
        $baseString .= rawurlencode($paramString);

        return $baseString;
    }
}
