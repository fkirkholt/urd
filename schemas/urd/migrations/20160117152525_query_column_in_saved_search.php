<?php

use Phpmig\Migration\Migration;
use URD\Models\Database as DB;

class QueryColumnInSavedSearch extends Migration
{
    /**
     * Do the migration
     */
    public function up()
    {
        $urd = DB::get();

        $urd->schema()->alter('lagret_sok', function($table) {
            $table->boolean('spoerring')->defaultValue(0)->notNull();
        });

    }

    /**
     * Undo the migration
     */
    public function down()
    {
        $urd = DB::get();

        $urd->schema()->alter('lagret_sok', function($table) {
            $table->dropColumn('spoerring');
        });
    }
}
