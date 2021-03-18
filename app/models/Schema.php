<?php

namespace URD\models;

use URD\models\Database as DB;
use URD\models\Table;
use dibi;
use PDO;

class Schema {

    private static $instances;
    public $name;
    public $tables;
    public $reports;
    public $contents;

    function __construct($schema_name) {
        $file = __DIR__ . '/../../schemas/' . $schema_name . '/schema.json';

        // Finds exisisting data in schema.json
        if (file_exists($file)) {
            $schema = json_decode(file_get_contents($file));
        } else {
            $schema = json_decode('{"tables": []}');
        }

        $this->name = $schema_name;
        $this->tables = (array) $schema->tables;
        $this->reports = isset($schema->reports) ? (array) $schema->reports : [];
        $this->contents = isset($schema->contents) ? (array) $schema->contents : [];
        $this->criteria = isset($schema->criteria) ? $schema->criteria : null;

        foreach ($this->tables as $alias => $table) {
            $table->fields = isset($table->fields) ? (array) $table->fields : [];

            if (isset($table->indexes)) $table->indexes = (array) $table->indexes;
            if (isset($table->foreign_keys)) $table->foreign_keys = (array) $table->foreign_keys;
            if (isset($table->records)) $table->records = (array) $table->records;
            if (isset($table->relations)) $table->relations = (array) $table->relations;

            $this->tables[$alias] = $table;
        }
    }

    public static function get($schema_name) {
        if (!isset(self::$instances[$schema_name])) {
            self::$instances[$schema_name] = new Schema($schema_name);
        }

        return self::$instances[$schema_name];
    }

    public function get_db_alias() {
        $sql = "SELECT name, alias
                FROM   database_
                WHERE  schema_ = '$this->name'";

        $base = DB::get()->conn->query($sql)->fetch();

        if ($base == false && $this->name == 'urd') {
            return dibi::getConnection()->getConfig('name');
        }

        return $base->alias ? $base->alias : $base->name;
    }

    public function get_db_name() {
        if ($this->name == 'urd') {
            return dibi::getConnection()->getConfig('name');
        } else {
            $sql = "SELECT name
                    FROM   database_
                    WHERE  schema_ = '$this->name'";

            $base = DB::get()->conn->query($sql)->fetch()->name;

            return $base;
        }
    }

    private function get_relation_tables($table_name, $relation_tables) {
        $table = $this->tables[$table_name];

        foreach ($table->relations as $relation) {
            $relation = (object) $relation;

            if (!empty($relation->hidden)) continue;

            if (!in_array($relation->table, $relation_tables)) {
                $relation_tables[] = $relation->table;

                $relation_tables = $this->get_relation_tables($relation->table, $relation_tables);
            }
        }

        return $relation_tables;
    }

