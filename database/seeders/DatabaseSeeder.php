<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Student;
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
            ['name' => 'quan_ly_bai_viet',   'display_name' => 'Quản lý bài viết'],
            ['name' => 'quan_ly_dao_tao',    'display_name' => 'Quản lý đào tạo'],
            ['name' => 'cai_dat_giao_dien',  'display_name' => 'Cài đặt giao diện'],
            ['name' => 'nhap_diem',          'display_name' => 'Nhập điểm'],
            ['name' => 'xem_diem',           'display_name' => 'Xem điểm'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                [
                    'name' => $permission['name'],
                    'guard_name' => 'web',
                ],
                [
                    'display_name' => $permission['display_name'],
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */

        Role::updateOrCreate(
            ['name' => 'sinh_vien', 'guard_name' => 'web'],
            ['display_name' => 'Sinh viên']
        )->syncPermissions(['xem_diem']);

        Role::updateOrCreate(
            ['name' => 'giang_vien', 'guard_name' => 'web'],
            ['display_name' => 'Giảng viên']
        )->syncPermissions(['nhap_diem', 'xem_diem']);

        // Ban chủ nhiệm: quản lý bài viết + điểm, không quản lý tài khoản
        Role::updateOrCreate(
            ['name' => 'ban_chu_nhiem', 'guard_name' => 'web'],
            ['display_name' => 'Ban Chủ Nhiệm Khoa']
        )->syncPermissions(['quan_ly_bai_viet', 'quan_ly_dao_tao', 'nhap_diem', 'xem_diem']);

        // Quản trị viên: quản lý người dùng + giao diện (không phải super admin)
        Role::updateOrCreate(
            ['name' => 'quan_tri_vien', 'guard_name' => 'web'],
            ['display_name' => 'Quản trị viên']
        )->syncPermissions(['quan_ly_nguoi_dung', 'cai_dat_giao_dien', 'quan_ly_bai_viet', 'quan_ly_dao_tao']);

        // Super Admin: bypass mọi permission qua Gate::before trong AppServiceProvider
        // Không cần givePermissionTo — Gate::before trả về true cho mọi ability
        Role::updateOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['display_name' => 'Super Admin']
        );

        /*
        |--------------------------------------------------------------------------
        | DEPARTMENTS
        |--------------------------------------------------------------------------
        */

        Department::insert([
            ['name' => json_encode(['vi' => 'Bộ môn Công nghệ phần mềm', 'en' => 'Department of Software Engineering'], JSON_UNESCAPED_UNICODE)],
            ['name' => json_encode(['vi' => 'Bộ môn Khoa học máy tính', 'en' => 'Department of Computer Science'], JSON_UNESCAPED_UNICODE)],
            ['name' => json_encode(['vi' => 'Bộ môn Mạng và Hệ thống thông tin', 'en' => 'Department of Networks and Information Systems'], JSON_UNESCAPED_UNICODE)],
            ['name' => json_encode(['vi' => 'Bộ môn Toán', 'en' => 'Department of Mathematics'], JSON_UNESCAPED_UNICODE)],
            ['name' => json_encode(['vi' => 'Bộ môn Vật lý', 'en' => 'Department of Physics'], JSON_UNESCAPED_UNICODE)],
            ['name' => json_encode(['vi' => 'Tổ văn phòng', 'en' => 'Office Team'], JSON_UNESCAPED_UNICODE)],
        ]);

        /*
        |--------------------------------------------------------------------------
        | MAJORS
        |--------------------------------------------------------------------------
        */

        $majors = [
            ['code' => '7480201', 'slug' => 'cong-nghe-thong-tin', 'name' => ['vi' => 'Công nghệ thông tin', 'en' => 'Information Technology']],
            ['code' => '7480201', 'slug' => 'cong-nghe-phan-mem', 'name' => ['vi' => 'Công nghệ phần mềm', 'en' => 'Software Engineering']],
            ['code' => '7480201', 'slug' => 'he-thong-thong-tin', 'name' => ['vi' => 'Hệ thống thông tin', 'en' => 'Information Systems']],
            ['code' => '7480201', 'slug' => 'an-toan-thong-tin', 'name' => ['vi' => 'An toàn thông tin', 'en' => 'Cybersecurity']],
            ['code' => '7480102', 'slug' => 'mang-may-tinh', 'name' => ['vi' => 'Mạng máy tính', 'en' => 'Computer Networks']],
            ['code' => '7480102', 'slug' => 'truyen-thong', 'name' => ['vi' => 'Truyền thông', 'en' => 'Communication']],
            ['code' => '7480112', 'slug' => 'khoa-hoc-du-lieu-va-tri-tue-nhan-tao', 'name' => ['vi' => 'Khoa học dữ liệu và Trí tuệ nhân tạo', 'en' => 'Data Science and Artificial Intelligence']],
        ];

        foreach ($majors as $major) {
            Major::updateOrCreate(
                ['slug' => $major['slug']],
                ['name' => $major['name'], 'code' => $major['code']]
            );
        }

        $majorCNTT = Major::where('slug', 'cong-nghe-thong-tin')->first();

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

        $admin->assignRole('super_admin');

        // Seed danh sách giảng viên mẫu
        $this->call(LecturerSeeder::class);

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

        $sinhVien->assignRole('sinh_vien');

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

        // --- Categories & sample posts seeding ---
        \App\Models\Category::insert([
            ['name' => json_encode(['vi' => 'Tin tức', 'en' => 'News']), 'slug' => 'tin-tuc'],
            ['name' => json_encode(['vi' => 'Thông báo', 'en' => 'Announcements']), 'slug' => 'thong-bao'],
            ['name' => json_encode(['vi' => 'Sự kiện', 'en' => 'Events']), 'slug' => 'su-kien'],
        ]);

        $this->call(PostSeeder::class);
        $this->call(GroupSubjectSeeder::class);
        $this->call(SubjectSeeder::class);
        $this->call(TrainingProgramSeeder::class);
        $this->call(TrainingProgramExplorerSeeder::class);

        $this->command->info('Seed dữ liệu mẫu thành công!');
    }
}
