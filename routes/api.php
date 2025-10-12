<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\Auth\OtpController;

Route::get('/user', function (Request $request) {
    return $request->user();
});
//->middleware('auth:sanctum');


Route::post('/register',[AuthController::class,'register']);
Route::post('/login',[AuthController::class,'login']);

Route::get('/admin/login', function () {
    return view('admin.login');
});

Route::middleware('auth:sanctum')->get('/profile', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
 // Route::post('/videos/upload', [VideoController::class, 'upload']);
    Route::post('/videos', [VideoController::class, 'store']);
    Route::get('/videos', [VideoController::class, 'index']);
    Route::get('/video/{id}', [VideoController::class, 'show']);
    Route::post('/videos/{id}/like', [VideoController::class, 'like']);
    Route::post('/logout', [AuthController::class, 'logout']);
      Route::post('/subscribe', [SubscriptionController::class, 'purchase']);
    Route::get('/subscription', [SubscriptionController::class, 'current']);
    

});

Route::middleware(['auth:sanctum', 'is_admin1'])->group(function () {
    Route::post('/videos/upload', [VideoController::class, 'upload']);
    Route::get('/admin/videos', [VideoController::class, 'index']);
    Route::put('/admin/videos/{id}', [VideoController::class, 'update']);
    Route::delete('/admin/videos/{id}', [VideoController::class, 'destroy']);
    Route::post('admin/videos/{id}/status', [VideoController::class, 'changeStatus']);

   

     Route::apiResource('admin/categories', CategoryController::class);
});

Route::prefix('auth')->group(function () {
    Route::post('send-otp', [OtpController::class, 'sendOtp']);
    Route::post('verify-otp', [OtpController::class, 'verifyOtp']);
});

Route::middleware(['jwt.auth'])->group(function () {
    Route::get('/user-profile', [UserController::class, 'profile']);
    // other secured routes
});

