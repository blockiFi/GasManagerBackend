<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
        if ($business && ((string) $business->owner_id === (string) $request->user()->id)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'User Not Permitted',
            'code' => 403,
            'errors' => ['You must be the business owner for this action.'],
        ], 403);
    }
}
