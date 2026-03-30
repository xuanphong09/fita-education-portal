<?php

namespace Database\Seeders;

use App\Models\Intake;
use App\Models\Major;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserStudentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@vnua.edu.vn'],
            [
                'name' => 'Quản trị hệ thống',
                'password' => Hash::make('@FITA-2015$'),
                'user_type' => 'admin',
                'is_active' => true,
            ]
        );

        $admin->syncRoles(['super_admin']);

        $studentUser = User::query()->updateOrCreate(
            ['email' => '671762@vnua.edu.vn'],
            [
                'name' => 'Phạm Xuân Phong',
                'password' => Hash::make('@FITA-2015$'),
                'user_type' => 'student',
                'is_active' => true,
            ]
        );

        $studentUser->syncRoles(['sinh_vien']);

        $intakeId = Intake::query()->where('name', 'K67')->value('id');
        $majorId = Major::query()->where('slug', 'cong-nghe-pham-men')->value('id');

        Student::query()->updateOrCreate(
            ['student_code' => '671762'],
            [
                'user_id' => $studentUser->id,
                'class_name' => 'K67CNPMA',
                'gender' => 'female',
                'intake_id' => $intakeId,
                'major_id' => $majorId,
                'date_of_birth' => '2004-13-09',
                'phone' => '0912345678',
            ]
        );
    }
}

