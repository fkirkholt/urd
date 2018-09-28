<?php

function parse($fieldref) {
    // Eks: $fieldref = uttrekk.deponering.arkivskaper.navn
    $refs = explode('.', $fieldref);
    $table_name = array_shift($refs);
    //-> $table_name = uttrekk
    //-> $refs = [deponering, arkivskaper, navn]
    $field = array_pop($refs);
    //-> $field = navn
    //-> $refs = [deponering, arkivskaper]
    $prev_tbl = $this; // $this is here the table the query runs against (uttrekk)
    foreach($refs as $i=>$ref) {
        $join_ref = implode('.', array_slice($refs, 0, $i+1));
        //-> $i == 0: $join_ref = deponering
        //-> $i == 1: $join_ref = deponering.arkivskaper
        if (!array_key_exists($join_ref, $this->joins)) {
            $fk = $prev_table->cols->{$ref};
            // $i == 0: $fk = uttrekk->cols->deponering
            // $i == 1: $fk = deponering->cols->arkivskaper

            // TODO: Check if database is initiated before
            // TODO: Is $fk->ref_base set? The database has 'kandidatmal'
            $join_db = Database::get_instance($fk->ref_base);
            // TODO: Shouldn't make new table if it has been created before
            $tbl = new Table($join_db, $fk->ref_table);
            $tbl->load_columns();
            $tbl->alias = 't'.count($this->joins);
            $join = "LEFT JOIN $fk->ref_base.$fk->ref_table $tbl->alias\n"
                  ."ON $tbl->alias.$fk->ref_key= $prev_tbl->alias.$fk->kolonne";
            $this->joins[$join_ref] = $join;
        }
    }

    /// Makes fields

    // Adds 1, 2 etc. to alias if field name exists
    // TODO: Access to array $field_aliases? Well - should be OK in Table-class
    if (array_key_exists($field, $this->field_aliases)) {
        $this->field_aliases[$field] = $this->field_aliases[$field] + 1;
        $field_alias = $field . $this->field_aliases[$field];
    } else {
        $this->field_aliases[$field] = 0;
        $field_alias = $field;
    }
    $this->fields[] = "$tbl->alias.$field AS $field_alias";
}
