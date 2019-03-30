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
        $db = DB::get($db_name);

        if ($db->platform == 'oracle') {
            $reflector = new \URD\lib\OracleReflector($db->conn->getDriver());
        } else {
            $reflector = $db->conn->getDriver()->getReflector();
        }

        // Don't return keys in lowercase
        // Necessary for database reflection to work
        $pdo = $db->conn->getDriver()->getResource();
        $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);

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

        // Removes tables that doesn't exist
        foreach ($this->tables as $table_name => $table) {
            if (!in_array($table_name, $db_tables)) {
                unset($this->tables[$table_name]);
            }
        }

        foreach ($db_tables as $tbl_name) {

            if ($db->platform == 'oracle') {
                $refl_table = new \Dibi\Reflection\Table($reflector, ['name'=> $tbl_name, 'view' => false]);
            } else {
                $refl_table = $db->conn->getDatabaseInfo()->getTable($tbl_name);
            }

            // Updates table properties

            $pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
            $pk = $refl_table->getPrimaryKey();

            $pk_columns = [];
            if ($pk) {
                foreach ($pk->columns as $column) {
                    $pk_columns[] = $column->getName();
                }
            }

            if (!array_key_exists($tbl_name, $this->tables)) {
                $record = (object) [
                    'name' => $tbl_name,
                    'icon' => null,
                    'label' => null,
                    'primary_key' => $pk_columns,
                    'type' => 'data',
                    'description' => null,
                ];

                $this->tables[$tbl_name] = $record;
            } else {
                $table = $this->tables[$tbl_name];
                $table->name = $tbl_name;
                $table->label = isset($table->label) ? $table->label : null;
                $table->primary_key = isset($table->primary_key) ? $table->primary_key : $pk_columns;
                $table->type = isset($table->type) ? $table->type : 'data';

                $this->tables[$tbl_name] = $table;
            }

            // Updates indexes
            {
                $indexes = $reflector->getIndexes($tbl_name);

                if (!isset($this->tables[$tbl_name]->indexes)) {
                    $this->tables[$tbl_name]->indexes = [];
                }

                foreach ($indexes as $index) {
                    $index = (object) $index;
                    $alias = end($index->columns);
                    $this->tables[$tbl_name]->indexes[$alias] = $index;
                }
            }

            // Updates foreign keys
            if ($db->platform == 'oracle') {
                // TODO: Fix foreign keys for oracle
                $foreign_keys = [];
            } else {
                // $foreign_keys = $db->conn->getDatabaseInfo()->getTable($tbl_name)->getForeignKeys();
                $foreign_keys = $reflector->getForeignKeys($tbl_name);

                if (!isset($this->tables[$tbl_name]->foreign_keys)) {
                    $this->tables[$tbl_name]->foreign_keys = [];
                }

                foreach ($foreign_keys as $key) {
                    $urd_key = (object) $key;
                    unset($urd_key->onDelete);
                    unset($urd_key->onUpdate);
                    $urd_key->schema = $this->name;
                    $key_alias = end($urd_key->local);
                    $this->tables[$tbl_name]->foreign_keys[$key_alias] = $urd_key;

                    // Checks if the relation defines this as an extension table
                    if ($urd_key->local === $pk_columns) {
                        if (!array_key_exists($urd_key->table, $this->tables)) {
                            $this->tables[$urd_key->table] = (object) ['extension_tables' => []];
                        }
                        if (!in_array($tbl_name, $this->tables[$urd_key->table]->extension_tables)) {
                            $this->tables[$urd_key->table]->extension_tables[] = $tbl_name;
                        }
                    }
                }
            }

            if (!isset($table->type) && count($foreign_keys) == 0) {
                $this->tables[$tbl_name]->type = 'reference';
            }

            // Updates column properties

            if (!isset($this->tables[$tbl_name]->fields)) {
                $this->tables[$tbl_name]->fields = [];
            }

            $fields = $this->tables[$tbl_name]->fields;

            // Fields may be defined with alias the same as name,
            // and avoid specifying the name
            if (count($fields)) {
                foreach ($fields as $alias => $field) {
                    if (!isset($field->name)) $fields[$alias]->name = $alias;
                }
            }

            $db_columns = $refl_table->getColumns();

            foreach ($db_columns as $col) {
                $col_name = strtolower($col->name);

                $type = $db->expr()->to_urd_type($col->nativetype);
                if ($type === 'integer' && $col->size === 1) $type = 'boolean';

                $items = array_filter($fields, function($item) use ($col_name) {
                    return $item->name === $col_name;
                });

                $key = key($items);

                // Desides what sort of input should be used
                // todo: support more
                if (!empty($this->tables[$tbl_name]->fields[$key]->element)) {
                    $element = $this->tables[$tbl_name]->fields[$key]->element;
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
                } else if (isset($this->tables[$tbl_name]->foreign_keys[$col_name])) {
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
                    $this->tables[$tbl_name]->fields[$col_name] = $urd_col;
                } else {
                    $this->tables[$tbl_name]->fields[$key] = (object) array_merge((array) $this->tables[$tbl_name]->fields[$key], (array) $urd_col);
                }
            }

            // Update records for reference tables

            if (!isset($db->tables->$tbl_name)) continue;

            $tbl = Table::get($db->name, $tbl_name);

            if ($tbl->type !== 'reference') continue;

            $sql = "SELECT * FROM $tbl->name";
            $pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
            $records = $db->conn->query($sql)->fetchAll();

            $this->tables[$tbl_name]->records = [];

            foreach ($records as $record) {
                $this->tables[$tbl_name]->records[] = $record;
            }
        }

        if (!file_exists(__DIR__ . '/../../schemas/' . $db->schema)) {
            mkdir(__DIR__ . '/../../schemas/' . $db->schema);
        }

        $schema_file = __DIR__ . '/../../schemas/' . $db->schema . '/schema.json';

        $fh_schema = fopen($schema_file, 'w');
        // $fh_schema = fopen(substr_replace($schema_file, '_new', strpos($schema_file, '.json'), 0), 'w');
        fwrite($fh_schema, json_encode(get_object_vars($this), JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return 'success';
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

        /// Create tables with primary key and indexes

        foreach ($this->tables as $table) {

            /// Create table

            $sql = "create table $table->name (";
            $columns = [];

            $autoinc_column = null;

            foreach ($table->fields as $field) {
                $size = isset($field->size) ? $field->size : null;
                $notnull = $field->nullable ? '' : ' not null';
                if (isset($field->extra) && $field->extra == 'auto_increment') $autoinc_column = $field;
                $columns[$field->name] = $field->name . ' ' . $db->expr($field->datatype)->to_native_type($size) . $notnull;
            }

            $sql .= implode(', ', $columns) . ')';

            $db->query($sql);

            /// Add primary key

            $pk_columns = [];
            foreach ($table->primary_key as $col) {
                $pk_columns[] = $table->fields[$col]->name;
            }
            $pk = implode(',', $pk_columns);
            $sql = "alter table $table->name add primary key ($pk)";

            $db->query($sql);

            /// Add autoinc if mysql (other databases not supported yet)

            if ($db->platform == 'mysql' && $autoinc_column) {
                $sql = "alter table $table->name modify column " . $columns[$autoinc_column->name] . " auto_increment";
                $db->query($sql);
            }

            /// Add indexes

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

            /// Add records

            if (!isset($table->records)) $table->records = [];

            foreach ($table->records as $rec) {
                $db->insert($table->name, (array) $rec)->execute();
            }
        }

        /// Add foreign keys

        foreach ($this->tables as $table) {

            if (!isset($table->foreign_keys)) continue;

            foreach ($table->foreign_keys as $fk) {

                // get foreign keys columns
                $fk_columns = [];
                foreach ($fk->local as $alias) {
                    $fk_columns[] = $table->fields[$alias]->name;
                }
                $fk_columns_str = implode(', ', $fk_columns);
                $sql  = "alter table $table->name ";
                $sql .= "add constraint $fk->name ";
                $sql .= "foreign key ($fk_columns_str) ";

                // get reference table and columns
                $ref_table = $this->tables[$fk->table]->name;
                $ref_columns = [];
                foreach ($fk->foreign as $alias) {
                    $ref_columns[] = $this->tables[$fk->table]->fields[$alias]->name;
                }
                $ref_columns_str = implode(', ', $ref_columns);

                $sql.= "references $ref_table($ref_columns_str)";

                $db->query($sql);
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
