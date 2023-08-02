<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CallbackController extends Controller
{
    public function handleCallback(Request $request)
    {
        // You can process the data received in the callback here
        $data = $request->all();

        $handler = fopen('response_received.txt','a');
        fwrite($handler,json_encode($data));
        fclose($handler);
        // You can perform any necessary processing on $data
        // Send a response back
        return response()->json(['message' => 'Callback received successfully']);
    }
}
