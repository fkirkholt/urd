<?php

require '../../inc/funksjoner.inc.php';

$reportid = $_GET['rapportid'];

$sql = "SELECT *
        FROM   rapport
        WHERE  id = $reportid";

$urd = Database::get_instance();
$res = $urd->query($sql);
$report = $urd->fetch_object($res);

echo json_encode($report);
