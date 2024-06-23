<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\HTTP\Controllers\AuthController;
use Auth;
class BusinessOwner
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $business = Auth::user()->Business()->with('Business')->first();
        if($business && ($business['business_id']== $request->business_id)){
            return $next($request);
        }
        else{
            return redirect()->action([AuthController::class, 'AuthError']);
        }
    }
}
