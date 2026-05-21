<?php

namespace App\Http\Controllers\Sanifu;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NetsuiteConnectorController;
use App\Models\CompanyMaster;
use Illuminate\Http\Request;

class SanifuEstimatesController extends Controller
{
    public $netsuite_connector;

    public function __construct()
    {
        $netsuite_connector = new NetsuiteConnectorController();
        $this->netsuite_connector = $netsuite_connector;
    }

    /**
     * Get estimates from NetSuite using ss_rl_get_estimates RESTlet
     *
     * @OA\Post(
     *     path="/api/truefoods-sanifu/get/estimates",
     *     tags={"Estimates"},
     *     summary="Get Estimates",
     *     description="Retrieve a paginated list of estimates with optional filters",
     *     @OA\Parameter(name="company_id", in="query", required=true, @OA\Schema(type="integer"), example=6, description="(mandatory) Company identifier"),
     *     @OA\Parameter(name="environment", in="query", required=true, @OA\Schema(type="string", enum={"sandbox","production"}), example="sandbox", description="(mandatory) Environment type"),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1), example=1, description="Page number for pagination"),
     *     @OA\Parameter(name="pageSize", in="query", required=false, @OA\Schema(type="integer", default=50), example=50, description="Number of records per page"),
     *     @OA\Parameter(name="estimateId", in="query", required=false, @OA\Schema(type="integer"), description="Filter by specific estimate internal ID"),
     *     @OA\Parameter(name="tranId", in="query", required=false, @OA\Schema(type="string"), description="Filter by estimate document number"),
     *     @OA\Parameter(name="customerId", in="query", required=false, @OA\Schema(type="integer"), description="Filter by customer internal ID"),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string"), description="Filter by estimate status (e.g. open, voided, expired)"),
     *     @OA\Parameter(name="dateFrom", in="query", required=false, @OA\Schema(type="string"), example="01/03/2026", description="Start date filter (DD/MM/YYYY format)"),
     *     @OA\Parameter(name="dateTo", in="query", required=false, @OA\Schema(type="string"), example="30/03/2026", description="End date filter (DD/MM/YYYY format)"),
     *     @OA\Parameter(name="subsidiary", in="query", required=false, @OA\Schema(type="integer"), description="Filter by subsidiary internal ID"),
     *     @OA\Parameter(name="department", in="query", required=false, @OA\Schema(type="integer"), description="Filter by department internal ID"),
     *     @OA\Parameter(name="location", in="query", required=false, @OA\Schema(type="integer"), description="Filter by location internal ID"),
     *     @OA\Parameter(name="salesRep", in="query", required=false, @OA\Schema(type="integer"), description="Filter by sales rep internal ID"),
     *     @OA\Parameter(name="minAmount", in="query", required=false, @OA\Schema(type="number"), description="Minimum estimate total amount"),
     *     @OA\Parameter(name="maxAmount", in="query", required=false, @OA\Schema(type="number"), description="Maximum estimate total amount"),
     *     @OA\Parameter(name="includeLineItems", in="query", required=false, @OA\Schema(type="boolean", default=true), description="Include line items in the response"),
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function getEstimates(Request $request)
    {
        try {
            // Get request parameters
            $company_id = $request->company_id;
            $environment = $request->environment;

            // Optional pagination parameters
            $page = $request->page ?? 1;
            $pageSize = $request->pageSize ?? 50;

            // Optional filter parameters
            $estimateId = $request->estimateId ?? '';
            $tranId = $request->tranId ?? '';
            $customerId = $request->customerId ?? '';
            $status = $request->status ?? '';
            $dateFrom = $request->dateFrom ?? '';
            $dateTo = $request->dateTo ?? '';
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
                . "?script=customscript_vw_rl_get_estimates"
                . "&deploy=customdeploy_vw_rl_get_estimates"
                . "&page=" . $page
                . "&pageSize=" . $pageSize;

            // Add optional filters to URL
            if (!empty($estimateId)) {
                $url .= "&estimateId=" . urlencode($estimateId);
            }
            if (!empty($tranId)) {
                $url .= "&tranId=" . urlencode($tranId);
            }
            if (!empty($customerId)) {
                $url .= "&customerId=" . urlencode($customerId);
            }
            if (!empty($status)) {
                $url .= "&status=" . urlencode($status);
            }
            if (!empty($dateFrom)) {
                $url .= "&dateFrom=" . urlencode($dateFrom);
            }
            if (!empty($dateTo)) {
                $url .= "&dateTo=" . urlencode($dateTo);
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
     * Create an estimate in NetSuite using vw_rl_create_estimate RESTlet
     *
     * @OA\Post(
     *     path="/api/truefoods-sanifu/create/estimate",
     *     tags={"Estimates"},
     *     summary="Create Estimate",
     *     description="Create a new estimate (quote)",
     *     @OA\Parameter(name="company_id", in="query", required=true, @OA\Schema(type="integer"), example=6, description="(mandatory) Company identifier"),
     *     @OA\Parameter(name="environment", in="query", required=true, @OA\Schema(type="string", enum={"sandbox","production"}), example="sandbox", description="(mandatory) Environment type"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"company_id","environment","customerId","tranDate","department","location","items"},
     *             @OA\Property(property="company_id", type="integer", description="(mandatory) Company identifier"),
     *             @OA\Property(property="environment", type="string", enum={"sandbox","production"}, description="(mandatory) Environment type"),
     *             @OA\Property(property="customerId", type="integer", description="(mandatory) Customer ID for the estimate"),
     *             @OA\Property(property="entityStatus", type="integer", description="Estimate status internal ID (e.g. open, voided, expired)"),
     *             @OA\Property(property="memo", type="string", description="Estimate memo/notes"),
     *             @OA\Property(property="tranDate", type="string", format="date", description="(mandatory) Transaction date (YYYY-MM-DD)"),
     *             @OA\Property(property="expectedCloseDate", type="string", format="date", description="Expected close date (YYYY-MM-DD)"),
     *             @OA\Property(property="dueDate", type="string", format="date", description="Due date (YYYY-MM-DD)"),
     *             @OA\Property(property="shipTo", type="integer", description="Internal ID of an address from the customer's address book (maps to NetSuite shipaddresslist)"),
     *             @OA\Property(property="shipDate", type="string", format="date", description="Ship date (YYYY-MM-DD)"),
     *             @OA\Property(property="otherRefNum", type="string", description="External reference number (e.g., PO number)"),
     *             @OA\Property(property="terms", type="integer", description="Payment terms ID"),
     *             @OA\Property(property="salesRep", type="integer", description="Sales representative ID"),
     *             @OA\Property(property="department", type="integer", description="(mandatory) Department ID"),
     *             @OA\Property(property="classId", type="integer", description="Class ID"),
     *             @OA\Property(property="location", type="integer", description="(mandatory) Location ID"),
     *             @OA\Property(property="subsidiary", type="integer", description="Subsidiary ID"),
     *             @OA\Property(property="currency", type="integer", description="Currency ID"),
     *             @OA\Property(property="salesType", type="integer", description="Sales type ID"),
     *             @OA\Property(property="channel", type="integer", description="Sales channel ID"),
     *             @OA\Property(property="region", type="integer", description="Region ID"),
     *             @OA\Property(property="widgetLink", type="string", description="Widget link URL"),
     *             @OA\Property(property="probability", type="number", description="Probability of close (0-100)"),
     *             @OA\Property(property="items", type="array", description="(mandatory) Line items for the estimate", @OA\Items(
     *                 required={"itemId","quantity","taxCode","location"},
     *                 @OA\Property(property="itemId", type="integer", description="(mandatory) Item ID"),
     *                 @OA\Property(property="quantity", type="integer", description="(mandatory) Quantity"),
     *                 @OA\Property(property="rate", type="number", description="Unit price"),
     *                 @OA\Property(property="amount", type="number", description="Line total amount"),
     *                 @OA\Property(property="description", type="string", description="Line item description"),
     *                 @OA\Property(property="taxCode", type="integer", description="(mandatory) Tax code ID"),
     *                 @OA\Property(property="location", type="integer", description="(mandatory) Item location ID")
     *             ))
     *         )
     *     ),
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Estimate created successfully"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function createEstimate(Request $request)
    {
        try {
            $company_id = $request->company_id;
            $environment = $request->environment;

            $company_data = CompanyMaster::where('id', $company_id)->first();

            if (!$company_data) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => 'Company not found'
                ]);
            }

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

            if ($environment == 'sandbox') {
                $account_number = $company_data->account_number . '-sb1';
            } else {
                $account_number = $company_data->account_number;
            }

            $url = "https://" . $account_number . ".restlets.api.netsuite.com/app/site/hosting/restlet.nl"
                . "?script=customscript_vw_rl_create_estimate"
                . "&deploy=customdeploy_vw_rl_create_estimate";

            $requestBody = [
                'customerId' => $request->customerId,
                'items' => $request->items
            ];

            // Header fields
            if ($request->has('entityStatus')) {
                $requestBody['entityStatus'] = $request->entityStatus;
            }
            if ($request->has('memo')) {
                $requestBody['memo'] = $request->memo;
            }
            if ($request->has('tranId')) {
                $requestBody['tranId'] = $request->tranId;
            }
            if ($request->has('tranDate')) {
                $requestBody['tranDate'] = $request->tranDate;
            }
            if ($request->has('expectedCloseDate')) {
                $requestBody['expectedCloseDate'] = $request->expectedCloseDate;
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
            if ($request->has('subsidiary')) {
                $requestBody['subsidiary'] = $request->subsidiary;
            }
            if ($request->has('currency')) {
                $requestBody['currency'] = $request->currency;
            }
            if ($request->has('shipMethod')) {
                $requestBody['shipMethod'] = $request->shipMethod;
            }
            if ($request->has('probability')) {
                $requestBody['probability'] = $request->probability;
            }

            // Ship-to: customer address book internal ID
            if ($request->has('shipTo')) {
                $requestBody['shipTo'] = $request->shipTo;
            }

            // Optional shipping address fields
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
            if ($request->has('widgetLink')) {
                $requestBody['widgetLink'] = $request->widgetLink;
            }

            $method = "POST";
            $data = json_encode($requestBody);

            $send_request = $this->netsuite_connector->callRestApi($url, $method, $data, $company_data, $environment);

            if ($send_request['statusCode'] != 200) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => '',
                    'error_message' => $send_request['message']
                ]);
            }

            $responseData = $send_request['message'];

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
}
