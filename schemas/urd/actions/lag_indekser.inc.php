<?php

// Dette skriptet legger på indekser basert på beskrivelsen primærnøkler i
// tabellen _urd_tabeller.

// TODO: Etter at jeg har endret fra å opprette primærnøkkel til å opprette
// index, kan jeg slå dette skriptet sammen med skriptet som legger inn data,
// samt skriptet som oppretter databasestruktur.

// TODO: Dokumenter skriptet!

set_time_limit(0);
mb_internal_encoding("UTF-8");

$feil_primnokkel_arr = array();
$feil_fremmednokkel_arr = array();

$sql = "SELECT tabell, prim_nokkel FROM _urd_tabeller";

$resultat_tabeller = mysql_query($sql, $tilkobling) or die
                   ("Kunne ikke foreta spørring: ".mysql_error().' '.$sql);

while ($rad_tabell = mysql_fetch_array($resultat_tabeller)) {
    $tabellnavn = $rad_tabell['tabell'];
    $prim_nokkel_streng = $rad_tabell['prim_nokkel'];
    $prim_nokkel_arr = explode(', ', $prim_nokkel_streng);

    // ============== Legger på index på primærnøkler ===============

    $sql = "SHOW INDEX FROM $tabellnavn";

    $resultat = mysql_query($sql, $tilkobling) or die
              ("Kunne ikke foreta spørring: ".mysql_error().' '.$sql);

    $rad = mysql_fetch_array($resultat);

    if (!$rad && $prim_nokkel_streng) {
        // Legger på index hvis den ikke eksisterer fra før.

        $sql = "ALTER TABLE $tabellnavn ADD INDEX ($prim_nokkel_streng)";

        $resultat = mysql_query($sql, $tilkobling); // or die
        //("Kunne ikke foreta spørring: ".mysql_error().' '.$sql);

        if (!$resultat) {
            $feil = mysql_error();
            echo('<span style="color:red">Kunne ikke legge på index i ');
            echo "tabell '$tabellnavn' med nøkkel '$prim_nokkel_streng': <br/>";
            echo '  - mysql_error: '.$feil.'. sql: '.$sql.'</span><br />';
            $feil_primnokkel_arr[$tabellnavn] = (string)$feil;
        }
    }

    // =========== Finner evt. duplikater ===========

    $duplikater_finnes = false;

    if ($prim_nokkel_streng) {

        $sql = "SELECT COUNT(*) AS n, $prim_nokkel_streng
      FROM $tabellnavn
      GROUP BY $prim_nokkel_streng
      HAVING n > 1";

        $resultat = mysql_query($sql, $tilkobling);

        if (!$resultat) {
            $feil = mysql_error();
            echo('<p style="color:red">Feil i spørring for sjekk av duplikater: '.$feil.': '.$sql.'</p>');
        }

        else {

            $lengde_arr = array('n'=>6); // TODO: Hva er dette?
            $duplikat_arr = array();

            while ($rad = mysql_fetch_array($resultat, MYSQL_ASSOC)) {
                $duplikat_arr[] = $rad;
                foreach ($rad as $felt=>$verdi) {
                    $lengde = mb_strlen($verdi);
                    if (isset($lengde_arr[$felt])) {
                        if ($lengde > $lengde_arr[$felt]) {
                            $lengde_arr[$felt] = $lengde;
                        }
                    }
                    else {
                        $lengde_arr[$felt] = $lengde;
                        // Lengden er nødvendig for å kunne tegne opp tabellen korrekt
                    }
                }
            }

            if (count($duplikat_arr) > 0) { // hvis det finnes poster med samme primærnøkkel
                $duplikater_finnes = true;
                $txt = "Feil: Duplikate primærnøkler i $tabellnavn:";
                echo '<h3 style="color:red">'.$txt.'</h3>';
                skriv_testresultat($innrykk+1, "\n$txt\n");
                skriv_testlogg($innrykk+1, "\n$txt\n");
                // Lager overskrift:
                $overskrift = 'Antall  ';
                $understrek = '--------';
                foreach ($prim_nokkel_arr as $prim_nokkel) {
                    // Som kolonnebredde settes det som er lengst - feltnavn eller feltverdi:
                    $lengde_overskrift = mb_strlen($prim_nokkel);
                    if ($lengde_overskrift > $lengde_arr[$prim_nokkel]) {
                        $lengde_arr[$prim_nokkel] = $lengde_overskrift;
                    }
                    // Antall mellomrom som etterfølger filnavnet i overskriften:
                    $ant_space = $lengde_arr[$prim_nokkel] + 2 - mb_strlen($prim_nokkel);
                    $overskrift .= $prim_nokkel . str_repeat(' ', $ant_space);
                    $understrek .= str_repeat('-', $lengde_arr[$prim_nokkel] + 2);
                }
                $overskrift = $overskrift."\n".$understrek;
                echo '<pre>'.$overskrift."\n";
                skriv_testresultat($innrykk+2, $overskrift, false);
                skriv_testlogg($innrykk+2, $overskrift, false);
                // Lager hver rad
                $n = 0;
                $ant_duplikater = count($duplikat_arr);
                foreach ($duplikat_arr as $duplikat) {
                    $tekstrad = '';
                    foreach ($duplikat as $felt=>$verdi) {
                        $ant_space = $lengde_arr[$felt] + 2 - mb_strlen($verdi);
                        $tekstrad .= $verdi . str_repeat(' ', $ant_space);
                    }
                    if ($n < 10) {
                        echo $tekstrad."\n";
                        skriv_testresultat($innrykk+2, $tekstrad, false);
                        skriv_testlogg($innrykk+2, $tekstrad, false);
                    }
                    else {
                        skriv_testlogg($innrykk+2, $tekstrad, false);
                    }
                    $n++;
                    if ($n == 10 && $ant_duplikater > 20) {
                        $txt = "Til sammen $ant_duplikater duplikater. ";
                        $txt.= 'Se testlogg.txt for en spesifikasjon av alle duplikater.';
                        echo "\n$txt";
                        skriv_testresultat($innrykk+2, "...\n\n$txt");
                    }
                }
                echo '</pre>';
                skriv_kommentar($innrykk+2);
            }

        }
    }
}
echo 'Indekser er lagt på primærnøkler';

