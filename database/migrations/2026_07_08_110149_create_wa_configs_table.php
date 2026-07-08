<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wa_configs', function (Blueprint $table) {
            $table->id();
            $table->text('monitored_devices')->nullable(); // JSON or comma-separated string
            $table->text('allowed_senders')->nullable();   // JSON or comma-separated string
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_configs');
    }
};
