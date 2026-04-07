<?php

namespace App\Http\Controllers;

use App\Models\CompanyMaster;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class PassportAuthController extends Controller
{
    public function register(Request $request)
    {
      try{
          $this->validate($request, [
              'company_id' => 'required',
              'name' => 'required|min:4',
              'email' => 'required|email',
              'password' => 'required|min:8',
          ]);


          $user = User::create([
              'company_id'=>$request->company_id,
              'name' => $request->name,
              'email' => $request->email,
              'password' => bcrypt($request->password),
              'role_id' => $request->role_id ?? 1
          ]);


          $token = $user->createToken('LaravelAuthApp')->accessToken;

          return response()->json(['token' => $token], 200);
      }catch (Exception $ex){
          return response()->json(['error' => $ex->getMessage()], 400);
      }

    }

    /**
     * Login
     *
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Authentication"},
     *     summary="User Login",
     *     description="Authenticate user and get access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", description="(mandatory) User email address", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", description="(mandatory) User password", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful"),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $data = [
            'email' => $request->email,
            'password' => $request->password
        ];

        if (auth()->attempt($data)) {
            $token = auth()->user()->createToken('LaravelAuthApp')->accessToken;
            $company_id = auth()->user()->company_id;
            $company_details  = CompanyMaster::where('id',$company_id)->first();

            $name = auth()->user()->name;
            $id = auth()->user()->id;
            $data =['company_id'=>$company_details->id,'company_name'=>$company_details->company_name,'user_id'=>$id,'user'=>$name,'token'=>$token];
            return response()->json(['data' => $data], 200);
        } else {
            return response()->json(['statusCode'=>401,'message' => 'invalid login credentials'], 401);
        }
    }
}
