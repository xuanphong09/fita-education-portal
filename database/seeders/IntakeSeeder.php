<?php

namespace Database\Seeders;

use App\Models\Intake;
use Illuminate\Database\Seeder;

class IntakeSeeder extends Seeder
{
    public function run(): void
    {
        $intakes = ['K65', 'K66', 'K67', 'K68', 'K69', 'K70', 'K71','K72', 'K73'];

        foreach ($intakes as $name) {
            Intake::query()->updateOrCreate(['name' => $name], ['name' => $name]);
        }
    }
}

