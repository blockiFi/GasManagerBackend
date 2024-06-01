<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Validator;
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
            return response()->json($response ,200);
      }
      $user = new User;
      $recoverykey = Str::random(40);
      $user->remember_token = $recoverykey;
      $user->name = $request->name;
      $user->email = $request->email;
      $user->role ='user';
      $user->password = bcrypt($request->password);
       $user->save();
       $response['code'] = 200;
        return response()->json($response ,200); 
    }
}
