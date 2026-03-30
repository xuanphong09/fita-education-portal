<?php

use App\Http\Controllers\AuthenticateController;
use App\Http\Middleware\SetAdminLocale;
use App\Http\Controllers\TinyMCEController;

Route::livewire('/', 'pages::client.home')->name('client.home');
Route::livewire('/gioi-thieu', 'pages::client.information')->name('client.information');
Route::livewire('/search', 'pages::client.search')->name('client.search');
Route::livewire('/dao-tao/chuong-trinh', 'pages::client.training-programs.index')->name('client.training-programs.index');
Route::livewire('/dao-tao/chuyen-nganh/{major}', 'pages::client.training-programs.major')->name('client.training-programs.major');

// Posts
Route::livewire('/bai-viet', 'pages::client.posts.index')->name('client.posts.index');
Route::livewire('/bai-viet/{slug}', 'pages::client.posts.show')
    ->middleware('throttle:60,1') // Giới hạn 60 requests/phút để chống bot spam
    ->name('client.posts.show');

Route::livewire('/giang-vien', 'pages::client.lecturers.index')->name('client.lecturers.index');
Route::livewire('/giang-vien/{slug}', 'pages::client.lecturers.profile')->name('client.lecturers.profile');


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
        Route::livewire('/banner/index', 'pages::admin.banner.index')->name('admin.banner.index');
        Route::livewire('/banner/trash', 'pages::admin.banner.trash')->name('admin.banner.trash');
        Route::livewire('/partner/index', 'pages::admin.partner.index')->name('admin.partner.index');
        Route::livewire('/partner/trash', 'pages::admin.partner.trash')->name('admin.partner.trash');
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
        Route::livewire('/post/trash', 'pages::admin.post.trash')->name('admin.post.trash');

    });

    // ---- Quản lý đào tạo ----
    Route::middleware('permission:quan_ly_dao_tao|quan_ly_bai_viet')->group(function () {
        Route::livewire('/training-program/index', 'pages::admin.training-program.index')->name('admin.training-program.index');
        Route::livewire('/training-program/trash', 'pages::admin.training-program.trash')->name('admin.training-program.trash');
        Route::livewire('/training-program/create', 'pages::admin.training-program.create')->name('admin.training-program.create');
        Route::livewire('/training-program/edit/{id}', 'pages::admin.training-program.edit')->name('admin.training-program.edit');
        Route::livewire('/training-program/{id}/semesters', 'pages::admin.training-program.semesters')->name('admin.training-program.semesters');

        Route::livewire('/group-subject/index', 'pages::admin.group-subject.index')->name('admin.group-subject.index');
        Route::livewire('/group-subject/create', 'pages::admin.group-subject.create')->name('admin.group-subject.create');
        Route::livewire('/group-subject/edit/{id}', 'pages::admin.group-subject.edit')->name('admin.group-subject.edit');

        Route::livewire('/subject/index', 'pages::admin.subject.index')->name('admin.subject.index');
        Route::livewire('/subject/trash', 'pages::admin.subject.trash')->name('admin.subject.trash');
        Route::livewire('/subject/create', 'pages::admin.subject.create')->name('admin.subject.create');
        Route::livewire('/subject/edit/{id}', 'pages::admin.subject.edit')->name('admin.subject.edit');
    });

    // ---- Preview (chỉ cần auth, không cần permission riêng) ----
    Route::livewire('/preview/introduction-page', 'pages::admin.preview.introduction-page')->name('admin.preview.introduction');
    Route::livewire('/preview/header-footer', 'pages::admin.preview.header-footer')->name('admin.preview.header-footer');
    Route::livewire('/preview/post/{id}', 'pages::admin.preview.post')->name('admin.preview.post');
    Route::livewire('/preview/post-new', 'pages::admin.preview.post-new')->name('admin.preview.post.new');
});
