<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', fn (Blueprint $table) => $table->unsignedBigInteger('refunded_cents')->default(0));
    }

    public function down(): void
    {
        Schema::table('payment_transactions', fn (Blueprint $table) => $table->dropColumn('refunded_cents'));
    }
};
