<?php

namespace URD\models;

use URD\models\Schema;
use URD\models\Expression;
use URD\models\Database as DB;
use URD\models\Column;
use dibi;
use Dibi\Type;

class Table {

    private static $instances = array();
    private $conditions = array();
    public $foreign_keys;
    private $view;
    public $limit = 30;
    public $offset = 0;

    function __construct($db_name, $tbl_name)
    {
        $this->db = Database::get($db_name);

        $tbl = $this->db->tables[$tbl_name];

        $tbl->label = $tbl->label ?: ucfirst(str_replace('_', ' ', $tbl_name));
        if (!isset($tbl->grid)) $tbl->grid = new \StdClass;
        $keys = array_keys((array) $tbl->fields);
        $tbl->grid->columns = isset($tbl->grid->columns) ? $tbl->grid->columns : array_slice(array_keys((array) $tbl->fields), 0, 5);
        $tbl->grid->sort_columns = isset($tbl->grid->sort_columns) ? $tbl->grid->sort_columns : $tbl->primary_key;

        foreach ($tbl as $attr => $value) {
            $this->$attr = $value;
        }

        $this->fields = $this->supplement_fields();

        $this->name = $tbl_name;

        if (!isset($tbl->form)) {
            $this->form = new \StdClass;
            $this->form->items = [];

            foreach ($this->fields as $alias => $field) {
                if ($field->table !== $this->name) continue;

                $this->form->items[] = $field->alias;
            }

            foreach ($this->extension_tables as $table_name) {
                $item = ['label' => $table_name, 'items' => []];
                foreach ($this->fields as $alias => $field) {
                    if ($field->table !== $table_name) continue;
                    $item['items'][] = $alias;
                }
                $this->form->items[] = $item;
            }

            foreach ($this->relations as $alias => $relation) {
                $this->form->items[] = 'relations.' . $alias;
            }
        }
    }

    public function __get($p) {
        $m = "get_$p";
        if (method_exists($this, $m)) return $this->$m();
        trigger_error("undefined property $p");
    }

    public static function get($db_name, $tbl_name) {
        if (!isset(self::$instances[$db_name])) {
            self::$instances[$db_name] = array();
        }
        if (!isset(self::$instances[$db_name][$tbl_name])) {
            self::$instances[$db_name][$tbl_name] = new Table($db_name, $tbl_name);
        }
        return self::$instances[$db_name][$tbl_name];
    }

    public function get_view() {

        if (isset($this->view)) {
            return $this->view;
        }

        $condition = !empty($this->filter)
            ? 'WHERE '. $this->db->expr()->replace_vars($this->filter)
            : '';

        // Finds view columns
        $cols = array();

        foreach ($this->fields as $alias => $col) {
            if ($col->table !== $this->name) {
                continue;
            }
            if (isset($col->source)) {
                $cols[] = "($col->source) AS $alias";
            } else if ($col->name != $alias) {
                $cols[] = "$col->name AS $alias";
            } else {
                $cols[] = $col->name;
            }
        }

        if (count($cols)) {
            $cols_list = implode(', ', $cols);
            $this->view = "
                (SELECT $cols_list
                 FROM {$this->db->alias}.$this->name
                 $condition)";
        } else if ($condition) {
            $this->view = "(SELECT $this->name.* FROM {$this->db->alias}.$this->name $condition)";
        } else {
            $this->view = "{$this->db->alias}.$this->name";
        }

        return $this->view;
    }

    public function get_joins() {
        $joins = [];
        foreach ($this->fields as $alias => $field) {
            if (
                !isset($this->foreign_keys[$alias]) || !isset($field->view) ||
                (isset($field->column_view) && $field->column_view === null)
            ) continue;

            $fk = $this->foreign_keys[$alias];

            if (!isset($fk->schema)) $fk->schema = $this->db->schema;

            if ($fk->schema !== $this->db->schema) {
                $schema = new Schema($fk->schema);
                $ref_base = $schema->get_db_name();
            } else {
                $ref_base = $this->db->name;
            }

            // Get view for reference table
            $table = Table::get($ref_base, $fk->table);
            $view = $table->get_view();

            // Check if user has permission to view table
            $permission = $table->get_user_permission();
            if ($permission->view == 0) {
                $field->expandable = false;
            }

            // Makes conditions for the ON statement in the join
            $conditions = [];
            foreach ($fk->local as $n => $fk_column) {
                $ref_field_name = $fk->foreign[$n];

                $conditions[] = "$alias.$ref_field_name = $this->name.$fk_column";
            }
            $conditions_list = implode(' AND ', $conditions);

            $joins[$alias] = "LEFT JOIN $view $alias ON $conditions_list";
        }

        // Joins extension tables
        foreach ($this->extension_tables as $table_name) {
            $table = Table::get($this->db->name, $table_name);
            $view = $table->get_view();
            $conditions = [];
            foreach ($table->primary_key as $i => $field) {
                $conditions[] = "$table_name.$field = $this->name.{$this->primary_key[$i]}";
            }

            $joins[$table_name] = "LEFT JOIN $view $table_name ON " . implode(' AND ', $conditions);
        }

        return $joins;
    }

