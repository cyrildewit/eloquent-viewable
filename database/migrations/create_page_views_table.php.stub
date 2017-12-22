<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePageViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $pageViewTableName = config('page-view-counter.page_views_table_name');

        Schema::create($pageViewTableName, function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('visitable_id')->unsigned();
            $table->string('visitable_type');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $pageViewTableName = config('page-view-counter.page_views_table_name');

        Schema::dropIfExists($pageViewTableName);
    }
}
