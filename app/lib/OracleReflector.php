<?php

namespace URD\lib;

/**
 * Reflector for Oracle database.
 */
class OracleReflector implements \Dibi\Reflector
{
    use \Dibi\Strict;
    /** @var Dibi\Driver */
    private $driver;
    public function __construct(\Dibi\Driver $driver)
    {
        $this->driver = $driver;
    }
    /**
     * Returns list of tables.
     */
    public function getTables()
    {
        $res = $this->driver->query('SELECT * FROM cat');
        $tables = [];
        while ($row = $res->fetch(false)) {
            if ($row[1] === 'TABLE' || $row[1] === 'VIEW') {
                $tables[] = [
                    'name' => strtolower($row[0]),
                    'view' => $row[1] === 'VIEW',
                ];
            }
        }
        return $tables;
    }
    /**
     * Returns metadata for all columns in a table.
     */
    public function getColumns($table)
    {
        $res = $this->driver->query('SELECT * FROM "ALL_TAB_COLUMNS" WHERE "TABLE_NAME" = ' . $this->driver->escapeText(strtoupper($table)));
        $columns = [];
        while ($row = $res->fetch(true)) {
            $columns[] = [
                'table' => $row['TABLE_NAME'],
                'name' => $row['COLUMN_NAME'],
                'nativetype' => $row['DATA_TYPE'],
                'size' => $row['DATA_LENGTH'] ?? null,
                'nullable' => $row['NULLABLE'] === 'Y',
                'default' => $row['DATA_DEFAULT'],
                'vendor' => $row,
            ];
        }
        return $columns;
    }
    /**
     * Returns metadata for all indexes in a table.
     */
    public function getIndexes($table)
    {
        $table = strtoupper($table);
        $res = $this->driver->query("SELECT i.index_name, i.uniqueness, c.constraint_type, column_name, column_position
                                     from user_indexes i
                                     join user_ind_columns col on col.index_name = i.index_name
                                     left join user_constraints c on c.index_name = i.index_name
                                     where i.table_name = '$table'");
        $indexes = [];
        while ($row = $res->fetch(TRUE)) {
            $indexes[$row['INDEX_NAME']]['name'] = $row['INDEX_NAME'];
            $indexes[$row['INDEX_NAME']]['unique'] = $row['UNIQUENESS'] === 'UNIQUE';
            $indexes[$row['INDEX_NAME']]['primary'] = $row['CONSTRAINT_TYPE'] === 'P';
            $indexes[$row['INDEX_NAME']]['columns'][$row['COLUMN_POSITION'] - 1] = $row['COLUMN_NAME'];
        }
 
        return array_values($indexes);
    }
    /**
     * Returns metadata for all foreign keys in a table.
     */
    public function getForeignKeys($table)
    {
        // throw new \Dibi\NotImplementedException;
        $table = strtoupper($table);
        $res = $this->driver->query("
            SELECT a.column_name, a.position, a.constraint_name,
                   c.owner, c.delete_rule,
                   -- referenced pk
                   c.r_owner, c_pk.table_name r_table_name,
                   c_pk.constraint_name r_pk,
                   ra.column_name r_column_name
            FROM all_cons_columns a
              JOIN all_constraints c
                ON a.owner = c.owner
               AND a.constraint_name = c.constraint_name
              JOIN all_constraints c_pk
                ON c.r_owner = c_pk.owner
               AND c.r_constraint_name = c_pk.constraint_name
              JOIN all_cons_columns ra
                ON ra.owner = c.owner
               AND ra.constraint_name = c_pk.constraint_name
               AND ra.position = a.position
            WHERE c.constraint_type = 'R'
            AND   a.table_name = '$table'
            ORDER BY a.position
        ");

        $foreignKeys = [];
        while ($row = $res->fetch(TRUE)) {
            $keyName = $row['CONSTRAINT_NAME'];

            $foreignKeys[$keyName]['name'] = $keyName;
            $foreignKeys[$keyName]['local'][$row['POSITION'] - 1] = $row['COLUMN_NAME'];
            $foreignKeys[$keyName]['table'] = $row['R_TABLE_NAME'];
            $foreignKeys[$keyName]['foreign'][$row['POSITION'] - 1] = $row['R_COLUMN_NAME'];
            $foreignKeys[$keyName]['onDelete'] = $row['DELETE_RULE'];
            $foreignKeys[$keyName]['onUpdate'] = 'NO ACTION'; // Oracle doesn't have "ON UPDATE"
        }
        return array_values($foreignKeys);
    }
}