    public function add_condition($condition) {
        $this->conditions[] = $condition;
    }

    public function get_conditions() {
        return $this->conditions;
    }

    private function supplement_fields()
    {
        $fields = $this->fields;
        $tables = $this->db->tables;

        // Get columns from tables with one-to-one relation
        $this->extension_tables = isset($this->extension_tables) ? $this->extension_tables : [];
        foreach ($this->extension_tables as $tbl_name) {
            $permission = $this->get_user_permission($tbl_name);
            $rel_fields = $tables[$tbl_name]->fields;
            foreach ($rel_fields as $fieldname => $field) {
                if (in_array($fieldname, $tables[$tbl_name]->primary_key)) {
                    unset($rel_fields[$fieldname]);
                    continue;
                }
                $field->editable = isset($field->editable) && $field->editable === false ? false : $permission->edit;
                $field->table = $tbl_name;
                $rel_fields[$fieldname] = $field;
            }
            $fields = array_merge($rel_fields, $fields);
        }

        foreach ($fields as $alias => $field) {

            $field->name = isset($field->name) ? $field->name : $alias;
            $field->alias = $alias;

            if (!isset($field->extra)) $field->extra = null;

            if (!isset($field->table)) $field->table = $this->name;

            if (!isset($field->editable)) {
                if (!isset($field->editable)) {
                    if ($field->extra || isset($field->source)) $field->editable = false;
                } else {
                    $field->editable = $permission->edit;
                }
            }


            if (isset($field->view)) {
                $view_parts = explode('||', $field->view);
                $field->view = $this->db->expr()->concat_ws('', $view_parts);

                if (empty($field->column_view)) {
                    $field->column_view = $field->view;
                }
            }

            if (empty($field->label)) {
                $field->label = ucfirst(str_replace('_', ' ', $field->alias));
            }


            // TODO What is this?
            if (
                $field->element == 'select' &&
                isset($field->options) &&
                !isset($field->relation) &&
                !isset($field->view)
            ) {
                $table_name = isset($field->table) ? $field->table : $this->name;
                $sql = "CASE $table_name.$field->name ";

                foreach ($field->options as $option) {
                    $option = (object) $option;
                    $sql .= "WHEN '$option->value' THEN '$option->label' ";
                }
                $sql .= "ELSE '' ";

                $sql .= "END";

                $field->view = $sql;
                $field->column_view = $sql;
            }

            // Add options for foreign key fields

            if ($this->db->schema === 'urd' && $field->name === 'schema_') {
                $admin_schemas = $this->db->get_user_admin_schemas();
                $options = array_map(function($value) {
                    return (object) ['value'=>$value, 'label'=>$value];
                }, $admin_schemas);

                $field->options = $options;
            }

            if ($this->db->schema === 'urd' && $field->name === 'table_') {
                $admin_schemas = $this->db->get_user_admin_schemas();
                $optgroups = array_map(function($value) {
                    $schema = Schema::get($value);
                    $options = [];
                    $options[] = (object) ['value' => '*', 'label' => '*'];
                    foreach ($schema->tables as $tablename => $table) {
                        $options[] = (object) ['value' => $tablename, 'label' => $table->label ? $table->label : $table->name];
                    }

                    return (object) ['label'=>$value, 'data-value' => $value, 'options' => $options];
                }, $admin_schemas);

                $field->optgroups = $optgroups;
                $field->optgroup_field = 'schema_';
            }

            if (!isset($this->foreign_keys[$alias])) continue;

            $fk = $this->foreign_keys[$alias];

            if (!isset($fk->schema) || $fk->schema === $this->db->schema) {
                $fk->schema = $this->db->schema;
                $fk->base = $this->db->req_name;
            } else {
                $fk->base = (new Schema($fk->schema))->get_db_alias();
            }



            $field->foreign_key = $fk;

            $ref_schema = Schema::get($fk->schema);
            $ref_tbl = $ref_schema->tables[$fk->table];

            // Make fields that link to data tables (not reference tables) expandable
            if ($ref_tbl->type === 'data' && !isset($field->expandable)) {
                $field->expandable = true;
            }

            if (!isset($field->view) && isset($ref_tbl->indexes)) {
                foreach ($ref_tbl->indexes as $index) {
                    if ($index->unique && !$index->primary) {
                        $columns = array_map(function($col) use ($alias) {
                            return "$alias.$col";
                        }, $index->columns);
                        $field->view = $this->db->expr()->concat_ws(', ', $columns);
                        break;
                    }
                }
            }

            if (empty($field->column_view)) $field->column_view = isset($field->view) ? $field->view : null;

            $fields[$alias] = $field;
        }

        // Add column properties from database
        if ($this->db->platform !== 'sqlite') {
            $pdo = $this->db->conn->getDriver()->getResource();
            $pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
        }

        $repl_connection = $this->db->get_replication_connection();

        $select_tables = $this->extension_tables;
        $select_tables[] = $this->name;
        foreach ($select_tables as $tbl_name) {
            // Break if connected with user who doesn't own the tables
            if (!$repl_connection->getDatabaseInfo()->hasTable($tbl_name)) break;
            $db_columns = $repl_connection->getDatabaseInfo()->getTable($tbl_name)->getColumns();

            foreach ($db_columns as $col) {
                $type = $this->db->expr()->to_urd_type($col->nativetype);
                if ($type === 'integer' && $col->size === 1) $type = 'boolean';

                $name = strtolower($col->name);

                $alias = array_search($col->name, array_map(function($field) {
                    return $field->name;
                }, $fields));

                if (!isset($fields[$alias])) {
                    continue;
                }

                $fields[$alias]->datatype = !empty($fields[$alias]->datatype) ? $fields[$alias]->datatype : $type;
                $fields[$alias]->size = $col->size;
                $fields[$alias]->nullable = $col->nullable;
                $fields[$alias]->default = !empty($fields[$alias]->default) ? $fields[$alias]->default : $col->default;
                $fields[$alias]->default = $this->db->expr()->replace_vars($fields[$alias]->default);

                if ($fields[$alias]->element == 'input[type=date]' && in_array($fields[$alias]->extra, ['auto', 'auto_update'])) {
                    $fields[$alias]->default = $this->db->expr()->replace_vars('$timestamp');
                }

                if ($fields[$alias]->element != 'input[type=date]' && in_array($fields[$alias]->extra, ['auto', 'auto_update'])) {
                    $fields[$alias]->default = $this->db->expr()->replace_vars('$user_id');
                }
            }
        }

        if ($this->db->platform !== 'sqlite') {
            $pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        }

        foreach ($fields as $name => $field) {
            // Add empty attr if not exists
            if (!isset($field->attr)) $fields[$name]->attr = new \StdClass;
        }

        return (array) $fields;
    }

