<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zoom_credentials', function (Blueprint $table) {
            // Meeting SDK app credentials. Zoom requires a SEPARATE app
            // type ("Meeting SDK") for SDK signature signing — Server-to-
            // Server OAuth Client ID/Secret cannot sign valid SDK JWTs.
            // sdk_key is semi-public (sent to the browser via signature
            // payload). sdk_secret is encrypted at rest.
            if (!Schema::hasColumn('zoom_credentials', 'sdk_key')) {
                $table->string('sdk_key')->nullable()->after('client_secret');
            }
            if (!Schema::hasColumn('zoom_credentials', 'sdk_secret')) {
                $table->text('sdk_secret')->nullable()->after('sdk_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zoom_credentials', function (Blueprint $table) {
            if (Schema::hasColumn('zoom_credentials', 'sdk_secret')) {
                $table->dropColumn('sdk_secret');
            }
            if (Schema::hasColumn('zoom_credentials', 'sdk_key')) {
                $table->dropColumn('sdk_key');
            }
        });
    }
};
