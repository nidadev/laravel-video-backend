<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //get all users
    public function index()
    {
        $user = User::latest()->get();

        return response()->json(
            [
                'success' => true,
                'data' => $user

            ]
        );
    }

    //create user
    public function store(Request $request)
    {

    $request->validate(
        [
          'name' => 'required|string|max:255' ,
          'email' => 'required|email|unique:users,email',
          'password'=> 'required|min:6'
        ]
    );

    User::create(
        [
            'name' => $request->name,
            'email'=> $request->email,
            'password' => $request->password
        ]
    );
    return response()->json([
        'success'=> true,
        'message' => 'User created successfully'
    ]);

    }
    public function show($id)
    {
        $user = User::find($id);
        if(!$user)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ],404);
            }
    }
    public function update(Request $request,$id)
    {
        $user = User::find($id);
        if(!$user)
            {
                return response()->json(
                    [
                        'success'=> false,
                        'message' => 'user not found'
                    ]
                );
            }
            $request->validate(
        [
          'name' => 'required|string|max:255' ,
          'email' => 'required|email|unique:users,email',
          'password'=> 'required|min:6'
        ]
    );

    User::update([
        'name' => $request->name ?? $user->name
    ]);

    }
}
