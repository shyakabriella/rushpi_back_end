<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'product_orders',
            function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('order_number', 40)->unique();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('status', 30)
                    ->default('pending');

                $table->string('payment_status', 30)
                    ->default('pending');

                $table->string('payment_method', 40);

                $table->char('currency', 3)
                    ->default('RWF');

                $table->decimal('subtotal', 18, 2);
                $table->decimal('delivery_fee', 18, 2)
                    ->default(0);
                $table->decimal('total', 18, 2);

                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email', 255);
                $table->string('phone', 40);

                $table->string('delivery_method', 40);
                $table->string('delivery_province', 150);
                $table->string('delivery_district', 150);
                $table->string('delivery_sector', 150);
                $table->string('delivery_street', 255);
                $table->text('delivery_instructions')
                    ->nullable();

                $table->timestamp('placed_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')
                    ->nullable();

                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['email', 'created_at']);
                $table->index(['phone', 'created_at']);
                $table->index(['status', 'payment_status']);
                $table->index('placed_at');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
};
