<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    /**
     * Let admins and agents into the dashboard; send visitors to their saved homes.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isStaff()) {
            return redirect()->route('saved');
        }

        if ($user?->isAgent() && $user->agent === null) {
            abort(Response::HTTP_FORBIDDEN, 'Your agent profile is missing. Please ask the office to set it up.');
        }

        if ($user?->isAgent() && ! $user->agent->is_active) {
            abort(Response::HTTP_FORBIDDEN, 'Your agent account is turned off. Please contact the office.');
        }

        return $next($request);
    }
}
