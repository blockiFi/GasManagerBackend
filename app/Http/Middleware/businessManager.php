<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Location;
use App\Models\Business;
use Auth;


class businessManager
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
       
        $business = Auth::user()->Business()->with('Business')->first();
        
        $Location = Location::find($request->location_id);
        $business = Business::find($request->business_id);
        if($business && ( $business->owner_id == (string)Auth::user()->id) ){
            return $next($request);
            
        }
        else if($Location->manager_id == (string)Auth::user()->id) {
            return $next($request);
        }
        else{
            return redirect()->action([AuthController::class, 'AuthError']);
        }
    }
}
