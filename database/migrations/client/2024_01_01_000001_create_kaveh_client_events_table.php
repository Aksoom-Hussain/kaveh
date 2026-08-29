<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The client-side event buffer.
 *
 * UPGRADE FROM <= 0.1.4: this migration used to be called
 * `..._create_kaveh_events_table` and created a table literally named
 * `kaveh_events`. 0.2.0 renamed both, so on an upgrade the old filename is
 * already in the `migrations` table but this one is not -- it therefore RUNS,
 * and must adopt the existing buffer instead of creating an empty duplicate
 * next to it.
 *
 * ORDERING: 2024_01_01 sorts before the server's 2026_08_09 rename migration,
 * and Laravel sorts every registered migration path together by filename. So on
 * a role=both upgrade this frees the name `kaveh_events` (client buffer ->
 * `kaveh_client_events`) *before* the server rename needs it as the target for
 * its own `events` table. Without that the server rename's "target must be
 * free" signal fails and its all-or-nothing batch aborts. Do not renumber.
 *
 * SAFETY: same discipline as the server rename -- the legacy table is only
 * adopted if its column set matches, exactly, what 0.1.4's client migration
 * created. The 0.1.4 client buffer has no `project_id`; the server's
 * `kaveh_events` does, so this can never grab the server's table (nor a host
 * app's own). Anything else: warn loudly and create the new table empty.
 */
return new class extends Migration
{
    /** Exact column set of `kaveh_events` as Kaveh <= 0.1.4's client migration created it. */
    private const LEGACY_COLUMNS = [
        'id', 'uuid', 'type', 'name', 'level', 'environment', 'hostname',
        'trace_id', 'user', 'context', 'tags', 'duration_ms', 'occurred_at',
        'created_at', 'updated_at',
    ];

    public function up(): void
    {
        if (Schema::hasTable('kaveh_client_events')) {
            return; // already upgraded, or renamed by hand.
        }

        if (Schema::hasTable('kaveh_events')) {
            if ($this->isLegacyClientBuffer()) {
                Schema::rename('kaveh_events', 'kaveh_client_events');

                return;
            }

            $this->warn();
        }

        Schema::create('kaveh_client_events', function (Blueprint $table) {
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
        Schema::dropIfExists('kaveh_client_events');
    }

    /**
     * Exact set equality -- a superset would mean columns were added to a table
     * that merely looks like Kaveh's, which is not proof enough.
     */
    private function isLegacyClientBuffer(): bool
    {
        $actual = array_map('strtolower', Schema::getColumnListing('kaveh_events'));
        sort($actual);

        $expected = self::LEGACY_COLUMNS;
        sort($expected);

        return $actual === $expected;
    }

    private function warn(): void
    {
        $message = 'Kaveh: `kaveh_events` exists but does not have the column signature of '
            .'Kaveh 0.1.4\'s client buffer, so it was left alone and an empty `kaveh_client_events` '
            .'was created. If it really is Kaveh\'s client buffer, rename it to `kaveh_client_events` '
            .'by hand. Note that while `kaveh_events` exists the server-side upgrade migration '
            .'cannot run either.';

        Log::warning($message);
        fwrite(STDERR, PHP_EOL.$message.PHP_EOL);
    }
};
