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
					'name' => $row[0],
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
		$res = $this->driver->query('SELECT * FROM "ALL_TAB_COLUMNS" WHERE "TABLE_NAME" = ' . $this->driver->escapeText($table));
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
        $res = $this->driver->query("select i.index_name, i.uniqueness, c.constraint_type, column_name, column_position
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
		throw new \Dibi\NotImplementedException;
	}
}