if ($duplikater_finnes == false) {
    $txt = "Ingen duplikate primærnøkler i databasen";
    echo '<h3>'.$txt.'</h3>';
    skriv_testresultat($innrykk+1, "\n$txt\n");
    skriv_testlogg($innrykk+1, "\n$txt\n");
}

// =========== Legger på index på sekundærnøkler ===============

$sql = "SELECT tabell, kolonne FROM _urd_kolonner WHERE kandidatnokkel IS NOT NULL";

$resultat_kolonner = mysql_query($sql, $tilkobling) or die
                   ("Kunne ikke foreta spørring: ".mysql_error().' '.$sql);

while ($kolonne = mysql_fetch_array($resultat_kolonner)) {
    $tabellnavn = $kolonne['tabell'];
    $fremmednokkel = $kolonne['kolonne'];

    $sql = "SHOW INDEX FROM $tabellnavn  WHERE Key_name != 'PRIMARY'";

    $indekser = mysql_query($sql, $tilkobling) or die
              ("Kunne ikke foreta spørring etter indekser: ".mysql_error().' '.$sql);

    $index_arr = array();

    while ($indeks = mysql_fetch_array($indekser)) {
        if (!in_array($indeks['Key_name'], $index_arr)) {
            $index_arr[] = $indeks['Key_name'];
        }
    }

    if (!in_array($fremmednokkel, $index_arr)) {

        $sql = "ALTER TABLE $tabellnavn ADD INDEX ($fremmednokkel)";

        $resultat = mysql_query($sql, $tilkobling); // or die
        //("Kunne ikke foreta spørring: ".mysql_error().' '.$sql);

        if (!$resultat) {
            $feil = mysql_error();
            echo('<span style="color:red">Kunne ikke legge på index i ');
            echo "tabell '$tabellnavn' med nøkkel '$prim_nokkel_streng': <br/>";
            echo '  - '.$feil.'</span><br />';
            $feil_fremmednokkel_arr[$tabellnavn] = (string)$feil;
        }

    }
}

echo 'Indekser er lagt på fremmednøkler';

?>
