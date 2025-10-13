<?php

namespace Database\Factories;

use App\Models\Level;
use App\Models\Student;
use App\Models\User;
use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $levelId = Level::query()->inRandomOrder()->value('id');

        return [
            'user_id'     => User::factory()->state($this->studentUserState()),
            'birth_date'   => fake()->dateTimeBetween('-18 years','-6 years')->format('Y-m-d'),
            'level_id'     => $levelId,
            'class_letter' => null,
        ];
    }

    /**
     * После создания — назначаем пользователю роль 'student'.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Student $s) {
            try {
                $u = $s->user ?? $s->user()->first();
                if ($u && method_exists($u, 'assignRole')) {
                    $u->assignRole('student');
                }
            } catch (\Throwable $e) {
                // роли ещё не посеяны — пропускаем молча
            }
        });
    }

    /**
     * Привязать к студенту N родителей (по умолчанию — двоих).
     */
    public function withGuardians(int $count = 2): static
    {
        return $this->afterCreating(function (Student $s) use ($count) {
            $guardians = Guardian::factory()->count(max(1, $count))->create();
            $s->guardians()->syncWithoutDetaching($guardians->pluck('id')->all());
        });
    }

    private function studentUserState(): array
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
