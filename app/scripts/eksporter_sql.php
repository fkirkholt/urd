<?php

require '../inc/funksjoner.inc.php';

$db = Database::get_instance($_REQUEST['base']);
$tbl = new Table($db, $_REQUEST['tabell']);
$cols = $tbl->fields;
$dialekt = $_REQUEST['dialekt'];

if ($tbl->betingelse) {
    $betingelse = $tbl->betingelse;
} else {
    $betingelse = '1 = 1';
}
if ($db->platform == 'oracle') {
  $sql = "alter session set nls_date_format = 'YYYY-MM-DD'";
  $res = $db->query($sql);
}
$sql = "SELECT * FROM $tbl->name WHERE $betingelse";
$res = $db->query($sql);
$firstrow = true;
$fields = array();
$insert = '';

if ($dialekt == 'oracle') {
    $insert .= "alter session set nls_date_format = 'YYYY-MM-DD';\n";
}

while ($row = $db->fetch_assoc($res)) {
    $values = array();
    $insert .= "INSERT INTO $tbl->name";
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

header("Cache-Control: ");
header("Content-type: txt/plain");
header('Content-Disposition: attachment; filename="'.$tbl->name.'.sql"');
echo $insert;
