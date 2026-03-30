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
            // Cán bộ quản lý / Ban chủ nhiệm
            [
                'name' => 'Phạm Quang Dũng',
                'email' => 'pqdung@vnua.edu.vn',
                'staff_code' => 'MTI05',
                'department' => 'Bộ môn Mạng và Hệ thống thông tin',
                'gender' => 'male',
                'academic_title' => '',
                'degree' => 'ts',
                'phone' => '0988123456',
                'positions' => ['vi' => 'Trưởng khoa', 'en' => 'Dean of Department'],
                'roles' => ['giang_vien', 'ban_chu_nhiem'],
            ],
            [
                'name' => 'Ngô Công Thắng',
                'email' => 'ncthang@vnua.edu.vn',
                'staff_code' => 'CNP02',
                'department' => 'Bộ môn Công nghệ phần mềm',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0911111111',
                'positions' => ['vi' => 'Phó trưởng khoa, Trưởng bộ môn Công nghệ phần mềm', 'en' => 'Deputy Dean, Head of the Software Engineering Department'],
                'roles' => ['giang_vien', 'ban_chu_nhiem'],
            ],
            [
                'name' => 'Nguyễn Trọng Kương',
                'email' => 'ntkuong@vnua.edu.vn',
                'staff_code' => 'TOT07',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ts',
                'phone' => '0900000307',
                'positions' => ['vi' => 'Phó trưởng khoa, Trưởng bộ môn Khoa học máy tính', 'en' => 'Deputy Dean, Head of the Computer Science Department'],
                'roles' => ['giang_vien', 'ban_chu_nhiem'],
            ],

            // Danh sách Giảng viên
            [
                'name' => 'Đỗ Thị Nhâm',
                'email' => 'dtnham@vnua.edu.vn',
                'staff_code' => 'CNP03',
                'department' => 'Bộ môn Công nghệ phần mềm',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000003',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Phan Trọng Tiến',
                'email' => 'ptgtien@vnua.edu.vn',
                'staff_code' => 'CNP05',
                'department' => 'Bộ môn Công nghệ phần mềm',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000005',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Hoàng Thị Hà',
                'email' => 'htha@vnua.edu.vn',
                'staff_code' => 'CNP07',
                'department' => 'Bộ môn Công nghệ phần mềm',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000007',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Trần Trung Hiếu',
                'email' => 'tthieu@vnua.edu.vn',
                'staff_code' => 'CNP09',
                'department' => 'Bộ môn Công nghệ phần mềm',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000009',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Lê Thị Minh Thùy',
                'email' => 'ltmthuy@vnua.edu.vn',
                'staff_code' => 'CNP11',
                'department' => 'Bộ môn Công nghệ phần mềm',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000011',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Lê Thị Nhung',
                'email' => 'ltnhung@vnua.edu.vn',
                'staff_code' => 'CNP12',
                'department' => 'Bộ môn Công nghệ phần mềm',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000012',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Trần Thị Thu Huyền',
                'email' => 'ttthuyen@vnua.edu.vn',
                'staff_code' => 'MTI01',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000101',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Ngô Tuấn Anh',
                'email' => 'ntanh@vnua.edu.vn',
                'staff_code' => 'MTI03',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000103',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Đoàn Thị Thu Hà',
                'email' => 'dttha@vnua.edu.vn',
                'staff_code' => 'MTI07',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000107',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Trần Vũ Hà',
                'email' => 'tvha@vnua.edu.vn',
                'staff_code' => 'MTI08',
                'department' => 'Bộ môn Mạng và Hệ thống thông tin',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000108',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Nguyễn Thị Huyền',
                'email' => 'nthuyen@vnua.edu.vn',
                'staff_code' => 'MTI10',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000110',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Nguyễn Văn Hoàng',
                'email' => 'nvhoang@vnua.edu.vn',
                'staff_code' => 'MTI11',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000111',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Vũ Thị Lưu',
                'email' => 'vtluu@vnua.edu.vn',
                'staff_code' => 'MTI12',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000112',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Nguyễn Thị Thảo',
                'email' => 'ntthao81@vnua.edu.vn',
                'staff_code' => 'MTI13',
                'department' => 'Bộ môn Mạng và Hệ thống thông tin',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000113',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Nguyễn Đức Thịnh',
                'email' => 'ndthinh@vnua.edu.vn',
                'staff_code' => 'MTI14',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000114',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Phạm Thị Lan Anh',
                'email' => 'ptlanh@vnua.edu.vn',
                'staff_code' => 'MTI15',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000115',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Nguyễn Hữu Hải',
                'email' => 'nhhai@vnua.edu.vn',
                'staff_code' => 'TOA27',
                'department' => 'Bộ môn Mạng và Hệ thống thông tin',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000227',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Nguyễn Hoàng Huy',
                'email' => 'nhhuy@vnua.edu.vn',
                'staff_code' => 'TOT03',
                'department' => 'Bộ môn Mạng và Hệ thống thông tin',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000303',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Nguyễn Thị Lan',
                'email' => 'ngtlan@vnua.edu.vn',
                'staff_code' => 'TOT10',
                'department' => 'Bộ môn Mạng và Hệ thống thông tin',
                'gender' => 'female',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000310',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Nguyễn Tiến Hiển',
                'email' => 'nguyentienhien@vnua.edu.vn',
                'staff_code' => 'VLY09',
                'department' => 'Bộ môn Mạng và Hệ thống thông tin',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000409',
                'positions' => ['vi' => 'Giảng viên', 'en' => 'Lecturer'],
                'roles' => ['giang_vien'],
            ],
            [
                'name' => 'Lương Minh Quân',
                'email' => 'lmquan@vnua.edu.vn',
                'staff_code' => 'VLY10',
                'department' => 'Bộ môn Khoa học máy tính',
                'gender' => 'male',
                'academic_title' => null,
                'degree' => 'ths',
                'phone' => '0900000410',
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
