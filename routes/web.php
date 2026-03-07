<?php

use App\Http\Controllers\AuthenticateController;
use App\Http\Middleware\SetAdminLocale;

Route::livewire('/', 'pages::client.home')->name('client.home');
Route::livewire('/gioi-thieu', 'pages::client.information')->name('client.information');
Route::livewire('/search', 'pages::client.search')->name('client.search');

//auth
Route::livewire('/login', 'pages::client.auth.login')->name('login');
Route::get('/logout', [AuthenticateController::class, 'logout'])->name('handleLogout');

Route::group(
    ["prefix" => "admin",
        "middleware" => ['auth', 'permission:cai_dat_giao_dien', SetAdminLocale::class]
    ],
    function () {
    Route::livewire('', 'pages::admin.dashboard')->name('admin.dashboard');
    Route::livewire('/configuration/introduction-page', 'pages::admin.configuration.introduction')->name('admin.configuration.introduction');
    Route::livewire('/configuration/footer', 'pages::admin.configuration.footer')->name('admin.configuration.footer');

//    user
    Route::livewire('/user/user-list', 'pages::admin.user.user-list')->name('admin.user.user-list');
    Route::livewire('/user/create', 'pages::admin.user.create')->name('admin.user.create');
    Route::livewire('/user/edit/{id}', 'pages::admin.user.edit')->name('admin.user.edit');

//    role
    Route::livewire('/role/role-list', 'pages::admin.role.index')->name('admin.role.index');
    Route::livewire('/role/role-create', 'pages::admin.role.create')->name('admin.role.create');
    Route::livewire('/role/role-edit/{id}', 'pages::admin.role.edit')->name('admin.role.edit');

});

Route::group(["prefix" => "admin"], function () {
    //    preview
    Route::livewire('/preview/introduction-page', 'pages::admin.preview.introduction-page')->name('admin.preview.introduction');
    Route::livewire('/preview/header-footer', 'pages::admin.preview.header-footer')->name('admin.preview.header-footer');
});
