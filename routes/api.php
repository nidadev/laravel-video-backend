<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\Auth\OtpController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\TrendingVideoController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\SubcategoryController;




Route::post('/register',[AuthController::class,'register']);
Route::post('/login',[AuthController::class,'login']);

Route::get('/admin/login', function () {
    return view('admin.login');
});


Route::middleware(['jwt.auth'])->group(function () {
    
    // User Routes
    //Route::get('/user-profile', [UserController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Video Routes
  

    // Subscription
    Route::post('/subscribe', [SubscriptionController::class, 'purchase']);
    Route::get('/subscription', [SubscriptionController::class, 'current']);
         Route::post('/videos/{id}/view', [VideoController::class, 'recordView']);


Route::post('/google-pay-purchase', [VideoController::class, 'googlePayPurchase']);

   

     Route::get('/users', [UserApiController::class, 'index']);
Route::get('/users/{id}', [UserApiController::class, 'show']);
Route::post('/users', [UserApiController::class, 'store']);
Route::delete('/users/{id}', [UserApiController::class, 'destroy']);
 Route::post('/users/update-profile', [UserApiController::class, 'updateProfile']);

 Route::post('/user/device-token', [UserApiController::class, 'saveDeviceToken']);
 Route::get('/watch-history', [VideoController::class, 'watchHistory']);
 Route::post('/watch-history', [VideoController::class, 'storeWatchHistory']);


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
         Route::get('/plans', [SubscriptionController::class, 'getPlanDetails']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/videos/trendingwatched', [VideoController::class, 'trendingAndMostWatched']);

     Route::get('/subcategories', [SubcategoryController::class, 'index']);
Route::get('/categories/{id}/subcategories', [SubcategoryController::class, 'byCategory']);
Route::post('/subcategories', [SubcategoryController::class, 'store']);

Route::get('/videos', [VideoController::class, 'index']);
Route::get('/videos/{id}', [VideoController::class, 'show']);
Route::get('/videos-by-category', [VideoController::class, 'fetchByCategory']);
Route::post('/videos/{id}/like', [VideoController::class, 'like']);

Route::get('/trendingvideos', [TrendingVideoController::class, 'index']);

Route::get('/videos/trendingwatched', [VideoController::class, 'trendingAndMostWatched']);
Route::get('/dashboard', [VideoController::class, 'dashboard']);
Route::get('/search', [VideoController::class, 'search']);
Route::post('/videos', [VideoController::class, 'seeall']);


Route::post('/payment/webhook', [PaymentWebhookController::class, 'handleWebhook']);




