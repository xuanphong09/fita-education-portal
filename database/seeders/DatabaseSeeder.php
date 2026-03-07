<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Student;
use App\Models\Lecturer;
use App\Models\Department;
use App\Models\Major;
use App\Models\Intake;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | PERMISSIONS
        |--------------------------------------------------------------------------
        */

        $permissions = [
            ['name' => 'quan_ly_nguoi_dung', 'display_name' => 'Quản lý người dùng'],
            ['name' => 'quan_ly_bai_viet', 'display_name' => 'Quản lý bài viết'],
            ['name' => 'cai_dat_giao_dien', 'display_name' => 'Cài đặt giao diện'],
            ['name' => 'nhap_diem', 'display_name' => 'Nhập điểm'],
            ['name' => 'xem_diem', 'display_name' => 'Xem điểm'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */

        $roleSinhVien = Role::create([
            'name' => 'sinh_vien',
            'display_name' => 'Sinh viên'
        ]);

        $roleSinhVien->givePermissionTo(['xem_diem']);

        $roleGiangVien = Role::create([
            'name' => 'giang_vien',
            'display_name' => 'Giảng viên'
        ]);

        $roleGiangVien->givePermissionTo(['nhap_diem', 'xem_diem']);

        $roleBanChuNhiem = Role::create([
            'name' => 'ban_chu_nhiem',
            'display_name' => 'Ban Chủ Nhiệm Khoa'
        ]);

        $roleBanChuNhiem->givePermissionTo(['quan_ly_bai_viet', 'nhap_diem', 'xem_diem']);

        $roleSuperAdmin = Role::create([
            'name' => 'super_admin',
            'display_name' => 'Super Admin'
        ]);

        $roleSuperAdmin->givePermissionTo(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | DEPARTMENTS
        |--------------------------------------------------------------------------
        */

        Department::insert([
            ['name' => 'Bộ môn Công nghệ phần mềm'],
            ['name' => 'Bộ môn Khoa học máy tính'],
            ['name' => 'Bộ môn Mạng và Hệ thống thông tin'],
            ['name' => 'Bộ môn Toán'],
            ['name' => 'Bộ môn Vật lý'],
            ['name' => 'Tổ văn phòng'],
        ]);

        $deptCNTT = Department::where('name', 'Bộ môn Công nghệ phần mềm')->first();

        /*
        |--------------------------------------------------------------------------
        | MAJORS
        |--------------------------------------------------------------------------
        */

        Major::insert([
            ['name' => 'Công nghệ thông tin'],
            ['name' => 'Công nghệ phần mềm'],
            ['name' => 'Hệ thống thông tin'],
            ['name' => 'An toàn thông tin'],
            ['name' => 'Mạng máy tính'],
            ['name' => 'Truyền thông'],
            ['name' => 'Khoa học dữ liệu và Trí tuệ nhân tạo'],
        ]);

        $majorCNTT = Major::where('name', 'Công nghệ thông tin')->first();

        /*
        |--------------------------------------------------------------------------
        | INTAKES
        |--------------------------------------------------------------------------
        */

        Intake::insert([
            ['name' => 'K65'],
            ['name' => 'K66'],
            ['name' => 'K67'],
            ['name' => 'K68'],
            ['name' => 'K69'],
            ['name' => 'K70'],
            ['name' => 'K71'],
        ]);

        $intakeK65 = Intake::where('name', 'K65')->first();

        /*
        |--------------------------------------------------------------------------
        | ADMIN USER
        |--------------------------------------------------------------------------
        */

        $admin = User::create([
            'name' => 'Quản trị hệ thống',
            'email' => 'admin@vnua.edu.vn',
            'password' => Hash::make('12345678'),
            'user_type' => 'admin',
            'is_active' => true,
        ]);

        $admin->assignRole($roleSuperAdmin);

        /*
        |--------------------------------------------------------------------------
        | LECTURER USER
        |--------------------------------------------------------------------------
        */

        $giangVien = User::create([
            'name' => 'Nguyễn Văn Thầy',
            'email' => 'thaynguyen@vnua.edu.vn',
            'password' => Hash::make('12345678'),
            'user_type' => 'lecturer',
            'is_active' => true,
        ]);

        $giangVien->assignRole([$roleGiangVien, $roleBanChuNhiem]);

        Lecturer::create([
            'user_id' => $giangVien->id,
            'staff_code' => 'GV001',
            'gender' => 'male',
            'department_id' => $deptCNTT->id,
            'academic_title' => 'PGS',
            'degree' => 'TS',
            'phone' => '0988123456',
            'positions' => 'Trưởng bộ môn',
        ]);

        /*
        |--------------------------------------------------------------------------
        | STUDENT USER
        |--------------------------------------------------------------------------
        */

        $sinhVien = User::create([
            'name' => 'Trần Thị Trò',
            'email' => '651234@vnua.edu.vn',
            'password' => Hash::make('12345678'),
            'user_type' => 'student',
            'is_active' => true,
        ]);

        $sinhVien->assignRole($roleSinhVien);

        Student::create([
            'user_id' => $sinhVien->id,
            'student_code' => '651234',
            'class_name' => 'K65CNTTA',
            'gender' => 'female',
            'intake_id' => $intakeK65->id,
            'major_id' => $majorCNTT->id,
            'date_of_birth' => '2003-05-10',
            'phone' => '0912345678',
        ]);

        $this->command->info('Seed dữ liệu mẫu thành công!');
    }
}
