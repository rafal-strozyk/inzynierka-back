<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    private const ROLE_ADMIN = 'admin';

    private const ROLE_OWNER = 'owner';

    private const ROLE_TENANT = 'tenant';

    public function run(): void
    {
        $password = Hash::make('inzynierka');
        $baseBirthDate = '1985-05-12';

        User::updateOrCreate(
            ['email' => 'admin@inz.test'],
            [
                'username' => 'admin',
                'name' => 'System',
                'surname' => 'Admin',
                'role' => self::ROLE_ADMIN,
                'password' => $password,
                'birth_date' => $baseBirthDate,
                'pesel' => '70010100011',
                'phone' => '+48111111111',
                'address' => 'ul. Admina 1, Warszawa',
                'postal_code' => '00-001',
            ]
        );

        $baseOwner = User::updateOrCreate(
            ['email' => 'owner@inz.test'],
            [
                'username' => 'owner',
                'name' => 'Jan',
                'surname' => 'Wlasciciel',
                'role' => self::ROLE_OWNER,
                'password' => $password,
                'birth_date' => $baseBirthDate,
                'pesel' => '70010100022',
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
                'role' => self::ROLE_TENANT,
                'password' => $password,
                'birth_date' => $baseBirthDate,
                'pesel' => '70010100033',
                'phone' => '+48333333333',
                'address' => 'ul. Najemcy 3, Gdansk',
                'postal_code' => '80-003',
            ]
        );

        if (Schema::hasColumn('users', 'assigned_to')) {
            $baseTenant = User::query()->where('username', 'tenant')->first();
            if ($baseTenant !== null) {
                $baseTenant->update(['assigned_to' => $baseOwner->id]);
            }
        }

        User::factory()->count(3)->create(['role' => self::ROLE_ADMIN]);
        $owners = User::factory()->count(8)->create(['role' => self::ROLE_OWNER])->prepend($baseOwner);
        $ownerIds = $owners->pluck('id');
        User::factory()
            ->count(28)
            ->create(['role' => self::ROLE_TENANT])
            ->each(function (User $tenant) use ($ownerIds): void {
                if (Schema::hasColumn('users', 'assigned_to')) {
                    $tenant->update(['assigned_to' => $ownerIds->random()]);
                }
            });

        $this->call([
            PropertySeeder::class,
            SystemTablesSeeder::class,
        ]);
    }
}
