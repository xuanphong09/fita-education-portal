<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            ['name' => 'quan_ly_nguoi_dung', 'display_name' => 'Quản lý người dùng'],
            ['name' => 'quan_ly_bai_viet', 'display_name' => 'Quản lý bài viết'],
            ['name' => 'viet_bai_viet', 'display_name' => 'Viết bài viết'],
            ['name' => 'duyet_bai_viet', 'display_name' => 'Duyệt bài viết'],
            ['name' => 'quan_ly_dao_tao', 'display_name' => 'Quản lý đào tạo'],
            ['name' => 'quan_ly_doi_tac', 'display_name' => 'Quản lý đối tác'],
            ['name' => 'quan_ly_anh', 'display_name' => 'Quản lý ảnh'],
            ['name' => 'quan_ly_lien_he', 'display_name' => 'Quản lý liên hệ'],
            ['name' => 'trang_quan_tri', 'display_name' => 'Truy cập trang quản trị'],
            ['name' => 'cau_hinh_he_thong', 'display_name' => 'Cấu hình hệ thống'],

            ['name' => 'quan_ly_banner', 'display_name' => 'Quản lý banner'],
            ['name' => 'cau_hinh_trang_chu', 'display_name' => 'Cấu hình trang chủ'],
            ['name' => 'cau_hinh_trang_gioi_thieu', 'display_name' => 'Cấu hình trang giới thiệu'],
            ['name' => 'cau_hinh_menu_tieu_de', 'display_name' => 'Cấu hình menu tiêu đề'],
            ['name' => 'cau_hinh_chan_trang', 'display_name' => 'Cấu hình chân trang'],
        ];

        $obsoletePermissions = [
            'Quan_ly_doi_tac',
            'quan_ly_giao_dien'
        ];

        foreach ($obsoletePermissions as $permissionName) {
            $permission = Permission::query()
                ->where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();

            if ($permission) {
                $permission->roles()->detach();
                $permission->users()->detach();

                $permission->delete();
            }
        }

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                [
                    'name' => $permission['name'],
                    'guard_name' => 'web',
                ],
                ['display_name' => $permission['display_name']]
            );
        }

        Category::query()->get()->each(function (Category $category) {
            $categoryName = trim($category->getTranslatedName()) ?: ('Category #' . $category->id);

            foreach (['viet_bai_viet' => 'Viết bài viết', 'duyet_bai_viet' => 'Duyệt bài viết'] as $permissionName => $displayName) {
                Permission::query()->updateOrCreate(
                    [
                        'name' => $permissionName . ':' . $category->id,
                        'guard_name' => 'web',
                    ],
                    [
                        'display_name' => $displayName . ': ' . $categoryName,
                    ]
                );
            }
        });

//        Role::query()->updateOrCreate(
//            ['name' => 'sinh_vien', 'guard_name' => 'web'],
//            ['display_name' => 'Sinh viên']
//        );
//
//        Role::query()->updateOrCreate(
//            ['name' => 'giang_vien', 'guard_name' => 'web'],
//            ['display_name' => 'Giảng viên']
//        )->syncPermissions(['viet_bai_viet', 'trang_quan_tri']);
//
//        Role::query()->updateOrCreate(
//            ['name' => 'ban_chu_nhiem', 'guard_name' => 'web'],
//            ['display_name' => 'Ban Chủ Nhiệm Khoa']
//        )->syncPermissions(['quan_ly_bai_viet', 'viet_bai_viet', 'duyet_bai_viet', 'quan_ly_dao_tao', 'Quan_ly_doi_tac', 'trang_quan_tri']);
//
//        Role::query()->updateOrCreate(
//            ['name' => 'quan_tri_vien', 'guard_name' => 'web'],
//            ['display_name' => 'Quản trị viên']
//        )->syncPermissions(['quan_ly_nguoi_dung', 'quan_ly_giao_dien', 'quan_ly_bai_viet', 'viet_bai_viet', 'duyet_bai_viet', 'quan_ly_dao_tao', 'Quan_ly_doi_tac', 'quan_ly_lien_he', 'trang_quan_tri']);
//
//        // Super admin vẫn dùng Gate::before để bypass permission.
//        Role::query()->updateOrCreate(
//            ['name' => 'super_admin', 'guard_name' => 'web'],
//            ['display_name' => 'Super Admin']
//        )->syncPermissions(['quan_ly_nguoi_dung', 'quan_ly_giao_dien', 'quan_ly_bai_viet', 'viet_bai_viet', 'duyet_bai_viet', 'quan_ly_dao_tao', 'Quan_ly_doi_tac', 'quan_ly_lien_he', 'trang_quan_tri', 'quan_ly_anh']);
//
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

