<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 90)->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('url');
            $table->string('normalized_url', 500)->unique();
            $table->string('destination_host', 253)->index();
            $table->string('short_description', 240);
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('logo_url')->nullable();
            $table->text('image_url')->nullable();
            $table->text('contact_email');
            $table->unsignedBigInteger('total_bid_cents')->default(0)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('click_count')->default(0);
            $table->unsignedBigInteger('impression_count')->default(0);
            $table->timestamp('last_bid_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('metadata_status', 24)->default('pending');
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'total_bid_cents', 'last_bid_at']);
            $table->index(['category_id', 'status', 'total_bid_cents', 'last_bid_at'], 'category_rank_idx');
        });

        Schema::create('listing_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('purpose', 24)->default('manage');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_reference')->unique();
            $table->string('direction', 12)->default('credit');
            $table->unsignedBigInteger('amount_cents');
            $table->bigInteger('signed_delta_cents');
            $table->unsignedBigInteger('previous_total_cents');
            $table->unsignedBigInteger('new_total_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('payment_provider', 32);
            $table->string('payment_reference')->nullable()->index();
            $table->string('payment_status', 24)->default('pending')->index();
            $table->unsignedInteger('ranking_before')->nullable();
            $table->unsignedInteger('ranking_after')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bid_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_transaction_id')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 24)->default('pending')->index();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_transaction_id']);
        });

        Schema::create('daily_listing_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('bot_filtered')->default(0);
            $table->timestamps();
            $table->unique(['listing_id', 'metric_date']);
        });

        Schema::create('activity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('public_message', 280);
            $table->json('metadata')->nullable();
            $table->boolean('visible')->default(true)->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('listing_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->text('reporter_email')->nullable();
            $table->string('reason', 60);
            $table->text('details')->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('domain_bans', function (Blueprint $table): void {
            $table->id();
            $table->string('normalized_domain', 253)->unique();
            $table->text('reason');
            $table->string('created_by');
            $table->timestamps();
        });

        Schema::create('marketplace_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('admin_identifier');
            $table->string('action', 80)->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_event_id');
            $table->string('event_type', 100)->index();
            $table->string('payload_hash', 64);
            $table->string('status', 24)->default('received')->index();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('marketplace_settings');
        Schema::dropIfExists('domain_bans');
        Schema::dropIfExists('listing_reports');
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('daily_listing_metrics');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('bids');
        Schema::dropIfExists('listing_access_tokens');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('categories');
    }
};
