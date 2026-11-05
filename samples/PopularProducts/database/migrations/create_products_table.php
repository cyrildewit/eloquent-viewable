<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Indexed with the id, which breaks ties, so the catalog can be sorted
            // and paginated on it without sorting the whole table.
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();

            $table->index(['views_count', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
