<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\HTTP\Controllers\AuthController;
use Auth;
use App\Models\Business;
class BusinessOwner
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        
        $business = Business::find($request->business_id);
        if($business && ( $business->owner_id == (string)Auth::user()->id) ){
            return $next($request);
            
        }
        else{
            return redirect()->action([AuthController::class, 'AuthError']);
        }
    }
}