    // Finner lagrede søk og legger dem i en array indeksert etter id
    public function get_saved_searches() {
        $sql = "SELECT id, expression, label, advanced, user_
              FROM filter
             WHERE schema_ = '{$this->db->schema}'
                   AND table_ = '$this->name'
                   AND (user_ = '{$_SESSION['user_id']}' OR user_ = 'urd')";
        $filters = DB::get()->conn->query($sql)->fetchAll();

        $saved_searches = [];
        foreach ($filters as $filter) {
            $filter->expression = $this->db->expr()->replace_vars($filter->expression);
            $filter->user_defined = $filter->user_ == $_SESSION['user_id'];
            unset($filter->user_);
            $saved_searches[] = $filter;
        }

        return $saved_searches;
    }

    /**
     * Get options for select box
     *
     * @param array $field  Field that shall have options
     */
    public function get_options($field, $fields=null)
    {
        $field = (object) $field;

        $fk = $this->foreign_keys[$field->alias];

        if (!isset($fk->schema) || $fk->schema === $this->db->schema) {
            $fk->schema = $this->db->schema;
            $ref_schema = $this->db->schema;
            $ref_base = $this->db->name;
        } else {
            $ref_schema = new Schema($fk->schema);
            $ref_base = $ref_schema->get_db_name();
        }

        $cand_tbl = Table::get($ref_base, $fk->table);

        // Array of ref fields
        $kodefelter = array();
        foreach($fk->foreign as $field_name) {
            $kodefelter[] = "$field->alias.$field_name";
        }

        // Field that holds the value of the options
        $value_field = end($kodefelter);

        // Sorting
        $sort_fields = array();
        foreach ($cand_tbl->grid->sort_columns as $sort_col) {
            $sort_fields[] = "$field->alias.$sort_col";
        }
        $order_kombo = count($sort_fields) ? "ORDER BY ".implode(',', $sort_fields) : '';

        // Conditions
        $conditions = array();
        if (!empty($fk->filter)) {
            $conditions[] = '('. $this->db->expr()->replace_vars($fk->filter) .')';
        }

        if ($ref_schema === 'urd' && isset($cand_tbl->fields['schema_'])) {
            $admin_schemas = "'" . implode("', '", $this->db->get_user_admin_schemas()) . "'";
            $conditions[] = "schema_ IN ($admin_schemas)";
        }

        // Adds condition if this select depends on other selects
        if (isset($field->value) && count($fk->local) > 1) {
            foreach ($fk->local as $i=>$foreign_field) {
                if ($foreign_field != $field->name && $fields[$foreign_field]->value) {
                    $conditions[] = $fk->foreign[$i] . " = '" . $fields[$foreign_field]->value . "'";
                }
            }
        }

        $betingelse = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : null;

        $view = $cand_tbl->get_view();

        $sql_count = "SELECT count(*) as ant
              FROM $view $field->alias
              $betingelse";

        $ant = $cand_tbl->db->conn->query($sql_count)->fetch()->ant;

        if ($ant > 200) {
            return false;
        };


        $sql_kombo = "SELECT $value_field as value, ($field->view) as label, ($field->column_view) as coltext
                      FROM   $view $field->alias
                      $betingelse
                      $order_kombo";

        $grunndata = $cand_tbl->db->conn->query($sql_kombo)->fetchAll();

        return $grunndata ? $grunndata : [];
    }

