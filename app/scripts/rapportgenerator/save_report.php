<?php

require '../../inc/funksjoner.inc.php';

$rep = new StdClass;
$rep = (object) $_REQUEST; // name, table, tpl, fields, conditions

$sql = "INSERT INTO rapport (navn, databasemal, tabell, felter, betingelser) VALUES ";
$sql.= "('$rep->name', '$rep->tpl', '$rep->table', '$rep->fields', '$rep->conditions')";

$urd = Database::get_instance($config['urd_base']);
$urd->query($sql);
