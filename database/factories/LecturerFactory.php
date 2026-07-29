<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lecturer>
 */
class LecturerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'staff_code' => fake()->unique()->regexify('[A-Z0-9]{6}'),
            'slug' => fake()->unique()->slug(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'department_id' => null,
            'degree' => null,
            'academic_title' => null,
            'phone' => null,
            'positions' => null,
        ];
    }
}
