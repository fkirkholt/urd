<?php

use Phpmig\Migration\Migration;
use URD\Models\Database as DB;
use URD\Models\Template;

class CreateTables extends Migration
{
    /**
     * Do the migration
     */
    public function up()
    {
        $urd = DB::get();

        $urd->schema()->create('bruker', function($table) {
            $table->string('id', 30)->autoincrement();
            $table->string('navn', 50)->notNull();
            $table->string('passord', 12);
            $table->integer('organisasjon')->unsigned();
        });

        $urd->schema()->create('database_', function($table) {
            $table->string('databasenavn', 30)->primary();
            $table->string('platform', 50);
            $table->string('host', 50);
            $table->integer('port')->size('tiny');
            $table->string('username', 30);
            $table->string('password', 30);
            $table->boolean('vis')->notNull()->defaultValue(1);
            $table->string('betegnelse')->notNull();
            $table->boolean('produksjon')->notNull()->defaultValue(1);
            $table->string('kommentar', 1000);
            $table->string('dokumentlager', 100);
            $table->string('databasemal', 50);
            $table->date('fra_dato');
            $table->date('til_dato');
        });

        $urd->schema()->create('databasemal', function($table) {
            $table->string('mal', 50)->primary();
            $table->string('betegnelse', 50)->notNull();
        });

        $urd->schema()->create('datatype', function($table) {
            $table->string('datatype', 20)->primary();
        });

        $urd->schema()->create('felttype', function($table) {
            $table->string('felttype', 30)->primary();
            $table->string('betegnelse', 30)->notNull();
            $table->string('beskrivelse', 400);
        });

        $urd->schema()->create('format', function($table) {
            $table->string('mal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->string('klasse', 30)->notNull();
            $table->string('betingelse', 250);
            $table->primary('format_pk', ['mal', 'tabell', 'klasse']);
        });

        $urd->schema()->create('handling', function($table) {
            $table->string('databasemal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->integer('nr')->size('tiny');
            $table->string('betegnelse', 50)->notNull();
            $table->string('skriptadresse', 100)->notNull();
            $table->string('betingelse', 400);
            $table->string('kommunikasjon', 15)->notNull();
            $table->string('beskrivelse', 1000);
        });

        $urd->schema()->create('ikon', function($table) {
            $table->string('navn', 30)->primary();
        });

        $urd->schema()->create('kolonne', function($table) {
            $table->string('databasemal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->string('kolonne', 30)->notNull();
            $table->string('ledetekst', 50);
            $table->string('datatype', 20)->notNull();
            $table->string('lengde', 5); // Allows decimal defined as e.g. 5,2
            $table->boolean('obligatorisk')->notNull()->defaultValue(0);
            $table->string('standardverdi', 1000);
            $table->string('extra', 30);
            $table->boolean('unik')->notNull()->defaultValue(0);
            $table->string('felttype', 30)->notNull()->defaultValue('textfield');
            $table->string('visningsformat', 30);
            $table->string('attributter', 512);
            $table->integer('vis')->size('tiny')->notNull()->defaultValue(1);
            $table->integer('gruppe')->size('tiny');
            $table->decimal('rekkefolge', 5, 2);
            $table->string('alternativt_sokefelt', 60);
            $table->string('beskrivelse', 1000);
            $table->string('kommentar', 1000);
        });

        $urd->schema()->create('kolonnegruppe', function($table) {
            $table->string('databasemal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->integer('nr')->size('tiny');
            $table->string('betegnelse', 100);
            $table->boolean('lukket')->defaultValue(0);
        });

        $urd->schema()->create('lagret_sok', function($table) {
            $table->integer('id')->autoincrement();
            $table->string('databasemal', 30);
            $table->string('tabell', 30)->notNull();
            $table->string('sokeverdier', 1000)->notNull();
            $table->string('betegnelse', 50)->notNull();
            $table->string('bruker', 30);
            $table->boolean('standard')->notNull()->defaultValue(0);
            $table->boolean('sperring')->notNull()->defaultValue(0);
        });

        $urd->schema()->create('logg', function($table) {
            $table->integer('id')->autoincrement();
            $table->string('database_', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->string('kolonne', 30);
            $table->string('prim_nokkel', 200)->notNull();
            $table->string('endret_av', 30)->notNull();
            $table->timestamp('tidsstempel');
            $table->string('type', 30)->notNull();
            $table->string('ny_verdi', 4000);
        });

        $urd->schema()->create('modul', function($table) {
            $table->integer('id')->autoincrement();
            $table->string('databasemal', 30)->notNUll();
            $table->integer('rekkefolge')->size('tiny')->notNull();
            $table->string('betegnelse', 50)->notNull();
            $table->integer('overskrift');
        });

        $urd->schema()->create('organisasjon', function($table) {
            $table->integer('id')->autoincrement();
            $table->string('navn', 200)->notNull();
            $table->integer('underlagt');
        });

        $urd->schema()->create('overskrift', function($table) {
            $table->integer('id')->autoincrement();
            $table->string('databasemal', 30);
            $table->string('betegnelse', 50);
            $table->integer('rekkefolge')->size('tiny');
        });

        $urd->schema()->create('rapport', function($table) {
            $table->integer('id')->autoincrement();
            $table->string('navn', 50)->notNull();
            $table->string('databasemal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->string('felter', 1000);
            $table->string('betingelser', 1000);
        });

        $urd->schema()->create('relasjon', function($table) {
            $table->string('databasemal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->string('fremmednokkel', 100)->notNull();
            $table->integer('relasjonsvisning')->size('tiny')->notNull();
            $table->string('kandidatmal', 30);
            $table->string('kandidattabell', 30);
            $table->string('kandidatnokkel', 100);
            $table->string('tabellvisning', 150);
            $table->string('postvisning', 150);
            $table->string('kandidatbetingelse', 1000);
            $table->string('relasjonsbetegnelse', 50);
            $table->primary('relasjon_pk', ['databasemal', 'tabell', 'fremmednokkel']);
        });

        $urd->schema()->create('rettighet', function($table) {
            $table->string('bruker', 30)->notNull();
            $table->string('databasemal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->boolean('vise')->notNull()->defaultValue(0);
            $table->boolean('legge_til')->notNull()->defaultValue(0);
            $table->boolean('redigere')->notNull()->defaultValue(0);
            $table->boolean('slette')->notNull()->defaultValue(0);
            $table->boolean('admin')->notNull()->defaultValue(0);
            $table->primary('rettighet_pk', ['bruker', 'databasemal', 'tabell']);
        });

        $urd->schema()->create('tabell', function($table) {
            $table->string('databasemal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->integer('modul');
            $table->string('ikon', 100);
            $table->string('ledetekst', 70);
            $table->boolean('grunndata')->notNull()->defaultValue(0);
            $table->boolean('koblingstabell')->notNull()->defaultValue(0);
            $table->boolean('vis')->notNull()->defaultValue(1);
            $table->decimal('rekkefolge', 5, 2);
            $table->string('prim_nokkel', 50)->notNull();
            $table->string('sortering', 50);
            $table->string('betingelse', 1000);
            $table->boolean('kan_legge_til')->notNull()->defaultValue(1);
            $table->boolean('kan_redigere')->notNull()->defaultValue(1);
            $table->boolean('kan_slettes')->notNull()->defaultValue(1);
            $table->boolean('kan_redigere_eksternt')->notNull()->defaultValue(0);
            $table->string('summeringsfelter', 100);
            $table->string('sti', 1000);
            $table->string('beskrivelse', 1000);
            $table->string('kommentar', 1000);
        });

        $urd->schema()->create('visningsformat', function($table) {
            $table->string('visningsformat', 30)->primary();
            $table->string('betegnelse', 30)->notNull();
            $table->string('felttype', 30);
        });


        // Add database description to urd tables
        $templ = new Template('urd');
        $templ->populate_urd_from_schema();

    }

    /**
     * Undo the migration
     */
    public function down()
    {

    }
}
