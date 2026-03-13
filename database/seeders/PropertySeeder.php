<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PriceHistory;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\Tax;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $owners = User::query()->where('role', 'owner')->get();
        if ($owners->isEmpty()) {
            $owners = User::factory()->count(6)->create(['role' => 'owner']);
        }

        $tenants = User::query()->where('role', 'tenant')->get();
        if ($tenants->isEmpty()) {
            $tenants = User::factory()->count(20)->create(['role' => 'tenant']);
        }

        $owners->each(function (User $owner) use ($tenants): void {
            $properties = Property::factory()
                ->count(random_int(5, 10))
                ->create(['owner_user_id' => $owner->id]);

            foreach ($properties as $property) {
                $photosCount = random_int(1, 5);
                for ($i = 1; $i <= $photosCount; $i++) {
                    PropertyPhoto::query()->create([
                        'property_id' => $property->id,
                        'properties_name' => $property->name,
                        'photo_name' => sprintf('property-%d-photo-%d.jpg', $property->id, $i),
                        'alt_name' => fake()->sentence(3),
                        'path' => sprintf('images/properties/%d/%s.jpg', $property->id, Str::lower(Str::random(12))),
                        'is_main' => $i === 1,
                    ]);
                }

                foreach (['rent' => $property->rent_cost, 'utility' => $property->utilities_cost, 'additional' => $property->additional_costs] as $type => $price) {
                    $entries = random_int(1, 4);
                    for ($i = 0; $i < $entries; $i++) {
                        PriceHistory::query()->create([
                            'property_id' => $property->id,
                            'properties_name' => $property->name,
                            'type' => $type,
                            'price' => max(0, (float) $price + fake()->randomFloat(2, -250, 350)),
                            'updated_at' => now()->subDays(random_int(0, 360))->setTime(random_int(9, 18), random_int(0, 59), random_int(0, 59)),
                        ]);
                    }
                }

                $contractsCount = random_int(1, 3);
                for ($contractIndex = 0; $contractIndex < $contractsCount; $contractIndex++) {
                    $status = fake()->randomElement(['draft', 'active', 'terminated', 'expired']);
                    $hasEndDate = in_array($status, ['terminated', 'expired'], true) || fake()->boolean(65);

                    $contract = Contract::query()->create([
                        'property_id' => $property->id,
                        'properties_name' => $property->name,
                        'contract_number' => 'CTR-' . strtoupper(Str::random(10)),
                        'start_date' => now()->subMonths(random_int(1, 24))->toDateString(),
                        'end_date' => $hasEndDate ? now()->addMonths(random_int(1, 18))->toDateString() : null,
                        'monthly_rent' => max(0, (float) $property->rent_cost + fake()->randomFloat(2, -200, 300)),
                        'deposit' => max(0, (float) $property->rent_cost + fake()->randomFloat(2, 0, 2000)),
                        'status' => $status,
                        'path' => fake()->optional()->filePath(),
                        'filename' => fake()->optional()->word() . '.pdf',
                        'payment_method' => fake()->randomElement(['cash', 'bank_transfer']),
                    ]);

                    $contractTenantsCount = min(random_int(1, 3), $tenants->count());
                    $selectedTenants = collect($tenants->random($contractTenantsCount));

                    foreach ($selectedTenants->values() as $index => $tenant) {
                        ContractTenant::query()->create([
                            'contract_id' => $contract->id,
                            'user_id' => $tenant->id,
                            'users_username' => $tenant->username,
                            'contracts_contract_number' => $contract->contract_number,
                            'is_primary' => $index === 0,
                        ]);
                    }

                    $primaryTenant = $selectedTenants->first();
                    if (!$primaryTenant) {
                        continue;
                    }

                    $paymentsCount = random_int(1, 6);
                    for ($paymentIndex = 0; $paymentIndex < $paymentsCount; $paymentIndex++) {
                        $amount = max(100, (float) $contract->monthly_rent + fake()->randomFloat(2, -100, 500));
                        $paymentStatus = fake()->randomElement(['to_be_paid', 'in_progress', 'confirmed']);
                        $paidAmount = $paymentStatus === 'confirmed' ? $amount : fake()->optional()->randomFloat(2, 0, $amount);
                        $paymentDate = $paymentStatus === 'confirmed'
                            ? now()->subDays(random_int(0, 90))->toDateString()
                            : null;

                        $payment = Payment::query()->create([
                            'contract_id' => $contract->id,
                            'paid_by_user_id' => $primaryTenant->id,
                            'username' => $primaryTenant->username,
                            'contract_number' => $contract->contract_number,
                            'payment_number' => 'PAY-' . strtoupper(Str::random(10)),
                            'invoice_title' => 'Czynsz ' . now()->subMonths($paymentIndex)->format('m/Y'),
                            'invoice_description' => fake()->sentence(),
                            'amount' => $amount,
                            'due_date' => now()->addDays(random_int(-20, 40))->toDateString(),
                            'status' => $paymentStatus,
                            'paid_amount' => $paidAmount,
                            'payment_date' => $paymentDate,
                            'paid_by' => fake()->randomElement(['cash', 'bank_transfer']),
                        ]);

                        if (fake()->boolean(75)) {
                            Invoice::query()->create([
                                'payment_id' => $payment->id,
                                'payments_payment_number' => $payment->payment_number,
                                'invoice_name' => 'FV-' . $payment->payment_number . '.pdf',
                                'invoice_path' => 'documents/invoices/' . $payment->payment_number . '.pdf',
                            ]);
                        }
                    }

                    $ticketCount = random_int(1, 4);
                    for ($ticketIndex = 0; $ticketIndex < $ticketCount; $ticketIndex++) {
                        $ticketNumber = 'TKT-' . strtoupper(Str::random(10));

                        $ticket = Ticket::query()->create([
                            'ticket_number' => $ticketNumber,
                            'contract_number' => $contract->contract_number,
                            'username' => $primaryTenant->username,
                            'property_id' => $property->id,
                            'created_by_user_id' => $primaryTenant->id,
                            'title' => fake()->sentence(4),
                            'description' => fake()->paragraph(),
                            'attachment' => fake()->optional()->filePath(),
                            'status' => fake()->randomElement(['open', 'in_progress', 'resolved', 'closed']),
                            'latest_response' => fake()->boolean(70)
                                ? now()->subDays(random_int(0, 30))->setTime(random_int(9, 18), random_int(0, 59), random_int(0, 59))
                                : null,
                        ]);

                        $repliesCount = random_int(1, 3);
                        for ($replyIndex = 0; $replyIndex < $repliesCount; $replyIndex++) {
                            $responder = fake()->boolean(60) ? $owner : $primaryTenant;
                            $reply = TicketReply::query()->create([
                                'ticket_id' => $ticket->id,
                                'tickets_ticket_number' => $ticketNumber,
                                'responded_by_user_id' => $responder->id,
                                'reply_title' => fake()->sentence(3),
                                'reply_description' => fake()->paragraph(),
                                'attachment' => fake()->optional()->filePath(),
                                'responded_at' => now()->subDays(random_int(0, 30))->setTime(random_int(9, 18), random_int(0, 59), random_int(0, 59)),
                            ]);

                            Attachment::query()->create([
                                'ticket_id' => $ticket->id,
                                'ticket_reply_id' => $reply->id,
                                'tickets_ticket_number' => $ticketNumber,
                                'ticket_number' => $reply->tickets_ticket_number,
                                'responded_at' => $reply->responded_at,
                                'attachment_name' => fake()->lexify('attachment-????') . '.txt',
                                'attachment_path' => 'documents/tickets/' . $ticket->ticket_number . '/' . Str::lower(Str::random(8)) . '.txt',
                            ]);
                        }
                    }

                    Notification::query()->create([
                        'user_id' => $primaryTenant->id,
                        'users_username' => $primaryTenant->username,
                        'notification' => fake()->sentence(),
                        'type' => fake()->randomElement(['in_app', 'email', 'full']),
                        'data' => [
                            'contract_id' => $contract->id,
                            'property_id' => $property->id,
                            'owner_id' => $owner->id,
                        ],
                        'is_read' => fake()->boolean(40),
                        'sent_at' => now()->subDays(random_int(0, 20))->setTime(random_int(9, 18), random_int(0, 59), random_int(0, 59)),
                        'read_at' => fake()->boolean(40)
                            ? now()->subDays(random_int(0, 10))->setTime(random_int(9, 18), random_int(0, 59), random_int(0, 59))
                            : null,
                    ]);
                }
            }

            $periods = [
                [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
                [now()->subYear()->startOfYear()->toDateString(), now()->subYear()->endOfYear()->toDateString()],
            ];

            foreach ($periods as [$from, $to]) {
                $income = fake()->randomFloat(2, 20000, 250000);
                $rate = fake()->randomFloat(2, 8.5, 19.0);

                Tax::query()->updateOrCreate(
                    [
                        'owner_user_id' => $owner->id,
                        'period_from' => $from,
                        'period_to' => $to,
                    ],
                    [
                        'username' => $owner->username,
                        'contract_number' => $contract->contract_number,
                        'tax_rate_percent' => $rate,
                        'income_base_amount' => $income,
                        'tax_amount' => round($income * ($rate / 100), 2),
                        'due_date' => fake()->dateTimeBetween($to, Carbon::parse($to)->addMonths(6)->toDateString())->format('Y-m-d'),
                        'status' => fake()->randomElement(['to_be_paid', 'in_progress', 'confirmed']),
                        'paid_amount' => fake()->optional()->randomFloat(2, 0, $income * ($rate / 100)),
                        'payment_date' => fake()->boolean(60) ? fake()->dateTimeBetween('-12 months', 'now')->format('Y-m-d') : null,
                        'paid_by' => fake()->randomElement(['cash', 'bank_transfer']),
                    ]
                );
            }
        });
    }
}
