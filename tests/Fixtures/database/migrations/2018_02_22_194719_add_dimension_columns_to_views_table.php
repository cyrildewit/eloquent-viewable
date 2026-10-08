<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The columns `views:dimensions` adds for the built-in dimensions, so a test
 * only has to list a dimension in config to record it.
 */
class AddDimensionColumnsToViewsTable extends Migration
{
    public const array Columns = ['source', 'medium', 'campaign', 'referrer_host', 'device', 'country'];

    public function up(): void
    {
        Schema::table('views', function (Blueprint $table): void {
            foreach (self::Columns as $column) {
                $table->string($column, 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('views', function (Blueprint $table): void {
            $table->dropColumn(self::Columns);
        });
    }
}