    /**
     * Get option values from searchable select box
     */
    public function get_select($request)
    {
        $search = isset($request->q) ? str_replace('*', '%', $request->q) : null;

        $value_column = isset($request->key) ? end($request->key) : end($this->primary_key);

        $view = isset($request->view) ? $request->view : $value_column;
        $column_view = isset($request->column_view) ? $request->column_view : $value_column;

        $conditions = $request->condition ? explode(' AND ', $request->condition) : [];
        // ignores case. Should work for all supported platforms
        if ($search) {
            $search = mb_strtolower($search, 'UTF-8');
            $conditions[] = "(lower($view) LIKE '%$search%')";
        }
        $betingelse = count($conditions) ? implode(' AND ', $conditions) : $value_column . ' IS NOT NULL';

        $kode_sql = "$request->alias." . $value_column;

        $tbl_view = $this->get_view();

        $sql = "SELECT DISTINCT $kode_sql AS value, $view AS label, $column_view AS coltext
        FROM   $tbl_view $request->alias
        WHERE  $betingelse
        ORDER BY $view %lmt";

        $rader = $this->db->conn->query($sql, $request->limit)->fetchAll();

        return $rader;
    }

    /**
     * Get the order of the record in the search result
     *
     * @param  array   $prim_key  Primary key
     * @return integer
     */
    function get_selected_idx($prim_key, $selects) {

        $record_conditions = array();
        foreach ($prim_key as $field=>$value) {
            $record_conditions[] = "$field = '$value'";
        }

        $record_condition = count($record_conditions)
            ? $record_condition = ' WHERE '.implode(' AND ', $record_conditions)
            : '';

        $join = implode("\n", $this->get_joins());

        $betingelse = count($this->conditions)
            ? $betingelse = 'WHERE '.implode(' AND ', $this->conditions)
            : 'WHERE 1=1';

        $order_by = $this->make_order_by($selects);

        $view = $this->get_view();

        if ($this->db->platform == 'mysql') {
            $sql = "SELECT iterator
              FROM   ( SELECT @i:=@i+1 AS iterator, foo.*
                       FROM ( SELECT  $this->name.*
                              FROM $view $this->name $join
                              $betingelse
                              $order_by
                             ) foo,  (SELECT @i:=0) foo2) foo3
              $record_condition";
        } else if ($this->db->platform == 'oracle') {
            $sql = "SELECT rnum
              FROM   (SELECT ROWNUM AS rnum, tab.*
                      FROM   (SELECT $this->name.*
                              FROM   $view $this->name
                              $join
                              $betingelse
                              $order_by) tab) tab2
              $record_condition";
        } else if ($db->platform == 'sqlite') {
            $betingelse_count = '';
            $sort_fields = $this->get_sort_fields($selects);
            foreach ($sort_fields as $key=>$sort) {
                if ($sort['order'] == 'DESC') {
                    $operator = '>';
                } else {
                    $operator = '<';
                }
                $sortfields = explode('.',$sort['field']);
                $sortfield = $sortfields[1];
                $betingelse_count .= ' AND '.$sort['field'].$operator
                                  ."'".$record->$sortfield."'";
            }
            foreach ($tbl->primary_key as $nokkel) {
                if ($record->$nokkel !== null) {
                    $betingelse_count .= " AND $tbl->name.$nokkel < {$record->$nokkel}";
                    $add = 1;
                } else {
                    $add = 0;
                }
            }
            $betingelse_count = $betingelse . $betingelse_count;
            $sql = "SELECT count(*)+$add
              FROM $tbl->view $tbl->name $join
              $betingelse_count";
        }

        // trigger_error($sql);
        $idx = $this->db->fetchSingle($sql);

        if ($idx !== false) {
            $idx = $idx -1;
        }

        return $idx;
    }

