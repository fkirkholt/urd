<?php

$pk = json_decode($_POST['primary_key']);
$base_navn = $pk->databasenavn;

?>

<!DOCTYPE html
          PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
          "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <?php echo '<script>' . file_get_contents("../../../../../node_modules/jquery/dist/jquery.min.js") . '</script>' ?>
        <!--<script type="text/javascript" src="../../../../node_modules/jquery/dist/jquery.min.js"></script>-->
        <script type="text/javascript" src="../../../../js/funksjoner.js"></script>

        <script type="text/javascript" src="run_sql.js"></script>

        <link rel="stylesheet" href="stil.css" type="text/css" media="screen" />

        <title>Kjør skript - <?php echo $base_navn ?></title>
    </head>
    <body>
        <?php
        echo '<input id="base_navn" type="hidden" value="'.$base_navn.'"/>';
        ?>

        <div id="skript">
            <textarea id="sql"></textarea>
            <a id="knapp_run_sql" href="#">Kjør sql</a>
        </div>
        <div id="visning">
            <div id="visning_header">
                <a id="resultat_fane" href="#">Resultat siste skript</a>
                <a id="hjelp_fane" href="#">Hjelp</a>
            </div>
            <div id="resultat"></div>
            <div id="hjelp" style="display: none"></div>
        </div>
    </body>
</html>
