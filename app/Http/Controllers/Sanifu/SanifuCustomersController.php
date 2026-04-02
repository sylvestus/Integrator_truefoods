<?php

namespace App\Http\Controllers\Sanifu;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NetsuiteConnectorController;
use App\Models\CompanyMaster;
use Illuminate\Http\Request;

class SanifuCustomersController extends Controller
{
    public $netsuite_connector;

    public function __construct()
    {
        $netsuite_connector = new NetsuiteConnectorController();
        $this->netsuite_connector = $netsuite_connector;
    }

    /**
     * Get customers from NetSuite using ss_rl_get_customers RESTlet
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function getCustomers(Request $request)
    {
        try {
            // Get request parameters
            $company_id = $request->company_id;
            $environment = $request->environment;

            // Optional pagination and filter parameters
            $page = $request->page ?? 1;
            $pageSize = $request->pageSize ?? 50;
            $customerId = $request->customerId ?? '';
            $companyName = $request->companyName ?? '';
            $email = $request->email ?? '';
            $phone = $request->phone ?? '';
            $isInactive = $request->isInactive ?? '';
            $subsidiary = $request->subsidiary ?? '';

            // Get company data
            $company_data = CompanyMaster::where('id', $company_id)->first();

            if (!$company_data) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => 'Company not found'
                ]);
            }

            // Determine account number based on environment
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            // Build URL with query parameters
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl"
                . "?script=customscript_ss_rl_get_customers"
                . "&deploy=customdeploy_ss_rl_get_customers"
                . "&page=" . $page
                . "&pageSize=" . $pageSize;

            // Add optional filters to URL
            if (!empty($customerId)) {
                $url .= "&customerId=" . urlencode($customerId);
            }
            if (!empty($companyName)) {
                $url .= "&companyName=" . urlencode($companyName);
            }
            if (!empty($email)) {
                $url .= "&email=" . urlencode($email);
            }
            if (!empty($phone)) {
                $url .= "&phone=" . urlencode($phone);
            }
            if (!empty($isInactive)) {
                $url .= "&isInactive=" . ($isInactive ? 'true' : 'false');
            }
            if (!empty($subsidiary)) {
                $url .= "&subsidiary=" . urlencode($subsidiary);
            }

            $method = "GET";
            $data = "";
            $data = json_decode($data);

            // Call NetSuite RESTlet
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => $send_request['message']
                ]);
            }

            // Return success response
            $responseData = $send_request['message'];

            // Check if the response contains an error from NetSuite
            if (isset($responseData->success) && $responseData->success === false) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => $responseData->error->type ?? '',
                    'error_message' => $responseData->error->message ?? 'An error occurred'
                ]);
            }

            // Format response with pagination at top level
            $formattedResponse = [
                'statusCode' => 200,
                'response' => 'Success',
                'data' => $responseData->data ?? $responseData
            ];

            // Add pagination info if available
            if (isset($responseData->pagination)) {
                $formattedResponse['pagination'] = [
                    'page' => $responseData->pagination->page ?? 1,
                    'pageSize' => $responseData->pagination->pageSize ?? $pageSize,
                    'totalRecords' => $responseData->pagination->totalRecords ?? 0,
                    'totalPages' => $responseData->pagination->totalPages ?? 1,
                    'currentPageCount' => $responseData->pagination->currentPageCount ?? 0,
                    'startItem' => $responseData->pagination->startItem ?? 0,
                    'endItem' => $responseData->pagination->endItem ?? 0,
                    'hasNextPage' => $responseData->pagination->hasNextPage ?? false,
                    'hasPreviousPage' => $responseData->pagination->hasPreviousPage ?? false
                ];
            }

            return response()->json($formattedResponse);

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'error',
                'error_code' => '',
                'error_message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()
            ]);
        }
    }

    /**
     * Get customer purchase history from NetSuite using ss_rl_get_customer_purchases RESTlet
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function getCustomerPurchases(Request $request)
    {
        try {
            // Get request parameters
            $company_id = $request->company_id;
            $environment = $request->environment;

            // Optional pagination parameters
            $page = $request->page ?? 1;
            $pageSize = $request->pageSize ?? 50;

            // Optional filter parameters
            $customerId = $request->customerId ?? '';
            $transactionType = $request->transactionType ?? ''; // 'invoice', 'salesorder', 'cashsale', 'creditmemo', 'returnauthorization', 'estimate'
            $dateFrom = $request->dateFrom ?? '';
            $dateTo = $request->dateTo ?? '';
            $tranId = $request->tranId ?? '';
            $status = $request->status ?? '';
            $subsidiary = $request->subsidiary ?? '';
            $department = $request->department ?? '';
            $location = $request->location ?? '';
            $salesRep = $request->salesRep ?? '';
            $minAmount = $request->minAmount ?? '';
            $maxAmount = $request->maxAmount ?? '';
            $includeLineItems = $request->includeLineItems ?? true;

            // Get company data
            $company_data = CompanyMaster::where('id', $company_id)->first();

            if (!$company_data) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => 'Company not found'
                ]);
            }

            // Determine account number based on environment
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            // Build URL with query parameters
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl"
                . "?script=customscript_ss_rl_get_cust_purchases"
                . "&deploy=customdeploy_ss_rl_get_cust_purchases"
                . "&page=" . $page
                . "&pageSize=" . $pageSize;

            // Add optional filters to URL
            if (!empty($customerId)) {
                $url .= "&customerId=" . urlencode($customerId);
            }
            if (!empty($transactionType)) {
                $url .= "&transactionType=" . urlencode($transactionType);
            }
            if (!empty($dateFrom)) {
                $url .= "&dateFrom=" . urlencode($dateFrom);
            }
            if (!empty($dateTo)) {
                $url .= "&dateTo=" . urlencode($dateTo);
            }
            if (!empty($tranId)) {
                $url .= "&tranId=" . urlencode($tranId);
            }
            if (!empty($status)) {
                $url .= "&status=" . urlencode($status);
            }
            if (!empty($subsidiary)) {
                $url .= "&subsidiary=" . urlencode($subsidiary);
            }
            if (!empty($department)) {
                $url .= "&department=" . urlencode($department);
            }
            if (!empty($location)) {
                $url .= "&location=" . urlencode($location);
            }
            if (!empty($salesRep)) {
                $url .= "&salesRep=" . urlencode($salesRep);
            }
            if (!empty($minAmount)) {
                $url .= "&minAmount=" . urlencode($minAmount);
            }
            if (!empty($maxAmount)) {
                $url .= "&maxAmount=" . urlencode($maxAmount);
            }
            if ($includeLineItems === false || $includeLineItems === 'false') {
                $url .= "&includeLineItems=false";
            }

            $method = "GET";
            $data = "";
            $data = json_decode($data);

            // Call NetSuite RESTlet
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => $send_request['message']
                ]);
            }

            // Return success response
            $responseData = $send_request['message'];

            // Check if the response contains an error from NetSuite
            if (isset($responseData->success) && $responseData->success === false) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => $responseData->error->type ?? '',
                    'error_message' => $responseData->error->message ?? 'An error occurred'
                ]);
            }

            return response()->json([
                'statusCode' => 200,
                'response' => 'Success',
                'data' => $responseData->data ?? $responseData
            ]);

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'error',
                'error_code' => '',
                'error_message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()
            ]);
        }
    }

    /**
     * Create a sales order in NetSuite using ss_rl_create_sales_order RESTlet
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function createSalesOrder(Request $request)
    {
        try {
            // Get request parameters
            $company_id = $request->company_id;
            $environment = $request->environment;

            // Get company data
            $company_data = CompanyMaster::where('id', $company_id)->first();

            if (!$company_data) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => 'Company not found'
                ]);
            }

            // Validate required parameters
            if (!$request->has('customerId')) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'MISSING_REQUIRED_PARAMETER',
                    'error_message' => 'customerId is required'
                ]);
            }

            if (!$request->has('items') || !is_array($request->items) || count($request->items) === 0) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'MISSING_REQUIRED_PARAMETER',
                    'error_message' => 'items array is required and must contain at least one item'
                ]);
            }

            // Determine account number based on environment
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            // Build URL
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl"
                . "?script=customscript_ss_rl_create_sales_order"
                . "&deploy=customdeploy_ss_rl_create_sales_order";

            // Build request body
            $requestBody = [
                'customerId' => $request->customerId,
                'items' => $request->items
            ];

            // Add optional header fields if provided
            if ($request->has('memo')) {
                $requestBody['memo'] = $request->memo;
            }
            if ($request->has('tranId')) {
                $requestBody['tranId'] = $request->tranId;
            }
            if ($request->has('otherRefNum')) {
                $requestBody['otherRefNum'] = $request->otherRefNum;
            }
            if ($request->has('poNumber')) {
                $requestBody['poNumber'] = $request->poNumber;
            }
            if ($request->has('tranDate')) {
                $requestBody['tranDate'] = $request->tranDate;
            }
            if ($request->has('shipDate')) {
                $requestBody['shipDate'] = $request->shipDate;
            }
            if ($request->has('dueDate')) {
                $requestBody['dueDate'] = $request->dueDate;
            }
            if ($request->has('subsidiary')) {
                $requestBody['subsidiary'] = $request->subsidiary;
            }
            if ($request->has('terms')) {
                $requestBody['terms'] = $request->terms;
            }
            if ($request->has('salesRep')) {
                $requestBody['salesRep'] = $request->salesRep;
            }
            if ($request->has('department')) {
                $requestBody['department'] = $request->department;
            }
            if ($request->has('classId')) {
                $requestBody['classId'] = $request->classId;
            }
            if ($request->has('location')) {
                $requestBody['location'] = $request->location;
            }
            if ($request->has('shipMethod')) {
                $requestBody['shipMethod'] = $request->shipMethod;
            }
            if ($request->has('currency')) {
                $requestBody['currency'] = $request->currency;
            }

            // Add optional shipping address fields
            if ($request->has('shipAddress')) {
                $requestBody['shipAddress'] = $request->shipAddress;
            }
            if ($request->has('shipAddressee')) {
                $requestBody['shipAddressee'] = $request->shipAddressee;
            }
            if ($request->has('shipAttention')) {
                $requestBody['shipAttention'] = $request->shipAttention;
            }
            if ($request->has('shipAddr1')) {
                $requestBody['shipAddr1'] = $request->shipAddr1;
            }
            if ($request->has('shipAddr2')) {
                $requestBody['shipAddr2'] = $request->shipAddr2;
            }
            if ($request->has('shipCity')) {
                $requestBody['shipCity'] = $request->shipCity;
            }
            if ($request->has('shipState')) {
                $requestBody['shipState'] = $request->shipState;
            }
            if ($request->has('shipZip')) {
                $requestBody['shipZip'] = $request->shipZip;
            }
            if ($request->has('shipCountry')) {
                $requestBody['shipCountry'] = $request->shipCountry;
            }
            if ($request->has('shipPhone')) {
                $requestBody['shipPhone'] = $request->shipPhone;
            }

            // Add custom fields (TruFoods specific)
            if ($request->has('salesType')) {
                $requestBody['salesType'] = $request->salesType;
            }
            if ($request->has('channel')) {
                $requestBody['channel'] = $request->channel;
            }
            if ($request->has('region')) {
                $requestBody['region'] = $request->region;
            }
            if ($request->has('widgetLink')) {
                $requestBody['widgetLink'] = $request->widgetLink;
            }

            $method = "POST";
            $data = json_encode($requestBody);

            // Call NetSuite RESTlet
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => $send_request['message']
                ]);
            }

            // Return success response
            $responseData = $send_request['message'];

            // Check if the response contains an error from NetSuite
            if (isset($responseData->success) && $responseData->success === false) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => $responseData->error->type ?? '',
                    'error_message' => $responseData->error->message ?? 'An error occurred',
                    'error_details' => $responseData->error->details ?? null
                ]);
            }

            return response()->json([
                'statusCode' => 200,
                'response' => 'Success',
                'data' => $responseData
            ]);

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'error',
                'error_code' => '',
                'error_message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()
            ]);
        }
    }

    /**
     * Update a sales order in NetSuite using ss_rl_update_sales_order RESTlet
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function updateSalesOrder(Request $request)
    {
        try {
            // Get request parameters
            $company_id = $request->company_id;
            $environment = $request->environment;

            // Get company data
            $company_data = CompanyMaster::where('id', $company_id)->first();

            if (!$company_data) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => 'Company not found'
                ]);
            }

            // Validate required parameters
            if (!$request->has('orderId')) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'MISSING_REQUIRED_PARAMETER',
                    'error_message' => 'orderId is required'
                ]);
            }

            // Determine account number based on environment
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            // Build URL
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl"
                . "?script=customscript_ss_rl_update_sales_order"
                . "&deploy=customdeploy_ss_rl_update_sales_order";

            // Build request body with all possible fields
            $requestBody = [
                'orderId' => $request->orderId
            ];

            // Header fields
            if ($request->has('memo')) {
                $requestBody['memo'] = $request->memo;
            }
            if ($request->has('tranDate')) {
                $requestBody['tranDate'] = $request->tranDate;
            }
            if ($request->has('dueDate')) {
                $requestBody['dueDate'] = $request->dueDate;
            }
            if ($request->has('shipDate')) {
                $requestBody['shipDate'] = $request->shipDate;
            }
            if ($request->has('otherRefNum')) {
                $requestBody['otherRefNum'] = $request->otherRefNum;
            }
            if ($request->has('terms')) {
                $requestBody['terms'] = $request->terms;
            }
            if ($request->has('salesRep')) {
                $requestBody['salesRep'] = $request->salesRep;
            }
            if ($request->has('department')) {
                $requestBody['department'] = $request->department;
            }
            if ($request->has('classId')) {
                $requestBody['classId'] = $request->classId;
            }
            if ($request->has('location')) {
                $requestBody['location'] = $request->location;
            }
            if ($request->has('shipMethod')) {
                $requestBody['shipMethod'] = $request->shipMethod;
            }

            // Shipping address fields
            if ($request->has('shipAddress')) {
                $requestBody['shipAddress'] = $request->shipAddress;
            }
            if ($request->has('shipAddressee')) {
                $requestBody['shipAddressee'] = $request->shipAddressee;
            }
            if ($request->has('shipAttention')) {
                $requestBody['shipAttention'] = $request->shipAttention;
            }
            if ($request->has('shipAddr1')) {
                $requestBody['shipAddr1'] = $request->shipAddr1;
            }
            if ($request->has('shipAddr2')) {
                $requestBody['shipAddr2'] = $request->shipAddr2;
            }
            if ($request->has('shipCity')) {
                $requestBody['shipCity'] = $request->shipCity;
            }
            if ($request->has('shipState')) {
                $requestBody['shipState'] = $request->shipState;
            }
            if ($request->has('shipZip')) {
                $requestBody['shipZip'] = $request->shipZip;
            }
            if ($request->has('shipCountry')) {
                $requestBody['shipCountry'] = $request->shipCountry;
            }
            if ($request->has('shipPhone')) {
                $requestBody['shipPhone'] = $request->shipPhone;
            }

            // Custom fields (TruFoods specific)
            if ($request->has('salesType')) {
                $requestBody['salesType'] = $request->salesType;
            }
            if ($request->has('channel')) {
                $requestBody['channel'] = $request->channel;
            }
            if ($request->has('region')) {
                $requestBody['region'] = $request->region;
            }

            // Line items
            if ($request->has('items')) {
                // Ensure items is an array and properly formatted
                $items = $request->items;
                if (is_string($items)) {
                    $items = json_decode($items, true);
                }

                // Validate each item has the required 'line' property
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (!isset($item['line'])) {
                            return response()->json([
                                'status' => 'error',
                                'error_code' => 'INVALID_ITEM_FORMAT',
                                'error_message' => 'Each item must have a "line" property with the line number (1-based)'
                            ]);
                        }
                    }
                    $requestBody['items'] = $items;
                }
            }

            $method = "PUT";
            $data = json_encode($requestBody);

            // Call NetSuite RESTlet
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => $send_request['message']
                ]);
            }

            // Return success response
            $responseData = $send_request['message'];

            // Check if the response contains an error from NetSuite
            if (isset($responseData->success) && $responseData->success === false) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => $responseData->error->type ?? '',
                    'error_message' => $responseData->error->message ?? 'An error occurred'
                ]);
            }

            // Add debug info to see what was sent
            $debugInfo = [];
            if (isset($requestBody['items'])) {
                $debugInfo['items_sent'] = $requestBody['items'];
                $debugInfo['items_count'] = count($requestBody['items']);
            }

            return response()->json([
                'statusCode' => 200,
                'response' => 'Success',
                'data' => $responseData,
                'debug' => $debugInfo
            ]);

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'error',
                'error_code' => '',
                'error_message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()
            ]);
        }
    }

    /**
     * Get shipping addresses from NetSuite using ss_rl_get_shipping_addresses RESTlet
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function getShippingAddresses(Request $request)
    {
        try {
            // Get request parameters
            $company_id = $request->company_id;
            $environment = $request->environment;

            // Optional pagination and filter parameters
            $page = $request->page ?? 1;
            $pageSize = $request->pageSize ?? 50;
            $customerId = $request->customerId ?? '';
            $country = $request->country ?? '';
            $city = $request->city ?? '';
            $defaultShipping = $request->defaultShipping ?? '';

            // Get company data
            $company_data = CompanyMaster::where('id', $company_id)->first();

            if (!$company_data) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => 'Company not found'
                ]);
            }

            // Determine account number based on environment
            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            // Build URL with query parameters
            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl"
                . "?script=customscript_ss_rl_get_shipping_addresses"
                . "&deploy=customdeploy_ss_rl_get_shipping_addresses"
                . "&page=" . $page
                . "&pageSize=" . $pageSize;

            // Add optional filters to URL
            if (!empty($customerId)) {
                $url .= "&customerId=" . urlencode($customerId);
            }
            if (!empty($country)) {
                $url .= "&country=" . urlencode($country);
            }
            if (!empty($city)) {
                $url .= "&city=" . urlencode($city);
            }
            if ($defaultShipping !== '') {
                $url .= "&defaultShipping=" . ($defaultShipping ? 'true' : 'false');
            }

            $method = "GET";
            $data = "";
            $data = json_decode($data);

            // Call NetSuite RESTlet
            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => $send_request['message']
                ]);
            }

            // Return success response
            $responseData = $send_request['message'];

            // Check if the response contains an error from NetSuite
            if (isset($responseData->success) && $responseData->success === false) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => $responseData->error ?? '',
                    'error_message' => $responseData->error ?? 'An error occurred'
                ]);
            }

            // Format response with pagination at top level
            $formattedResponse = [
                'statusCode' => 200,
                'response' => 'Success',
                'data' => $responseData->data ?? $responseData
            ];

            // Add pagination info if available
            if (isset($responseData->pagination)) {
                $formattedResponse['pagination'] = [
                    'page' => $responseData->pagination->page ?? 1,
                    'pageSize' => $responseData->pagination->pageSize ?? $pageSize,
                    'totalRecords' => $responseData->pagination->totalRecords ?? 0,
                    'totalPages' => $responseData->pagination->totalPages ?? 1,
                    'hasNextPage' => $responseData->pagination->hasNextPage ?? false,
                    'hasPreviousPage' => $responseData->pagination->hasPreviousPage ?? false
                ];
            }

            return response()->json($formattedResponse);

        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'error',
                'error_code' => '',
                'error_message' => 'Error: ' . $ex->getMessage() . ' File: ' . $ex->getFile() . ' Line: ' . $ex->getLine()
            ]);
        }
    }
}
