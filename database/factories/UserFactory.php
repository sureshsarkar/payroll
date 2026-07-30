<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 2026-05-29 — Hardened email uniqueness.
        //
        // Was: `fake()->unique()->safeEmail()`. Faker's safeEmail draws
        // from a small finite pool of names → emails. With multiple
        // residual rows from earlier (interrupted) test runs in the
        // DB, plus Faker's uniqueness cache resetting between test
        // classes, the pool was exhausted in ways that surfaced as
        // `users_email_unique` violations (the `cbraun@example.com`
        // flake on TeacherBatchAssignmentTest > destroy soft remove).
        //
        // The fix combines a Faker name with a microtime + random
        // suffix so collisions are statistically impossible across
        // the lifetime of the test process.
        $localPart = strtolower(Str::slug(fake()->firstName() . '-' . fake()->lastName()))
            . '-' . substr((string) microtime(true), -8)
            . '-' . Str::random(4);
        return [
            'name'              => fake()->name(),
            'email'             => $localPart . '@e2e-factory.test',
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        // return $this->state(fn (array $attributes) => [
        //     'email_verified_at' => null,
        // ]);
    }
}
