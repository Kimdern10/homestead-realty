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
     * There is no demo content: the admin adds cities, agents and homes in the dashboard.
     * The usual home types and amenities are added by their migration.
     * Create the first admin login with: php artisan app:create-admin
     */
    public function run(): void
    {
        //
    }
}
