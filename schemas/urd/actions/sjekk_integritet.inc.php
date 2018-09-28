<?php

/* ========= Beskrivelse av skriptet =========

   Dette skriptet sjekker integriteten av dataene i basen, dvs. det sjekker om alle
   fremmednøkler refererer til en kandidatnøkkel.

   Skriptet sjekker kun integriteten der det er definert primærnøkler. Primærnøkler
   må først lages ved å kjøre skriptet for å lage primærnøkler. Dette kan gå feil
   for enkelte tabeller, da det kan finnes flere poster med samme verdi for
   kolonnen som representerer primærnøkkelen. Integriteten er da også brutt, men
   man får beskjed om det i skriptet for å lage nøkler.

   TODO: Skriv om skriptet slik at det bruker LEFT JOIN for å hente ut
   fremmednøkkel og kandidatnøkkel. Legg inn funksjonalitet for automatisk å lage
   index hvis den ikke eksisterer. Bør jo ha index på primærnøkler uansett. Ellers
   kan jeg ha et eget skript som lager index hvis den ikke eksisterer fra før. Da
   vil også sjekken på integritet oppleves langt raskere, enn om skriptet også
   lager indexene.

   TODO: Dokumenter dette skriptet langt bedre

   TODO: Har endret rekkefølgen i tabellene til å sortere etter antall feil. Dette
   gjør nok at $order og logikken forbundet med den skal fjernes.

*/

$urd = Database::get_instance();
$db = Database::get_instance($base_navn);

// ======== Finner navnet til databasen =========

// ======== Lager en array over eksisterende tabeller i basen =======

// TODO: Hvorfor lager jeg en slik array? Bruker jeg ikke info fra _urd_tabeller
// og _urd_kolonner for å finne fremmednøkler og kandidatnøkler? Hvorfor
// skal jeg da hente info fra information_schema?

$sql = "SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = '$base_navn'";

$resultat = $urd->query($sql);

while ($rad = $urd->fetch_object($resultat)) {
    $tabell_finnes_arr[] = $rad->table_name;
}

$sql = "SELECT tabell, kolonne, kandidattabell, kandidatnokkel
        FROM kolonne
        WHERE kandidatnokkel IS NOT null
        AND kandidatnokkel != ''";
$res = $urd->query($sql);
$rad_nokler_arr = $urd->fetch_all($res);

$j = 1;
$feil_finnes = false;

