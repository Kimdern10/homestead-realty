<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Adds the sample homes, cities and agents (skipped if any homes already exist).
     * Create the real admin login with: php artisan app:create-admin
     */
    public function run(): void
    {
        $this->call(SampleDataSeeder::class);
    }
}
