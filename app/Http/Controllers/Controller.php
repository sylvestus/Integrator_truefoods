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
 *     url="https://netsuiteintegrator.dynamicsserv.com:4140",
 *     description="Production Server"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter the token you received from the login endpoint"
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
