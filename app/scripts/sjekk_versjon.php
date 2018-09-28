<?php

include '../inc/funksjoner.inc.php';
include '../inc/FirePHPCore/fb.php'; // for debugging via FirePHP

ob_start(); // for debugging via FirePHP

$klient_versjon = $_GET['versjon'];
$klient_versjon = dotdelim_expand($klient_versjon);

function dotdelim_expand($verdi) {
    $verdi_arr = explode('.',$verdi);
    $ny_arr = array();
    foreach ($verdi_arr as $verdi) {
        if ($verdi == '0000') {
            $ny_arr[] = '0';
        }
        else {
            $ny_arr[] = ltrim($verdi, "0");
        }
    }
    $ny_verdi = implode('.', $ny_arr);
    return $ny_verdi;
}

$tilkobling = koble_til('urd_tabellstruktur');

$sql = "SELECT versjon FROM info";

$resultat = mysql_query($sql, $tilkobling);

$rad = mysql_fetch_array($resultat);

if ($klient_versjon != $rad['versjon']) {
    $versjon = dotdelim_expand($rad['versjon']);
    chdir('../installering/databaseoppdatering');
    $fil_arr = glob('*.php');
    foreach ($fil_arr as $fil) {
        $filnavn = basename($fil, '.php');
        $filnavn = dotdelim_expand($filnavn);
        if ($filnavn > $versjon) {
            include($filnavn.'.php');
        }
    }
}

?>
