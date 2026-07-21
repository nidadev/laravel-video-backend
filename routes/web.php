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

/*
|--------------------------------------------------------------------------
| Admin Login (No Auth Required)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {

    Route::get('/login', [AuthController::class, 'showLoginForm'])
        ->name('admin.login');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('admin.login.submit');

});

/*
|--------------------------------------------------------------------------
| Protected Admin Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware(['web','auth:admin'])
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [AuthController::class, 'dashboard'])
            ->name('admin.dashboard');

        // Logout
        Route::get('/logout', [AuthController::class, 'logout'])
            ->name('admin.logout');

        /*
        |--------------------------------------------------------------------------
        | Videos
        |--------------------------------------------------------------------------
        */

        Route::get('/videos', [VideoController::class, 'index'])
            ->name('admin.videos');

        Route::get('/videos/upload-presigned', [VideoController::class, 'createPresigned'])
            ->name('admin.videos.presigned.create');

        Route::post('/videos/presigned-url', [VideoController::class, 'generatePresignedUrl'])
            ->name('admin.videos.presigned.url');

        Route::post('/videos/presigned-store', [VideoController::class, 'storePresigned'])
            ->name('admin.videos.presigned.store');

        Route::get('/videos/{id}/edit', [VideoController::class, 'edit'])
            ->name('admin.videos.edit');

        Route::put('/videos/{id}/update', [VideoController::class, 'updatePresigned'])
            ->name('admin.videos.update');

        Route::delete('/videos/{id}', [VideoController::class, 'destroy'])
            ->name('admin.videos.destroy');

        Route::get('/videos/most-watched', [VideoController::class, 'mostWatched'])
            ->name('admin.videos.mostWatched');

        Route::patch('/videos/{id}/trending', [VideoController::class, 'toggleTrending'])
            ->name('admin.videos.toggleTrending');

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        Route::get('/users', [UserController::class, 'index'])
            ->name('admin.users.index');

        Route::post('/users/{id}/ban', [UserController::class, 'ban'])
            ->name('admin.users.ban');

        Route::post('/users/{id}/unban', [UserController::class, 'unban'])
            ->name('admin.users.unban');

        Route::post('/users/{id}/upgrade', [UserController::class, 'upgrade'])
            ->name('admin.users.upgrade');

        Route::delete('/users/{id}', [UserController::class, 'destroy'])
            ->name('admin.users.delete');

        /*
        |--------------------------------------------------------------------------
        | Trending Videos
        |--------------------------------------------------------------------------
        */

        Route::get('/trending', [TrendingVideoController::class, 'index'])
            ->name('admin.trending.index');

        Route::get('/trending/create', [TrendingVideoController::class, 'create'])
            ->name('admin.trending.create');

        Route::post('/trending/store', [TrendingVideoController::class, 'store'])
            ->name('admin.trending.store');

        Route::delete('/trending/{id}', [TrendingVideoController::class, 'destroy'])
            ->name('admin.trending.destroy');

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('admin.notifications.index');

        Route::post('/notifications/send', [NotificationController::class, 'send'])
            ->name('admin.notifications.send');

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        Route::get('/categories', [CategoryController::class, 'index'])
            ->name('admin.categories.index');

        Route::get('/categories/create', [CategoryController::class, 'create'])
            ->name('admin.categories.create');

        Route::post('/categories/store', [CategoryController::class, 'store'])
            ->name('admin.categories.store');

        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])
            ->name('admin.categories.edit');

        Route::put('/categories/{category}/update', [CategoryController::class, 'update'])
            ->name('admin.categories.update');

        Route::delete('/categories/{category}/delete', [CategoryController::class, 'destroy'])
            ->name('admin.categories.destroy');

        /*
        |--------------------------------------------------------------------------
        | Subcategories
        |--------------------------------------------------------------------------
        */

        Route::get('/subcategories', [SubcategoryController::class, 'index'])
            ->name('admin.subcategories.index');

        Route::get('/subcategories/create', [SubcategoryController::class, 'create'])
            ->name('admin.subcategories.create');

        Route::post('/subcategories/store', [SubcategoryController::class, 'store'])
            ->name('admin.subcategories.store');

        Route::get('/subcategories/{subcategory}/edit', [SubcategoryController::class, 'edit'])
            ->name('admin.subcategories.edit');

        Route::put('/subcategories/{subcategory}/update', [SubcategoryController::class, 'update'])
            ->name('admin.subcategories.update');

        Route::delete('/subcategories/{subcategory}/delete', [SubcategoryController::class, 'destroy'])
            ->name('admin.subcategories.destroy');

    });