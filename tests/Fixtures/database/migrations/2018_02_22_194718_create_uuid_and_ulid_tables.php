<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUuidAndUlidTables extends Migration
{
    public function up(): void
    {
        Schema::create('uuid_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('ulid_posts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('uuid_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('ulid_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ulid_users');
        Schema::dropIfExists('uuid_users');
        Schema::dropIfExists('ulid_posts');
        Schema::dropIfExists('uuid_posts');
    }
}
