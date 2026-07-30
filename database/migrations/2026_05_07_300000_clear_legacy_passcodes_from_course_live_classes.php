<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('course_live_classes')->update([
            'password' => '',
            'join_url' => null,
        ]);
    }

    public function down(): void
    {
        // Passcodes were intentionally cleared — there is nothing to restore.
    }
};
