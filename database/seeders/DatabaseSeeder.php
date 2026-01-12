<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // wspólne hasło dla wszystkich
        $password = Hash::make('inzynierka');

        // ADMIN
        User::create([
            'name' => 'Admin',
            'email' => 'admin@inz.test',
            'role' => 'admin',
            'password' => $password,
        ]);

        // TENANT (najemca)
        User::create([
            'name' => 'Tenant',
            'email' => 'tenant@inz.test',
            'role' => 'tenant',
            'password' => $password,
        ]);

        // OWNER (właściciel)
        User::create([
            'name' => 'Owner',
            'email' => 'owner@inz.test',
            'role' => 'owner',
            'password' => $password,
        ]);

        $this->call([
            PropertySeeder::class,
        ]);
    }
}
