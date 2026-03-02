<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('inzynierka');

        User::updateOrCreate(
            ['email' => 'admin@inz.test'],
            [
                'username' => 'admin',
                'name' => 'System',
                'surname' => 'Admin',
                'role' => 'admin',
                'password' => $password,
                'phone' => '+48111111111',
                'address' => 'ul. Admina 1, Warszawa',
                'postal_code' => '00-001',
            ]
        );

        User::updateOrCreate(
            ['email' => 'owner@inz.test'],
            [
                'username' => 'owner',
                'name' => 'Jan',
                'surname' => 'Wlasciciel',
                'role' => 'owner',
                'password' => $password,
                'phone' => '+48222222222',
                'address' => 'ul. Wlascicielska 2, Krakow',
                'postal_code' => '30-002',
            ]
        );

        User::updateOrCreate(
            ['email' => 'tenant@inz.test'],
            [
                'username' => 'tenant',
                'name' => 'Anna',
                'surname' => 'Najemca',
                'role' => 'tenant',
                'password' => $password,
                'phone' => '+48333333333',
                'address' => 'ul. Najemcy 3, Gdansk',
                'postal_code' => '80-003',
            ]
        );

        User::factory()->count(3)->create(['role' => 'admin']);
        User::factory()->count(8)->create(['role' => 'owner']);
        User::factory()->count(28)->create(['role' => 'tenant']);

        $this->call([
            PropertySeeder::class,
            SystemTablesSeeder::class,
        ]);
    }
}