    public function get_icon() {
        if ($this->icon) {
            if (strtolower(substr($this->icon, 0, 6)) == 'select') {
                $icon = '('.$this->icon.')';
            } else {
                $icon = "'".$this->icon."'";
            }
        } else {
            $icon = 'NULL';
        }
        return $icon;
    }


    /**
     * Make ORDER BY expression in sql
     *
     * $selects array SELECT expressions
     */
    public function make_order_by($selects) {
        if ($this->grid->sort_columns || count($this->primary_key)) {
            $order_by = 'ORDER BY ';

            $sort_fields = $this->get_sort_fields($selects);
            foreach ($sort_fields as $key=>$sort) {
                if ($this->db->platform == 'mysql') {
                    $order_by .= "ISNULL($sort->field), $sort->field $sort->order, ";
                } else if ($this->db->platform == 'oracle') {
                    $order_by .= "$sort->field $sort->order, ";
                } else if ($this->db->platform == 'sqlite') {
                    $order_by .= "$sort->field IS NULL, $sort->field $sort->order, ";
                }
            }
            foreach ($this->primary_key as $field) {
                $order_by .= "$this->name.$field, ";
            }
            $order_by = substr($order_by, 0, -2);
            if ($this->db->platform == 'oracle') {
                $order_by .= ' NULLS LAST';
            }
        } else {
            $order_by = '';
        }
        return $order_by;
    }

    public function get_values($selects, $join, $condition, $order) {
        foreach ($selects as $key=>$value) {
            $selects[$key] = "$value AS $key";
        }
        $kolonner_sql = implode(', ', $selects);
        $view = $this->get_view();
        $sql = "SELECT $kolonner_sql
                FROM   $view $this->name
                $join
                $condition
                $order
                %lmt %ofs";

        $this->sql = $sql;
        $rader = $this->db->conn->query($sql, $this->limit, $this->offset)->setFormat(Type::DATETIME, 'Y-m-d H:i:s')->fetchAll();

        // Henter ut array med assosiative nøkler:
        $i = 0;
        $this->records = array();
        foreach ($rader as $rad) {
            $prim_values = explode(',', $rad->urd_primary_key);
            $this->records[$i]['primary_key'] = array_combine($this->primary_key, $prim_values);
            unset($rad->urd_primary_key);
            if (isset($rad->count_children)) {
                $this->records[$i]['count_children'] = $rad->count_children;
                unset($rad->count_children);
            }
            $this->records[$i]['columns'] = $rad;
            $i++;
        }
    }

    public function get_record_count($condition, $join = '') {
        $view = $this->get_view();
        $sql = "SELECT count(*) AS ant
            FROM $view $this->name
            $join
            $condition";

        $count_records = $this->db->conn->query($sql)->fetch()->ant;
        return $count_records;
    }

    public function get_sums($join, $condition) {
        $summer = array();
        if (!empty($this->grid->summation_columns)) {
            $sql_sumfelter = array();
            foreach ($this->grid->summation_columns as $col) {
                $sql_sumfelter[] = "SUM($col) AS $col";
            }
            $sql_sumfelter_string = implode(', ', $sql_sumfelter);

            $view = $this->get_view();

            $sql = "SELECT $sql_sumfelter_string
              FROM  $view $this->name
              $join
              $condition";

            $summer = $this->db->fetch($sql);

            return $summer;
        }
    }

    // TODO: Fungerer ikke
    public function set_action_visibility($join, $condition) {
        if (count($this->handlinger) > 0) {
            $select_handling = array();

            foreach ($this->handlinger as $handling => $def) {
                if ($def->betingelse) {
                    $select_handling[] = "CASE
                                WHEN $def->betingelse THEN 1
                                ELSE 0
                                END AS h_{$handling}";
                }
                else {
                    $select_handling[] = "1 AS h_{$handling}";
                }
                $select_setning = implode(", ", $select_handling);

                $sql = "SELECT $select_setning
                FROM $fra_base.$tbl->name
                $join
                $condition";

                // TODO: Should we have a order by clause here ($order_by)?


                $res = $this->db->query($sql, $this->limit, $this->offset);
                $rader = $this->db->fetchAll($res);

                $i = 0;
                foreach ($rader as $rad) {
                    $tbl->records[$i]->handlinger = $rad;
                    $i++;
                }
            }
        }
    }

