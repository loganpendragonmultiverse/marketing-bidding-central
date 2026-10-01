<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('requested_name', 100);
            $table->text('reason')->nullable();
            $table->text('example_url')->nullable();
            $table->text('requester_email')->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_requests');
    }
};
