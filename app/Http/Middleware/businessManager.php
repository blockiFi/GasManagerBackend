<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\Location;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class businessManager
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $business = Business::find($request->business_id);

        if ($business && ((string) $business->owner_id === (string) $user->id)) {
            return $next($request);
        }

        $location = $request->filled('location_id')
            ? Location::find($request->location_id)
            : null;

        if ($location && ((string) $location->manager_id === (string) $user->id)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'User Not Permitted',
            'code' => 403,
            'errors' => ['You do not have access to manage this business or location.'],
        ], 403);
    }
}
