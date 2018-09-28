<?php

require '../../inc/funksjoner.inc.php';

$base = $_REQUEST['base'];
$tabell = $_REQUEST['tabell'];

$db = Database::get_instance($base);
$urd = Database::get_instance();

// If the table consists of several parts
$parts = explode('.', $tabell);
$tbl = new Table($db, $parts[0]);
$tbl->load_fields();

echo json_encode($tbl->fields);