    /**
     * Get array of sort fields
     *
     * $criterias array Sort criterias with field name and direction
     * $selects array SELECT expressions
     *
     * return array Indexed by field name and with attributes `field` and `order`
     */
    function get_sort_fields($selects) {
        $criterias = $this->grid->sort_columns;
        if (!$criterias) {
            return array();
        }
        $sort_fields = array();
        foreach ($criterias as $value) {
            // Splits the value into field and sort order
            $value_parts = explode(' ', $value);
            $key = $value_parts[0];
            if (isset($value_parts[1])) {
                $sort_order = $value_parts[1];
            } else {
                $sort_order = 'ASC';
            }
            $sort_fields[$key] = new \StdClass;
            $sort_fields[$key]->field = $this->name.'.'.$key;
            if (isset($selects[$key])) {
                $sort_fields[$key]->field = $selects[$key];
            }
            $sort_fields[$key]->order = $sort_order;
        }

        return $sort_fields;
    }


    function get_user_permission($tbl_name = null)
    {
        $tbl_name = $tbl_name ? $tbl_name : $this->name;

        $roles = $this->db->get_user_roles();

        $permission = DB::get()->conn->query(
            "SELECT max(rp.view_) as `view`, max(rp.add_) as `add`, max(rp.edit) as `edit`, max(rp.delete_) as `delete`, max(rp.admin) as admin
            FROM role_permission rp inner join
            (
                select max(schema_) schema_, max(role) role, max(table_) table_
                from role_permission
                where schema_ = %s", $this->db->schema, "
                  and table_ in (?)", [$tbl_name, '*'], "
                  and role in (?)", $roles ?: [0], "
                group by role
            ) rp2 on rp.role = rp2.role and rp.schema_ = rp2.schema_ and rp.table_ = rp2.table_")
            ->fetch();

        if ($permission->view === null) {
            $permission = (object) [
                'view' => $this->db->schema === 'urd' && $tbl_name === 'database_' ? 1 : 0,
                'add' => 0,
                'edit' => 0,
                'delete' => 0,
            ];
        }

        if ($this->db->schema === 'urd') {

            $admin_schemas = $this->db->get_user_admin_schemas();

            if (count($admin_schemas)) {
                if (in_array($this->name, ['filter', 'format', 'role', 'role_permission', 'user_role'])) {
                    $this->add_condition("$this->name.schema_ IN ('" . implode("','", $admin_schemas) . "')");
                }

                if (in_array($this->name, ['filter', 'format', 'role', 'role_permission', 'user_', 'user_role'])) {
                    $permission->view = 1;
                    $permission->add = 1;
                    $permission->edit = 1;
                    $permission->delete = 1;
                }
            }
        }

        if ($this->type === 'reference' && empty($permission->admin)) {
            $permission->view = 0;
        }

        return $permission;
    }

    function get_relations() {

        if (!isset($this->relations)) return [];

        return array_filter((array) $this->relations, function($relation) {
            $permission = $this->get_user_permission($relation->table);
            return $permission->view;
        });
    }

    function get_format($join, $betingelse, $order) {
        $formats = DB::get()->conn
                 ->select('class, filter')
                 ->from('format f')
                 ->where('f.schema_ = ?', $this->db->schema)
                 ->where('f.table_ = ?', $this->name)
                 ->fetchAll();

        $selects = array();
        foreach ($formats as $format) {
            $selects[] = '(' . $format->filter .') AS ' . $format->class;
        }
        if (!count($selects)) {
            return array();
        }
        $select = implode(', ', $selects);

        $view = $this->get_view();

        // TODO: Looks like it doesn't work with Oracle
        $sql = "SELECT $select
                FROM $view $this->name
                $join
                $betingelse
                $order
                %lmt %ofs";

        $rader = $this->db->conn->query($sql, $this->limit, $this->offset)->fetchAll();

        return $rader;
    }

    function hent_tabell($prim_key = []) {

        $permission = $this->get_user_permission($this->name);

        if ($permission->view == 0) {
            return false;
        }


        $selects = array(); // array of select expressions

        // Legger alle primærnøkler til arrayen $selects.
        $arr = array();
        foreach ($this->primary_key as $felt) {
            $arr[] = $this->name.'.'.$felt;
        }
        $selects['urd_primary_key'] = $this->db->expr()->concat_ws(',', $arr);

        // This is not currently in use, therefore commented out
        // $selects['urd_icon'] = $this->get_icon();

        foreach ($this->fields as $field_alias => $field) {

            // TODO: lazy dropdown hvis feltet beror på annet felt

            if (!empty($this->foreign_keys[$field_alias]) && !empty($field->view)) {
                $field->options = $this->get_options($field);
            }

            if (!in_array($field_alias, (array) $this->grid->columns)) {
                continue;
            }

            $fk = isset($this->foreign_keys[$field_alias]) ? $this->foreign_keys[$field_alias] : null;

            if (
                isset($field->column_view) &&
                ($fk === null || $field->column_view !== $fk->table . '.' . $fk->foreign[0])
            ) {
                $selects[$field->alias] = $field->column_view;
            } else if (($field->datatype == 'string' && ($field->size > 255 || $field->size == null)) or $field->datatype == 'binary') {
                $selects[$field->alias] = "substr($this->name.$field->alias, 1, 256)";
            } else {
                $selects[$field->alias] = "$this->name.$field->alias";
            }
        }

        // Get number of relations to same table for expanding row
        if (!empty($this->expansion_column)) {
            $relations = array_filter((array) $this->relations, function($relation) {
                return $relation->table === $this->name;
            });

            $rel = reset($relations);

            // TODO: Support composite key
            $rel_column = $this->fields[$rel->foreign_key];
            $pk_column = $this->primary_key[0];

            $selects['count_children'] = "(SELECT count(*)
                    FROM {$this->db->name}.$this->name child_table
                    WHERE $rel_column->name = $this->name.$pk_column)";

            // Filters on highest level if not filtered by user
            if (isset($this->user_filtered) && $this->user_filtered === false) {
                $this->add_condition("$this->name.$rel_column->alias " . ($rel_column->default ? "= '" . $rel_column->default . "'" : "IS NULL"));
            }
        }

        $joins = $this->get_joins();

        // todo: Must take search filters into consideration when filtering keys
        //       This code is therefore wrong and therefore commented out
        /*
        $filtered_keys = array_filter(array_keys($joins), function($k) {
            return in_array($k, (array) $this->grid->columns);
        });
        $joins = array_intersect_key($joins, array_flip($filtered_keys));
         */

        $join = implode("\n", $joins);

        $condition = count($this->conditions) ? 'WHERE '.implode(' AND ', $this->conditions) : '';

        if (!empty($prim_key)) {
            $idx = $this->get_selected_idx($prim_key, $selects);
            if ($idx !== null) {
                $page_nr = floor($idx / $this->limit);
                $this->offset = $page_nr * $this->limit;
                $row_idx = $idx - $this->offset;
            } else {
                $this->offset = 0;
                $row_idx = 0;
            }
        } else {
            $row_idx = null;
        }

        $order_by = $this->make_order_by($selects);

        $this->get_values($selects, $join, $condition, $order_by);
        $row_formats = $this->get_format($join, $condition, $order_by);
        foreach ($row_formats as $i=>$rad) {
            $classes = array();
            foreach ($rad as $key=>$value) {
                if ($value) $classes[] = $key;
            }
            $class = implode(' ', $classes);
            $this->records[$i]['class'] = $class;
        }

        $count_records = $this->get_record_count($condition, $join);

        $summer = $this->get_sums($join, $condition, $order_by);

        // $this->set_action_visibility($join, $condition);

        // TODO: Vurder å returnere $tabell
        $data = array(); // returverdiene


        // Don't let $fields be reference to $this->fields
        $fields = json_decode(json_encode($this->fields));

        array_walk($fields, function($field, $alias) {
            $field->name = $alias;
            unset($field->alias);
        });

        $data['name'] = $this->name;
        // TODO: Seems that it doesn't belong here
        $data['records'] = (array) $this->records;
        $data['count_records'] = $count_records;
        $data['fields'] = (array) $fields;
        $data['grid'] = array();
        $data['grid']['columns'] = $this->grid->columns;
        $data['grid']['sums'] = $summer;
        $data['grid']['sort_columns'] = $this->grid->sort_columns;
        $data['form'] = array();
        $data['form']['items'] = isset($this->form->items) ? $this->form->items : null;
        $data['permission'] = $permission;
        // TODO: Is this needed anymore?
        $data['type'] = $this->type;
        $data['primary_key'] = $this->primary_key;
        $data['label'] = $this->label;
        $data['actions'] = isset($this->actions) ? $this->actions : [];
        // TODO: Seems that these two doesn't belong here
        $data['limit'] = $this->limit;
        $data['offset'] = $this->offset;
        $data['selection'] = $row_idx;
        $data['sql'] = $this->sql;
        $data['conditions'] = $this->conditions;
        $data['date_as_string'] = isset($this->date_as_string) ? $this->date_as_string : ["separator" => "-"];
        $data['expansion_column'] = isset($this->expansion_column) ? $this->expansion_column : null;
        $data['relations'] = $this->get_relations();

        // Sjekker om det finnes en hjelpefil
        $filename = '../../schemas/'.$this->db->schema .'/hjelp/'.$this->name.'.html';
        $data['help'] = file_exists($filename) ? true : false;

        return $data;
    }

