<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
	public function run(): void
	{
		$departments = [
			['vi' => 'Bộ môn Công nghệ phần mềm', 'en' => 'Department of Software Engineering', 'slug' => 'bo-mon-cong-nghe-phan-mem'],
			['vi' => 'Bộ môn Khoa học máy tính', 'en' => 'Department of Computer Science', 'slug' => 'bo-mon-khoa-hoc-may-tinh'],
			['vi' => 'Bộ môn Mạng và Hệ thống thông tin', 'en' => 'Department of Networks and Information Systems', 'slug' => 'bo-mon-mang-va-he-thong-thong-tin'],
			['vi' => 'Bộ môn Toán', 'en' => 'Department of Mathematics', 'slug' => 'bo-mon-toan'],
			['vi' => 'Bộ môn Vật lý', 'en' => 'Department of Physics', 'slug' => 'bo-mon-vat-ly'],
			['vi' => 'Tổ văn phòng', 'en' => 'Faculty Office', 'slug' => 'to-van-phong'],
		];

		foreach ($departments as $department) {
			Department::query()->updateOrCreate(
				['name->vi' => $department['vi']],
				['name' => ['vi' => $department['vi'], 'en' => $department['en']]]
			);
		}
	}
}

