<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionRoleSeeder::class,
            DepartmentSeeder::class,
            MajorSeeder::class,
            IntakeSeeder::class,
            UserStudentSeeder::class,
            LecturerSeeder::class,
            CategorySeeder::class,
            PostSeeder::class,
            PartnerSeeder::class,
            GroupSubjectSeeder::class,
            SubjectSeeder::class,
            TrainingProgramSeeder::class,
//            TrainingProgramExplorerSeeder::class,
            PageSeeder::class,
        ]);

        $this->command->info('Seed dữ liệu mẫu thành công!');
    }
}
