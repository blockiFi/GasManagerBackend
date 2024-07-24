<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Validator;
use Str;
use DB;
use Auth;
class AuthController extends Controller
{
    //
    public function Register(Request $request){
        $validator = Validator::make($request->all(), [
            "name" =>  "required",
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|'
      ]);

      if ($validator->fails()) {

           
            $response['code'] = 400;
            $response['errors'] = $validator->messages()->all();
            return response()->json($response ,400);
      }
      $user = new User;
      $recoverykey = Str::random(40);
      $user->remember_token = $recoverykey;
      $user->name = $request->name;
      $user->email = $request->email;
      $user->password = bcrypt($request->password);
       $user->save();
       $response['code'] = 200;
       $response['message'] = "User Registered Sucessfully!!!";
        return response()->json($response ,200); 
    }

    public function Login(Request $request) 
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users',
            'password' => 'required'
      ]); 
      if ($validator->fails()) {
        $response['code'] = 401;
        $response['errors'] = $validator->messages()->all();
        return response()->json($response ,401);
        }
        
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){
            $user = Auth::user();
            $res['token'] = $user->createToken(name : 'gasManager')->accessToken;
            $res['user'] = $user;
            $res['message'] = "Login Successful";
            $res['code'] = 200;
            return response()->json($res ,200);  
        }else{
            
          $res['errors'] = ["Invalid Password"];
            $res['code'] = 401;
            return response()->json($res ,401);  
        }
       
        }

        public function AuthError(){
            $response['error'] = "User Not Permitted";
            $response['code']  = "401";
            return response()->json($response ,401);  
        }
        
    }