    /*
     * Update json file from actual database structure
     */
    public function update_schema_from_database($db_name, $config)
    {
        ini_set('max_execution_time', 600); // 10 minutes

        $threshold = $config->threshold / 100;

        if ($config->replace) {
            $this->tables = [];
            $this->reports = [];
            $this->contents = [];
        }

        $_SESSION['progress'] = 0;

        $report = [];

        $drops = [];

        $db = DB::get($db_name);

        if ($db->platform == 'oracle') {
            $reflector = new \URD\lib\OracleReflector($db->conn->getDriver());
        } else if ($db->platform == 'mysql') {
            $reflector = new \URD\lib\MySqlReflector($db->conn->getDriver());
        } else {
            $reflector = $db->conn->getDriver()->getReflector();
        }

        // Don't return keys in lowercase
        // Necessary for database reflection to work
        if ($db->conn->getConfig('driver') == 'pdo') {
            $pdo = $db->conn->getDriver()->getResource();
            $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
        }

        // Finds all tables in database
        if ($db->platform == 'oracle') {
            $tables = $reflector->getTables();
            $db_tables = [];
            foreach ($tables as $table) {
                $db_tables[] = $table['name'];
            }
        } else {
            $db_tables = $db->conn->getDatabaseInfo()->getTableNames();
        }

        // Build array of table aliases and remove tables that doesn't exist
        $tbl_aliases = [];
        foreach ($this->tables as $table_alias => $table) {
            $tbl_aliases[$table->name] = $table_alias;
            if (!in_array($table->name, $db_tables)) {
                unset($this->tables[$table_alias]);
            }
        }

        if (in_array('meta_terminology', $db_tables)) {
            $sql = "select * from meta_terminology";
            $terms = $db->conn->query($sql)->fetchAssoc('term');
        } else {
            $terms = [];
        }

        $modules = [];
        $tbl_groups = [];
        $warnings = [];

        $total = count($db_tables);
        $processed = -1;

        foreach ($db_tables as $tbl_name) {

            $report[$tbl_name] = [
                'empty_columns' => [],
                'almost_empty_columns' => []
            ];

            // Tracks progress
            $processed++;
            $_SESSION['progress'] = floor($processed/$total * 100);
            session_write_close();
            session_start();

            $tbl_alias = isset($tbl_aliases[$tbl_name])
                ? $tbl_aliases[$tbl_name]
                : strtolower($tbl_name);

            if ($db->platform == 'oracle') {
                $refl_table = new \Dibi\Reflection\Table($reflector, ['name'=> $tbl_name, 'view' => false]);
            } else {
                $refl_table = $db->conn->getDatabaseInfo()->getTable($tbl_name);
            }

            // Updates table properties

            if ($db->conn->getConfig('driver') == 'pdo') {
                $pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
            }
            $pk = $refl_table->getPrimaryKey();

            $pk_columns = [];
            if ($pk) {
                foreach ($pk->columns as $column) {
                    $pk_columns[] = strtolower($column->getName());
                }
            } else {
                $warnings[] = "Tabell $tbl_name mangler primærnøkkel";
            }

            if (!array_key_exists($tbl_alias, $this->tables)) {
                $table = (object) [
                    'name' => strtolower($tbl_name),
                    'icon' => null,
                    'label' => isset($terms[$tbl_name]) ? $terms[$tbl_name]['label'] : null,
                    'primary_key' => $pk_columns,
                    'description' => isset($terms[$tbl_name]) ? $terms[$tbl_name]['description'] : null,
                    'relations' => [],
                ];

            } else {
                $table = $this->tables[$tbl_alias];
                $table->label = isset($terms[$tbl_name])
                    ? $terms[$tbl_name]['label'] : (
                        isset($table->label) ? $table->label : null
                    );
                $table->description = isset($terms[$tbl_name])
                    ? $terms[$tbl_name]['description'] : (
                        isset($table->description) ? $table->description : null
                    );
                $table->primary_key = !empty($pk_columns)
                    ? $pk_columns
                    : (isset($table->primary_key)
                        ? $table->primary_key
                        : []
                    );
            }

            // Hides table if user has marked the table to be hidden
            if (isset($config->dirty->{$table->name}->hidden)) {
                $table->hidden = $config->dirty->{$table->name}->hidden;
            }

            // Updates indexes
            {
                $indexes = $reflector->getIndexes($tbl_name);

                if (!isset($this->tables[$tbl_alias]->indexes)) {
                    $table->indexes = [];
                }

                $index_names = [];

                foreach ($indexes as $index) {
                    $index = (object) $index;
                    $index->name = strtolower($index->name);
                    $index_names[] = $index->name;
                    $index->columns = array_map('strtolower', $index->columns);
                    $table->indexes[$index->name] = $index;
                }

                $grid_idx = isset($table->indexes[$tbl_name . '_grid_idx'])
                    ? $table->indexes[$tbl_name . '_grid_idx']
                    : null;

                $sort_cols = isset($table->indexes[$tbl_name . '_sort_idx'])
                    ? $table->indexes[$tbl_name . '_sort_idx']->columns
                    : ( $grid_idx
                        ? array_slice($grid_idx->columns, 0, 3)
                        : null
                    );

                // Remove dropped indexes
                foreach ($table->indexes as $alias => $index) {
                    if (!in_array($index->name, $index_names)) {
                        unset($table->indexes[$alias]);
                    }
                }

            }

            // Updates foreign keys
            {
                $foreign_keys = $reflector->getForeignKeys($tbl_name);

                if (!isset($table->foreign_keys)) {
                    $table->foreign_keys = [];
                }

                foreach ($foreign_keys as $key) {
                    $key = (object) $key;
                    unset($key->onDelete);
                    unset($key->onUpdate);
                    $key->name = strtolower($key->name);
                    $key->schema = $this->name;
                    $key->table = strtolower($key->table);
                    $key->local = array_map('strtolower', $key->local);
                    $key->foreign = array_map('strtolower', $key->foreign);
                    $key_alias = end($key->local);

                    // Checks if reference table exists.
                    // This might not be the case if foreign key check is disabled
                    if (!in_array($key->table, $db_tables)) {
                        $warnings[] = "Fremmednøkkel $key->name er ugyldig";
                    }

                    $table->foreign_keys[$key_alias] = $key;

                    // Add to relations of relation table
                    $key_table_alias = isset($tbl_aliases[$key->table])
                    ? $tbl_aliases[$key->table]
                    : $key->table;

                    if (in_array($key->table, $db_tables) && !isset($this->tables[$key_table_alias])) {
                        $this->tables[$key_table_alias] = (object) [
                            "name" => $key->table,
                            "relations" => [],
                            "extension_tables" => [],
                        ];
                    }

                    // Checks if the relation defines this as an extension table
                    if ($key->local === $pk_columns) {
                        // TODO: Dokumenter
                        if (!isset($this->tables[$key_table_alias]->extension_tables)) {
                            $this->tables[$key_table_alias]->extension_tables = [];
                        }
                        if (!in_array($tbl_alias, $this->tables[$key_table_alias]->extension_tables)) {
                            $this->tables[$key_table_alias]->extension_tables[] = $tbl_alias;
                        }

                        $table->extends = $key->table;
                    }

                    // Finds index associated with the foreign key
                    $key_index = array_reduce($table->indexes, function($carry, $index) use ($key) {
                        if (!$carry && $index->columns === $key->local) {
                            $carry = $index;
                        }
                        return $carry;
                    });

                    $patterns = [];
                    $patterns[] = '/^(fk_|idx_)/'; // find prefix
                    $patterns[] = '/(_' . $key->table . ')(_fk|_idx)?$/'; // find referenced table
                    $key_string = implode('_', $key->local);
                    $patterns[] = '/(_' . $key_string . ')(_fk|_idx)?$/'; // find column names
                    $patterns[] = '/(_fk|_idx)$/'; // find postfix

                    $replace = $key->table == $key_string ? '' : ' (' . $key_string . ')';
                    $replacements = ['', '', $replace, ''];

                    $label = $config->urd_structure && $key_index
                    ? ucfirst(str_replace('_', ' ', preg_replace($patterns, $replacements, $key_index->name)))
                    : ucfirst($tbl_alias);

                    if ($config->norwegian_chars) {
                        $label = str_replace('ae', 'æ', $label);
                        $label = str_replace('oe', 'ø', $label);
                        $label = str_replace('aa', 'å', $label);
                    }

                    if (in_array($key->table, $db_tables) && !isset($table->extends)) {
                        $this->tables[$key_table_alias]->relations[$key->name] = [
                            "table" => $tbl_name,
                            "foreign_key" => $key_alias,
                            "label" => $label,
                            "hidden" => (!$key_index && !empty($config->urd_structure)) || !empty($table->hidden)
                            ? true
                            : false
                        ];
                    }
                }
            }

            // Count table rows
            $count_rows = $db->select('*')->from($tbl_name)->count();
            $report[$tbl_name]['rows'] = $count_rows;
            if ($config->count_rows) {
                $table->count_rows = $count_rows;
            } else {
                unset($table->count_rows);
            }


            if (!isset($table->fields)) {
                $table->fields = [];
            }

            // Delete columns that doesn't exist anymore
            $colnames = $refl_table->getColumnNames();
            foreach ($table->fields as $alias => $field) {
                // Keep virtual columns
                if (isset($field->source)) continue;

                if (!in_array($field->name, $colnames) && !in_array($alias, $colnames)) {
                    unset($table->fields[$alias]);
                }
            }

            $db_columns = $refl_table->getColumns();

            // Updates column properties
            foreach ($db_columns as $col) {
                $col_name = strtolower($col->name);
                $tbl_col = "$tbl_name.$col_name";

                $type = $db->expr()->to_urd_type($col->nativetype);
                if (
                    $type === 'integer' && $col->size === 1 &&
                    !isset($table->foreign_keys[$col_name])
                ) $type = 'boolean';

                $drop_me = false;
                $ratio_comment = '';
                $hidden = false;

                // Find if column is (largly) empty
                {
                    $count = $db->select('*')
                        ->from($tbl_name)
                        ->where($col_name . ' IS NOT NULL')
                        ->count();

                    // for setting comment in front of drop statements for not empty columns
                    $comment = $count > 0 ? '--' : '';

                    if ($count_rows && $count/$count_rows < $threshold) {

                        // for setting ratio of columns with value behind drop statements
                        $ratio_comment = $count ? '-- ' . $col->nativetype . "($col->size)  Brukt: " . $count. '/' . $count_rows : '';

                        if ($count == 0) {
                            $report[$tbl_name]['empty_columns'][] = $col_name;
                        } else {
                            $report[$tbl_name]['almost_empty_columns'][] = $col_name;
                        }

                        $drop_me = true;
                    }
                }

                // Find distinct values for some column types
                $comments = [];
                $values = [];
                do {
                    if ($count_rows < 2) break;
                    if (!in_array($type, ['integer', 'float', 'boolean', 'string'])) break;
                    if ($type == 'string' && ($col->size > 12 && $count/$count_rows > $threshold)) break;
                    if (in_array($col_name, $report[$tbl_name]['empty_columns'])) break;
                    if (in_array($col_name, $pk_columns)) break;

                    $sql = "select count(*) as count, $col_name as value
                            from $tbl_name
                            group by $col_name";
                    $distincts = $db->query($sql);

                    foreach ($distincts as $distinct) {
                        if ($distinct->count/$count_rows > (1 - $threshold)) {
                            $drop_me = true;
                        }

                        $value = $distinct->value === null ? 'NULL' : $distinct->value;
                        $comments[] = $value . ' (' . $distinct->count . ')';
                        $values[] = $distinct->value;
                    }
                } while (false);

                if ($drop_me) {

                    $hidden = true;

                    // If column is in a fk, drop the fk before dropping column
                    $rec_comment = '';
                    $sub = [];
                    foreach ($table->foreign_keys as $key) {
                        if (in_array($col_name, $key->local)) {
                            if ($db->platform === 'mysql') {
                                $sub[] = "drop foreign key $key->name";
                            } else {
                                $sub[] = "drop constraint $key->name";
                            }

                            if (count($values) && count($values) < 5) {

                                $conditions = [];
                                foreach ($key->local as $i => $field_name) {
                                    $conditions[] = 'l.'.$field_name . ' = ' . 'f.' . $key->foreign[$i];
                                }
                                $on_clause = implode(' AND ', $conditions);

                                $sql = "select distinct f.* from $key->table f
                                        join $tbl_name l on $on_clause
                                        where l.$col_name in (?)";

                                $records = $db->query($sql, $values);

                                foreach ($records as $rec) {
                                    $rec_comment .= "   -- -- Rec: " . str_replace(',"', ', "', json_encode($rec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "\n";
                                }
                            }

                        }
                    }

                    $sub[] = "drop column $col_name; $ratio_comment\n";
                    $drops[$tbl_col] = "$comment alter table $tbl_name " . implode(', ', $sub);

                    if ($type == 'string' && $col->size > 12) {
                        for ($i = 0; $i < 4; $i++) {
                            if (!isset($comments[$i])) break;
                            if (substr($comments[$i], 0, 4) === 'NULL') continue;
                            $drops[$tbl_col] .= "   -- -- Eks: '$comments[$i]'\n";
                        }
                    } else if (count($comments)) {
                        $drops[$tbl_col] .= "   -- -- Distinkte verdier: " . implode(", ", $comments);
                    }
                    if ($rec_comment) $drops[$tbl_col] .= "\n" . $rec_comment;
                }

                // Find alias ($key) of existing column in schema
                if (isset($table->fields[$col_name])) {
                    $key = $col_name;
                } else {
                    $items = array_filter($table->fields, function($item) use ($col_name) {
                        return $item->name === $col_name;
                    });

                    $key = key($items);
                }

                // Desides what sort of input should be used
                // todo: support more
                if (!empty($table->fields[$key]->element)) {
                    $element = $table->fields[$key]->element;
                } else if ($type === 'date') {
                    $element = 'input[type=date]';
                } else if ($type === 'boolean') {
                    if ($col->nullable) {
                        $element = 'select';
                        $options = [
                            [
                                'value' => 0,
                                'label' => 'Nei'
                            ],
                            [
                                'value' => 1,
                                'label' => 'Ja'
                            ]
                        ];
                    } else {
                        $element = 'input[type=checkbox]';
                    }
                } else if (isset($table->foreign_keys[$col_name])) {
                    $element = 'select';
                    $options = null;
                } else if ($type == 'binary' || ($type == 'string' && (!$col->size || $col->size > 255))) {
                    $element = 'textarea';
                } else {
                    $element = 'input[type=text]';
                }

                $label = isset($terms[$col_name])
                    ? $terms[$col_name]['label']
                    : $col_name;
                
                if ($config->norwegian_chars) {
                    $label = str_replace('ae', 'æ', $label);
                    $label = str_replace('oe', 'ø', $label);
                    $label = str_replace('aa', 'å', $label);
                }

                $label = ucfirst($label);

                $urd_col = (object) [
                    'name' => $col_name,
                    'element' => $element,
                    'datatype' => $type,
                    'nullable' => $col->nullable,
                    'label' => $label,
                    'description' => isset($terms[$col_name])
                        ? $terms[$col_name]['description']
                        : null,
                ];
                if ($type !== 'boolean') {
                    $urd_col->size = $col->size;
                }
                if ($col->autoincrement) {
                    $urd_col->extra = 'auto_increment';
                }
                if ($element === 'select' && !empty($options)) {
                    $urd_col->options = $options;
                }

                if ($hidden) {
                    $urd_col->hidden = true;
                }

                if (!$key) {
                    $table->fields[$col_name] = $urd_col;
                } else {
                    $table->fields[$key] = (object) array_merge(
                        (array) $table->fields[$key],
                        (array) $urd_col
                    );
                }
            }

            // Try to decide if the table is a reference table
            if ($config->urd_structure) {
                if (
                    substr($tbl_name, 0, 4) === 'ref_' ||
                    substr($tbl_name, -4) === '_ref' ||
                    substr($tbl_name, 0, 5) === 'meta_'
                ) {
                    $table->type = 'reference';
                } else {
                    $table->type = 'data';
                }
            } else {
                // use number of visible fields to decide if table is reference table
                $count_visible_fields = count(array_filter($table->fields, function($field) {
                    return !empty($field->hidden);
                }));
                if (
                    !isset($table->type) &&
                    $count_visible_fields < 4 &&
                    count($table->foreign_keys) == 0
                ) {
                    $table->type = 'reference';
                } else {
                    if (isset($config->dirty->{$table->name}->type)) {
                        $table->type = $config->dirty->{$table->name}->type;
                    }
                    $table->type = isset($table->type) ? $table->type : 'data';
                }
            }

            // Decide which columns should be shown in grid
            if ($grid_idx) {
                $table->grid = (object) [
                    'columns' => $grid_idx->columns,
                    'sort_columns' => $sort_cols
                ];
            } else {
                $table->grid = (object) [
                    'columns' => array_slice(array_keys(array_filter((array) $table->fields, function($field) use ($table) {
                        // Don't show hidden columns
                        if (substr($field->name, 0, 1) === '_') return false;
                        if (!empty($field->hidden)) return false;
                        // an autoinc column is an integer column that is also primary key (like in SQLite)
                        return !($field->datatype == 'integer' && [$field->name] == $table->primary_key)
                               // but we show autoinc columns for reference tables
                               || $table->type == 'reference';
                    })), 0, 5),
                    'sort_columns' => $sort_cols
                ];
            }

            // Make ation for displaying files
            if (isset($table->indexes[$tbl_name . '_file_path_idx'])) {

                $last_col = end($table->indexes[$tbl_name . '_file_path_idx']->columns);

                $action = (object) [
                    "label" => "Vis fil",
                    "url" => "/file",
                    "icon" => "external-link",
                    "communication" => "download",
                    "disabled" => '(' . $last_col . ' is null)'
                ];

                $table->actions = (object) [
                    "vis_fil" => $action
                ];

                $table->grid->columns[] = 'actions.vis_fil';
            }



            // Make form
            {
                $form = [
                    'items' => []
                ];

                $col_groups = [];

                // Group fields according to first part of field name
                foreach ($table->fields as $field) {
                    // Don't add column to form if it's part of primary key but not shown in grid
                    if (
                        in_array($field->name, $pk_columns)
                        && (
                            (isset($table->grid) && !in_array($field->name, $table->grid->columns))
                            || !isset($table->grid)
                        )
                    ) continue;

                    // Group by prefix
                    $parts = explode('_', $field->name);
                    $group = $parts[0];

                    // Don't add fields that start with _
                    // They are treated as hidden fields
                    if ($group == '') $field->hidden = true;
                    if (!empty($field->hidden)) continue;

                    if (!isset($col_groups[$group])) $col_groups[$group] = [];
                    $col_groups[$group][] = $field->name;
                }

                foreach ($col_groups as $group_name => $col_names) {
                    if (count($col_names) == 1) {
                        $label = ucfirst(str_replace('_', ' ', $col_names[0]));
                        if ($config->norwegian_chars) {
                            $label = str_replace('ae', 'æ', $label);
                            $label = str_replace('oe', 'ø', $label);
                            $label = str_replace('aa', 'å', $label);
                        }
                        $form['items'][$label] = $col_names[0];
                    } else {
                        foreach ($col_names as $i => $col_name) {
                            // removes group name prefix from column name and use the rest as label
                            $rest = str_replace($group_name . '_', '', $col_name);
                            $label = isset($terms[$rest])
                                ? $terms[$rest]['label']
                                : ucfirst(str_replace('_', ' ', $rest));
                            // replace indexed key in array with named key
                            $col_names[$label] = $col_name;
                            unset($col_names[$i]);
                        }
                        $group_label = isset($terms[$group_name])
                            ? $terms[$group_name]['label']
                            : ucfirst($group_name);
                        $form['items'][$group_label] = [
                            'items' => $col_names
                        ];
                    }
                }

                $table->form = $form;
            }


            // Add table to table group
            {
                if ($config->urd_structure) {
                    $group = explode('_', $tbl_alias)[0];

                    // Find if the table is subordinate to other tables
                    // i.e. the primary key also has a foreign key
                    $subordinate = false;
                    if (empty($table->primary_key)) $subordinate = true;
                    foreach ($table->primary_key as $colname) {
                        if (isset($table->foreign_keys[$colname])) {
                            $subordinate = true;
                        }
                    }
                } else {
                    $group = $tbl_alias;
                    $subordinate = false;
                }

                // Only add tables that are not subordinate to other tables
                if (!$subordinate) {
                    // Remove group prefix from label
                    $rest = str_replace($group . '_', '', $tbl_alias);
                    $label = isset($terms[$rest])
                        ? $terms[$rest]['label']
                        : ucfirst(str_replace('_', ' ', $rest));

                    if (!isset($tbl_groups[$group])) $tbl_groups[$group] = [];
                    $tbl_groups[$group][$label] = $tbl_alias;
                }
            }

            $this->tables[$tbl_alias] = $table;

            // Update records for reference tables
            {
                if (empty($config->add_ref_records)) {
                    unset($table->records);
                    continue;
                }

                if (!isset($db->tables[$tbl_alias])) continue;

                if ($table->type !== 'reference') continue;

                $sql = "SELECT * FROM $table->name";
                $pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);

                $table->records = $db->conn->query($sql)->fetchAll();
            }

            // Updates table definition with records
            $this->tables[$tbl_alias] = $table;

        }


        // Add form data from associated tables
        foreach ($this->tables as $tbl_alias => $table) {

            // Add fields from expansion tables
            if (!empty($table->extension_tables)) {
                foreach ($table->extension_tables as $ext) {
                    $rest = str_replace($table->name . '_', '', $ext);
                    $label = isset($terms[$rest]) ? $terms[$rest]['label'] : ucfirst($rest);
                    $table->form["items"][$label] = [
                        "items"=> $this->tables[$ext]->form["items"]
                    ];
                }
            }

            // Add relations to form
            if (isset($table->relations)) {
                foreach ($table->relations as $alias => $relation) {
                    $relation = (object) $relation;

                    if (!isset($this->tables[$relation->table])) {
                        unset($table->relations[$alias]);
                        continue;
                    }

                    $rel_table = $this->tables[$relation->table];

                    if (!empty($rel_table->hidden)) {
                        $table->relations[$alias]['hidden'] = true;
                    }

                    $fk = $rel_table->foreign_keys[$relation->foreign_key];

                    // Find indexes that can be used to get relation
                    $indexes = array_filter($rel_table->indexes, function($index) use ($fk) {
                        return array_slice($index->columns, 0, count($fk->local)) === $fk->local;
                    });

                    if (count($indexes) && empty($rel_table->hidden)) {
                        $label = !empty($relation->label) ? ucfirst($relation->label) : ucfirst($alias);
                        $table->form['items'][$label] = 'relations.'.$alias;
                    }

                    $ref_field_name = end($fk->local);
                    $ref_field = $this->tables[$relation->table]->fields[$ref_field_name];
                    $ref_tbl_col = $relation->table . '.' . $ref_field_name;

                    // Don't show relations coming from hidden fields
                    if (empty($config->urd_structure) && !empty($ref_field->hidden)) {
                        $relation->hidden = true;
                        if (!empty($label)) unset($table->form['items'][$label]);
                    }

                    // Don't show fields referring to hidden table
                    if (!empty($table->hidden) && !in_array($ref_field_name, $rel_table->primary_key)) {
                        $ref_field->hidden = true;
                    } else if (isset($config->dirty->{$table->name}->hidden) && !isset($drops[$ref_tbl_col])) {
                        // unset property hidden for columns where fk table is shown again
                        // and where the column is not hidden for other reasons
                        unset($ref_field->hidden);
                    }
                    $this->tables[$relation->table]->fields[$ref_field_name] = $ref_field;

                    $table->relations[$alias] = $relation;

                }
            }

            // Add drop table statement if hidden or less than 2 rows
            $rows = $report[$tbl_alias]['rows'];
            $comment = $rows == 0 ? '' : '-- ';

            if ($rows < 2 || !empty($table->hidden)) {
                // drop foreign key constraints first
                $statements = [];
                $records = [];
                foreach ($table->relations as $alias => $rel) {
                    $rel = (object) $rel;
                    $tbl_col_fk = $rel->table . "." . $rel->foreign_key;
                    if (isset($drops[$tbl_col_fk])) {
                        $new_statements = explode("\n", $drops[$tbl_col_fk]);
                        foreach ($new_statements as $i => $stmt) {
                            if (strpos($stmt, '{') !== false) {
                                $records[] = $stmt;
                                unset($new_statements[$i]);
                            }
                        }
                        $new_statements = array_filter($new_statements);
                        $statements = array_merge($statements, $new_statements);

                        unset($drops[$tbl_col_fk]);
                    } else {

                        $sub = [];

                        $key = $this->tables[$rel->table]->foreign_keys[$rel->foreign_key];

                        if ($db->platform === 'mysql') {
                            $sub[] = "drop foreign key $key->name";
                        } else {
                            $sub[] = "drop constraint $key->name";
                        }

                        $sub[] = "drop column $rel->foreign_key;";

                        $statements[] = $comment . "alter table $rel->table " . implode(', ', $sub);
                    }
                }

                array_unshift($statements, "\n-- -- -- $tbl_alias ($rows rader) - drop -- -- --");

                $statements[] = $comment . "drop table $table->name;";

                $records = array_unique($records);
                $statements = array_merge($statements, $records);

                $drops[$tbl_alias] = implode("\n", $statements);

            } else {
                $drops[$tbl_alias] = "\n-- -- -- $tbl_alias ($rows rader) -- -- --";
            }

            // Add delete statements for unreferenced records in reference tables
            if ($table->type == 'reference' && count($table->relations)) {
                $exists_conditions = [];
                foreach ($table->relations as $relation) {
                    $relation = (object) $relation;

                    // Exclude relation defining hierarchy within same table
                    if ($relation->table == $table->name) continue;

                    $fk = $this->tables[$relation->table]->foreign_keys[$relation->foreign_key];

                    $conditions = [];

                    foreach ($fk->local as $i => $col) {
                        $conditions[] = "$relation->table.$col = $table->name." . $fk->foreign[$i];
                    }

                    $exists_conditions[] = "select * from $relation->table\n        " .
                                           "where " . implode(' and ', $conditions);
                }

                $where = "where not exists (\n        " .
                    implode("\n      ) and not exists (\n        ", $exists_conditions) .
                    "\n      );";

                $select = "select count(*) from $table->name\n" . $where;

                $count = $db->conn->query($select)->fetchSingle();

                $delete = "delete from $table->name\n" . $where;

                if ($count) {
                    $drops[$tbl_alias] .= "\n-- -- Slett $count rader\n" . $delete;
                }
            }

            // Find how tables are grouped in modules

            $top_level = true;

            // Reference tables should not be used to group tables in modules
            if ($table->type == 'reference') {
                $top_level = false;
            }

            foreach ($table->foreign_keys as $alias => $fk) {
                if (!isset($this->tables[$fk->table])) continue;

                if ($fk->table !== $table->name && empty($table->fields[$alias]->hidden)) {
                    $fk_table = $this->tables[$fk->table];

                    if ($fk_table->type !== 'reference') {
                        $top_level = false;
                    }
                }
            }

            if ($top_level) {
                $related_tables = $this->get_relation_tables($tbl_alias, []);
                $related_tables[] = $table->name;

                $module_id = null;
                foreach ($modules as $i => $module) {
                    $common = array_intersect($related_tables, $module);
                    if (count($common) > 0) {
                        if (is_null($module_id)) {
                            $modules[$i] = array_unique(array_merge($module, $related_tables));
                            $module_id = $i;
                        } else {
                            $modules[$module_id] = array_unique(array_merge($module, $modules[$module_id]));
                            unset($modules[$i]);
                         }
                    }
                }

                if (is_null($module_id)) {
                    $modules[] = $related_tables;
                }
            }

            $this->tables[$tbl_alias] = $table;
        }

        // Check if reference table is attached to only one mudule
        foreach ($this->tables as $tbl_alias => $table) {
            if ($table->type != 'reference') continue;
            $in_modules = [];

            foreach ($table->relations as $rel) {
                if ($rel->hidden) continue;
                $rel = (object) $rel;
                foreach ($modules as $i => $module) {
                    if (in_array($rel->table, $module)) {
                        $in_modules[] = $i;
                    }
                }
            }

            $in_modules = array_unique($in_modules);

            if (count($in_modules) == 1) {
                $mod = $in_modules[0];
                $modules[$mod][] = $tbl_alias;
                $modules[$mod] = array_unique($modules[$mod]);
             }
        }

        $main_module = max($modules); // Find module with most tables

        // Generate drop statements for tables not connected to other tables
        if (count($main_module) > 2) {
            foreach ($modules as $module) {
                if (count($module) == 1) {
                    $tbl_name = $module[0];
                    if (strpos($drops[$tbl_name], "drop table $tbl_name") === false) {
                        // Add underscore to key for placing drop statement for table after drop statements for columns
                        $drops[$tbl_name .'_'] = "-- drop table $tbl_name;";
                        $tbl_name = $tbl_name . '_';
                    }
                    $drops[$tbl_name] .= "  -- Ikke knyttet til andre tabeller";
                }
            }
        }

        ksort($drops);


        // Makes contents

        $contents = [];

        // Sort modules so that modules with most tables are listed first
        array_multisort(array_map('count', $modules), SORT_DESC, $modules);

        foreach ($tbl_groups as $group_name => $table_names) {
            if (count($table_names) == 1 && $group_name != 'meta') {
                // Get first element in array
                $table_alias = reset($table_names);
                $label = isset($terms[$table_alias])
                    ? $terms[$table_alias]['label']
                    : ucfirst(str_replace('_', ' ', $table_alias));

                if ($config->norwegian_chars) {
                    $label = str_replace('ae', 'æ', $label);
                    $label = str_replace('oe', 'ø', $label);
                    $label = str_replace('aa', 'å', $label);
                }

                // Loop through modules to find which one the table belongs to
                $placed = false;

                if ($config->urd_structure) {
                    $contents[$label] = 'tables.' . $table_alias;
                    continue;
                }

                foreach ($modules as $i => $module) {
                    if (count($module) > 2 && in_array($table_alias, $module)) {
                        $mod = 'Modul ' . ($i + 1);
                        $contents[$mod]['class_label'] = 'b';
                        $contents[$mod]['class_content'] = 'ml3';
                        $contents[$mod]['items'][$label] = 'tables.' . $table_alias;
                        if (!isset($contents[$mod]['count'])) $contents[$mod]['count'] = 0;
                        $contents[$mod]['count']++;
                        $placed = true;
                    }
                }
                if (!$placed) {
                    if (!isset($contents['Andre'])) {
                        $contents['Andre'] = ['class_label' => 'b', 'class_content' => 'ml3', 'items' => [], 'count' => 0];
                    }
                    $contents['Andre']['items'][$label] = 'tables.' . $table_alias;
                    $contents['Andre']['count']++;
                }
            } else {
                $label = isset($terms[$group_name]) ? $terms[$group_name]['label'] : ucfirst($group_name);
                if ($label === 'Ref') $label = 'Referansetabeller';

                if ($config->urd_structure) {
                    $contents[$label] = [
                        'class_label' => 'b',
                        'class_content' => 'ml3',
                        'items' => array_map(function($value) { return 'tables.'.$value; }, $table_names)
                    ];

                    continue;
                }

                $placed = false;
                foreach ($modules as $i => $module) {
                    if (count($module) > 2 && count(array_intersect($table_names, $module))) {
                        $mod = 'Modul ' . ($i + 1);
                        $contents[$mod]['class_label'] = 'b';
                        $contents[$mod]['class_content'] = 'ml3';
                        $contents[$mod]['items'][$label] = [
                            'class_label' => 'b',
                            'class_content' => 'ml3',
                            'items' => $table_names
                        ];
                        if (!isset($contents[$mod]['count'])) $contents[$mod]['count'] = 0;
                        $contents[$mod]['count'] += count($table_names);
                        $placed = true;
                    }
                }

                if (!$placed) {
                    if (!isset($contents['Andre'])) {
                        $contents['Andre'] = ['class_label' => 'b', 'class_content' => 'ml3', 'items' => [], 'count' => 0];
                    }
                    $contents['Andre']['items'][$label] = [
                        'class_label' => 'b',
                        'class_content' => 'ml3',
                        'items' => $table_names
                    ];
                    $contents['Andre']['count'] += count($table_names);
                }
            }
        }

        ksort($contents, SORT_NATURAL);

        // Move 'Andre' last
        if (!empty($contents['Andre'])) {
            $other = $contents['Andre'];
            unset($contents['Andre']);
            $contents['Andre'] = $other;
        }

        $this->contents = $contents;

        if (!empty($config->add_criteria)) {
            unset($config->dirty);
            $this->criteria = $config;
        }

        $_SESSION['progress'] = 100;
        session_write_close();
        session_start();


        if (!file_exists(__DIR__ . '/../../schemas/' . $db->schema)) {
            try {
                mkdir(__DIR__ . '/../../schemas/' . $db->schema);
            } catch (\Exception $e) {
                return ['success' => false, 'msg' => 'Feilet: PHP-brukeren har ikke skriverettigheter'];
            }
        }

        $json_string = json_encode(get_object_vars($this), JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json_string === false) {
            return ['success' => false, 'msg' => json_last_error_msg()];
        }

        $schema_file = __DIR__ . '/../../schemas/' . $db->schema . '/schema.json';
        $drop_file   = __DIR__ . '/../../schemas/' . $db->schema . '/drop.sql';

        try {
            $fh_schema = fopen($schema_file, 'w');
            $fh_drop   = fopen($drop_file, 'w');
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => 'Feilet: PHP-brukeren har ikke skriverettigheter'];
        }

        fwrite($fh_schema, $json_string);
        fwrite($fh_drop, implode("\n", $drops));

        return ['success' => true, 'msg' => 'Skjema oppdatert', 'warn' => $warnings];
    }

