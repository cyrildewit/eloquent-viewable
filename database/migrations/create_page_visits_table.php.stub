<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePageVisitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $pageVisitsTableName = config('page-visits-counter.page_visits_table_name', 'page-visits');

        Schema::create($pageVisitsTableName, function (Blueprint $table) {
            $table->increments('id')->unsigned();

            $table->bigInteger('visitable_id')->unsigned();
            $table->string('visitable_type');

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
        $pageVisitsTableName = config('page-visits-counter.page_visits_table_name', 'page-visits');

        Schema::dropIfExists(pageVisitsTableName);
    }
}
