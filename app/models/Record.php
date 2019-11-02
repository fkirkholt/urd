<?php

namespace URD\models;

use URD\models\Schema;
use URD\models\Database as DB;
use URD\models\Table;
use URD\models\Expression;
use Dibi\Type;

class Record {

    function __construct($db_name, $tbl_name, $primary_key)
    {
        $this->db = DB::get($db_name);
        $this->db_name = $db_name;
        $this->tbl = Table::get($db_name, $tbl_name);
        $this->primary_key = $primary_key;
    }

    function get()
    {
        // relations get parsed and values added to
        // $this->tbl->selects and $this->tbl->joins

        $joins = $this->tbl->get_joins();
        $view = $this->tbl->get_view();

        // -------------------------------
        // Get values for the table fields
        // -------------------------------

        // Holds all select statements for getting field values for the record
        $selects = [];

        foreach ($this->tbl->fields as $field_alias => $field) {

            if (isset($field->view) && !isset($field->datatype)) {
                $selects[$field_alias] = "($field->view)" . ' AS ' . $field_alias;
            } else {
                $selects[$field_alias] = $field->table . '.' . $field_alias;
            }
        }

        $join = implode("\n", $joins);

        $selects = array_unique($selects);
        // kolonnene som skal velges
        $select_sql = implode(', ', $selects);

        $conditions = array();
        foreach ($this->primary_key as $field_name => $value) {
            $conditions[] = "{$this->tbl->name}.$field_name = '$value'";
        }
        $cond = implode(' AND ', $conditions);
        // Must use pure sql so that dibi doesn't strip single quotes
        // round numbers. We want numbers with leading zero as strings in database,
        // but there must be a bug i dibi that strips quotes even if we use %s
        $where = 'WHERE %SQL'; // . implode(' AND ', $conditions);

        $sql = "SELECT $select_sql
                FROM   $view {$this->tbl->name}
                       $join
                $where";

        $row = $this->db->query($sql, $cond)->setFormat(Type::DATETIME, 'Y-m-d H:i:s')->fetch();


        // Build array over fields, with value and other properties
        $this->tbl->permission = $this->tbl->get_user_permission($this->tbl->name);
        $fields = [];
        foreach ($this->tbl->fields as $alias=>$field) {
            // TODO: Denne genererer feil for view-kolonner
            $field->value = $row[$alias];
            // trigger_error(json_encode($field));
            $field->editable = isset($field->editable) ? $field->editable : $this->tbl->permission->edit;
            $field->datatype = isset($field->datatype) ? $field->datatype : null;

            $fields[$alias] = $field;
        }

        // ---------------------------------------
        // Get textual representation of relations
        // ---------------------------------------

        $visninger = array();


        foreach ($this->tbl->fields as $alias => $field) {
            if (isset($field->view)) {
                $visninger[$alias] = "($field->view) AS $alias";
            }
        }

        if (count($visninger)) {

            $kolonner_visningsdata_sql = implode(', ', $visninger);

            $sql_view = "SELECT $kolonner_visningsdata_sql
                FROM   $view {$this->tbl->name}
                $join
                $where";

            $row = $this->db->conn->query($sql_view, $cond)->fetch();

            foreach ($row as $field_name => $value) {
                $field = $fields[$field_name];
                $field->text = $value;

                // TODO: Is this necessary
                if (!isset($field->foreign_key)) continue;

                // Don't load options if there's a reference to current table in condition
                $searchable = false;
                if (!empty($field->foreign_key->filter)) {
                    $tbl_name = $this->tbl->name;
                    $pattern = "/\b$tbl_name\.\b/";
                    if (preg_match($pattern, $field->foreign_key->filter)) {
                        $searchable = true;
                    }
                }
                if ($searchable) continue;

                if (isset($field->view)) {
                    $field->options = $this->tbl->get_options($field, $fields);
                }

                $fields[$field_name] = $field;
            }
        }

        // Don't let $fields be reference to $this->tbl->fields
        $fields = json_decode(json_encode($fields), true);

        foreach ($fields as $alias => $field) {
            $field = (object) $field;
            $field->name = $field->alias;
            unset($field->alias);
            $fields[$alias] = $field;
        }

        return [
            'base_name'    => $this->db_name,
            'table_name'   => $this->tbl->name,
            'primary_key'  => $this->primary_key,
            'fields'       => $fields,
            'sql'          => $sql,
        ];
    }

