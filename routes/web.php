<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('company-master',[\App\Http\Controllers\CompanyMasterController::class, 'create'])->name('company_master.create');
Route::post('company-master/store',[\App\Http\Controllers\CompanyMasterController::class, 'store'])->name('company_master.store');
Route::get('company-master/get-all',[\App\Http\Controllers\CompanyMasterController::class, 'getAllCompany'])->name('company_master.get.all');

Route::get('redirected', function (){
    return response()->json(['statusCode'=>401,'message'=>'Kindly provide valid token to proceed']);
})->name('redirect');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
