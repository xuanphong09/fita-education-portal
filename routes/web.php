<?php

use App\Http\Controllers\AuthenticateController;
use App\Http\Middleware\SetAdminLocale;

Route::livewire('/', 'pages::client.home')->name('client.home');
Route::livewire('/gioi-thieu', 'pages::client.information')->name('client.information');
Route::livewire('/search', 'pages::client.search')->name('client.search');

// Posts
Route::livewire('/bai-viet', 'pages::client.posts.index')->name('client.posts.index');
Route::livewire('/bai-viet/{slug}', 'pages::client.posts.show')
    ->middleware('throttle:60,1') // Giới hạn 60 requests/phút để chống bot spam
    ->name('client.posts.show');

// Auth
Route::livewire('/login', 'pages::client.auth.login')->name('login');
Route::get('/logout', [AuthenticateController::class, 'logout'])->name('handleLogout');

// ============================================================
// ADMIN — middleware chung: auth + locale
// ============================================================
Route::prefix('admin')->middleware(['auth', SetAdminLocale::class])->group(function () {

    // Dashboard — chỉ cần đăng nhập
    Route::livewire('', 'pages::admin.dashboard')->name('admin.dashboard');

    // ---- Cấu hình giao diện ----
    Route::middleware('permission:cai_dat_giao_dien')->group(function () {
        Route::livewire('/configuration/introduction-page', 'pages::admin.configuration.introduction')->name('admin.configuration.introduction');
        Route::livewire('/configuration/footer', 'pages::admin.configuration.footer')->name('admin.configuration.footer');
    });

    // ---- Quản lý người dùng & vai trò ----
    Route::middleware('permission:quan_ly_nguoi_dung')->group(function () {
        Route::livewire('/user/user-list', 'pages::admin.user.user-list')->name('admin.user.user-list');
        Route::livewire('/user/create', 'pages::admin.user.create')->name('admin.user.create');
        Route::livewire('/user/edit/{id}', 'pages::admin.user.edit')->name('admin.user.edit');

        Route::livewire('/role/role-list', 'pages::admin.role.index')->name('admin.role.index');
        Route::livewire('/role/role-create', 'pages::admin.role.create')->name('admin.role.create');
        Route::livewire('/role/role-edit/{id}', 'pages::admin.role.edit')->name('admin.role.edit');
    });

    // ---- Quản lý bài viết & danh mục ----
    Route::middleware('permission:quan_ly_bai_viet')->group(function () {
        Route::livewire('/category/index', 'pages::admin.category.index')->name('admin.category.index');
        Route::livewire('/category/create', 'pages::admin.category.create')->name('admin.category.create');
        Route::livewire('/category/edit/{id}', 'pages::admin.category.edit')->name('admin.category.edit');

        Route::livewire('/post/index', 'pages::admin.post.index')->name('admin.post.index');
        Route::livewire('/post/create', 'pages::admin.post.create')->name('admin.post.create');
        Route::livewire('/post/edit/{id}', 'pages::admin.post.edit')->name('admin.post.edit');
    });

    // ---- Preview (chỉ cần auth, không cần permission riêng) ----
    Route::livewire('/preview/introduction-page', 'pages::admin.preview.introduction-page')->name('admin.preview.introduction');
    Route::livewire('/preview/header-footer', 'pages::admin.preview.header-footer')->name('admin.preview.header-footer');
    Route::livewire('/preview/post/{id}', 'pages::admin.preview.post')->name('admin.preview.post');
    Route::livewire('/preview/post-new', 'pages::admin.preview.post-new')->name('admin.preview.post.new');
});
