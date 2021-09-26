<?php

namespace URD\models;

use URD\models\Schema;
use URD\models\Database as DB;
use URD\models\Table;
use URD\models\Expression;
use Dibi\Type;
use Symfony\Component\Filesystem\Filesystem;

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
            $selects[$field_alias] = $field->table . '.' . $field_alias;
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

        $row = $this->db->query($sql, $cond)
            ->setFormat(Type::DATETIME, 'Y-m-d H:i:s')
            ->setFormat(Type::DATE, 'Y-m-d')
            ->fetch();

        $new = false;
        if (!$row) $new = true;

        // Build array over fields, with value and other properties
        $this->tbl->privilege = $this->tbl->get_user_privilege($this->tbl->name);
        $fields = [];
        foreach ($this->tbl->fields as $alias=>$field) {
            // TODO: Denne genererer feil for view-kolonner
            $field->value = $row[$alias];
            // trigger_error(json_encode($field));
            $field->editable = isset($field->editable) ? $field->editable : $this->tbl->privilege->update;
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

        if (count($visninger) && !$new) {

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

                if (isset($field->view) && !isset($field->column_view)) {
                    $field->column_view = $field->view;
                }
                $field->options = $this->tbl->get_options($field, $fields);

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
            'new'          => $new,
            'loaded'       => true,
            'sql'          => $sql,
        ];
    }

    function get_relations($count = false, $relation_alias = null, $types = null)
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

            $privilege = $tbl_rel->get_user_privilege();
            if ($privilege->select === 0) continue;

            $rel->fk_columns = $tbl_rel->foreign_keys[$rel->foreign_key]->foreign;
            $rel->ref_columns = $tbl_rel->foreign_keys[$rel->foreign_key]->primary;

            if (array_intersect($rel->fk_columns, $tbl_rel->primary_key) == $tbl_rel->primary_key) {
                $rel->type = '1:1';
            } else {
                $rel->type = '1:M';
            }

            $parts = explode("_", $tbl_rel->name);
            $suffix_1 = end($parts);
            $suffix_2 = prev($parts);
            if (!empty($types) && in_array($suffix_1, $types)) {
                $show_if = ['type_' => $suffix_1];
            } elseif (!empty($types) && in_array($suffix_2, $types)) {
                $show_if = ['type_' => $suffix_2];
            } else {
                $show_if = null;
            }

            $pk = [];

            // Find index used
            foreach ($tbl_rel->indexes as $index) {
                if (array_slice($index->columns, 0, count($rel->fk_columns)) === $rel->fk_columns) {
                    $rel->index = $index;
                    if ($index->unique) {
                        break;
                    }
                }
            }

            // Add condition to fetch only rows that link to record
            $conds = [];
            $inherited_nulls = [];
            $inherited_values = [];
            foreach ($rel->fk_columns as $i => $fk_field_alias) {
                $fk_field = $tbl_rel->fields[$fk_field_alias];
                $ref_field_alias = $rel->ref_columns[$i];
                $ref_field = $this->tbl->fields[$ref_field_alias];

                $value = reset($this->primary_key) ? $rec['fields'][$ref_field_alias]->value : null;
                if ($tbl_rel->fields[$fk_field_alias]->nullable &&
                    $fk_field_alias != $rel->fk_columns[0] &&
                    $rel->ref_columns == array_keys($this->primary_key) &&
                    $index->uniqe
                ) {
                    $tbl_rel->add_condition("($rel->table.$fk_field_alias = '$value' or $rel->table.$fk_field_alias is null)");
                    $inherited_nulls[] = "$rel->table.$fk_field_alias is null";
                } else {
                    $tbl_rel->add_condition("$rel->table.$fk_field_alias = '$value'");
                    $inherited_values[] = "$rel->table.$fk_field_alias = '$value'";
                }

                $conds[$fk_field_alias] = $value;
                $pk[$fk_field_alias] = $value;
            }

            if (!empty($rel->filter)) {
                $tbl_rel->add_condition($rel->filter);
            }

            // if relations should be counted, get record count
            // else get all relations
            if ($count) {
                $start = microtime(true);

                // Filter on highest level
                if (!empty($tbl_rel->expansion_column) && $tbl_rel->name !== $this->tbl->name) {
                    $fk = $tbl_rel->get_parent_fk();
                    $parent_col = $tbl_rel->fields[$fk->alias];
                    $tbl_rel->add_condition("$tbl_rel->name.$parent_col->alias " . ($parent_col->default ? "= '" . $parent_col->default . "'" : "IS NULL"));
                }

                $conditions = $tbl_rel->get_conditions();
                $condition = count($conditions) ? 'WHERE '.implode(' AND ', $conditions) : '';
                $inherited_conds = array_merge($inherited_nulls, $inherited_values);
                $inherited_cond = count($inherited_nulls) ? 'WHERE ' . implode(' AND ', $inherited_conds) : '';
                $count_records = $tbl_rel->get_record_count($condition);
                $count_inherited = count($inherited_nulls) ? $tbl_rel->get_record_count($inherited_cond) : 0;
                $end = microtime(true);
                $relation = [
                    'count_records' => $count_records,
                    'count_inherited' => $count_inherited,
                    'time' => $end - $start,
                    'name' => $rel->table,
                    'conditions' => $conditions,
                    'inherited_conds' => $inherited_conds,
                    'conds' => $conds,
                    'base_name' => $rel->db_name,
                    'relationship' => $rel->type,
                    'delete_rule' => $rel->delete_rule
                ];
                if($show_if) $relation['show_if'] = $show_if;
            } else { // get all relations
                // TODO: Are these necessary?
                $tbl_rel->limit = 500;
                $tbl_rel->offset = 0;
                $tbl_rel->csv = false;

                // Filter the list on highest level when necessary
                if ($this->tbl->name !== $tbl_rel->name) {
                    $tbl_rel->user_filtered = false;
                }

                $relation = $tbl_rel->hent_tabell();

                // Finds condition for relation
                $values = [];
                foreach ($rel->ref_columns as $ref_field) {
                    $values[] = reset($this->primary_key) ? $rec['fields'][$ref_field]->value : null;
                }

                foreach ($rel->fk_columns as $idx => $fk_column) {
                    $relation['fields'][$fk_column]->default = $values[$idx];
                    $relation['fields'][$fk_column]->defines_relation = true;
                }

                if ($rel->type == "1:1") {
                    $record = new Record($this->db->name, $rel->table, $pk);
                    $relation['records'] = [$record->get()];
                    $relation['relationship'] = "1:1";
                } else {
                    $relation['relationship'] = "1:M";
                }
            }


            $relations[$alias] = $relation;
        }

        return $relations;
    }

    function get_values()
    {
        $conds = [];
        foreach ($this->primary_key as $field_name => $value) {
            $conds[] = "$field_name = '$value'";
        }
        $cond = implode(" and ", $conds);

        $sql = "select * from {$this->tbl->name} where {$cond}";

        return $this->db->conn->query($sql)->fetch();
    }

    public function get_children()
    {
        $rec = $this->get();

        $relations = array_filter((array) $this->tbl->relations, function($relation) {
            return $relation->table === $this->tbl->name;
        });
        $rel = reset($relations);

        $fk = $this->tbl->foreign_keys[$rel->foreign_key];

        foreach ($fk->foreign as $i => $col_name) {
            $primary = $fk->primary[$i];
            $value = $rec['fields'][$primary]->value;
            $this->tbl->add_condition("$rel->table.$col_name = '$value'");
        }

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

        foreach ($this->tbl->fields as $fieldname => $field) {
            //  Get values for auto and auto_update fields
            if (in_array($field->extra, ['auto', 'auto_update'])) {
                $values->$fieldname = $this->db->expr()->replace_vars($field->default);
            }

            // Workaround for problem with triggers for not null columns in MariaDB
            // https://jira.mariadb.org/browse/MDEV-19761
            if (!isset($values->$fieldname) && $field->nullable === false) {
                $values->$fieldname = null;
            }
        }

        // Get autoinc values for compound primary keys
        $last_pk_col = end($this->tbl->primary_key);
        if (
            empty($values->$last_pk_col) &&
            count($this->tbl->primary_key) > 1 &&
            $this->tbl->fields[$last_pk_col]->extra == 'auto_increment'
        ) {
            $length = count($this->tbl->primary_key) - 1;
            $cols = array_slice($this->tbl->primary_key, 0 , $length);
            $vals = [];

            foreach ($cols as $col) {
                $vals[$col] = $values->$col;
            }

            $next = $this->db->fetchSingle(
                'select case when max(%n) is null then 1 else max(%n) +1 end from %n where %and', 
                $last_pk_col, $last_pk_col, $this->tbl->name, $vals
            );

            $values->$last_pk_col = $next;
        }

        // Array of values to be inserted
        $tbl_inserts = [];

        foreach ($values as $field_alias => $value) {
            $field = $this->tbl->fields[$field_alias];

            if ($value === '') {
                $value = null;
            }

            $tbl_inserts[$field->name] = $value;
        }


        // Finds if primary key is auto_increment
        $first_pk_field = $this->tbl->primary_key[0];
        $autoinc = $this->tbl->fields[$first_pk_field]->extra == 'auto_increment';

        if ($autoinc) {
            $result = $this->db->insert($this->tbl->name, $tbl_inserts)->execute(\dibi::IDENTIFIER);

            foreach ($this->tbl->primary_key as $fieldname) {
                $this->primary_key->$fieldname = $result;
            }
        } else {
            $result = $this->db->insert($this->tbl->name, $tbl_inserts)->execute();
            foreach ($this->tbl->primary_key as $fieldname) {
                $this->primary_key->$fieldname = $tbl_inserts[$fieldname];
            }
        }

        unset($tbl_inserts[$this->tbl->name]);

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

        // Array of values to be updated
        $tbl_values = [];

        foreach ($values as $field_alias => $value) {
            if ($value === '') {
                $value = null;
            }
            $fields = $this->tbl->fields;
            $field_name = $fields[$field_alias]->name;
            $tbl_values[$field_name] = $value;
        }

        $result = $this->db->update($this->tbl->name, (array) $tbl_values)
                ->where((array) $this->primary_key)->execute();

        // Update primary key
        foreach ($values as $key => $value) {
            if (isset($this->primary_key->{$key})) {
                $this->primary_key->{$key} = $value;
            }
        }

        return $values;
    }

    public function delete()
    {
        $result = $this->db->delete($this->tbl->name)
            ->where((array) $this->primary_key)->execute();

        return $result;
    }

    public function get_file_path()
    {
        $columns = [];
        foreach ($this->tbl->indexes as $index) {
            if ($index->name == $this->tbl->name . '_file_path_idx') {
                foreach ($index->columns as $col_name) {
                    $columns[] = $col_name;
                }
            }
        }

        $select = implode("|| '/' ||", $columns);

        $conditions = array();
        foreach ($this->primary_key as $field_name => $value) {
            $conditions[] = "{$this->tbl->name}.$field_name = '$value'";
        }
        $cond = implode(' AND ', $conditions);


        $sql = "SELECT $select
                FROM {$this->tbl->name}
                WHERE %SQL";

        $path = $this->db->query($sql, $cond)->fetchSingle();

        $fs = new Filesystem;

        $abs = $fs->isAbsolutePath($path);

        if (!$abs) {
            $app = \Slim\Slim::getInstance();
            $fileroot = $app->config('fileroot');
            if (!$fileroot) {
                return false;
            }
            $path = $fileroot . '/' . $this->db->name . '/' . $path;
        }

        return $path;
    }

}
