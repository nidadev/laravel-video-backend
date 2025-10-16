<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->status === 'banned') {
            Auth::logout();

            return response()->json(['message' => 'Your account has been disabled.'], 403);
            // or redirect()->route('banned.notice');
        }

        return $next($request);
    }
}
