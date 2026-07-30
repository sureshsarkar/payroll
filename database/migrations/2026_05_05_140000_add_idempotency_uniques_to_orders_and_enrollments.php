<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            DELETE e1 FROM enrollments e1
            INNER JOIN enrollments e2
              ON e1.user_id   = e2.user_id
             AND e1.course_id = e2.course_id
             AND e1.id        > e2.id
        ');

        Schema::table('orders', function (Blueprint $t) {
            $t->unique('transaction_id', 'orders_transaction_id_unique');
            $t->unique('invoice_id', 'orders_invoice_id_unique');
        });

        Schema::table('enrollments', function (Blueprint $t) {
            $t->unique(['user_id', 'course_id'], 'enrollments_user_course_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropUnique('orders_transaction_id_unique');
            $t->dropUnique('orders_invoice_id_unique');
        });

        Schema::table('enrollments', function (Blueprint $t) {
            $t->dropUnique('enrollments_user_course_unique');
        });
    }
};
