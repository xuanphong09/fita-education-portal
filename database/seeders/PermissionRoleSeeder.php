<?php

namespace Database\Seeders;

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
            ['name' => 'quan_ly_giao_dien', 'display_name' => 'Quản lý giao diện'],
            ['name' => 'Quan_ly_doi_tac', 'display_name' => 'Quản lý đối tác'],
            ['name' => 'quan_ly_anh', 'display_name' => 'Quản lý ảnh'],
            ['name' => 'quan_ly_lien_he', 'display_name' => 'Quản lý liên hệ'],
            ['name' => 'trang_quan_tri', 'display_name' => 'Truy cập trang quản trị'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                [
                    'name' => $permission['name'],
                    'guard_name' => 'web',
                ],
                ['display_name' => $permission['display_name']]
            );
        }

        Role::query()->updateOrCreate(
            ['name' => 'sinh_vien', 'guard_name' => 'web'],
            ['display_name' => 'Sinh viên']
        );

        Role::query()->updateOrCreate(
            ['name' => 'giang_vien', 'guard_name' => 'web'],
            ['display_name' => 'Giảng viên']
        )->syncPermissions(['viet_bai_viet', 'trang_quan_tri']);

        Role::query()->updateOrCreate(
            ['name' => 'ban_chu_nhiem', 'guard_name' => 'web'],
            ['display_name' => 'Ban Chủ Nhiệm Khoa']
        )->syncPermissions(['quan_ly_bai_viet', 'viet_bai_viet', 'duyet_bai_viet', 'quan_ly_dao_tao', 'Quan_ly_doi_tac', 'trang_quan_tri']);

        Role::query()->updateOrCreate(
            ['name' => 'quan_tri_vien', 'guard_name' => 'web'],
            ['display_name' => 'Quản trị viên']
        )->syncPermissions(['quan_ly_nguoi_dung', 'quan_ly_giao_dien', 'quan_ly_bai_viet', 'viet_bai_viet', 'duyet_bai_viet', 'quan_ly_dao_tao', 'Quan_ly_doi_tac', 'quan_ly_lien_he', 'trang_quan_tri']);

        // Super admin vẫn dùng Gate::before để bypass permission.
        Role::query()->updateOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['display_name' => 'Super Admin']
        )->syncPermissions(['quan_ly_nguoi_dung', 'quan_ly_giao_dien', 'quan_ly_bai_viet', 'viet_bai_viet', 'duyet_bai_viet', 'quan_ly_dao_tao', 'Quan_ly_doi_tac', 'quan_ly_lien_he', 'trang_quan_tri', 'quan_ly_anh']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

