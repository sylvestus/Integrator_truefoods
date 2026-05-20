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
}
