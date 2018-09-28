<?php

function replace_key($array, $old_key, $new_key)
{
	$keys = array_keys($array);
	$index = array_search($old_key, $keys);

	if ($index !== false) {
		$keys[$index] = $new_key;
		$array = array_combine($keys, $array);
	}

	return $array;
}
	
$file_paths = glob('*/schema.json');

foreach ($file_paths as $path) {
	$schema_name = explode('/', $path)[0];

	$schema = json_decode(file_get_contents($path), true);
	
	foreach ($schema['tables'] as $tbl_alias => $table) {
		
		foreach ($table['fields'] as $alias => $field) {

			$table['fields'][$alias] = $field;
		}
		
		if (!isset($table['relations'])) $table['relations'] = [];
		foreach ($table['relations'] as $rel_alias => $relation) {

			$table['relations'][$rel_alias] = $relation;
		}
			
		$schema['tables'][$tbl_alias] = $table;
	}
	
	// if ($schema_name !== 'betty') continue;
	$file = fopen($path, 'w');
	fwrite($file, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	// echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}