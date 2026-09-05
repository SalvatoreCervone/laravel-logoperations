<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::create([
            'name'     => 'Mario Rossi',
            'email'    => 'mario.rossi@example.com',
            'password' => bcrypt('password'),
        ]);

        User::create([
            'name'     => 'Luigi Bianchi',
            'email'    => 'luigi.bianchi@example.com',
            'password' => bcrypt('password'),
        ]);

        User::create([
            'name'     => 'Giulia Verdi',
            'email'    => 'giulia.verdi@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
