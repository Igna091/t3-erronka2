<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Runs on every deploy, so every seeder must be safe to repeat.
     */
    public function run(): void
    {
        $this->call(MedicineSeeder::class);
    }
}