    /*
     * Update json file from urd.tabell og urd.kolonne (URD v0.5)
     */
    public function update_schema_from_urd_tables()
    {
        if (!file_exists(__DIR__ . '/../../schemas/' . $this->name)) {
            mkdir(__DIR__ . '/../../schemas/' . $this->name);
        }

        $schema_file = __DIR__ . '/../../schemas/' . $this->name . '/schema.json';

        if (file_exists($schema_file)) {
            $schema = json_decode(file_get_contents($schema_file), true);
        } else {
            $schema = [
                'tables' => [],
            ];
        }

        // Find all tables in schema
        $tables = DB::get()->conn->select('*')
            ->from('tabell')
            ->where('databasemal = ?', $this->name)
            ->fetchAll();

        foreach ($tables as $table) {
            if (!array_key_exists($table->tabell, $schema['tables'])) {

                // Get table type
                if ($table->grunndata) {
                    $type = 'reference';
                } else if ($table->koblingstabell) {
                    $type = 'cross-reference';
                } else {
                    $type = 'data';
                }

                $record = [
                    'name' => $table->tabell,
                    'primary_key' => array_map('trim', explode(',', $table->prim_nokkel)),
                    'foreign_keys' => [],
                    'type' => $type,
                    'fields' => [],
                    'grid' => [],
                    'relations' => [],
                ];

                $schema['tables'][$table->tabell] = $record;
            }

            $columns = DB::get()->conn->select('*')
                ->from('kolonne')
                ->where('databasemal = ?', $this->name)
                ->and('tabell = ?', $table->tabell)
                ->fetchAll();

            foreach ($columns as $column) {
                $column_fields = explode(',', $column->kolonne);
                $column->name = strtolower(trim(end($column_fields)));

                // Add foreign key
                if ($column->kandidattabell) {
                    $key = [
                        'name' => $column->name,
                        'local' => array_map('trim', explode(',', $column->kolonne)),
                        'schema' => $column->kandidatmal ? $column->kandidatmal : $this->name,
                        'table' => $column->kandidattabell,
                        'foreign' => array_map('trim', explode(',', strtolower($column->kandidatnokkel))),
                    ];
                    $schema['tables'][$table->tabell]['foreign_keys'][$column->name] = $key;
                }

                // Add field info
                $field = [
                    'label' => $column->ledetekst,
                    'element' => $this->get_new_element($column->felttype),
                    'description' => $column->beskrivelse,
                ];

                if ($column->kandidatvisning) {
                    $field['view'] = $column->kandidatvisning;
                }

                if ($column->tabellvisning) {
                    $schema['tables'][$table->tabell]['grid'][] = $column->name;
                }

                $schema['tables'][$table->tabell]['fields'][$column->name] = $field;
            }

            // Get relations to this table
            $relation_fields = DB::get()->conn->select('databasemal, tabell, kolonne')
                ->from('kolonne')
                ->where('kandidatmal = ?', $this->name)
                ->or('(kandidatmal IS NULL AND databasemal = ?)', $this->name)
                ->and('kandidattabell = ?', $table->tabell)
                ->fetchAll();

            foreach ($relation_fields as $field) {
                $column_fields = explode(',', $field->kolonne);
                $field->name = strtolower(trim(end($column_fields)));

                $relation = [
                    'label' => $field->tabell,
                    'schema' => $field->databasemal,
                    'table' => $field->tabell,
                    'foreign_key' =>$field->name
                ];

                $schema['tables'][$table->tabell]['relations'][$field->tabell] = $relation;
            }


        }

        $fh_schema = fopen(substr_replace($schema_file, '_new', strpos($schema_file, '.json'), 0), 'w');
        fwrite($fh_schema, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function create_tables_from_schema($db_name)
    {
        $db = DB::get($db_name);

        // Create tables with primary key and indexes

        foreach ($this->tables as $table) {

            // Create table

            $sql = "create table $table->name (";
            $columns = [];

            $autoinc_column = null;

            foreach ($table->fields as $field) {
                $size = isset($field->size) ? $field->size : null;
                $notnull = $field->nullable ? '' : ' not null';
                if (isset($field->extra) && $field->extra == 'auto_increment') $autoinc_column = $field;
                $columns[$field->name] = $field->name . ' ' . $db->expr($field->datatype)->to_native_type($size) . $notnull;
            }

            $sql .= implode(', ', $columns);

            // Add primary key

            $pk_columns = [];
            foreach ($table->primary_key as $col) {
                $pk_columns[] = $table->fields[$col]->name;
            }

            $pk = implode(',', $pk_columns);

            $sql .= ", constraint {$table->name}_pk primary key ($pk)";

            // Add foreign keys

            foreach ($table->foreign_keys as $fk) {

                // get foreign keys columns
                $fk_columns = [];
                foreach ($fk->local as $alias) {
                    $fk_columns[] = $table->fields[$alias]->name;
                }
                $fk_columns_str = implode(', ', $fk_columns);
                $sql .= ", constraint $fk->name ";
                $sql .= "foreign key ($fk_columns_str) ";

                // get reference table and columns
                $ref_table = $this->tables[$fk->table]->name;
                $ref_columns = [];
                foreach ($fk->foreign as $alias) {
                    $ref_columns[] = $this->tables[$fk->table]->fields[$alias]->name;
                }
                $ref_columns_str = implode(', ', $ref_columns);

                $sql.= "references $ref_table($ref_columns_str)";
            }

            $sql .= ')';

            $db->query($sql);

            // Add autoinc if mysql (other databases not supported yet)
            // SQLite doesn't need it

            if ($db->platform == 'mysql' && $autoinc_column) {
                $sql = "alter table $table->name modify column " . $columns[$autoinc_column->name] . " auto_increment";
                $db->query($sql);
            }

            // Add indexes

            if (!isset($table->indexes)) $table->indexes = [];

            foreach ($table->indexes as $index) {

                if ($index->primary == true) continue;

                $columns = [];
                foreach ($index->columns as $column) {
                    $columns[] = $table->fields[$column]->name;
                }
                $columns_str = implode(', ', $columns);

                $unique = $index->unique ? 'unique' : '';

                $sql = "create $unique index $index->name on $table->name ($columns_str)";
                $db->query($sql);
            }

            // Add records

            if (!isset($table->records)) $table->records = [];

            foreach ($table->records as $rec) {
                $db->insert($table->name, (array) $rec)->execute();
            }
        }
    }

    private function get_new_element($old_element) {
        switch ($old_element) {
        case 'textfield':
            return 'input[type=text]';
        case 'dropdownlist':
        case 'dropdownlazy':
            return 'select';
        case 'checkbox':
            return 'input[type=checkbox]';
        case 'textarea':
            return 'textarea';
        default:
            return false;
        }
    }

}