foreach ($rad_nokler_arr as $rad_nokler) {

    $tabell = $rad_nokler->tabell;
    $kolonne_streng = $rad_nokler->kolonne;
    $kandidattabell = $rad_nokler->kandidattabell;
    $kandidatnokkel_streng = $rad_nokler->kandidatnokkel;

    fwrite($tidslogg, "  $tabell.$kolonne_streng\n");

    // Nullstiller arrayene for hver løkke
    $join_arr = array();
    $tabell_kolonne_arr = array();
    $tabell_kolonne_arr_as = array();
    $kandidattabell_nokkel_arr = array();
    $kandidattabell_nokkel_arr_alias = array();
    $betingelse_arr = array();

    $kolonne_arr = explode(', ',$kolonne_streng);
    // fremmednøkler som består av flere kolonner angis med + imellom i
    // _urd_kolonner

    foreach ($kolonne_arr as $kolonne) {
        $tabell_kolonne_arr_as[] = "$tabell.$kolonne as tabell_$kolonne";
        $tabell_kolonne_arr[] = "$tabell.$kolonne";
    }

    $kolonner_sql_as = implode(', ', $tabell_kolonne_arr_as);
    $kolonner_sql = implode(', ', $tabell_kolonne_arr);

    foreach ($kolonne_arr as $fremmednokkel) {
        $betingelse_arr[] = "$tabell.$fremmednokkel IS NOT NULL";
        $betingelse_arr[] = "$tabell.$fremmednokkel != ''";
    }
    $betingelse = 'WHERE '.implode(' AND ', $betingelse_arr);


    if (in_array($kandidattabell, $tabell_finnes_arr)) {
        // Sjekker integritet kun hvis kandidattabellen finnes
        // TODO: Antar jeg kan kutte ut denne sjekken. Jf. TODO over.

        if ($kandidattabell == $tabell) {
            // Hvis referansen går til samme tabell, må vi lage et alias for
            // kandidattabellen, slik at referansen til kolonnen blir entydig i
            // join-setningen
            $kandidattabell_alias = $kandidattabell.'_2';
        }
        else $kandidattabell_alias = $kandidattabell;




        $kandidatnokkel_arr = explode(', ',$kandidatnokkel_streng);
        foreach ($kandidatnokkel_arr as $kandidatnokkel) {
            $kandidattabell_nokkel_arr_alias[] =
                                               "$kandidattabell_alias.$kandidatnokkel as kandidattabell_$kandidatnokkel";
            $kandidattabell_nokkel_arr[] = "$kandidattabell.$kandidatnokkel";
        }

        $i = 0;
        foreach ($kolonne_arr as $kolonne) {
            $join_arr[] = "$tabell.$kolonne = $kandidattabell_alias.$kandidatnokkel_arr[$i]";
            $i++;
        }

        if ($kandidattabell_alias == $kandidattabell) {
            $join = "LEFT JOIN $kandidattabell_alias ON ".implode(' AND ', $join_arr);
        }
        else {
            $join  = "LEFT JOIN $kandidattabell AS $kandidattabell_alias ON ";
            $join .= implode(' AND ', $join_arr);
        }

        $kandidatnokler_sql = implode(', ', $kandidattabell_nokkel_arr_alias);
        $order = implode(', ', $kandidattabell_nokkel_arr);

        $sql = "EXPLAIN SELECT $kolonner_sql_as, $kandidatnokler_sql
            FROM $tabell
            $join";
        $res = $db->query($sql);
        $rader = $db->fetch_all($res);

        foreach ($rader as $rad) {
            if ($rad->table == $kandidattabell_alias) {
                if ($rad->possible_keys != null) {
                    // Hvis indexer er tilgjengelig for join-setningen, sjekkes
                    // integriteten
                    $index = true;
                }
                else $index = false;
            }
        }

        if (!$index) {
            $kolonner_index = implode(', ', $kandidatnokkel_arr);
            $sql = "ALTER TABLE $kandidattabell ADD INDEX ($kolonner_index)";

            $resultat = db_query($sql, $base_navn);
        }

        $sql = "SELECT count(*) AS n, $kolonner_sql_as, $kandidatnokler_sql
      FROM $tabell
      $join
      $betingelse
      GROUP BY $kolonner_sql
      ORDER BY n DESC";
        $res = $db->query($sql);
        $rader = $db->fetch_all($res);

        $i = 0;
        $j = 0; // teller antall distinkte verdier
        $totalt_ant = 0; // totalt antall feil poster
        foreach ($rader as $rad) {
            $fremmednokkel_verdi_arr = array(); // Nullstiller mellom hver løkke
            $kandidatnokkel_finnes = false;
            foreach ($kandidatnokkel_arr as $kandidatnokkel) {
                $kolonne_alias = "kandidattabell_$kandidatnokkel";
                if ($rad->$kolonne_alias != null) {
                    $kandidatnokkel_finnes = true;
                }
            }
            if ($kandidatnokkel_finnes) {
                //break;
                // Går ut av while-sløyfen når første ikke-null-verdi finnes.
                // Null-verdiene skal legge seg først da det sorteres på verdien til
                // kandidatnøklene.
            }
            else {
                foreach ($kolonne_arr as $fremmednokkel) {
                    $kolonne_alias = "tabell_$fremmednokkel";
                    $fremmednokkel_verdi_arr[] = $rad->$kolonne_alias;
                }
                $ant = $rad->n;
                $totalt_ant = $totalt_ant + $ant;
                $feil_finnes = true;

                if ($i == 0) { // Angir navn på tabeller og nøkler før første feil
                    $txt  = "Feil: Kandidatnøkler mangler for $tabell.$kolonne_streng:";
                    echo '<h3 style="color:red">'.$txt.'</h3><pre>';
                    skriv_testresultat($innrykk+1, "\n$txt\n");
                    skriv_testlogg($innrykk+1, "\n$txt\n");
                    $txt  = "Tabell:         $tabell\n";
                    $txt .= "Fremmednøkkel:  $kolonne_streng\n";
                    $txt .= "Kandidattabell: $kandidattabell\n";
                    $txt .= "Kandidatnøkkel: $kandidatnokkel_streng\n";
                    echo $txt."\n";
                    skriv_testresultat($innrykk+2, $txt."\n");
                    skriv_testlogg($innrykk+2, $txt."\n");
                    $txt = 'Fremmednøkkel               Antall';
                    echo $txt."\n";
                    skriv_testresultat($innrykk+2, $txt);
                    skriv_testlogg($innrykk+2, $txt);
                    $txt = '----------------------------------';
                    echo $txt.'<br/>';
                    skriv_testresultat($innrykk+2, $txt);
                    skriv_testlogg($innrykk+2, $txt);
                }
                //$txt = $ant.' - Fremmednøkkel: '.implode('+', $fremmednokkel_verdi_arr);
                $txt = implode('+', $fremmednokkel_verdi_arr);
                $txt.= str_repeat(' ', 34 - mb_strlen($txt) - strlen($ant)).$ant;

                if ($i < 10) {
                    echo $txt.'<br />';
                    skriv_testresultat($innrykk+2, $txt);
                    skriv_testlogg($innrykk+2, $txt);
                }
                else {
                    skriv_testlogg($innrykk+2, $txt);
                }
                $i++;
            }
            $j++;
        }

        if ($i > 0) {
            if ($i > 10) {
                echo '... <br />';
                skriv_testresultat($innrykk+2, '... ');
            }
            $txt = '----------------------------------';
            echo $txt.'<br/>';
            skriv_testresultat($innrykk+2, $txt);
            skriv_testlogg($innrykk+2, $txt);
            $txt = 'Totalt'.str_repeat(' ', 28 - strlen($totalt_ant)).$totalt_ant;
            echo $txt.'</pre>';
            skriv_testresultat($innrykk+2, $txt);
            skriv_testlogg($innrykk+2, $txt);
            $txt = "Til sammen $i feil av $j ulike verdier";
            echo $txt.'<br /><br />';
            skriv_testresultat($innrykk+2, "\n$txt");
            skriv_kommentar($innrykk+2);
        }

        if ($i == 0) {
            //echo 'Ingen feil i: ';
            //echo '<em>Tabell:</em> '.$tabell.', ';
            //echo '<em>Fremmednøkkel:</em> '.$kolonne_streng.'<br />';
        }
    }
    else {

        $sql = "SELECT count(*) AS n, $kolonner_sql_as
        FROM $tabell
        $betingelse
        GROUP BY $kolonner_sql
        ORDER BY n DESC";
        $res = $db->query($sql);
        $rader = $db->fetch_all($res);

        $i = 0;
        $totalt_ant = 0; // totalt antall feil poster
        foreach ($rader as $rad) {
            $fremmednokkel_verdi_arr = array(); // Nullstiller mellom hver løkke
            $ant = $rad->n;
            $totalt_ant = $totalt_ant + $ant;
            foreach ($kolonne_arr as $fremmednokkel) {
                $kolonne_alias = "tabell_$fremmednokkel";
                $fremmednokkel_verdi_arr[] = $rad->$kolonne_alias;
            }
            if ($i == 0) { // Angir navn på tabeller og nøkler for første feil
                $txt  = "Feil: Kandidattabell mangler for $tabell.$kolonne_streng:";
                echo '<h3 style="color:red">'.$txt.'</h3><pre>';
                skriv_testresultat($innrykk+1, "\n$txt\n");
                skriv_testlogg($innrykk+1, "\n$txt\n");
                $txt  = "Tabell:         $tabell\n";
                $txt .= "Fremmednøkkel:  $kolonne_streng\n";
                $txt .= "Kandidattabell: $kandidattabell\n";
                $txt .= "Kandidatnøkkel: $kandidatnokkel_streng\n";
                echo $txt."\n";
                skriv_testresultat($innrykk+2, $txt);
                skriv_testlogg($innrykk+2, $txt."\n");
                $txt = 'Fremmednøkkel               Antall';
                echo $txt.'<br/>';
                skriv_testresultat($innrykk+2, $txt);
                skriv_testlogg($innrykk+2, $txt);
                $txt = '----------------------------------';
                echo $txt.'<br/>';
                skriv_testresultat($innrykk+2, $txt);
                skriv_testlogg($innrykk+2, $txt);
            }
            $txt = implode('+', $fremmednokkel_verdi_arr);
            $txt.= str_repeat(' ', 34 - mb_strlen($txt) - strlen($ant)).$ant;
            if ($i < 10) {
                echo $txt.'<br />';
                skriv_testresultat($innrykk+2, $txt);
                skriv_testlogg($innrykk+2, $txt);
                $i++;
            }
            else {
                skriv_testlogg($innrykk+2, $txt);
                $i++;
            }
        }
        if ($i > 0) {
            if ($i > 10) {
                echo '... <br />';
                skriv_testresultat($innrykk+2, '... ');
            }
            $txt = '----------------------------------';
            echo $txt.'<br/>';
            skriv_testresultat($innrykk+2, $txt);
            skriv_testlogg($innrykk+2, $txt);
            $txt = 'Totalt'.str_repeat(' ', 28 - strlen($totalt_ant)).$totalt_ant;
            echo $txt.'</pre>';
            skriv_testresultat($innrykk+2, $txt);
            skriv_testlogg($innrykk+2, $txt);
            $txt = "Til sammen $i feil";
            echo $txt.'<br /><br />';
            skriv_testresultat($innrykk+2, "\n$txt");
            skriv_kommentar($innrykk+2);
        }
        else {
            echo '</pre>';
        }

        if ($i == 0) {
            //echo 'Ingen feil i: ';
            //echo '<em>Tabell:</em> '.$tabell.', ';
            //echo '<em>Fremmednøkkel:</em> '.$kolonne_streng.'<br />';
        }
    }
    $j++;
}


if ($feil_finnes == false) {
    $txt = "Ingen feil i relasjoner";
    skriv_testresultat($innrykk+1, "\n$txt");
    skriv_testlogg($innrykk+1, "\n$txt");
    echo '<h3>'.$txt.'</h3>';
}


?>
