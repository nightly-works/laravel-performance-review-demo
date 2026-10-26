<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The order list filters by status and sorts by date: one index serves both, and the page count. Lines are
 * read by order: MySQL's InnoDB indexes a foreign key column by itself, PostgreSQL and SQLite do not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        if (! $this->hasIndexOn('order_items', 'order_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index('order_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        if (Schema::hasIndex('order_items', 'order_items_order_id_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropIndex(['order_id']);
            });
        }
    }

    private function hasIndexOn(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'][0] === $column) {
                return true;
            }
        }

        return false;
    }
};
