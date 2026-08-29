<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaveh_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('kaveh_organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('kaveh_organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('kaveh_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('kaveh_organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('retention_days')->default(14);
            $table->unsignedBigInteger('max_events')->default(1_000_000);
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('kaveh_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('kaveh_projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 12);
            $table->string('key_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kaveh_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('kaveh_projects')->cascadeOnDelete();
            $table->string('uuid')->index();
            $table->string('type', 32)->index();
            $table->string('name')->index();
            $table->string('level', 32)->default('info')->index();
            $table->string('environment', 64)->nullable()->index();
            $table->string('hostname')->nullable();
            $table->string('trace_id')->nullable()->index();
            $table->json('user')->nullable();
            $table->json('context')->nullable();
            $table->json('tags')->nullable();
            $table->decimal('duration_ms', 12, 3)->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['project_id', 'uuid']);
        });

        Schema::create('kaveh_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('kaveh_projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('metric'); // exception_rate, failed_jobs, custom_event
            $table->string('event_name')->nullable();
            $table->unsignedInteger('threshold')->default(10);
            $table->unsignedInteger('window_minutes')->default(5);
            $table->unsignedInteger('cooldown_minutes')->default(30);
            $table->string('channel'); // webhook, email
            $table->string('target'); // url or email
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kaveh_alert_firings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained('kaveh_alert_rules')->cascadeOnDelete();
            $table->unsignedInteger('count');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Only the kaveh_* names are dropped here. The <= 0.1.4 generic names
        // are never dropped by Kaveh: `events`/`projects` may belong to the host
        // app. See 2026_08_09_000001_rename_legacy_server_tables.
        Schema::dropIfExists('kaveh_alert_firings');
        Schema::dropIfExists('kaveh_alert_rules');
        Schema::dropIfExists('kaveh_events');
        Schema::dropIfExists('kaveh_api_keys');
        Schema::dropIfExists('kaveh_projects');
        Schema::dropIfExists('kaveh_organization_user');
        Schema::dropIfExists('kaveh_organizations');
    }
};
