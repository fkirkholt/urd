<?php

// Skriptet lager databasestrukturen (tabeller og kolonner) basert på beskrivelsen
// i tabellene urd.tabell og urd.kolonne

function convert_datatype($in_type, $length) {
    switch $in_type {
        case 'string':
            $out_type = "varchar($length)";
            break;
        case 'integer':
            $out_type = "int($length)";
            break;
        case 'date':
            $out_type = "date";
            break;
        case 'float';
        $out_type = "float";
        // todo: håndter lengde
        break;
        default:
            echo 'feil datatype';
        }
    return $out_type;
}

$db_navn = $_GET['base'];
$db = Database::get_instance($db_navn);
$urd = Database::get_instance();

// # Finner kolonner og legger inn i array

$sql = "SELECT * FROM kolonne WHERE databasemal = '$db->tpl'";
$res = $urd->query($sql);
$kolonner = $urd->fetch_all($res);

// lager hashet array
foreach ($kolonner as $kolonne) {
    while ($kolonne = mysql_fetch_assoc($res)) {
        if (!isset($kolonner[$kolonne['tabell']])) {
            $kolonner[$kolonne['tabell']] = array();
        }
        $kolonner[$kolonne['tabell']][$kolonne['kolonne']] = $kolonne;
    }

    // # Finner tabellene

    $sql = "SELECT * FROM tabell WHERE databasemal = $db->tpl";
    $res = $urd->query($sql);
    $tabeller = $urd->fetch_all($res);
    foreach ($tabeller as $tabell) {
        // todo: sjekk om tabellen eksisterer først.
        $sql = "CREATE TABLE `{$tabell['tabell']}` (";
        foreach ($kolonner[$tabell['tabell']] AS $kol_navn=>$kolonne) {
            $datatype = convert_datatype($kolonne['datatype'], $kolonne['lengde']);
            if ($kolonne['obligatorisk'] == '0') {
                $null = 'DEFAULT NULL';
            }
            else {
                $null = 'NOT NULL';
            }
            $sql.= "`{$kolonne['kolonne']}` $datatype $null";
        }
        $sql = "PRIMARY KEY (`{$tabell['prim_nokkel']}`))";
    }
