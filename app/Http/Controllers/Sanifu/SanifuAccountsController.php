<?php

namespace App\Http\Controllers\Sanifu;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NetsuiteConnectorController;
use App\Models\CompanyMaster;
use Illuminate\Http\Request;

class SanifuAccountsController extends Controller
{
    public $netsuite_connector;

    public function __construct()
    {
        $netsuite_connector = new NetsuiteConnectorController();
        $this->netsuite_connector = $netsuite_connector;
    }

    /**
     * Get GL accounts from NetSuite using vw_rl_get_accounts RESTlet.
     * Defaults to the expense-type accounts used for vendor services.
     *
     * @OA\Post(
     *     path="/api/truefoods-sanifu/get/accounts",
     *     tags={"Accounts"},
     *     summary="Get Accounts",
     *     description="Retrieve a paginated list of GL accounts. By default only active, non-summary expense-type accounts (Expense, OthExpense, COGS) used for vendor services are returned",
     *     @OA\Parameter(name="company_id", in="query", required=true, @OA\Schema(type="integer"), example=6, description="(mandatory) Company identifier"),
     *     @OA\Parameter(name="environment", in="query", required=true, @OA\Schema(type="string", enum={"sandbox","production"}), example="sandbox", description="(mandatory) Environment type"),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1), example=1, description="Page number for pagination"),
     *     @OA\Parameter(name="pageSize", in="query", required=false, @OA\Schema(type="integer", default=50), example=50, description="Number of records per page (max 1000)"),
     *     @OA\Parameter(name="accountId", in="query", required=false, @OA\Schema(type="integer"), description="Filter by specific account internal ID"),
     *     @OA\Parameter(name="number", in="query", required=false, @OA\Schema(type="string"), description="Filter by account number (starts with)"),
     *     @OA\Parameter(name="name", in="query", required=false, @OA\Schema(type="string"), description="Filter by account name or full name (contains)"),
     *     @OA\Parameter(name="accountType", in="query", required=false, @OA\Schema(type="string", default="Expense,OthExpense,COGS"), example="Expense,OthExpense,COGS", description="Comma-separated account type codes (e.g. Expense, OthExpense, COGS, DeferExpense, OthCurrAsset, FixedAsset)"),
     *     @OA\Parameter(name="allTypes", in="query", required=false, @OA\Schema(type="boolean", default=false), description="Return accounts of every type (overrides accountType)"),
     *     @OA\Parameter(name="subsidiary", in="query", required=false, @OA\Schema(type="integer"), description="Filter by subsidiary internal ID (accounts available to that subsidiary)"),
     *     @OA\Parameter(name="parent", in="query", required=false, @OA\Schema(type="integer"), description="Filter by parent account internal ID"),
     *     @OA\Parameter(name="includeSummary", in="query", required=false, @OA\Schema(type="boolean", default=false), description="Also return summary (non-posting header) accounts"),
     *     @OA\Parameter(name="isInactive", in="query", required=false, @OA\Schema(type="boolean", default=false), description="true returns only inactive accounts, false (default) only active ones"),
     *     @OA\Parameter(name="includeInactive", in="query", required=false, @OA\Schema(type="boolean", default=false), description="Return both active and inactive accounts (overrides isInactive)"),
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function getAccounts(Request $request)
    {
        try {
            // Get request parameters
            $company_id = $request->company_id;
            $environment = $request->environment;

            // Optional pagination parameters
            $page = $request->page ?? 1;
            $pageSize = $request->pageSize ?? 50;

            // Optional filter parameters
            $accountId = $request->accountId ?? '';
            $number = $request->number ?? '';
            $name = $request->name ?? '';
            $accountType = $request->accountType ?? '';
            $allTypes = $request->allTypes ?? false;
            $subsidiary = $request->subsidiary ?? '';
            $parent = $request->parent ?? '';
            $includeSummary = $request->includeSummary ?? false;
            $isInactive = $request->isInactive ?? '';
            $includeInactive = $request->includeInactive ?? false;

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
                . "?script=customscript_vw_rl_get_accounts"
                . "&deploy=customdeploy_vw_rl_get_accounts"
                . "&page=" . $page
                . "&pageSize=" . $pageSize;

            // Add optional filters to URL
            if (!empty($accountId)) {
                $url .= "&accountId=" . urlencode($accountId);
            }
            if (!empty($number)) {
                $url .= "&number=" . urlencode($number);
            }
            if (!empty($name)) {
                $url .= "&name=" . urlencode($name);
            }
            if (!empty($accountType)) {
                $url .= "&accountType=" . urlencode(is_array($accountType) ? implode(',', $accountType) : $accountType);
            }
            if ($allTypes === true || $allTypes === 'true') {
                $url .= "&allTypes=true";
            }
            if (!empty($subsidiary)) {
                $url .= "&subsidiary=" . urlencode($subsidiary);
            }
            if (!empty($parent)) {
                $url .= "&parent=" . urlencode($parent);
            }
            if ($includeSummary === true || $includeSummary === 'true') {
                $url .= "&includeSummary=true";
            }
            if ($isInactive === true || $isInactive === 'true') {
                $url .= "&isInactive=true";
            }
            if ($includeInactive === true || $includeInactive === 'true') {
                $url .= "&includeInactive=true";
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
