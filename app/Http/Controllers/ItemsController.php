<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;

class ItemsController extends Controller
{
    public $netsuite_connector;

    public function __construct()
    {
        $netsuite_connector = new NetsuiteConnectorController();

        $this->netsuite_connector = $netsuite_connector;
    }

    public function getInventoryItems(Request $request)
    {
        try {
            $company_id = $request->company_id;

            $company_data = CompanyMaster::where('id', $company_id)->first();
            $url = "https://" . $company_data->account_number . '-SB1.suitetalk.api.netsuite.com/services/rest/query/v1/suiteql';
            $method = "POST";


            if (!empty($request->item_id) && !empty($request->warehouse_id)) {
                $query = array(
                    "q" => "SELECT item.fullname as item_name, item.id as item_code, ivl.location as warehosuse_code, sum(ivl.quantityavailable) as stock_balance, location.fullname as warehouse_name FROM inventoryitemlocations ivl JOIN item ON item.id = ivl.item JOIN location on location.id = ivl.location where ivl.location in (".implode(",",$request->warehouse_id).") and ivl.item in (".implode(",",$request->item_id).") group by item.fullname,item.id,location.fullname,ivl.location ORDER BY item.id, ivl.location asc"
                );
            } elseif (!empty($request->item_id) && empty($request->warehouse_id)) {
                $query = array(
                    "q" => "SELECT item.fullname as item_name, item.id as item_code, ivl.location as warehosuse_code, sum(ivl.quantityavailable) as stock_balance, location.fullname as warehouse_name FROM inventoryitemlocations ivl JOIN item ON item.id = ivl.item JOIN location on location.id = ivl.location where ivl.item in (".implode(",",$request->item_id).") group by item.fullname,item.id,location.fullname,ivl.location  ORDER BY item.id, ivl.location asc"
                );
            }elseif (empty($request->item_id) && !empty($request->warehouse_id)) {
                $query = array(
                    "q" => "SELECT item.fullname as item_name, item.id as item_code, ivl.location as warehosuse_code, sum(ivl.quantityavailable) as stock_balance, location.fullname as warehouse_name FROM inventoryitemlocations ivl JOIN item ON item.id = ivl.item JOIN location on location.id = ivl.location where ivl.location in (".implode(",",$request->warehouse_id).") group by item.fullname,item.id,location.fullname,ivl.location  ORDER BY item.id, ivl.location asc"
                );

            }
            else{
                $query = array(
                    "q" => "SELECT item.fullname as item_name, item.id as item_code, ivl.location as warehosuse_code, sum(ivl.quantityavailable) as stock_balance, location.fullname as warehouse_name FROM inventoryitemlocations ivl JOIN item ON item.id = ivl.item JOIN location on location.id = ivl.location  group by item.fullname,item.id,location.fullname,ivl.location  ORDER BY item.id, ivl.location asc"
                );
            }
            $data = json_encode($query);

            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, 'sandbox');
            if($send_request['statusCode'] != 200){
                return $send_request;
            }else{
                $data  = $send_request['message'];
                return  $data->items;
            }


            //call function
        } catch (\Exception $ex) {
            return response()->json(['statusCode' => 300, 'response' => 'Something went wrong',
                'message' => 'Error: '.$ex->getMessage().' File: '.$ex->getFile().' Line: '.$ex->getLine()]);
        }

    }
}
