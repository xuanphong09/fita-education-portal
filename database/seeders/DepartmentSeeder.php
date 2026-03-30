<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
	public function run(): void
	{
		$departments = [
			['vi' => 'Bộ môn Công nghệ phần mềm', 'en' => 'Department of Software Engineering'],
			['vi' => 'Bộ môn Khoa học máy tính', 'en' => 'Department of Computer Science'],
			['vi' => 'Bộ môn Mạng và Hệ thống thông tin', 'en' => 'Department of Networks and Information Systems'],
			['vi' => 'Bộ môn Toán', 'en' => 'Department of Mathematics'],
			['vi' => 'Bộ môn Vật lý', 'en' => 'Department of Physics'],
			['vi' => 'Tổ văn phòng', 'en' => 'Faculty Office'],
		];

		foreach ($departments as $department) {
			Department::query()->updateOrCreate(
				['name->vi' => $department['vi']],
				['name' => ['vi' => $department['vi'], 'en' => $department['en']]]
			);
		}
	}
}

