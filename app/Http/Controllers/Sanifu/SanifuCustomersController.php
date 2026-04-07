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
     * @OA\Post(
     *     path="/get/customers",
     *     tags={"Customers"},
     *     summary="Get Customers",
     *     description="Retrieve a paginated list of customers with optional filters",
     *     @OA\Parameter(name="company_id", in="query", required=true, @OA\Schema(type="integer"), example=6, description="Company identifier"),
     *     @OA\Parameter(name="environment", in="query", required=true, @OA\Schema(type="string", enum={"sandbox","production"}), example="sandbox", description="Environment type"),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1), example=1, description="Page number for pagination"),
     *     @OA\Parameter(name="customerId", in="query", required=false, @OA\Schema(type="string"), description="Filter by specific customer ID"),
     *     @OA\Parameter(name="companyName", in="query", required=false, @OA\Schema(type="string"), description="Filter by company name"),
     *     @OA\Parameter(name="email", in="query", required=false, @OA\Schema(type="string"), description="Filter by customer email"),
     *     @OA\Parameter(name="phone", in="query", required=false, @OA\Schema(type="string"), description="Filter by customer phone"),
     *     @OA\Parameter(name="isInactive", in="query", required=false, @OA\Schema(type="boolean"), description="Filter by inactive status"),
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
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
     * @OA\Post(
     *     path="/get/customer-purchases",
     *     tags={"Customers"},
     *     summary="Get Customer Purchases",
     *     description="Retrieve purchase history for a specific customer",
     *     @OA\Parameter(name="company_id", in="query", required=true, @OA\Schema(type="integer"), example=6, description="Company identifier"),
     *     @OA\Parameter(name="environment", in="query", required=true, @OA\Schema(type="string", enum={"sandbox","production"}), example="sandbox", description="Environment type"),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1), example=1, description="Page number for pagination"),
     *     @OA\Parameter(name="pageSize", in="query", required=false, @OA\Schema(type="integer", default=20), example=20, description="Number of records per page"),
     *     @OA\Parameter(name="customerId", in="query", required=true, @OA\Schema(type="integer"), example=1378, description="Customer ID to retrieve purchases for"),
     *     @OA\Parameter(name="transactionType", in="query", required=false, @OA\Schema(type="string", enum={"invoice","salesorder","cashsale","creditmemo","returnauthorization","estimate"}), example="salesorder", description="Filter by transaction type"),
     *     @OA\Parameter(name="dateFrom", in="query", required=false, @OA\Schema(type="string"), example="29/03/2026", description="Start date filter (DD/MM/YYYY format)"),
     *     @OA\Parameter(name="dateTo", in="query", required=false, @OA\Schema(type="string"), example="30/03/2026", description="End date filter (DD/MM/YYYY format)"),
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
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
     * @OA\Post(
     *     path="/create/sales-order",
     *     tags={"Sales Orders"},
     *     summary="Create Sales Order",
     *     description="Create a new sales order",
     *     @OA\Parameter(name="company_id", in="query", required=true, @OA\Schema(type="integer"), example=6, description="Company identifier"),
     *     @OA\Parameter(name="environment", in="query", required=true, @OA\Schema(type="string", enum={"sandbox","production"}), example="sandbox", description="Environment type"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"company_id","environment","customerId","tranDate","otherRefNum","shipDate","department","location","status","items"},
     *             @OA\Property(property="company_id", type="integer", description="Company identifier"),
     *             @OA\Property(property="environment", type="string", enum={"sandbox","production"}, description="Environment type"),
     *             @OA\Property(property="customerId", type="integer", description="Customer ID for the order"),
     *             @OA\Property(property="status", type="string", enum={"A","B"}, description="Order status"),
     *             @OA\Property(property="memo", type="string", description="Order memo/notes"),
     *             @OA\Property(property="tranDate", type="string", format="date", description="Transaction date (YYYY-MM-DD)"),
     *             @OA\Property(property="dueDate", type="string", format="date", description="Due date (YYYY-MM-DD)"),
     *             @OA\Property(property="shipDate", type="string", format="date", description="Ship date (YYYY-MM-DD)"),
     *             @OA\Property(property="otherRefNum", type="string", description="External reference number (e.g., PO number)"),
     *             @OA\Property(property="terms", type="integer", description="Payment terms ID"),
     *             @OA\Property(property="salesRep", type="integer", description="Sales representative ID"),
     *             @OA\Property(property="department", type="integer", description="Department ID"),
     *             @OA\Property(property="classId", type="integer", description="Class ID"),
     *             @OA\Property(property="location", type="integer", description="Location ID"),
     *             @OA\Property(property="salesType", type="integer", description="Sales type ID"),
     *             @OA\Property(property="channel", type="integer", description="Sales channel ID"),
     *             @OA\Property(property="region", type="integer", description="Region ID"),
     *             @OA\Property(property="widgetLink", type="string", description="Widget link URL"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 required={"itemId","quantity","taxCode","location"},
     *                 @OA\Property(property="itemId", type="integer", description="Item ID"),
     *                 @OA\Property(property="quantity", type="integer", description="Quantity"),
     *                 @OA\Property(property="rate", type="number", description="Unit price"),
     *                 @OA\Property(property="amount", type="number", description="Line total amount"),
     *                 @OA\Property(property="description", type="string", description="Line item description"),
     *                 @OA\Property(property="taxCode", type="integer", description="Tax code ID"),
     *                 @OA\Property(property="location", type="integer", description="Item location ID")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sales order created successfully"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
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
     * @OA\Post(
     *     path="/update/sales-order",
     *     tags={"Sales Orders"},
     *     summary="Update Sales Order",
     *     description="Update an existing sales order",
     *     @OA\Parameter(name="company_id", in="query", required=true, @OA\Schema(type="integer"), example=6, description="Company identifier"),
     *     @OA\Parameter(name="environment", in="query", required=true, @OA\Schema(type="string", enum={"sandbox","production"}), example="sandbox", description="Environment type"),
     *     @OA\Parameter(name="orderId", in="query", required=true, @OA\Schema(type="integer"), example=3870177, description="Sales order ID to update"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="memo", type="string", description="Order memo/notes"),
     *             @OA\Property(property="tranDate", type="string", format="date", description="Transaction date (YYYY-MM-DD)"),
     *             @OA\Property(property="dueDate", type="string", format="date", description="Due date (YYYY-MM-DD)"),
     *             @OA\Property(property="shipDate", type="string", format="date", description="Ship date (YYYY-MM-DD)"),
     *             @OA\Property(property="otherRefNum", type="string", description="External reference number"),
     *             @OA\Property(property="terms", type="integer", description="Payment terms ID"),
     *             @OA\Property(property="salesRep", type="integer", description="Sales representative ID"),
     *             @OA\Property(property="department", type="integer", description="Department ID"),
     *             @OA\Property(property="classId", type="integer", description="Class ID"),
     *             @OA\Property(property="location", type="integer", description="Location ID"),
     *             @OA\Property(property="salesType", type="integer", description="Sales type ID"),
     *             @OA\Property(property="channel", type="integer", description="Sales channel ID"),
     *             @OA\Property(property="region", type="integer", description="Region ID"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 required={"line"},
     *                 @OA\Property(property="line", type="integer", description="Line number to update"),
     *                 @OA\Property(property="quantity", type="integer", description="Quantity"),
     *                 @OA\Property(property="rate", type="number", description="Unit price"),
     *                 @OA\Property(property="amount", type="number", description="Line total amount"),
     *                 @OA\Property(property="description", type="string", description="Line item description"),
     *                 @OA\Property(property="taxCode", type="integer", description="Tax code ID"),
     *                 @OA\Property(property="location", type="integer", description="Item location ID")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sales order updated successfully"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Sales order not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
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
     * @OA\Post(
     *     path="/get/shipping-addresses",
     *     tags={"Customers"},
     *     summary="Get Shipping Addresses",
     *     description="Retrieve a paginated list of shipping addresses with optional filters",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"company_id","environment"},
     *             @OA\Property(property="company_id", type="integer", description="Company identifier"),
     *             @OA\Property(property="environment", type="string", enum={"sandbox","production"}, description="Environment type"),
     *             @OA\Property(property="page", type="integer", default=1, description="Page number for pagination"),
     *             @OA\Property(property="pageSize", type="integer", default=50, description="Number of records per page (max 1000)"),
     *             @OA\Property(property="customerId", type="string", description="Filter by customer internal ID"),
     *             @OA\Property(property="country", type="string", description="Filter by country code (e.g., KE, US)"),
     *             @OA\Property(property="city", type="string", description="Filter by city name (contains)"),
     *             @OA\Property(property="defaultShipping", type="boolean", description="Filter by default shipping addresses only")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
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
