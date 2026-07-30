<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-coach white-label — custom-domain GOVERNANCE layer (2026-06-09).
 *
 * The mapping / resolution / verification core already exists. This
 * migration adds the corporate governance fields the admin panel and
 * the status state machine need — all ADDITIVE and nullable, so it is
 * safe on a live system and rolls back cleanly.
 *
 *   coach_domains:
 *     status           pending|verified|active|failed|suspended
 *     last_verified_at  last successful DNS check (distinct from
 *                       verified_at, which means "live / may resolve")
 *     verify_attempts   failed-attempt counter
 *     last_error        last verification failure reason (coach-facing)
 *     ssl_status        none|pending|issued|failed
 *     ssl_checked_at    last SSL state observation
 *     approved_at/by    strict-mode admin approval
 *     rejected_reason   admin rejection note
 *
 *   coach_domain_events  append-only audit trail
 *
 *   settings (key/value) seed:
 *     custom_domain_enabled            feature flag
 *     custom_domain_server_ip          A-record target shown + verified
 *     custom_domain_requires_approval  strict approval mode
 *     custom_domain_max_per_coach      quota
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coach_domains')) {
            Schema::table('coach_domains', function (Blueprint $table) {
                if (! Schema::hasColumn('coach_domains', 'status')) {
                    $table->enum('status', ['pending', 'verified', 'active', 'failed', 'suspended'])
                          ->default('pending')->after('verified_at')->index();
                }
                if (! Schema::hasColumn('coach_domains', 'last_verified_at')) {
                    $table->timestamp('last_verified_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('coach_domains', 'verify_attempts')) {
                    $table->unsignedSmallInteger('verify_attempts')->default(0)->after('last_verified_at');
                }
                if (! Schema::hasColumn('coach_domains', 'last_error')) {
                    $table->string('last_error', 500)->nullable()->after('verify_attempts');
                }
                if (! Schema::hasColumn('coach_domains', 'ssl_status')) {
                    $table->enum('ssl_status', ['none', 'pending', 'issued', 'failed'])
                          ->default('none')->after('last_error');
                }
                if (! Schema::hasColumn('coach_domains', 'ssl_checked_at')) {
                    $table->timestamp('ssl_checked_at')->nullable()->after('ssl_status');
                }
                if (! Schema::hasColumn('coach_domains', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('ssl_checked_at');
                }
                if (! Schema::hasColumn('coach_domains', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
                }
                if (! Schema::hasColumn('coach_domains', 'rejected_reason')) {
                    $table->string('rejected_reason', 500)->nullable()->after('approved_by');
                }
            });

            // Backfill: anything already verified is ACTIVE; the rest start
            // PENDING. Keeps the live system + existing tests consistent.
            DB::table('coach_domains')->whereNotNull('verified_at')->update(['status' => 'active']);
            DB::table('coach_domains')->whereNull('verified_at')->update(['status' => 'pending']);
        }

        if (! Schema::hasTable('coach_domain_events')) {
            Schema::create('coach_domain_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coach_domain_id')->nullable()->index();
                $table->unsignedBigInteger('coach_id')->nullable()->index();
                $table->string('hostname', 255)->nullable();
                $table->string('event', 40);          // added, verify_attempt, verified,
                                                       // failed, approved, rejected,
                                                       // suspended, resumed, ssl_issued,
                                                       // ssl_failed, removed, renamed
                $table->string('actor_type', 20)->default('system'); // coach|admin|system
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('ip', 45)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        $this->seedSettings();
    }

    protected function seedSettings(): void
    {
        if (! Schema::hasTable('settings')) return;

        $defaults = [
            'custom_domain_enabled'           => '0',
            'custom_domain_server_ip'         => (string) (config('app.platform_ip') ?: ''),
            'custom_domain_requires_approval' => '0',
            'custom_domain_max_per_coach'     => '1',
        ];
        $now = now();
        foreach ($defaults as $key => $value) {
            $exists = DB::table('settings')->where('key', $key)->exists();
            if (! $exists) {
                DB::table('settings')->insert([
                    'key' => $key, 'value' => $value,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        \Illuminate\Support\Facades\Cache::forget('setting');
    }

    public function down(): void
    {
        if (Schema::hasTable('coach_domains')) {
            Schema::table('coach_domains', function (Blueprint $table) {
                foreach ([
                    'status', 'last_verified_at', 'verify_attempts', 'last_error',
                    'ssl_status', 'ssl_checked_at', 'approved_at', 'approved_by', 'rejected_reason',
                ] as $col) {
                    if (Schema::hasColumn('coach_domains', $col)) {
                        if ($col === 'status') {
                            // drop the index created with the column
                            try { $table->dropIndex(['status']); } catch (\Throwable $e) {}
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('coach_domain_events');

        if (Schema::hasTable('settings')) {
            DB::table('settings')->whereIn('key', [
                'custom_domain_enabled', 'custom_domain_server_ip',
                'custom_domain_requires_approval', 'custom_domain_max_per_coach',
            ])->delete();
            \Illuminate\Support\Facades\Cache::forget('setting');
        }
    }
};
