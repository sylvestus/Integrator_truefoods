<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use Illuminate\Http\Request;

class CompanyMasterController extends Controller
{
    public function __invoke(Request $request)
    {
        //
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
       return view('company_create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $company_master  = new CompanyMaster();
        $company_master->company_name  = $request->company_name;
        $company_master->company_email  = $request->company_email;
        $company_master->account_number  = $request->account_number;
        $company_master->consumerKey  = isset($request->consumerKey) ? $request->consumerKey : NULL;
        $company_master->tokenId  = isset($request->tokenId) ? $request->tokenId : NULL;
        $company_master->consumerSecret  = isset($request->consumerSecret) ? $request->consumerSecret : NULL;
        $company_master->tokenSecret  = isset($request->tokenSecret) ? $request->tokenSecret : NULL;
        $company_master->staging_consumerKey  = isset($request->staging_consumerKey) ? $request->staging_consumerKey : NULL;
        $company_master->staging_tokenId  = isset($request->staging_tokenId) ? $request->staging_tokenId : NULL;
        $company_master->staging_consumerSecret  = isset($request->staging_consumerSecret) ? $request->staging_consumerSecret : NULL;
        $company_master->staging_tokenSecret  = isset($request->staging_tokenSecret) ? $request->staging_tokenSecret : NULL;

        $company_master->save();

        return redirect()->route('company_master.create');


    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }



    public function getAllCompany(){
        $companies  = CompanyMaster::all();
        return response()->json(['statusCode'=>200,'company_data'=>$companies]);
    }
}
