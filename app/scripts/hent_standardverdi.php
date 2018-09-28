<?php

include '../inc/funksjoner.inc.php';

$urd = Database::get_instance();
$req = (object) $_POST;

$base = $req->base;
$db = Database::get_instance($base);
$table = $req->table;
$field = $req->field;
$rec = json_decode($req->record, true);

$sql = "SELECT standardverdi, kandidatmal, kandidattabell, kandidatalias,
               kandidatnokkel, kandidatvisning, kandidatbetingelse
        FROM   kolonne
        WHERE  databasemal = '$db->schema' and tabell = '$table' and kolonne='$field'";
$res = $urd->query($sql);
$col = $urd->fetch_object($res);
$expr = $col->standardverdi;

$expr = str_replace('$user_id', $_SESSION['user_id'], $expr);
$expr = str_replace('$user_name', $_SESSION['user_name'], $expr);
$expr = str_replace('$date', date($datoformat_php), $expr);


foreach ($rec as $field=>$value) {
    $expr = str_replace($table.'.'.$field, $value, $expr);
}


if (strtolower(substr($expr, 0, 6)) == 'select') {
    $stdval = $db->column($expr);
}

// Finds evt. display value

if ($col->kandidatvisning) {

    if ($col->kandidatmal == null) {
        $col->kandidatmal = $db->schema;
    }

    $fra_mal = $col->kandidatmal;

    $kandidatbase = hent_kandidatbase($fra_mal, $base);

    $sql = "SELECT $col->kandidatvisning
          FROM   $kandidatbase.$col->kandidattabell $col->kandidatalias
          WHERE  $col->kandidatnokkel = $stdval";
    urd_log($sql, true);
    $display = $db->column($sql);
} else {
    $display = null;
}
$result = array('value'=>$stdval, 'display'=>$display);

echo json_encode($result);
