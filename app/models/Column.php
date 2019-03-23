<?php

namespace URD\models;

class Column {

    public $tabell;
    public $kolonne;
    public $ledetekst;
    public $datatype; // date, float, integer, string
    public $size;
    public $nullable;
    public $default;
    public $unik = 0;
    public $element = 'input';
    public $attr = array();
    public $vis;
    public $gruppe;
    public $rekkefolge;
    public $standard_sokeverdi;
    public $description;

    function __construct($schema, $tbl, $name, $datatype, $size, $not_null=null) {
        // TODO: Sett ledetekst basert på kolonnenavnet
        $this->databasemal = $schema;
        $this->tabell = $tbl;
        $this->kolonne = $name;
        $this->datatype = $datatype;
        $this->size = $size;
        if ($not_null) {
            $this->nullable = 0;
        } else {
            $this->nullable = 1;
        }
    }

    public function create_view_column($view, $db_name, $pk_list)
    {
        switch ($view) {
        case 'updated':
            $this->label = 'Sist oppdatert';
            $this->default = "
                SELECT max(tidsstempel)
                FROM {$urd->name}.logg
                WHERE database_ = '$db_name'
                  AND tabell = '{$this->tabell}'
                  AND prim_nokkel = $pk_list";

            $this->element = 'input';
            $this->attr['type'] = 'date';
            return $this;
        case 'updated_by':
            $this->label = 'Sist oppdatert av';
            $this->default = "
                SELECT MAX(endret_av)
                FROM {$urd->name}.logg l1
                WHERE l1.database_ = '$db_name'
                  AND l1.tabell = '{$this->tabell}'
                  AND l1.prim_nokkel = $pk_list
                  AND l1.tidsstempel = (
                    SELECT MAX(tidsstempel)
                    FROM {$urd->name}.logg l2
                    WHERE l2.database_ = '$db_name'
                      AND l2.tabell = '{$this->tabell}'
                      AND l2.prim_nokkel = $pk_list)";

            $this->element = 'select';
            return $this;
        }
    }
}
