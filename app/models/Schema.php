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
        if ($this->name == 'urd') {
            return dibi::getConnection()->getConfig('database');
        } else {
            $sql = "SELECT name, alias
                    FROM   database_
                    WHERE  schema_ = '$this->name'";

            $base = DB::get()->conn->query($sql)->fetch();

            return $base->alias ? $base->alias : $base->name;
        }
    }

    public function get_db_name() {
        if ($this->name == 'urd') {
            return dibi::getConnection()->getConfig('database');
        } else {
            $sql = "SELECT name
                    FROM   database_
                    WHERE  schema_ = '$this->name'";

            $base = DB::get()->conn->query($sql)->fetch()->name;

            return $base;
        }
    }

    /*
     * Update json file from actual database structure
     */
    public function update_schema_from_database($db_name)
    {
        ini_set('max_execution_time', 600); // 10 minutes

        $_SESSION['progress'] = 0;

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
        if ($db->platform !== 'sqlite') {
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

        $tbl_groups = [];
        $warnings = [];

        $total = count($db_tables);
        $processed = -1;

        $urd_structure = !empty(array_filter($db_tables, function($tbl_name) {
            return strpos($tbl_name, 'ref_') !== false || strpos($tbl_name, '_ref');
        }));

        foreach ($db_tables as $tbl_name) {

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

            if ($db->platform !== 'sqlite') {
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
                $table->name = strtolower($tbl_name);
                $table->label = isset($terms[$tbl_name])
                    ? $terms[$tbl_name]['label'] : (
                        isset($table->label) ? $table->label : null
                    );
                $table->description = isset($terms[$tbl_name]) 
                    ? $terms[$tbl_name]['description'] : (
                        isset($table->description) ? $table->description : null
                    );
                $table->primary_key = !empty($pk_columns) ? $pk_columns : $table->primary_key;
            }

            $colnames = $refl_table->getColumnNames();

            if ($urd_structure) {
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
                if (!isset($table->type) && count($colnames) < 4) {
                    $table->type = 'reference'; 
                } else {
                    $table->type = 'data';
                }
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
                $table->foreign_keys[$key_alias] = $key;

                // Add to relations of relation table
                $key_table_alias = isset($tbl_aliases[$key->table])
                ? $tbl_aliases[$key->table]
                : $key->table;
                if (!isset($this->tables[$key_table_alias])) {
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

                $key_index = array_reduce($table->indexes, function($carry, $index) use ($key) {
                    if (!$carry && $index->columns === $key->local) {
                        $carry = $index;
                    }
                    return $carry;
                });

                if (!$key_index) continue;

                $patterns = [];
                $patterns[] = '/^(?:fk_|idx_)?(?:' . $key->table . '_)?/';
                $patterns[] = '/(?:_' . implode('_', $key->local) . ')?(?:_fk|_idx)?$/';

                $replacements = ['', '', '', ''];

                $label = $urd_structure 
                    ? ucfirst(str_replace('_', ' ', preg_replace($patterns, $replacements, $key_index->name)))
                    : ucfirst($tbl_alias);

                if (!isset($table->extends)) {
                    $this->tables[$key_table_alias]->relations[$key->name] = [
                        "table" => $tbl_name,
                        "foreign_key" => $key_alias,
                        "label" => $label,
                    ];
                }
            }


            // Updates column properties

            if (!isset($table->fields)) {
                $table->fields = [];
            }

            // Fields may be defined with alias the same as name,
            // and avoid specifying the name
            if (count($table->fields)) {
                foreach ($table->fields as $alias => $field) {
                    if (!isset($field->name)) $table->fields[$alias]->name = $alias;
                }
            }

            $db_columns = $refl_table->getColumns();

            foreach ($db_columns as $col) {
                $col_name = strtolower($col->name);

                $type = $db->expr()->to_urd_type($col->nativetype);
                if ($type === 'integer' && $col->size === 1) $type = 'boolean';

                $items = array_filter($table->fields, function($item) use ($col_name) {
                    return $item->name === $col_name;
                });

                $key = key($items);

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
                } else if ($type == 'binary' || ($type == 'string' && (!$col->size || $col->size > 60))) {
                    $element = 'textarea';
                } else {
                    $element = 'input[type=text]';
                }

                $urd_col = (object) [
                    'name' => $col_name,
                    'element' => $element,
                    'datatype' => $type,
                    'nullable' => $col->nullable,
                    'label' => isset($terms[$col_name])
                        ? $terms[$col_name]['label']
                        : null,
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

                if (!$key) {
                    $table->fields[$col_name] = $urd_col;
                } else {
                    $table->fields[$key] = (object) array_merge(
                        (array) $table->fields[$key],
                        (array) $urd_col
                    );
                }
            }

            if ($grid_idx) {
                $table->grid = (object) [
                    'columns' => $grid_idx->columns,
                    'sort_columns' => $sort_cols
                ];
            } else {
                $table->grid = (object) [
                    'columns' => array_slice(array_keys(array_filter((array) $table->fields, function($field) use ($table) {
                        // an autoinc column is an integer column that is also primary key (like in SQLite)
                        return !($field->datatype == 'integer' && [$field->name] == $table->primary_key)
                               // but we show autoinc columns for reference tables
                               || $table->type == 'reference';
                    })), 0, 5),
                    'sort_columns' => $sort_cols
                ];
            }

            

            // Make form

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

                if (!isset($col_groups[$group])) $col_groups[$group] = [];
                $col_groups[$group][] = $field->name;
            }

            foreach ($col_groups as $group_name => $col_names) {
                if (count($col_names) == 1) {
                    $label = ucfirst(str_replace('_', ' ', $col_names[0]));
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

            $this->tables[$tbl_alias] = $table;

            // Group tables
            $parts = explode('_', $tbl_alias);
            $group = $parts[0];
            
            if (!isset($tbl_groups[$group])) $tbl_groups[$group] = [];

            // Find if the table is subordinate to other tables
            // i.e. it has an other table's name as prefix
            $parent_tables = array_filter($db_tables, function($name) use ($tbl_name, $table) {
                // add underscore to $name if it doesn't end with underscore
                $name_ = substr($name, -1) === '_' ? $name : $name . '_';
                return ($name != $tbl_name && substr($tbl_name, 0, strlen($name_)) === $name_)
                    || (!empty($table->extends) && $table->extends === $name);
            });

            // Only add tables that are not subordinate to other tables
            if (!count($parent_tables)) {
                $tbl_groups[$group][] = $tbl_alias;
            }

            // Update records for reference tables

            if (!isset($db->tables->$tbl_alias)) continue;

            $tbl = Table::get($db->name, $tbl_alias);

            if ($tbl->type !== 'reference') continue;

            $sql = "SELECT * FROM $tbl->name";
            $pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
            $records = $db->conn->query($sql)->fetchAll();

            $this->tables[$tbl_alias]->records = [];

            foreach ($records as $record) {
                $this->tables[$tbl_alias]->records[] = $record;
            }

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
                    $label = !empty($relation->label) ? ucfirst($relation->label) : ucfirst($alias);
                    $table->form['items'][$label] = 'relations.'.$alias;
                }
            }

            $this->tables[$tbl_alias] = $table;
        }


        // Makes contents
        $contents = [
            'Innhold' => [
                'items' => []
            ]
        ];

        foreach ($tbl_groups as $group_name => $table_names) {
            if (count($table_names) == 1 && $group_name != 'meta') {
                $table_alias = $table_names[0];
                $label = isset($terms[$table_alias]) 
                    ? $terms[$table_alias]['label']
                    : ucfirst(str_replace('_', ' ', $table_alias));
                $contents['Innhold']['items'][$label] = 'tables.' . $table_alias;
            } else {
                // Remove group prefix from label
                foreach ($table_names as $i => $table_alias) {
                    unset($table_names[$i]);
                    $rest = str_replace($group_name . '_', '', $table_alias);
                    $label = isset($terms[$rest]) 
                        ? $terms[$rest]['label']
                        : ucfirst(str_replace('_', ' ', $rest));
                    $table_names[$label] = 'tables.' . $table_alias;
                }
                $label = isset($terms[$group_name]) ? $terms[$group_name]['label'] : ucfirst($group_name);
                if ($label === 'Ref') $label = 'Referansetabeller';
                $contents['Innhold']['items'][$label] = [
                    'class_label' => 'b', 
                    'class_content' => 'ml3',
                    'items' => $table_names
                ];
            }
        }

        $this->contents = $contents;

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

        try {
            $fh_schema = fopen($schema_file, 'w');
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => 'Feilet: PHP-brukeren har ikke skriverettigheter'];
        }

        fwrite($fh_schema, $json_string);

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
