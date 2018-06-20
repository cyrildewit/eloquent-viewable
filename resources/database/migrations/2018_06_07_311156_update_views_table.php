<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try {
            Schema::rename('page-views', 'views');

            Schema::table('views', function (Blueprint $table) {
                $table->string('ip_address')->nullable(false)->change();

                $table->dropColumn('updated_at');

                $table->renameColumn('created_at', 'viewed_at');

                $table->renameColumn('visitable_id', 'viewable_id');
                $table->renameColumn('visitable_type', 'viewable_type');
                $table->renameColumn('ip_address', 'visitor');
            });
        } catch (\Exception $e) {
            dd($e);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        try {
            Schema::table('views', function (Blueprint $table) {
                $table->renameColumn('visitor', 'ip_address');
                $table->renameColumn('viewable_type', 'visitable_type');
                $table->renameColumn('viewable_id', 'visitable_id');

                $table->renameColumn('viewed_at', 'created_at');
            });

            Schema::table('views', function (Blueprint $table) {
                $table->timestamp('updated_at')->after('created_at');

                $table->string('ip_address')->nullable()->change();
            });

            Schema::rename('views', 'page-views');
        } catch (\Exception $e) {
            dd($e);
        }
    }
}
