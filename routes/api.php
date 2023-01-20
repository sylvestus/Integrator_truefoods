<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use  App\Http\Controllers\PassportAuthController;
use  App\Http\Controllers\PostController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::post('register', [PassportAuthController::class, 'register']);
Route::post('login', [PassportAuthController::class, 'login']);
Route::middleware('auth:api')->group(function () {
    Route::post('sales_order', PostController::class);
    Route::get('company-master/get-all',[\App\Http\Controllers\CompanyMasterController::class, 'getAllCompany'])->name('company_master.get.all');

    //stock items
    Route::post('get_stock',[\App\Http\Controllers\ItemsController::class, 'getInventoryItems']);

    //Order
    Route::post('search/salesOrder',[\App\Http\Controllers\SalesOrderController::class, 'searchSalesOrder']);
    Route::post('post/salesOrder',[\App\Http\Controllers\SalesOrderController::class, 'postSalesOrder']);

    //invoices
    Route::post('search/invoices',[\App\Http\Controllers\SalesOrderController::class, 'searchInvoices']);
    Route::post('get/invoices',[\App\Http\Controllers\InvoiceController::class, 'getInvoices']);

    //credit notes
    Route::post('search/credit-note',[\App\Http\Controllers\CreditNoteController::class, 'searchCreditNotes']);
    Route::post('get/credit-note',[\App\Http\Controllers\CreditNoteController::class, 'getCreditNotes']);
});
