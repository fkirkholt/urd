<?php

require '../../inc/funksjoner.inc.php';
$response = Response::get_instance();

$req = json_decode(file_get_contents('php://input'));

$dbs = array();
$db = Database::get_instance($req->base);
$tbl = new Table($db, $req->table);
$tbl->load_fields();
$tbl->alias = $req->table;
$db->tables[$req->table] = $tbl;
$dbs[$req->base] = $db;

// Makes joins
foreach ($req->fields as $fieldref) {
    // Eks: $fieldref = uttrekk.deponering.arkivskaper.navn
    // Side-effects: populating $tbl->joins and $tbl->fields
    $tbl->parse_fieldref($fieldref);
}

foreach ($req->conditions as $cond) {
    $alias = $table_aliases[$cond->table_ref];
    if (in_array($cond->operator, array('IS NULL', 'IS NOT NULL'))) {
        $value = '';
    } else if ($cond->operator == 'LIKE') {
        $value = "'%$cond->value%'";
    } else {
        $value = "'$cond->value'";
    }
    $tbl->conditions[] = "$alias.$cond->field $cond->operator $value";
}

$sql = "SELECT " . implode(', ', $tbl->selects)."\n";
$sql.= "FROM ".$req->table."\n";
$sql.= implode("\n", $tbl->joins);
if (count($tbl->wheres)) {
    $sql.= "\nWHERE ".implode(' AND ', $tbl->wheres);
}
$response->log($sql);
$res = $db->query($sql);
$response->data->recs = $db->fetch_all($res);

if (isset($_REQUEST['csv'])) {
    header("Cache-Control: ");
    header("Content-type: txt/plain");
    header('Content-Disposition: attachment; filename="'.$tbl->name.'.csv"');
    // Makes heading
    $headings = array();
    $rec = $recs[0];
    foreach ($rec as $field=>$value) {
        $headings[] = $field;
    }
    $content = implode(';', $headings) . "\n";
    foreach ($recs as $rec) {
        $verdier = array();
        foreach ($rec as $field=>$value) {
            $verdi = str_replace('"', '""', $value);
            $verdier[] = $verdi;
        }
        $content .= implode(';', $verdier) . "\n";
    }
    $content = mb_convert_encoding($content, "ISO-8859-1", "UTF-8");
    echo $content;
} else {
    $response->send();
}
