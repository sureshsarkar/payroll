<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes on FK columns that lack them. These are columns we know are
 * filtered/joined-on hot paths (per session-1 audit): admin order lookups,
 * student dashboard counts, course listings filtered by instructor, etc.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->index('buyer_id', 'orders_buyer_id_index');
            $t->index('seller_id', 'orders_seller_id_index');
        });

        Schema::table('order_items', function (Blueprint $t) {
            $t->index('product_id', 'order_items_product_id_index');
        });

        Schema::table('quiz_results', function (Blueprint $t) {
            $t->index('user_id', 'quiz_results_user_id_index');
            $t->index('quiz_id', 'quiz_results_quiz_id_index');
        });

        Schema::table('courses', function (Blueprint $t) {
            $t->index('instructor_id', 'courses_instructor_id_index');
        });

        // courses.added_by was referenced by an earlier draft of this
        // migration but the column itself was never created by any
        // migration in the tree (a stray manual ALTER in someone's
        // dev environment). Guarded conditionally so the index is added
        // wherever the column happens to exist, and the migration
        // succeeds otherwise.
        if (Schema::hasColumn('courses', 'added_by')) {
            Schema::table('courses', function (Blueprint $t) {
                $t->index('added_by', 'courses_added_by_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex('orders_buyer_id_index');
            $t->dropIndex('orders_seller_id_index');
        });

        Schema::table('order_items', function (Blueprint $t) {
            $t->dropIndex('order_items_product_id_index');
        });

        Schema::table('quiz_results', function (Blueprint $t) {
            $t->dropIndex('quiz_results_user_id_index');
            $t->dropIndex('quiz_results_quiz_id_index');
        });

        Schema::table('courses', function (Blueprint $t) {
            $t->dropIndex('courses_instructor_id_index');
        });

        if (Schema::hasColumn('courses', 'added_by')) {
            try {
                Schema::table('courses', function (Blueprint $t) {
                    $t->dropIndex('courses_added_by_index');
                });
            } catch (\Throwable $e) {
                // Index may not exist if up() ran before this guard was added.
            }
        }
    }
};
