<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;

class AdminController extends Controller
{
    //
    public function dashboard()
    {
        $videoCount = Video::count();
        return view('admin.dashboard', compact('videoCount'));
    }
}
