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
use App\Http\Controllers\Api\SubCategoryController;




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
    //Route::post('/videos', [VideoController::class, 'store']);
    Route::get('/videos', [VideoController::class, 'index']);
    Route::get('/videos/{id}', [VideoController::class, 'show']);
    Route::get('/videos-by-category', [VideoController::class, 'fetchByCategory']);

    Route::post('/videos/{id}/like', [VideoController::class, 'like']);

    // Subscription
    Route::post('/subscribe', [SubscriptionController::class, 'purchase']);
    Route::get('/subscription', [SubscriptionController::class, 'current']);
         Route::post('/videos/{id}/view', [VideoController::class, 'recordView']);
         Route::get('/plans', [SubscriptionController::class, 'getPlanDetails']);



    //payment

    // Route::post('/payment/checkout', [PaymentController::class, 'createCheckoutSession']);
    //Route::get('/payment/success', [PaymentController::class, 'paymentSuccess']);

     Route::get('/trendingvideos', [TrendingVideoController::class, 'index']);
     Route::get('/categories', [CategoryController::class, 'index']);

     Route::get('/subcategories', [SubcategoryController::class, 'index']);
Route::get('/categories/{id}/subcategories', [SubcategoryController::class, 'byCategory']);
Route::post('/subcategories', [SubcategoryController::class, 'store']);

     Route::get('/users', [UserApiController::class, 'index']);
Route::get('/users/{id}', [UserApiController::class, 'show']);
Route::post('/users', [UserApiController::class, 'store']);
Route::delete('/users/{id}', [UserApiController::class, 'destroy']);
 Route::post('/users/update-profile', [UserApiController::class, 'updateProfile']);

 Route::post('/user/device-token', [UserApiController::class, 'saveDeviceToken']);
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

Route::post('/payment/webhook', [PaymentWebhookController::class, 'handleWebhook']);




