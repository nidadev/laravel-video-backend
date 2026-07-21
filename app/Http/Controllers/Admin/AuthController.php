<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Subscription;


class AuthController extends Controller
{
    //
     public function showLoginForm()
    {
        if (Auth::guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }
        return view('admin.login');
    }

    public function login(Request $request)
{
    $credentials = $request->only('email', 'password');

    if (Auth::guard('admin')->attempt($credentials)) {

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    return back()->withErrors([
        'email' => 'Invalid credentials'
    ]);
}

   public function logout(Request $request)
{
    Auth::guard('admin')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('admin.login');
}

    public function dashboard()
    {
        //dd(Auth::guard('admin')->user());
        $videoCount = \App\Models\Video::count();
         $totalUsers = User::count();
        //dd($totalUsers);
$bannedUsers = User::where('status', 'banned')->count();
$activeSubscriptions = Subscription::where('status', 'active')->count();
        return view('admin.dashboard', compact('videoCount','totalUsers', 'bannedUsers', 'activeSubscriptions'));
 
    }
}
