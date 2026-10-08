<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('timezone')->default('UTC');
            // The Monday of the last week a digest went out for, as a date on
            // the author's clock.
            $table->date('digested_week')->nullable();
            $table->timestamps();
        });

        Schema::create('essays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained();
            $table->string('title');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('essays');
        Schema::dropIfExists('authors');
    }
};
