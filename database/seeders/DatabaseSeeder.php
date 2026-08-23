<?php

namespace Database\Seeders;

use App\Features\Concerts\Models\Concert;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $studio = Studio::factory()->create([
            'name' => 'DancePro Demo Studio',
            'slug' => 'dancepro-demo-studio',
            'description' => 'A local demonstration studio for exploring the customer concert experience.',
            'brand_color' => '#0AA0DB',
        ]);

        Concert::factory()->published()->for($studio)->create([
            'name' => 'A Night in Motion',
            'slug' => 'a-night-in-motion',
            'description' => 'A demonstration concert ready for local media to be attached.',
            'event_date' => now()->toDateString(),
            'access_password_hash' => 'dancepro',
        ]);
    }
}
