<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="Truefoods Sanifu API",
 *     version="1.0.0",
 *     description="API for Truefoods Sanifu integration with NetSuite. This API provides endpoints for managing items, customers, customer purchases, and sales orders.",
 *     @OA\Contact(name="API Support")
 * )
 * @OA\Server(
 *     url="https://netsuiteintegrator.dynamicsserv.com:4140/api/truefoods-sanifu",
 *     description="Production Server"
 * )
 * @OA\Tag(name="Authentication", description="User authentication endpoints")
 * @OA\Tag(name="Items", description="Item management endpoints")
 * @OA\Tag(name="Customers", description="Customer management endpoints")
 * @OA\Tag(name="Sales Orders", description="Sales order management endpoints")
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
