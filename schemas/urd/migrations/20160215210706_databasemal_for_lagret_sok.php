<?php

use Phpmig\Migration\Migration;
use URD\Models\Database as DB;

class DatabasemalForLagretSok extends Migration
{
    /**
     * Do the migration
     */
    public function up()
    {
        $urd = DB::get();

        $cols = $urd->schema()->getColumns('lagret_sok');

        if (!in_array('databasemal', $cols)) {
            $urd->schema()->alter('lagret_sok', function($table) {
                $table->renameColumn('mal', 'databasemal');
            });
        }

    }

    /**
     * Undo the migration
     */
    public function down()
    {
        $urd = DB::get();

        $urd->schema()->alter('lagret_sok', function($table) {
            $table->renameColumn('databasemal', 'mal');
        });
    }
}
