<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('marketplace_admin')) {
            return redirect()->route('admin.login')->with('error', 'Please sign in to continue.');
        }

        return $next($request);
    }
}
