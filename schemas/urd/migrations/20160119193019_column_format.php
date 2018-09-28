<?php

use Phpmig\Migration\Migration;
use URD\Models\Database as DB;

class ColumnFormat extends Migration
{
    /**
     * Do the migration
     */
    public function up()
    {
        $urd = DB::get();

        $urd->schema()->create('format', function($table) {
            $table->string('mal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->string('klasse', 30)->notNull();
            $table->string('betingelse', 250)->notNull();
            $table->primary('format_pk', ['mal', 'tabell', 'klasse']);
        });
    }

    /**
     * Undo the migration
     */
    public function down()
    {
        $urd = DB::get();

        $urd->schema()->drop('format');
    }
}
