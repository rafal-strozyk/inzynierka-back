<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemTablesSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get();
        if ($users->isEmpty()) {
            return;
        }

        $now = Carbon::now();

        foreach ($users->take(6) as $user) {
            DB::table('sessions')->updateOrInsert(
                ['id' => Str::uuid()->toString()],
                [
                    'user_id' => $user->id,
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'payload' => base64_encode(json_encode(['user_id' => $user->id, 'role' => $user->role], JSON_THROW_ON_ERROR)),
                    'last_activity' => $now->subMinutes(random_int(0, 180))->timestamp,
                ]
            );
        }

        foreach ($users->take(4) as $user) {
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make(Str::random(64)),
                    'created_at' => $now->subMinutes(random_int(0, 45)),
                ]
            );
        }

        foreach ($users->take(8) as $user) {
            DB::table('login_sessions')->insert([
                'user_id' => $user->id,
                'token' => Str::random(64),
                'created_at' => $now->copy()->subMinutes(random_int(0, 120)),
                'expires_at' => $now->copy()->addDays(random_int(1, 7)),
            ]);
        }

        for ($i = 0; $i < 6; $i++) {
            DB::table('cache')->updateOrInsert(
                ['key' => 'seed:cache:' . $i],
                [
                    'value' => json_encode(['idx' => $i, 'stamp' => $now->toIso8601String()], JSON_THROW_ON_ERROR),
                    'expiration' => $now->copy()->addHours(random_int(1, 24))->timestamp,
                ]
            );
        }

        for ($i = 0; $i < 3; $i++) {
            DB::table('cache_locks')->updateOrInsert(
                ['key' => 'seed:lock:' . $i],
                [
                    'owner' => Str::uuid()->toString(),
                    'expiration' => $now->copy()->addMinutes(random_int(5, 120))->timestamp,
                ]
            );
        }

        for ($i = 0; $i < 4; $i++) {
            DB::table('jobs')->insert([
                'queue' => fake()->randomElement(['default', 'mail', 'notifications']),
                'payload' => json_encode([
                    'uuid' => (string) Str::uuid(),
                    'displayName' => 'SeededJob',
                    'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                    'data' => ['index' => $i],
                ], JSON_THROW_ON_ERROR),
                'attempts' => random_int(0, 2),
                'reserved_at' => null,
                'available_at' => $now->copy()->addMinutes(random_int(0, 15))->timestamp,
                'created_at' => $now->copy()->subMinutes(random_int(0, 30))->timestamp,
            ]);
        }

        DB::table('job_batches')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'seeded-batch',
            'total_jobs' => 4,
            'pending_jobs' => 2,
            'failed_jobs' => 1,
            'failed_job_ids' => json_encode([1], JSON_THROW_ON_ERROR),
            'options' => json_encode(['seeded' => true], JSON_THROW_ON_ERROR),
            'cancelled_at' => null,
            'created_at' => $now->copy()->subMinutes(10)->timestamp,
            'finished_at' => null,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['seeded' => true, 'job' => 'ExampleFailedJob'], JSON_THROW_ON_ERROR),
            'exception' => 'RuntimeException: Seeded failed job example.',
            'failed_at' => $now,
        ]);
    }
}

