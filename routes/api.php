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


Route::post('test-connection', [\App\Http\Controllers\CallbackController::class, 'testConnection']);
Route::post('ibl-get-files', [\App\Http\Controllers\IBLEFTController::class, 'getFileFromNetsuite']);
Route::post('ibl-fetch-files', [\App\Http\Controllers\IBLEFTController::class, 'getFiles']);
Route::post('register-users', [PassportAuthController::class, 'register']);
Route::post('login', [PassportAuthController::class, 'login']);
Route::post('callback', [\App\Http\Controllers\CallbackController::class, 'handleCallback']);


Route::middleware('auth:api')->group(function () {

    // Truefoods - Sanifu API endpoints
    
    Route::post('truefoods-sanifu/get/items', [\App\Http\Controllers\Sanifu\SanifuItemsController::class, 'getItems']);
    Route::post('truefoods-sanifu/get/customers', [\App\Http\Controllers\Sanifu\SanifuCustomersController::class, 'getCustomers']);
    Route::post('truefoods-sanifu/get/customer-purchases', [\App\Http\Controllers\Sanifu\SanifuCustomersController::class, 'getCustomerPurchases']);
    Route::post('truefoods-sanifu/create/sales-order', [\App\Http\Controllers\Sanifu\SanifuCustomersController::class, 'createSalesOrder']);
    Route::post('truefoods-sanifu/update/sales-order', [\App\Http\Controllers\Sanifu\SanifuCustomersController::class, 'updateSalesOrder']);


    Route::post('sales_order', PostController::class);
    Route::get('company-master/getAll',[\App\Http\Controllers\CompanyMasterController::class, 'getAllCompany'])->name('company_master.get.ALl');

    //stock items
    Route::post('get_stock',[\App\Http\Controllers\ItemsController::class, 'getInventoryItems']);

    //Order
    Route::post('search/salesOrder',[\App\Http\Controllers\SalesOrderController::class, 'searchSalesOrder']);
    Route::post('post/salesOrders',[\App\Http\Controllers\SalesOrderController::class, 'postSalesOrder']);
    Route::post('post/transform_sales_order',[\App\Http\Controllers\SalesOrderController::class, 'transformSalesOrder']);

    //invoices
    Route::post('search/invoices',[\App\Http\Controllers\SalesOrderController::class, 'searchInvoices']);
    Route::post('get/invoices',[\App\Http\Controllers\InvoiceController::class, 'getInvoices']);
    Route::post('post/invoices',[\App\Http\Controllers\InvoiceController::class, 'postInvoicesnew']);
    Route::post('post/update_invoice_status',[\App\Http\Controllers\InvoiceController::class, 'updateInvoicePaymentStatus']);
    Route::post('post/update_sale_status',[\App\Http\Controllers\InvoiceController::class, 'updateAllSaleStatus']);

    //Cash Sales
    Route::post('post/cash_sale',[\App\Http\Controllers\CashSaleController::class, 'postCashSale']);


    //credit notes
    Route::post('search/credit-note',[\App\Http\Controllers\CreditNoteController::class, 'searchCreditNotes']);
    Route::post('get/credit-note',[\App\Http\Controllers\CreditNoteController::class, 'getCreditNotes']);

    //subsidiary info
    Route::post('get/subsidiary',[\App\Http\Controllers\SubsidiaryController::class, 'getSubsidiaryData']);
    //currency data
    Route::post('get/currency',[\App\Http\Controllers\CurrencyController::class, 'getCurrencies']);
    //location  data
    Route::post('get/location',[\App\Http\Controllers\LocationController::class, 'getLocation']);
    //employee  data
    Route::post('get/employee',[\App\Http\Controllers\EmployeeController::class, 'getEmployee']);
    //category  data
    Route::post('get/category',[\App\Http\Controllers\CategoriesGetController::class, 'getCategory']);
    //item  data
    Route::post('get/items',[\App\Http\Controllers\ItemGetController::class, 'getItem']);
    //tax code  data
    Route::post('get/taxCode',[\App\Http\Controllers\TaxCodeController::class, 'getTaxCode']);
    //UOM  data
    Route::post('get/uom',[\App\Http\Controllers\UomGetController::class, 'getUomData']);
    Route::post('get/unit_id',[\App\Http\Controllers\UomGetController::class, 'getUnit']);
    //UOM  Types
    Route::post('get/uom_type',[\App\Http\Controllers\UomTypesGetController::class, 'getUomTypes']);
    //Location Qty
    Route::post('get/location_qty',[\App\Http\Controllers\LocationQtyController::class, 'getLocationQty']);

    Route::post('get/location_qty-specific-items',[\App\Http\Controllers\LocationQtyController::class, 'getLocationQtySpecificItems']);
    //Customers  Data
    Route::post('get/customers',[\App\Http\Controllers\CustomerGetController::class, 'getCustomers']);
    Route::post('get/customer_class',[\App\Http\Controllers\CustomerGetController::class, 'getCustomerClass']);
    //post customer data
    Route::post('post/customer',[\App\Http\Controllers\CustomerController::class, 'postCustomers']);
    //get accounts data
    Route::post('get/accounts',[\App\Http\Controllers\AccountsController::class, 'getAccounts']);
    //get billers data
    Route::post('get/billers',[\App\Http\Controllers\BillersGetController::class, 'getBillers']);
    //get billers data
    Route::post('get/branch',[\App\Http\Controllers\BranchController::class, 'getBranch']);

    //Payments
    Route::post('post/payments',[\App\Http\Controllers\PaymentsController::class, 'postPayments']);

    //Drivers
    Route::post('get/drivers',[\App\Http\Controllers\DriversController::class, 'getDrivers']);

    //deliveries
    Route::post('post/delivery',[\App\Http\Controllers\DeliveryController::class, 'postDelivery']);

     //Returns
     Route::post('post/returns',[\App\Http\Controllers\ReturnsController::class, 'postReturns']);
   
    //by Vinnie for GLINK
     //Transfer Order, Item Fulfillment, Item Receipt
    Route::post('glink/post/transfer-order', [\App\Http\Controllers\TransferOrderController::class, 'postTransfer']);
    Route::post('glink/post/item-fulfillment', [\App\Http\Controllers\DeliveryController::class, 'postTransferDelivery']);
    Route::post('glink/post/item-receipt', [\App\Http\Controllers\ItemReceiptController::class, 'postItemReceipt']);

    //sarit city
    //invoices
    Route::post('sarit/post/invoices',[\App\Http\Controllers\SaritInvoiceController::class, 'postSaritInvoice']);
    //cash sales
    Route::post('sarit/post/cashsales',[\App\Http\Controllers\SaritICashSalesController::class, 'postSaritCashSale']);
    //Redemption
    Route::post('sarit/post/redemptions',[\App\Http\Controllers\SaritIRedemptionController::class, 'postSaritRedemptions']);
    //gift Voucher Redemption
    Route::post('sarit/post/gift-voucher-redemptions',[\App\Http\Controllers\SaritIGiftVoucherRedemptionController::class, 'postSaritGiftVoucherRedemptions']);

    //Sunculture
    Route::post('create/customer',[\App\Http\Controllers\SuncultureController::class, 'createSCCustomer']);
    Route::post('create/amt-customer-account',[\App\Http\Controllers\SuncultureController::class, 'createAMTCustomerAccount']);
    Route::post('get/regions',[\App\Http\Controllers\SuncultureController::class, 'getRegions']);
    Route::post('create/payment',[\App\Http\Controllers\SuncultureController::class, 'createPayment']);
    Route::post('get/payments',[\App\Http\Controllers\SuncultureController::class, 'getPayments']);
    Route::get('get/ns-accounts',[\App\Http\Controllers\SuncultureController::class, 'getNsAccount']);
    Route::post('create/credit-note',[\App\Http\Controllers\SuncultureController::class, 'createCreditNote']);
    Route::get('get/ns-items',[\App\Http\Controllers\SuncultureController::class, 'getItems']);
    Route::get('get/ns-locations',[\App\Http\Controllers\SuncultureController::class, 'getLocations']);
    Route::get('get/ns-item-location-qty',[\App\Http\Controllers\SuncultureController::class, 'getItemLocationQty']);

    //MONEy POINT
    //Journal Entry
    Route::post('moneypoint/post/journals',[\App\Http\Controllers\MoneyPointJournalEntryController::class, 'postMoneyPointJournalEntry']);

    //SANIFU
    //Get Inventory Items
    // Route::post('sanifu/get/items',[\App\Http\Controllers\Sanifu\SanifuItemsController::class, 'getItems']);

});
