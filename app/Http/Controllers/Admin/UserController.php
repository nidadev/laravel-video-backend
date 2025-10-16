<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Plan;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['subscriptions.plan'])->paginate(15);
        return view('admin.users.index', compact('users'));
    }

    public function ban($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'banned']);

        return back()->with('success', 'User has been banned.');
    }

    public function unban($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'active']);

        return back()->with('success', 'User has been unbanned.');
    }

    public function upgrade($id, Request $request)
    {
        $user = User::findOrFail($id);
        $plan = Plan::findOrFail($request->plan_id);

        Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => now()->addDays($plan->duration_days),
                'status' => 'active'
            ]
        );

        return back()->with('success', 'User upgraded to ' . $plan->name);
    }
}