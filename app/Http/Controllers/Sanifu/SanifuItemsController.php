<?php

namespace App\Http\Controllers\Sanifu;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NetsuiteConnectorController;
use App\Models\CompanyMaster;
use Illuminate\Http\Request;

class SanifuItemsController extends Controller
{
    public $netsuite_connector;

    public function __construct()
    {
        $netsuite_connector = new NetsuiteConnectorController();
        $this->netsuite_connector = $netsuite_connector;
    }

    /**
     * Get inventory items from NetSuite using ss_rl_get_inventory_items RESTlet
     *
     * @OA\Post(
     *     path="/api/truefoods-sanifu/get/items",
     *     tags={"Items"},
     *     summary="Get Items",
     *     description="Retrieve a paginated list of items with optional filters",
     *     @OA\Parameter(name="company_id", in="query", required=true, @OA\Schema(type="integer"), example=6, description="(mandatory) Company identifier"),
     *     @OA\Parameter(name="environment", in="query", required=true, @OA\Schema(type="string", enum={"sandbox","production"}), example="sandbox", description="(mandatory) Environment type"),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1), example=1, description="Page number for pagination"),
     *     @OA\Parameter(name="pageSize", in="query", required=false, @OA\Schema(type="integer", default=50), example=50, description="Number of items per page"),
     *     @OA\Parameter(name="itemId", in="query", required=false, @OA\Schema(type="string"), description="Filter by specific item ID"),
     *     @OA\Parameter(name="displayName", in="query", required=false, @OA\Schema(type="string"), description="Filter by item display name"),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string"), description="Filter by item category"),
     *     @OA\Parameter(name="isInactive", in="query", required=false, @OA\Schema(type="boolean", default=false), description="Include inactive items"),
     *     @OA\Parameter(name="includeLocations", in="query", required=false, @OA\Schema(type="boolean", default=false), description="Include location data in response"),
     *     @OA\Parameter(name="includePricing", in="query", required=false, @OA\Schema(type="boolean", default=false), description="Include pricing data in response"),
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function getItems(Request $request)
    {
        try {
            // Get request parameters
            $company_id = $request->company_id;
            $environment = $request->environment;

            // Optional pagination and filter parameters
            $page = $request->page ?? 1;
            $pageSize = $request->pageSize ?? 50;
            $itemId = $request->itemId ?? '';
            $displayName = $request->displayName ?? '';
            $category = $request->category ?? '';
            $isInactive = $request->isInactive ?? '';
            $includeLocations = $request->includeLocations ?? false;
            $includePricing = $request->includePricing ?? false;

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
                . "?script=customscript_ss_rl_get_inventory_items"
                . "&deploy=customdeploy_ss_rl_get_inventory_items"
                . "&page=" . $page
                . "&pageSize=" . $pageSize;

            // Add optional filters to URL
            if (!empty($itemId)) {
                $url .= "&itemId=" . urlencode($itemId);
            }
            if (!empty($displayName)) {
                $url .= "&displayName=" . urlencode($displayName);
            }
            if (!empty($category)) {
                $url .= "&category=" . urlencode($category);
            }
            if (!empty($isInactive)) {
                $url .= "&isInactive=" . ($isInactive ? 'true' : 'false');
            }
            if ($includeLocations) {
                $url .= "&includeLocations=true";
            }
            if ($includePricing) {
                $url .= "&includePricing=true";
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
     * Get UOM conversion data from NetSuite using ss_rl_uom_conversion RESTlet
     *
     * @OA\Post(
     *     path="/api/truefoods-sanifu/get/uom-conversion",
     *     tags={"Items"},
     *     summary="Get UOM Conversion",
     *     description="Get unit of measure conversion data. No parameters: Get all unit types. unitTypeId: Get all units for a specific unit type. unitTypeId + unitName: Get conversion rate for a specific unit. itemId: Get units from an item's unit type.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"company_id","environment"},
     *             @OA\Property(property="company_id", type="integer", description="(mandatory) Company identifier"),
     *             @OA\Property(property="environment", type="string", enum={"sandbox","production"}, description="(mandatory) Environment type"),
     *             @OA\Property(property="unitTypeId", type="string", description="Unit type internal ID"),
     *             @OA\Property(property="unitName", type="string", description="Specific unit name to get conversion rate for"),
     *             @OA\Property(property="itemId", type="string", description="Item internal ID to get its unit type")
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
    public function getUomConversion(Request $request)
    {
        try {
            // Get request parameters
            $company_id = $request->company_id;
            $environment = $request->environment;

            // Optional filter parameters
            $unitTypeId = $request->unitTypeId ?? '';
            $unitName = $request->unitName ?? '';
            $itemId = $request->itemId ?? '';

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
                . "?script=customscript_ss_rl_uom_conversion"
                . "&deploy=customdeploy_ss_rl_uom_conversion";

            // Add optional filters to URL
            if (!empty($unitTypeId)) {
                $url .= "&unitTypeId=" . urlencode($unitTypeId);
            }
            if (!empty($unitName)) {
                $url .= "&unitName=" . urlencode($unitName);
            }
            if (!empty($itemId)) {
                $url .= "&itemId=" . urlencode($itemId);
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