    function get_relations($count = false, $relation_alias = null)
    {
        // Don't try to get record for new records that's not saved
        if (!empty($this->primary_key) && !in_array(null, (array) $this->primary_key)) {
            $rec = $this->get();
        }

        $relations = [];

        if (!isset($this->tbl->relations)) return [];

        foreach ($this->tbl->relations as $alias => $rel) {

            if ($relation_alias && $relation_alias !== $alias) continue;

            $rel->schema = !empty($rel->schema) ? $rel->schema : $this->db->schema;

            $rel->db_name = $rel->schema !== $this->db->schema
                ? (new Schema($rel->schema))->get_db_name()
                : $this->db->name;

            $rel->table = !empty($rel->table) ? $rel->table : $alias;

            $tbl_rel = Table::get($rel->db_name, $rel->table);

            $permission = $tbl_rel->get_user_permission();
            if ($permission->view === 0) continue;

            $rel->fk_columns = $tbl_rel->foreign_keys[$rel->foreign_key]->local;
            $rel->ref_columns = $tbl_rel->foreign_keys[$rel->foreign_key]->foreign;

            // Add condition to fetch only rows that link to record
            foreach ($rel->fk_columns as $i => $fk_field_alias) {
                $fk_field = $tbl_rel->fields[$fk_field_alias];
                $ref_field_alias = $rel->ref_columns[$i];
                $ref_field = $this->tbl->fields[$ref_field_alias];

                $value = reset($this->primary_key) ? $rec['fields'][$ref_field_alias]->value : null;
                $tbl_rel->add_condition("$rel->table.$fk_field_alias = '$value'");
            }

            if (!empty($rel->filter)) {
                $tbl_rel->add_condition($rel->filter);
            }

            // if relations should be counted, get record count
            // else get all relations
            if ($count) {
                $start = microtime(true);
                $conditions = $tbl_rel->get_conditions();
                $condition = count($conditions) ? 'WHERE '.implode(' AND ', $conditions) : '';
                $count_records = $tbl_rel->get_record_count($condition);
                $end = microtime(true);
                $relation = [
                    'count_records' => $count_records,
                    'time' => $end - $start,
                    'name' => $rel->table,
                    'conditions' => $conditions,
                ];

            } else { // get all relations
                // TODO: Are these necessary?
                $tbl_rel->limit = 500;
                $tbl_rel->offset = 0;
                $tbl_rel->csv = false;

                $relation = $tbl_rel->hent_tabell();
                $relation['db_name'] = $rel->db_name;

                // Finds condition for relation
                $values = [];
                foreach ($rel->ref_columns as $ref_field) {
                    $values[] = reset($this->primary_key) ? $rec['fields'][$ref_field]->value : null;
                }

                foreach ($rel->fk_columns as $idx => $fk_column) {
                    $relation['fields'][$fk_column]->default = $values[$idx];
                    $relation['fields'][$fk_column]->defines_relation = true;
                }
            }


            $relations[$alias] = $relation;
        }

        return $relations;
    }

    public function get_children()
    {
        $rec = $this->get();

        $relations = array_filter((array) $this->tbl->relations, function($relation) {
            return $relation->table === $this->tbl->name;
        });
        $rel = reset($relations);

        // TODO Support composite key
        $ref_column = $this->tbl->primary_key[0];
        $value = $rec['fields'][$ref_column]->value;
        $this->tbl->add_condition("$rel->table.$rel->foreign_key = '$value'");

        if (!empty($rel->filter)) {
            $this->tbl->add_condition($rel->filter);
        }

        $relation = $this->tbl->hent_tabell();

        return $relation['records'];
    }

