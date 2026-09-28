<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->must_change_password
            && ! $request->routeIs('profile.*', 'password.update', 'logout')
        ) {
            return redirect()
                ->route('profile.edit')
                ->with('warning', 'Vous devez définir un nouveau mot de passe avant de continuer.');
        }

        return $next($request);
    }
}
