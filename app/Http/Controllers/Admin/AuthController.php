<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Admin;
use App\Models\Subscription;
use PragmaRX\Google2FA\Google2FA;


class AuthController extends Controller
{

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


    if (!Auth::guard('admin')->attempt($credentials)) {

        return back()->withErrors([
            'email' => 'Invalid credentials'
        ]);
    }


    $admin = Auth::guard('admin')->user();


    $request->session()->regenerate();


    // Secret exists but 2FA is not confirmed
    if (!$admin->two_factor_enabled && $admin->google2fa_secret) {

        return redirect()
            ->route('admin.2fa.setup');
    }


    // 2FA enabled - require OTP
    if ($admin->two_factor_enabled) {

        session([
            '2fa:id' => $admin->id
        ]);


        Auth::guard('admin')->logout();


        return redirect()
            ->route('admin.2fa.verify');
    }


    return redirect()
        ->route('admin.dashboard');
}



    public function logout(Request $request)
    {

        Auth::guard('admin')->logout();


        $request->session()->invalidate();

        $request->session()->regenerateToken();



        return redirect()
            ->route('admin.login');
    }





    public function dashboard()
    {

        $videoCount = \App\Models\Video::count();

        $totalUsers = User::count();

        $bannedUsers = User::where('status','banned')->count();

        $activeSubscriptions = Subscription::where('status','active')->count();



        return view('admin.dashboard', compact(
            'videoCount',
            'totalUsers',
            'bannedUsers',
            'activeSubscriptions'
        ));

    }





    /*
    |--------------------------------------------------------------------------
    | 2FA SETUP
    |--------------------------------------------------------------------------
    */


    public function setup2FA()
{
    $admin = Auth::guard('admin')->user();

    $google2fa = new Google2FA();

    // Generate only if no secret exists
    if (!$admin->google2fa_secret) {

        $secret = $google2fa->generateSecretKey();

        $admin->update([
            'google2fa_secret' => $secret
        ]);

    } else {

        $secret = $admin->google2fa_secret;
    }


    $qrCodeUrl = $google2fa->getQRCodeUrl(
        config('app.name'),
        $admin->email,
        $secret
    );


    return view('admin.2fa.setup', [
        'qrCodeUrl' => $qrCodeUrl,
        'secret' => $secret
    ]);
}




   public function enable2FA(Request $request)
{
    $request->validate([
        'code'=>'required'
    ]);


    $admin = Auth::guard('admin')->user();


    $google2fa = new Google2FA();


    $valid = $google2fa->verifyKey(
        $admin->google2fa_secret,
        $request->code
    );


    if(!$valid){

        return back()->withErrors([
            'code'=>'Invalid authentication code'
        ]);

    }


    $admin->update([
        'two_factor_enabled'=>true
    ]);


    return redirect()
        ->route('admin.dashboard')
        ->with(
            'success',
            '2FA enabled successfully'
        );
}





    /*
    |--------------------------------------------------------------------------
    | 2FA VERIFY LOGIN
    |--------------------------------------------------------------------------
    */


    public function show2FA()
    {
        return view('admin.2fa.verify');
    }





    public function verify2FA(Request $request)
    {

        $request->validate([
            'code'=>'required'
        ]);



        $adminId = session('2fa:id');



        if(!$adminId){

            return redirect()
                ->route('admin.login');

        }



        $admin = Admin::findOrFail($adminId);




        $google2fa = new Google2FA();



        $valid = $google2fa->verifyKey(
            $admin->google2fa_secret,
            $request->code
        );




        if(!$valid){

            return back()->withErrors([
                'code'=>'Invalid OTP'
            ]);

        }





        Auth::guard('admin')->login($admin);



        session()->forget('2fa:id');




        return redirect()
            ->route('admin.dashboard');

    }

}