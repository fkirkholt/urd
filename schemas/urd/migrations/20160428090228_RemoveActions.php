<?php

use Phpmig\Migration\Migration;
use URD\Models\Database as DB;

class RemoveActions extends Migration
{
    /**
     * Do the migration
     */
    public function up()
    {
        $urd = DB::get();

        $urd->from('handling')
            ->where('databasemal')->is('urd')
            ->where('tabell')->is('database_')
            ->where('nr')->in([1, 2, 3])
            ->delete();

        $urd->update('handling')
            ->where('databasemal')->is('urd')
            ->where('tabell')->is('database_')
            ->set(['skriptadresse' => '/update_schema_tables']);
    }

    /**
     * Undo the migration
     */
    public function down()
    {

    }
}