    /**
     * Inserts new records into database
     *
     * @param  object $values Values to insert into database
     * @return object primary key
     */
    public function insert($values)
    {
        $inserts = [];
        // Get values for auto and auto_update fields
        foreach ($this->tbl->fields as $fieldname => $field) {
            if (in_array($field->extra, ['auto', 'auto_update'])) {
                $values->$fieldname = $this->db->expr()->replace_vars($field->default);
            }
        }

        // Array of values to be inserted, grouped by table, in order to support
        // extension tables, i.e 1:1 relations
        $tbl_inserts = [];

        foreach ($values as $field_alias => $value) {
            $field = $this->tbl->fields[$field_alias];

            if (!isset($tbl_inserts[$field->table])) $tbl_inserts[$field->table] = [];

            if ($value === '') {
                $value = null;
            }

            $tbl_inserts[$field->table][$field->name] = $value;
        }

        // Inserts into main table first
        {
            // Finds if primary key is auto_increment
            $first_pk_field = $this->tbl->primary_key[0];
            $autoinc = $this->tbl->fields[$first_pk_field]->extra == 'auto_increment';

            if ($autoinc) {
                $result = $this->db->insert($this->tbl->name, $tbl_inserts[$this->tbl->name])->execute(\dibi::IDENTIFIER);

                foreach ($this->tbl->primary_key as $fieldname) {
                    $this->primary_key->$fieldname = $result;
                }
            } else {
                $result = $this->db->insert($this->tbl->name, $tbl_inserts[$this->tbl->name])->execute();
            }

            unset($tbl_inserts[$this->tbl->name]);
        }

        // Inserts into extension tables
        foreach ($tbl_inserts as $tbl_name => $insert) {

            $primary_key = $this->get_pk_values($tbl_name);

            $insert = array_merge($insert, (array) $primary_key);

            $result = $this->db->insert($tbl_name, $insert)->execute();
        }

        return $this->primary_key;
    }

    /**
     * Updates record in database and return updated values
     *
     * @param array|object $values
     * @return array
     */
    public function update($values)
    {
        $values = (array) $values;

        foreach ($this->tbl->fields as $fieldname => $field) {
            if ($field->extra == 'auto_update') {
                $values[$fieldname] = $this->db->expr()->replace_vars($field->default);
            }
        }

        // Array of values to be updated, grouped by table, in order to support
        // extension tables, i.e 1:1 relations
        $tbl_values = [];

        foreach ($values as $field_alias => $value) {
            if ($value === '') {
                $value = null;
            }
            $fields = $this->tbl->fields;
            $tbl_name = $fields[$field_alias]->table;
            $field_name = $fields[$field_alias]->name;
            if (!isset($tbl_values[$tbl_name])) $tbl_values[$tbl_name] = [];
            $tbl_values[$tbl_name][$field_name] = $value;
        }

        // Updates database
        foreach ($tbl_values as $tbl_name => $tbl_values) {
            // Check if field is in 1:1 relation table and that record exists
            $primary_key = $this->get_pk_values($tbl_name);
            $count = $this->db->select('*')
                ->from($tbl_name)
                ->where((array) $primary_key)
                ->count();

            if ($count === 0) {
                // If a record doesn't exist in extension table, we make an insert
                $tbl_values = array_merge($tbl_values, (array) $primary_key);
                $sql = $this->db->insert($tbl_name, $tbl_values)->execute();
            } else {
                $result = $this->db->update($tbl_name, (array) $tbl_values)
                    ->where((array) $primary_key)->execute();
            }
        }

        return $values;
    }

    public function delete()
    {
        $relations = $this->get_relations();

        foreach ($relations as $rel) {
            foreach ($rel['records'] as $rec) {
                $record = new Record($rel['db_name'], $rel['name'], (object) $rec['primary_key']);
                $record->delete();
            }
        }

        $primary_key = $this->get_pk_values($this->tbl->name);

        $result = $this->db->delete($this->tbl->name)
            ->where((array) $primary_key)->execute();

        return $result;
    }

    /**
     * Get primary key values for record
     *
     * @param string $table_name - table name
     */
    protected function get_pk_values($table_name)
    {
        // if ($table_name === $this->tbl->name) return $this->primary_key;

        $prim_key = [];

        // Can't use $this->tbl because the table might be extension table
        $table = Table::get($this->db->name, $table_name);
        $pk_keys = array_keys((array) $this->primary_key);

        foreach ($table->primary_key as $i => $field_alias) {
            $field_name = $table->fields[$field_alias]->name;
            $prim_key[$field_name] = $this->primary_key->{$pk_keys[$i]};
        }

        return (object) $prim_key;
    }

}
