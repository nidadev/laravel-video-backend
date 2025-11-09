<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\TrendingVideoController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\SubcategoryController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.submit');
Route::get('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::middleware('admin.auth')->group(function () {
    Route::get('/admin/dashboard', [AuthController::class, 'dashboard'])->name('admin.dashboard');

    // Video Management
    Route::get('/admin/videos', [VideoController::class, 'index'])->name('admin.videos');
    //Route::get('/admin/videos/create', [VideoController::class, 'create'])->name('admin.videos.create');
    //Route::post('/admin/videos', [VideoController::class, 'store'])->name('admin.videos.store');
    Route::get('/admin/videos/{id}/edit', [VideoController::class, 'edit'])->name('admin.videos.edit');
    Route::post('/admin/videos/{id}/update', [VideoController::class, 'update'])->name('admin.videos.update');
    Route::delete('/admin/videos/{id}', [VideoController::class, 'destroy'])->name('admin.videos.destroy');

     // 🆕 Presigned S3 Upload Routes
  Route::get('/admin/videos/upload-presigned', [VideoController::class, 'createPresigned'])
    ->name('admin.videos.presigned.create');

Route::post('/admin/videos/presigned-url', [VideoController::class, 'generatePresignedUrl'])
    ->name('admin.videos.presigned.url');

Route::post('/admin/videos/presigned-store', [VideoController::class, 'storePresigned'])
    ->name('admin.videos.presigned.store');

    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/users/{id}/ban', [UserController::class, 'ban'])->name('admin.users.ban');
    Route::post('/users/{id}/unban', [UserController::class, 'unban'])->name('admin.users.unban');
    Route::post('/users/{id}/upgrade', [UserController::class, 'upgrade'])->name('admin.users.upgrade');

 // ----------------- 🔥 Trending Videos -----------------
    Route::get('/admin/trending', [TrendingVideoController::class, 'index'])->name('admin.trending.index');
    Route::get('/admin/trending/create', [TrendingVideoController::class, 'create'])->name('admin.trending.create');
    Route::post('/admin/trending/store', [TrendingVideoController::class, 'store'])->name('admin.trending.store');
    Route::delete('/admin/trending/{id}', [TrendingVideoController::class, 'destroy'])->name('admin.trending.destroy');

    Route::get('/admin/videos/most-watched', [VideoController::class, 'mostWatched'])
    ->name('admin.videos.mostWatched');

  Route::get('/notifications', [NotificationController::class, 'index'])->name('admin.notifications.index');
    Route::post('/notifications/send', [NotificationController::class, 'send'])->name('admin.notifications.send');

Route::get('/categories', [CategoryController::class, 'index'])->name('admin.categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('admin.categories.create');
    Route::post('/categories/store', [CategoryController::class, 'store'])->name('admin.categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('admin.categories.edit');
    Route::put('/categories/{category}/update', [CategoryController::class, 'update'])->name('admin.categories.update');
    Route::delete('/categories/{category}/delete', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');


     Route::get('/subcategories', [SubcategoryController::class, 'index'])->name('admin.subcategories.index');
    Route::get('/subcategories/create', [SubcategoryController::class, 'create'])->name('admin.subcategories.create');
    Route::post('/subcategories/store', [SubcategoryController::class, 'store'])->name('admin.subcategories.store');
    Route::get('/subcategories/{subcategory}/edit', [SubcategoryController::class, 'edit'])->name('admin.subcategories.edit');
    Route::put('/subcategories/{subcategory}/update', [SubcategoryController::class, 'update'])->name('admin.subcategories.update');
    Route::delete('/subcategories/{subcategory}/delete', [SubcategoryController::class, 'destroy'])->name('admin.subcategories.destroy');
Route::patch('/admin/videos/{id}/trending', [VideoController::class, 'toggleTrending'])->name('admin.videos.toggleTrending');

});



// ----------- API ROUTES -----------



