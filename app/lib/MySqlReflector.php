<?php

namespace URD\lib;

Class MySqlReflector extends \Dibi\Drivers\MySqlReflector
{
	private $driver;

	public function __construct(\Dibi\Driver $driver)
	{
		$this->driver = $driver;
		parent::__construct($driver);

	}

	/**
	 * Returns metadata for all foreign keys in a table.
	 * @param  string
	 * @return array
	 * @throws Dibi\NotSupportedException
	 */
	public function getForeignKeys($table)
	{
		$data = $this->driver->query("SELECT `ENGINE` FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = {$this->driver->escapeText($table)}")->fetch(TRUE);
		if ($data['ENGINE'] !== 'InnoDB') {
			throw new Dibi\NotSupportedException("Foreign keys are not supported in {$data['ENGINE']} tables.");
		}

		$res = $this->driver->query("
			SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME,
				   GROUP_CONCAT(REFERENCED_COLUMN_NAME ORDER BY ORDINAL_POSITION) AS REFERENCED_COLUMNS,
				   GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) AS COLUMNS
			FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = DATABASE() AND
			  TABLE_NAME = {$this->driver->escapeText($table)} AND
			  REFERENCED_TABLE_NAME is not null
			GROUP BY CONSTRAINT_NAME
		");

		$foreignKeys = [];
		while ($row = $res->fetch(TRUE)) {
			$keyName = $row['CONSTRAINT_NAME'];

			$foreignKeys[$keyName]['name'] = $keyName;
			$foreignKeys[$keyName]['local'] = explode(',', $row['COLUMNS']);
			$foreignKeys[$keyName]['table'] = $row['REFERENCED_TABLE_NAME'];
			$foreignKeys[$keyName]['foreign'] = explode(',', $row['REFERENCED_COLUMNS']);
			$foreignKeys[$keyName]['onDelete'] = 'NO ACTION';
			$foreignKeys[$keyName]['onUpdate'] = 'NO ACTION';
		}
		return array_values($foreignKeys);
	}	
}