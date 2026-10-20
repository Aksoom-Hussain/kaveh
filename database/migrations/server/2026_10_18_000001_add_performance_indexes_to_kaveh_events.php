<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaveh_events', function (Blueprint $table) {
            if (! Schema::hasIndex('kaveh_events', 'idx_kaveh_events_project_occurred_type')) {
                $table->index(['project_id', 'occurred_at', 'type'], 'idx_kaveh_events_project_occurred_type');
            }
            if (! Schema::hasIndex('kaveh_events', 'idx_kaveh_events_project_type_occurred')) {
                $table->index(['project_id', 'type', 'occurred_at'], 'idx_kaveh_events_project_type_occurred');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kaveh_events', function (Blueprint $table) {
            if (Schema::hasIndex('kaveh_events', 'idx_kaveh_events_project_occurred_type')) {
                $table->dropIndex('idx_kaveh_events_project_occurred_type');
            }
            if (Schema::hasIndex('kaveh_events', 'idx_kaveh_events_project_type_occurred')) {
                $table->dropIndex('idx_kaveh_events_project_type_occurred');
            }
        });
    }
};
