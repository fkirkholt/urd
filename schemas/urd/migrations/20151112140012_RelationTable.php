<?php

use Phpmig\Migration\Migration;
use URD\Models\Database as DB;


class RelationTable extends Migration
{
    /**
     * Do the migration
     */
    public function up()
    {
        $urd = DB::get();

        echo "Create table `relasjon`\n";
        $urd->schema()->create('relasjon', function($table) {
            $table->string('databasemal', 30)->notNull();
            $table->string('tabell', 30)->notNull();
            $table->string('fremmednokkel', 100)->notNull();
            $table->integer('relasjonsvisning')->size('tiny')->unsigned()
                ->notNull()->defaultValue(1);
            $table->string('kandidatmal', 30);
            $table->string('kandidattabell', 30);
            $table->string('kandidatnokkel', 100);
            $table->string('tabellvisning', 150);
            $table->string('postvisning', 150);
            $table->string('kandidatbetingelse', 1000);
            $table->string('relasjonsbetegnelse', 50);
            $table->primary('relasjon_pk', ['databasemal', 'tabell', 'fremmednokkel']);
        });

        echo "Move data from `kolonne` to `relasjon`\n";
        $sql = <<<SQL
INSERT INTO relasjon (
    databasemal, tabell, fremmednokkel, relasjonsvisning,
    kandidatmal, kandidattabell, kandidatnokkel, kandidatbetingelse,
    tabellvisning, postvisning, relasjonsbetegnelse)
SELECT databasemal, tabell, kolonne, relasjonsvisning,
    kandidatmal, kandidattabell, kandidatnokkel, kandidatbetingelse,
    kandidatvisning, kandidatvisning, relasjonsbetegnelse
FROM   kolonne
WHERE  postvisning = 1 AND kandidatnokkel IS NOT NULL
SQL;
        $urd->query($sql);

        echo "Add column `vis` to replace `tabellvisning` and `postvisning`\n";
        $urd->schema()->alter('kolonne', function($table) {
            $table->integer('vis')->size('tiny')->notNull()->defaultValue(1);
        });

        
        $sql = "UPDATE kolonne
                SET vis = CASE WHEN tabellvisning = 1 THEN 2
                               WHEN postvisning   = 1 THEN 1
                               ELSE 0
                          END";

        $urd->query($sql);

        $composites = $urd->from('kolonne')
            ->where('kolonne')->like('%,%')
            ->where('postvisning')->is(1)
            ->select()
            ->all();

        foreach ($composites as $comp) {
            $fields = explode(',', $comp->kolonne);
            $field = trim(end($fields));
            if ((int) $comp->tabellvisning === 1) {
                $vis = 2;
            } elseif ((int) $comp->postvisning === 1) {
                $vis = 1;
            } else {
                $vis = 0;
            }

            $urd->update('kolonne')
                ->where('databasemal')->is($comp->databasemal)
                ->where('tabell')->is($comp->tabell)
                ->where('kolonne')->is($field)
                ->set([
                    'vis' => $vis,
                    'felttype' => $comp->felttype,
                    'visningsformat' => $comp->visningsformat,
                    'gruppe' => $comp->gruppe,
                    'rekkefolge' => $comp->rekkefolge,
                ]);
        }

    }

    /**
     * Undo the migration
     */
    public function down()
    {
        $urd = DB::get();

        $urd->schema()->drop('relasjon');

        $urd->schema()->alter('kolonne', function($table) {
            $table->dropColumn('vis');
        });
    }
}
