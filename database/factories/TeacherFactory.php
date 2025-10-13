<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state($this->teacherUserState()),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Teacher $t) {
            try {
                $u = $t->user ?? $t->user()->first();
                if ($u && method_exists($u, 'assignRole')) {
                    $u->assignRole('teacher');
                }
            } catch (\Throwable $e) {
                // роли ещё не посеяны — пропускаем
            }
        });
    }

    private function teacherUserState(): array
    {
        return [
            'last_name'   => fake()->lastName(),
            'first_name'  => fake()->firstName(),
            'middle_name' => null,
            'email'       => fake()->unique()->safeEmail(),
            'phone'       => fake()->unique()->e164PhoneNumber(),
            'sex'         => fake()->randomElement(['male','female']),
            'password'    => 'password',
        ];
    }
}

