<?php

namespace Database\Factories;

use App\Models\Guardian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state($this->guardianUserState()),
        ];
    }
    /**
     * После создания — назначаем пользователю роль 'guardian'.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Guardian $g) {
            try {
                $u = $g->user ?? $g->user()->first();
                if ($u && method_exists($u, 'assignRole')) {
                    $u->assignRole('guardian');
                }
            } catch (\Throwable $e) {
                // роли ещё не посеяны — пропускаем молча
            }
        });
    }

    private function guardianUserState(): array
    {
        return [
            'last_name'   => fake()->lastName(),
            'first_name'  => fake()->firstName(),
            'middle_name' => null,
            'email'       => fake()->unique()->safeEmail(),
            'phone'       => fake()->unique()->e164PhoneNumber(),
            'sex'         => fake()->randomElement(['male','female']),
            'password'    => 'password', // захэшируется через casts в User
        ];
    }
}
