<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestApiMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session('api_token')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