    function save($records)
    {
        $result = new \StdClass;

        foreach ($records as $rec) {
            $record = new Record($this->db->name, $this->name, $rec->prim_key);
            if ($rec->method == 'delete') {
                $record->delete();
                $action = 'delete';
                // TODO: Delete relations
            } elseif ($rec->method == 'post') {
                $action = 'insert';
                $pk = $record->insert($rec->values);

                // Must get autoinc-value for selected record to get correct offset
                // when reloading table after saving
                if (!empty($rec->selected)) $result->selected = $pk;

                // Insert value for primary key also in the relations
                foreach ($rec->relations as $alias => $rel) {
                    $rel->schema = !empty($rel->schema) ? $rel->schema : $this->db->schema;

                    $rel->db_name = $rel->schema !== $this->db->schema
                        ? (new Schema($rel->schema))->get_db_name()
                        : $this->db->name;

                    $rel->table = !empty($rel->table) ? $rel->table : $alias;

                    $tbl_rel = Table::get($rel->db_name, $rel->table);

                    $fk_alias = $this->relations[$alias]->foreign_key;
                    $fk = $tbl_rel->foreign_keys[$fk_alias];

                    foreach ($rel->records as $rel_rec) {
                        $rel_rec->values = (array) $rel_rec->values;

                        foreach ($fk->local as $n => $alias) {
                            $rel_rec->values[$alias] = !empty($rel_rec->values[$alias]) ? $rel_rec->values[$alias] : $pk->{$fk->foreign[$n]};
                        }
                    }
                }
            } elseif ($rec->method == 'put') {
                $action = 'update';
                if (!empty($rec->values)) {
                    $res = $record->update($rec->values);
                }
            }

            // logs to log table
            if ($this->db->log && $rec->method !== 'none') {
                if (isset($rec->values)) {
                    $this->log_db_event($rec->prim_key, $action, $rec->values);
                }
            }

            // Iterates of all the relations to the record
            foreach ($rec->relations as $rel) {
                $rel_table = Table::get($rel->base_name, $rel->table_name);
                $rel_table->save($rel->records);
            }
        }

        return $result;
    }


