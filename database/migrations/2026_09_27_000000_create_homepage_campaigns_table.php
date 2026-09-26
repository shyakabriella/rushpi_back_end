<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->string('title', 180);
            $table->string('subtitle', 255)->nullable();

            $table->string('desktop_image_path', 1000);
            $table->string('mobile_image_path', 1000)->nullable();

            $table->string('background_color', 20)
                ->default('#ffffff');

            $table->string('text_color', 20)
                ->default('#0f172a');

            $table->string('button_text', 80)
                ->default('Shop now');

            $table->enum('link_type', [
                'category',
                'brand',
                'product',
                'custom',
                'none',
            ])->default('none');

            $table->string('link_value', 1000)->nullable();

            $table->enum('card_size', [
                'large',
                'medium',
                'small',
            ])->default('medium');

            $table->unsignedInteger('position')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index([
                'is_active',
                'starts_at',
                'ends_at',
            ], 'homepage_campaign_schedule_index');

            $table->index([
                'position',
                'id',
            ], 'homepage_campaign_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_campaigns');
    }
};
