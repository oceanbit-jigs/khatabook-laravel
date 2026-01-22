<?php

namespace Database\Factories;

use App\User; // Use your exact namespace
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName,
            'middle_name' => $this->faker->optional()->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'is_email_verify' => $this->faker->boolean,
            'phone' => $this->faker->unique()->numerify('###########'),
            'is_phone_verify' => $this->faker->boolean,
            'image_url' => $this->faker->optional()->imageUrl(),
            'contact_name' => $this->faker->optional()->name,
            'fcm_token' => $this->faker->optional()->uuid,
            'password' => bcrypt('password'),
            'email_verified_at' => $this->faker->optional()->dateTimeThisYear(),
            'remember_token' => Str::random(10),
        ];
    }
}