    // TODO: Skal denne ligge her, i Table-klassen?
    function log_db_event($prim_key, $event, $new_values=[]) {
        $prim_key_values = array_values((array) $prim_key);
        $prim_key_list = implode(', ', $prim_key_values);
        $user_id = $_SESSION['user_id'];
        foreach ($new_values as $field=>$value) {
            $value = str_replace("'", "''", $value);
            $sql = "INSERT INTO log (table_, column_, prim_key, updated_by, type, updated_at, new_value)
                VALUES ('$this->name', '$field', '$prim_key_list', '$user_id', '$event', CURRENT_TIMESTAMP, '$value')";
            $res = $this->db->query($sql);
        }

    }

    // TODO: Hører denne funksjonen hjemme under Table-klassen?
    function save_search($search, $name, $advanced, $id = null) {
        $urd = Database::get();
        $user = $_SESSION['user_id'];
        $search = str_replace("'", "''", $search);

        if ($id) {
            $sql = "UPDATE filter SET expression = '$search' WHERE id = $id";
        } else {
            $sql = "INSERT INTO filter (schema_, table_, expression, label, user_, advanced)
            VALUES ('{$this->db->schema}', '{$this->name}', '$search', '$name', '$user', $advanced)";
        }

        if ($id) {
            $urd->query($sql);
            return $id;
        }

        // TODO: Fiks denne
        if ($this->db->platform == 'oracle') {
            $sql .= " RETURNING id INTO :id";
            $stmt = $urd->conn->getDriver()->getResource()->prepare($sql);
            $stmt->bindParam('id', $id);
            $stmt->execute();
        } else {
            $urd->query($sql);
            $id = $urd->conn->getInsertId();
        }
        return $id;
    }

    // TODO: Hører denne funksjonen hjemme under Table-klassen
    public static function delete_search($id) {
        $sql = "DELETE FROM filter WHERE id = '$id'";
        return dibi::query($sql);
    }

    // TODO: Skal funksjonen her heller ligge i TableController.php?
    function export_sql($dialect) {
        $cols = (array) $this->fields;

        $betingelse = $this->filter ?: '1 = 1';

        $sql = "SELECT * FROM $this->name WHERE $betingelse";
        $rows = $this->db->conn->query($sql)->fetchAll();
        $firstrow = true;
        $fields = array();
        $insert = '';

        if ($dialect == 'oracle') {
            $insert .= "alter session set nls_date_format = 'YYYY-MM-DD';\n";
        }

        foreach ($rows as $row) {
            $values = array();
            $insert .= "INSERT INTO $this->name";
            foreach ($row as $field=>$value) {
                if ($firstrow) {
                    $fields[] = $field;
                }
                $datatype = $cols[$field]->datatype;

                if ($value == null) {
                    $value = 'null';
                } else if ($datatype == 'string' || $datatype == 'date') {
                    $value = str_replace("'", "''", $value);
                    $value = "'$value'";
                }
                $values[] = $value;
            }
            $firstrow = false;
            $insert .= ' (' . implode(',', $fields) . ') ';
            $insert .= 'values (' . implode(',', $values) . ");\n";
        }

        return $insert;
    }

}
