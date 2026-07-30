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
            'seller_profiles',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();

                $table->string('legal_business_name');
                $table->string('trading_name')->nullable();

                $table->string('registration_number')->nullable();
                $table->string('tax_identification_number')->nullable();

                $table->string('business_email')->nullable();
                $table->string('business_phone', 30)->nullable();
                $table->string('website')->nullable();

                $table->text('description')->nullable();

                $table->string('status', 40)
                    ->default('draft')
                    ->index();

                $table->timestamp('approved_at')->nullable();
                $table->timestamp('suspended_at')->nullable();

                $table->foreignId('approved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('suspended_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('suspension_reason')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index('registration_number');
                $table->index('tax_identification_number');
            }
        );

        Schema::create(
            'seller_members',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('seller_profile_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('role', 30);
                $table->string('status', 30)
                    ->default('active')
                    ->index();

                $table->foreignId('invited_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('invited_at')->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('removed_at')->nullable();

                $table->timestamps();

                $table->unique([
                    'seller_profile_id',
                    'user_id',
                ]);
            }
        );

        Schema::create(
            'addresses',
            function (Blueprint $table): void {
                $table->id();

                $table->morphs('addressable');

                $table->string('type', 30);
                $table->string('contact_name')->nullable();
                $table->string('contact_phone', 30)->nullable();

                $table->string('country', 100);
                $table->string('province')->nullable();
                $table->string('district')->nullable();
                $table->string('sector')->nullable();
                $table->string('cell')->nullable();
                $table->string('village')->nullable();

                $table->string('address_line');
                $table->string('postal_code')->nullable();

                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();

                $table->boolean('is_default')->default(false);

                $table->timestamps();
                $table->softDeletes();

                $table->index([
                    'addressable_type',
                    'addressable_id',
                    'type',
                ]);
            }
        );

        Schema::create(
            'seller_applications',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();

                $table->foreignId('seller_profile_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->unsignedInteger('version')->default(1);

                $table->string('status', 50)
                    ->default('draft')
                    ->index();

                $table->text('seller_message')->nullable();
                $table->text('information_request')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('review_started_at')->nullable();
                $table->timestamp('decided_at')->nullable();

                $table->foreignId('current_reviewer_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('decided_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique([
                    'seller_profile_id',
                    'version',
                ]);
            }
        );

        Schema::create(
            'seller_documents',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();

                $table->foreignId('seller_profile_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('seller_application_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();

                $table->foreignId('uploaded_by')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('document_type', 80);
                $table->string('status', 40)
                    ->default('quarantined')
                    ->index();

                $table->string('original_name');
                $table->string('storage_disk', 50)
                    ->default('private');

                $table->string('storage_path');
                $table->string('mime_type', 150);
                $table->unsignedBigInteger('size_bytes');

                $table->string('checksum_sha256', 64);

                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();

                $table->timestamp('scanned_at')->nullable();
                $table->text('scan_result')->nullable();

                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index([
                    'seller_profile_id',
                    'document_type',
                ]);

                $table->index('expires_at');
                $table->index('checksum_sha256');
            }
        );

        Schema::create(
            'verification_reviews',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('seller_application_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('reviewer_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('decision', 50)->index();

                $table->text('reason')->nullable();
                $table->text('internal_notes')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();
            }
        );

        Schema::create(
            'document_access_logs',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('seller_document_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('action', 50);
                $table->ipAddress('ip_address')->nullable();
                $table->text('user_agent')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamp('created_at')->useCurrent();

                $table->index([
                    'seller_document_id',
                    'created_at',
                ]);
            }
        );

        Schema::create(
            'notification_templates',
            function (Blueprint $table): void {
                $table->id();

                $table->string('key')->unique();
                $table->string('channel', 30);
                $table->string('subject')->nullable();
                $table->text('body');

                $table->json('variables')->nullable();

                $table->boolean('is_active')->default(true);

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('document_access_logs');
        Schema::dropIfExists('verification_reviews');
        Schema::dropIfExists('seller_documents');
        Schema::dropIfExists('seller_applications');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('seller_members');
        Schema::dropIfExists('seller_profiles');
    }
};