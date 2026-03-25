<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class LecturerSeeder extends Seeder
{
    public function run(): void
    {
        $departmentIds = Department::query()
            ->get(['id', 'name'])
            ->mapWithKeys(function (Department $department) {
                $name = $department->getTranslation('name', 'vi', false)
                    ?: $department->getTranslation('name', 'en', false)
                    ?: '';

                return [$name => $department->id];
            });

        $rows = [
            [
                'name' => 'Nguyễn Văn Thầy',
                'email' => 'thaynguyen@vnua.edu.vn',
                'staff_code' => 'GV001',
                'department' => 'Bộ môn Công nghệ phần mềm',
                'gender' => 'male',
                'academic_title' => 'pgs',
                'degree' => 'ts',
                'phone' => '0988123456',
                'positions' => ['vi' => 'Trưởng bộ môn', 'en' => 'Head of Department'],
                'roles' => ['giang_vien', 'ban_chu_nhiem'],
            ],
            [
                'name' => 'Trần Thu Hương',
                'email' => 'huongtt@vnua.edu.vn',
                'staff_code' => 'GV002',
                'department' => 'Bộ môn Công nghệ phần mềm',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0911111111',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Phạm Minh Đức',
                'email' => 'ducpm@vnua.edu.vn',
                'staff_code' => 'GV003',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ts',
                'phone' => '0922222222',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Lê Hải An',
                'email' => 'anlh@vnua.edu.vn',
                'staff_code' => 'GV004',
                'department' => 'Bộ môn Mạng và Hệ thống thông tin',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0933333333',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Đỗ Quỳnh Mai',
                'email' => 'maidq@vnua.edu.vn',
                'staff_code' => 'GV005',
                'department' => 'Bộ môn Toán',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0944444444',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Vũ Đức Huy',
                'email' => 'huyvd@vnua.edu.vn',
                'staff_code' => 'GV006',
                'department' => 'Bộ môn Vật lý',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0955555555',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
        ];

        foreach ($rows as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make('12345678'),
                    'user_type' => 'lecturer',
                    'is_active' => true,
                ]
            );

            if (!empty($row['roles'])) {
                $user->syncRoles($row['roles']);
            }

            Lecturer::query()->updateOrCreate(
                ['staff_code' => $row['staff_code']],
                [
                    'user_id' => $user->id,
                    'slug' => Str::slug($row['name']) . '-' . Str::lower($row['staff_code']),
                    'gender' => $row['gender'],
                    'department_id' => $departmentIds[$row['department']] ?? null,
                    'degree' => $row['degree'],
                    'academic_title' => $row['academic_title'],
                    'phone' => $row['phone'],
                    'positions' => $row['positions'],
                ]
            );
        }
    }
}



