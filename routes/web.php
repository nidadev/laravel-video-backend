<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\VideoController;
use App\Http\Controllers\Admin\VideoUploadController;

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
    Route::get('/admin/videos/create', [VideoController::class, 'create'])->name('admin.videos.create');
    Route::post('/admin/videos', [VideoController::class, 'store'])->name('admin.videos.store');
    Route::get('/admin/videos/{id}/edit', [VideoController::class, 'edit'])->name('admin.videos.edit');
    Route::post('/admin/videos/{id}/update', [VideoController::class, 'update'])->name('admin.videos.update');
    Route::delete('/admin/videos/{id}', [VideoController::class, 'destroy'])->name('admin.videos.destroy');

     // 🆕 Presigned S3 Upload Routes
  Route::get('/admin/videos/upload-presigned', [VideoController::class, 'createPresigned'])
    ->name('admin.videos.presigned.create');

Route::post('/admin/videos/presigned-url', [VideoController::class, 'generatePresignedUrl'])
    ->name('admin.videos.presigned.url');

Route::post('/admin/videos/presigned-store', [VideoController::class, 'storePresigned'])
    ->name('admin.videos.presigned.store');});

// ----------- API ROUTES -----------



