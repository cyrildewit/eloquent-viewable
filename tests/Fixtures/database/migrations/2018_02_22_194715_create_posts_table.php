<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->text('body');
            // Counter columns `views:recount` keeps, named apart from the
            // `views_count` alias the scopes select.
            $table->unsignedInteger('cached_views')->default(0);
            $table->unsignedInteger('cached_unique_views')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
}
