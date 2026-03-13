<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username', 50)->unique()->after('id');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['admin', 'owner', 'tenant'])->default('tenant')->after('password');
            }
            if (!Schema::hasColumn('users', 'surname')) {
                $table->string('surname', 120)->after('name');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('surname');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 12)->nullable()->after('address');
            }
            if (!Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('postal_code');
            }
            if (!Schema::hasColumn('users', 'pesel')) {
                $table->string('pesel', 11)->nullable()->unique()->after('birth_date');
            }
            if (!Schema::hasColumn('users', 'assigned_to')) {
                $table->foreignId('assigned_to')
                    ->nullable()
                    ->after('role')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('name', 150)->unique();
            $table->string('street', 150);
            $table->string('street_number', 30);
            $table->string('apartment_number', 30);
            $table->string('city', 120);
            $table->string('postal_code', 12);
            $table->decimal('area', 10, 2);
            $table->unsignedInteger('rooms_count');
            $table->unsignedInteger('bathrooms_count')->default(1);
            $table->boolean('has_balcony')->default(false);
            $table->decimal('rent_cost', 12, 2)->default(0);
            $table->decimal('utilities_cost', 12, 2)->default(0);
            $table->decimal('additional_costs', 12, 2)->default(0);
            $table->enum('type', ['room', 'flat']);
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('property_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('properties_name', 150);
            $table->string('photo_name');
            $table->string('alt_name')->nullable();
            $table->string('path', 1024);
            $table->boolean('is_main')->default(false);
            $table->timestamp('uploaded_at')->useCurrent();
            $table->index('properties_name');
            $table->foreign('properties_name')->references('name')->on('properties')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('properties_name', 150);
            $table->enum('type', ['rent', 'utility', 'additional']);
            $table->decimal('price', 12, 2);
            $table->timestamp('updated_at')->useCurrent();
            $table->unique(['updated_at', 'properties_name']);
            $table->index('properties_name');
            $table->foreign('properties_name')->references('name')->on('properties')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('contract_number', 80)->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->decimal('monthly_rent', 12, 2)->default(0);
            $table->decimal('deposit', 12, 2)->default(0);
            $table->enum('status', ['draft', 'active', 'terminated', 'expired'])->default('draft');
            $table->string('path', 1024)->nullable();
            $table->string('filename')->nullable();
            $table->enum('payment_method', ['cash', 'bank_transfer'])->default('bank_transfer');
            $table->string('properties_name', 150);
            $table->index('properties_name');
            $table->foreign('properties_name')->references('name')->on('properties')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('contract_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->string('users_username', 50);
            $table->string('contracts_contract_number', 80);
            $table->unique(['contract_id', 'user_id']);
            $table->foreign('users_username')->references('username')->on('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('contracts_contract_number')->references('contract_number')->on('contracts')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('paid_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('payment_number', 80)->unique();
            $table->string('invoice_title');
            $table->text('invoice_description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->enum('status', ['to_be_paid', 'in_progress', 'confirmed'])->default('to_be_paid');
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('paid_by', ['cash', 'bank_transfer'])->default('bank_transfer');
            $table->timestamp('created_at')->useCurrent();
            $table->string('username', 50);
            $table->string('contract_number', 80);
            $table->index('contract_number');
            $table->index('username');
            $table->foreign('username')->references('username')->on('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('contract_number')->references('contract_number')->on('contracts')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('payments')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('payments_payment_number', 80);
            $table->string('invoice_name');
            $table->string('invoice_path', 1024);
            $table->timestamp('created_at')->useCurrent();
            $table->unique('payments_payment_number');
            $table->foreign('payments_payment_number')->references('payment_number')->on('payments')->onDelete('cascade');
        });

        Schema::create('tax', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('tax_rate_percent', 5, 2);
            $table->decimal('income_base_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->date('due_date');
            $table->enum('status', ['to_be_paid', 'in_progress', 'confirmed'])->default('to_be_paid');
            $table->decimal('paid_amount', 14, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('paid_by', ['cash', 'bank_transfer'])->default('bank_transfer');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['owner_user_id', 'period_from', 'period_to']);
            $table->string('username', 50);
            $table->string('contract_number', 80);
            $table->index('username');
            $table->index('contract_number');
            $table->foreign('username')->references('username')->on('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('contract_number')->references('contract_number')->on('contracts')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 80)->unique();
            $table->string('contract_number', 80);
            $table->string('username', 50);
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('attachment', 1024)->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('latest_response')->nullable();
            $table->index('contract_number');
            $table->index('username');
            $table->foreign('contract_number')->references('contract_number')->on('contracts')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('username')->references('username')->on('users')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->string('tickets_ticket_number', 80);
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('responded_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('reply_title')->nullable();
            $table->text('reply_description')->nullable();
            $table->string('attachment', 1024)->nullable();
            $table->timestamp('responded_at')->useCurrent();
            $table->unique(['tickets_ticket_number', 'responded_at']);
            $table->foreign('tickets_ticket_number')->references('ticket_number')->on('tickets')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('ticket_reply_id')->nullable()->constrained('ticket_replies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('attachment_name');
            $table->string('attachment_path', 1024);
            $table->timestamp('uploaded_at')->useCurrent();
            $table->string('tickets_ticket_number', 80);
            $table->string('ticket_number', 80)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreign('tickets_ticket_number')->references('ticket_number')->on('tickets')->onDelete('cascade');
            $table->foreign(['ticket_number', 'responded_at'])
                ->references(['tickets_ticket_number', 'responded_at'])
                ->on('ticket_replies')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('users_username', 50);
            $table->string('notification');
            $table->enum('type', ['in_app', 'email', 'full'])->default('in_app');
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
            $table->foreign('users_username')->references('username')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('tax');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('contract_participants');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('property_photos');
        Schema::dropIfExists('properties');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'username')) {
                $table->dropUnique('users_username_unique');
                $table->dropColumn('username');
            }
            if (Schema::hasColumn('users', 'pesel')) {
                $table->dropUnique('users_pesel_unique');
                $table->dropColumn('pesel');
            }
            $dropColumns = [];
            foreach (['role', 'surname', 'phone', 'address', 'postal_code', 'birth_date'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
