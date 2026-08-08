<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaveh_events', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('type', 32)->index();
            $table->string('name')->index();
            $table->string('level', 32)->default('info');
            $table->string('environment', 64)->nullable();
            $table->string('hostname')->nullable();
            $table->string('trace_id')->nullable()->index();
            $table->json('user')->nullable();
            $table->json('context')->nullable();
            $table->json('tags')->nullable();
            $table->decimal('duration_ms', 12, 3)->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaveh_events');
    }
};
