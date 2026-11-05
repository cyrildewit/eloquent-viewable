<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected Builder $schema;

    protected string $table;

    public function __construct()
    {
        $this->schema = Schema::connection(
            config('eloquent-viewable.models.view.connection')
        );

        $this->table = config('eloquent-viewable.retention.rollups.table');
    }

    public function up(): void
    {
        $this->schema->create($this->table, function (Blueprint $table) {
            $table->id();
            $table->string('rollup', 64);
            $table->string('tier', 8);
            $table->timestamp('bucket_start');
            $table->string('grouping', 32);
            $table->string('viewable_type');

            // The key type of the views table, null for the type groupings.
            match (Builder::$defaultMorphKeyType) {
                'uuid' => $table->uuid('viewable_id')->nullable(),
                'ulid' => $table->ulid('viewable_id')->nullable(),
                default => $table->unsignedBigInteger('viewable_id')->nullable(),
            };

            $table->string('collection')->nullable();
            $table->string('dimension')->nullable();
            $table->unsignedBigInteger('views');
            $table->unsignedBigInteger('unique_visitors');

            $table->index(['viewable_type', 'viewable_id', 'rollup', 'grouping', 'tier', 'bucket_start'], "{$this->table}_read_index");
            $table->index(['rollup', 'tier', 'bucket_start'], "{$this->table}_fold_index");
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists($this->table);
    }
};
