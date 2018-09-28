<?php
/**
 * Created by: Frode Kirkholt
 * Date: 30.05.2013
 * Time: 10:23
 */

session_start();

date_default_timezone_set('Europe/Oslo');
$file = fopen('../../log/feilmelding.log', 'a');
$text = $_POST['text'];
$time = date('d.m.Y, H:i:s');
if (isset($_SESSION['user_id'])) {
    $bruker = $_SESSION['user_id'];
} else {
    $bruker = 'anonymous';
}
$line = "\n[".$time.' - '.$bruker.'] '.$text;
fwrite($file, $line);
