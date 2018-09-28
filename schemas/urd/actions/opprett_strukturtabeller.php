<?php

require_once '../../../inc/funksjoner.inc.php';

ob_start(); // brukes for debugging med FirePHP


$prim_nokkel_json = $_GET['prim_nokkel'];
$prim_nokkel_arr = json_decode($prim_nokkel_json, true);
$base = $prim_nokkel_arr[0];
$success = true;


$sql = "CREATE TABLE IF NOT EXISTS `_urd_tabeller` (
  `tabell` varchar(30) NOT NULL,
  `ledetekst` varchar(70) default NULL,
  `vis` tinyint(1) NOT NULL default '1',
  `rekkefolge` int(11) default NULL,
  `prim_nokkel` varchar(50) NOT NULL,
  `sortering` varchar(50) default NULL,
  `sti` varchar(50) NOT NULL,
  `beskrivelse` varchar(1000) default NULL,
  `kommentar` varchar(1000) default NULL,
  `ferdig_beskrevet` tinyint(1) NOT NULL,
  `deponeres` tinyint(1) default NULL,
  PRIMARY KEY  (`tabell`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$resultat = db_query($sql, $base);

$sql = "CREATE TABLE IF NOT EXISTS `_urd_kolonner` (
  `tabell` varchar(30) NOT NULL,
  `kolonne` varchar(30) NOT NULL,
  `ledetekst` varchar(50) default NULL,
  `datatype` varchar(20) NOT NULL default 'string',
  `lengde` int(11) default NULL,
  `obligatorisk` tinyint(1) NOT NULL default '0',
  `standardverdi` varchar(50) default NULL,
  `unik` tinyint(1) NOT NULL default '0',
  `felttype` varchar(30) NOT NULL default 'textfield',
  `visningsformat` varchar(30) default NULL,
  `tabellvisning` tinyint(1) NOT NULL default '0',
  `postvisning` tinyint(1) NOT NULL default '1',
  `relasjonsvisning` tinyint(1) NOT NULL default '1',
  `gruppe` tinyint(4) DEFAULT NULL,
  `rekkefolge` int(11) default NULL,
  `standard_sokeverdi` varchar(30) default NULL,
  `kandidatbase` varchar(50) default NULL,
  `kandidattabell` varchar(30) default NULL,
  `kandidatnokkel` varchar(100) default NULL,
  `kandidatvisning` varchar(150) default NULL,
  `kandidatbetingelse` varchar(200) default NULL,
  `relasjonsbetegnelse` varchar(50) DEFAULT NULL,
  `beskrivelse` varchar(1000) default NULL,
  `kommentar` varchar(1000) default NULL,
  `ferdig_beskrevet` tinyint(1) NOT NULL,
  `deponeres` tinyint(1) default '1',
  PRIMARY KEY  (`tabell`,`kolonne`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
";

$resultat = db_query($sql, $base);

$sql = "

CREATE TABLE IF NOT EXISTS `_urd_handlinger` (
  `navn` varchar(30) NOT NULL,
  `betegnelse` varchar(50) NOT NULL,
  `rekkefolge` tinyint(4) DEFAULT NULL,
  `skriptadresse` varchar(100) NOT NULL,
  `tabell` varchar(50) NOT NULL,
  `betingelse` varchar(400) DEFAULT NULL,
  `kommunikasjon` varchar(15) NOT NULL,
  `beskrivelse` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`navn`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$resultat = db_query($sql, $base);

$sql = "CREATE TABLE IF NOT EXISTS `_urd_kolonnegrupper` (
  `tabell` varchar(50) NOT NULL,
  `nummer` tinyint(4) NOT NULL,
  `betegnelse` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$resultat = db_query($sql, $base);

echo json_encode('Strukturtabellene er opprettet.');

?>
