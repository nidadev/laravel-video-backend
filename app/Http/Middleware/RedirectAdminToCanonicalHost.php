<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectAdminToCanonicalHost
{
    public function handle(Request $request, Closure $next)
    {
        $canonicalHost = 'admin.bitdrama.io';

        if (
            ($request->is('admin') || $request->is('admin/*')) &&
            $request->getHost() !== $canonicalHost
        ) {
            return redirect()->away('https://' . $canonicalHost . $request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
