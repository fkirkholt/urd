<?php

// Makes installation script based on actual database structure

require_once '../../../inc/funksjoner.inc.php';

if (!isset($urd_inst)) {
    $urd_inst = false;
}

// TODO: change - document root can be different according to apache setup
$urd_rot = $_SERVER['DOCUMENT_ROOT'].'/'.$config['urd_rot'];

// Set this to true to generate ddl using dbms_metadata.get_dll
$use_ddl = true;

$prim_key_json = $_GET['prim_nokkel'];
$prim_keys = json_decode($prim_key_json);
$db_navn = $prim_keys->databasenavn;
$db = Database::get_instance($db_navn);
$urd = Database::get_instance();

$response = Response::get_instance();

// todo: rename the old file to databasestruktur.old.php
if (!file_exists($urd_rot.'/templates/'.$db->tpl)) {
    mkdir($urd_rot.'/templates/'.$db->tpl);
}
if (!file_exists($urd_rot.'/templates/'.$db->tpl.'/sql')) {
    mkdir($urd_rot.'/templates/'.$db->tpl.'/sql') or die('kunne ikke opprette sql-mappe');
}
$db_struktur_fil = "$urd_rot/templates/$db->tpl/sql/databasestruktur_{$db->platform}.sql";
$db_struktur_hdl = fopen($db_struktur_fil, 'w');


// ----------------
// Oppretter skjema
// ----------------

$db_fil = "$urd_rot/templates/$db->tpl/sql/database_{$db->platform}.sql";
$db_inst = "$urd_rot/templates/$db->tpl/sql/installasjon_{$db->platform}.sql";
$db_fil_hdl = fopen($db_fil, 'w');
$db_inst_hdl = fopen($db_inst, 'w');

if ($db->platform == 'mysql') {
    $create = "
CREATE DATABASE $db->tpl
DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;\n\n";
    fwrite($db_fil_hdl, $create);
} else if ($db->platform == 'oracle') {
    $create = "
CREATE USER $db->tpl IDENTIFIED BY $db->tpl
DEFAULT TABLESPACE users
TEMPORARY TABLESPACE temp;

GRANT connect, resource TO $db->tpl;";
    fwrite($db_inst_hdl, $create."\n\n");
}

/*

  $txt = "--------------------------------------------------\n";
  $txt.= "--  CREATE SCHEMA\n";
  $txt.= "--------------------------------------------------\n\n";
  fwrite($db_fil_hdl, $txt);

  $create = "
  CREATE USER betty IDENTIFIED BY betty
  DEFAULT TABLESPACE users
  TEMPORARY TABLESPACE temp;

  GRANT connect, resource TO betty";

  fwrite($db_fil_hdl, $create);
*/

// --------------------------------
// Genererer create table-setninger
// --------------------------------

$sql = "SELECT tabell, grunndata FROM tabell WHERE databasemal = '{$db->tpl}'";
$res = $urd->query($sql);
$rows = $urd->fetch_all($res);
$grunndata = array();
foreach ($rows as $row) {
    $tabell = $row->tabell;
    if ($row->grunndata) {
        $grunndata[] = $tabell;
    }
}

$txt = "--------------------------------------------------\n";
$txt.= "--  CREATE TABLES \n";
$txt.= "--------------------------------------------------\n\n";
fwrite($db_fil_hdl, $txt);
fwrite($db_inst_hdl, $txt);

$tables = array();

if ($db->platform == 'mysql') {
    $sql = "SHOW TABLES";
    $res = $db->query($sql);
    $tbls = $db->fetch_all($res);
    foreach ($tbls as $tbl) {
        $tbl_navn = $tbl->{'tables_in_'.$db_navn};
        $tables[] = $tbl_navn;
        $sql = "SHOW CREATE TABLE $tbl_navn";
        $row = $db->fetch($sql);
        if (!isset($row->{'create table'})) continue;
        $create = $row->{'create table'};
        $pos = strpos($create, 'AUTO_INCREMENT=');
        if ($pos && !array_key_exists($tbl_navn, $grunndata)) {
            $after_auto_inc = substr($create, $pos+15);
            $len_auto_inc = strpos($after_auto_inc, ' ');
            $create = substr($create, 0, $pos).substr($after_auto_inc, $len_auto_inc);
        }
        fwrite($db_fil_hdl, $create.";\n");
        fwrite($db_inst_hdl, $create.";\n");
    }
} else if ($db->platform == 'oracle') {
    if ($use_ddl) {
        $sql = "
BEGIN
DBMS_METADATA.SET_TRANSFORM_PARAM(DBMS_METADATA.SESSION_TRANSFORM,'STORAGE',FALSE);
DBMS_METADATA.SET_TRANSFORM_PARAM(DBMS_METADATA.SESSION_TRANSFORM,'TABLESPACE',FALSE);
DBMS_METADATA.SET_TRANSFORM_PARAM(DBMS_METADATA.SESSION_TRANSFORM,'SEGMENT_ATTRIBUTES',FALSE);
END;";
        $res = $db->query($sql);
        if (!$res) {
            $m = oci_error($res);
            echo $m['message'];
        }
        $sql = "SELECT dbms_metadata.get_ddl('TABLE', t.table_name), t.table_name
            FROM user_tables t
            ORDER BY table_name";
        $res = $db->query($sql);
        while ($row = $db->fetch_array($res)) {
            $tables[] = $row[1];
            $d = $row[0]->load();
            fwrite($db_fil_hdl, $d.";\n");
            fwrite($db_inst_hdl, $d.";\n");
        }
        //foreach ($ddls as $ddl) {
        //  $txt = $ddl->load();
        //  fwrite($db_fil_hdl, $txt."\n");
        //}
    } else {

        $sql = "SELECT table_name
            FROM   user_tables
            ORDER  BY table_name";
        $res = $db->query();
        $tbls = $db->fetch_all($res);
        foreach ($tbls as $tbl) {
            $tbl_navn = $tbl->table_name;
            $tables[] = $tbl_navn;
            $create = 'CREATE TABLE "'.$db_navn.'"."'.$tbl_navn.'" ('."\n";
            $sql = "SELECT column_name, data_type, data_precision, data_scale,
            nullable, char_length, char_used
            FROM user_tab_cols
            WHERE table_name = '$tbl_navn'";
            $res = $db->query($sql);
            $cols = $db->fetch_all($res);
            foreach ($cols as $col) {
                $create .= '    "'.$col->column_name.'" ';
                $create .= $col->data_type;
                if ($col->data_precision) {
                    $create .= '('.$col->data_precision;
                    if ($col->data_scale !== null) {
                        $create .= ','.$col->data_scale;
                    }
                    $create .= ')';
                } else if ($col->char_length) {
                    $create .= '('.$col->char_length;
                    if ($col->char_used == 'B') {
                        $create .= ' BYTE';
                    } else if ($col->char_used == 'C') {
                        $create .= ' CHAR';
                    }
                    $create .= ')';
                }
                $create .= ",\n";
            }
            $create = substr($create, 0, -2)."\n";
            fwrite($db_fil_hdl, $create.");\n\n");
            fwrite($db_inst_hdl, $create.");\n\n");
        }
    }
}

// --------------------------
// Genererer insert-setninger
// --------------------------

$txt = "--------------------------------------------------\n";
$txt.= "-- INSERTS INTO TABLES\n";
$txt.= "--------------------------------------------------\n\n";
fwrite($db_fil_hdl, $txt);
fwrite($db_inst_hdl, $txt);

$sql = "SELECT tabell as navn, grunndata, prim_nokkel
        FROM   tabell
        WHERE databasemal = '{$db->tpl}'";
$res = $urd->query($sql);
$tables = $urd->fetch_all($res);

foreach ($tables as $tbl) {
    $table = $tbl->navn;
    // Hvis tabellen er av type 'Grunndata'
    if ($tbl->grunndata) {
        // sett if (true) hvis hele basen skal kopieres

        fwrite($db_fil_hdl, "\n");
        fwrite($db_inst_hdl, "\n");

        // Finner datatype for hver kolonne
        if ($db->platform == 'mysql') {
            $sql = "SHOW columns FROM $table";
        } else if ($db->platform == 'oracle') {
            $table = strtoupper($table);
            $sql = "SELECT column_name as field, data_type as type
              FROM user_tab_cols
              WHERE table_name = '$table'";
        }
        $res = $db->query($sql);
        $rows = $db->fetch_all($res);
        $col_type = new StdClass;
        foreach ($rows as $row) {
            $field = strtolower($row->field);
            $col_type->$field = $row->type;
        }
        if ($tbl->grunndata == 2) {
            $where = " where databasemal = '$db->tpl'";
        } else {
            $where = '';
        }
        $sql = "SELECT count(*) FROM $table $where";
        $res = $db->query($sql);
        $count = $db->fetch_column($res);
        if ($count) {
            $sql = "SELECT * FROM $table $where order by $tbl->prim_nokkel";
            $res = $db->query($sql);
            $rows = $db->fetch_all($res);
            if ($db->platform == 'mysql') {
                $sql_insert = "INSERT INTO $db_navn.$table VALUES \n";
                fwrite($db_fil_hdl, $sql_insert);
                fwrite($db_inst_hdl, $sql_insert);
                $i = 0;
                foreach ($rows as $row) {
                    $sql_insert = '';
                    if ($i != 0) {
                        $sql_insert .= ', ';
                    }
                    foreach ($row as $field=>$value) {
                        if ($value == '') {
                            $row->$field = 'null';
                        } else if (
                            strstr($col_type->$field, 'varchar')
                            || strstr($col_type->$field, 'text')
                            || strstr($col_type->$field, 'date')
                        ) {
                            $row->$field = "'".mysqli_real_escape_string($db->conn->dbh, $value)."'";
                        } else {
                            $row->$field = mysqli_real_escape_string($db->conn->dbh, $value);
                        }
                    } // end foreach ($row)
                    $sql_insert .= "(".implode(", ", (array) $row).")";
                    fwrite($db_fil_hdl, $sql_insert);
                    fwrite($db_inst_hdl, $sql_insert);
                    $i++;
                } // end foreach ($rows)
                fwrite($db_fil_hdl, ";\n");
                fwrite($db_inst_hdl, ";\n");
            } else if ($db->platform == 'oracle') {
                foreach ($rows as $row) {
                    foreach ($row as $field=>$value) {
                        $column_type = strtolower($col_type->$field);
                        if (in_array($column_type, array('varchar2', 'date', 'char'))) {
                            $row->$field = "'".str_replace("'", "''", $value)."'";
                        }
                    }
                    $sql_insert = "INSERT INTO $db_navn.$table";
                    $row = array_change_key_case($row, CASE_UPPER);
                    $sql_insert .= " (".implode(",", array_keys($row)).") ";
                    $sql_insert .= "VALUES (".implode(", ", $row).")";
                    fwrite($db_fil_hdl, $sql_insert.";\n");
                    fwrite($db_inst_hdl, $sql_insert.";\n");
                } // end foreach ($rows)
            } // end if
        }
    }
}

if ($urd_inst) {
    $txt = "INSERT INTO urd.BRUKER (ID,NAVN,PASSORD)";
    $txt.= " VALUES ('admin', 'Admin', 'admin');";
    fwrite($db_fil_hdl, "\n".$txt."\n");
    fwrite($db_inst_hdl, "\n".$txt."\n");
}

// ## Genererer create-setninger for views

if ($db->platform == 'mysql') {
    $sql = "SHOW FULL TABLES IN $db_navn WHERE TABLE_TYPE = 'VIEW'";
    $res = $db->query($sql);
    $views = $db->fetch_all($res);
    foreach ($views as $view) {
        $index = 'Tables_in_'.$db_navn;
        $vw_navn = $view->{$index};
        $sql = "SHOW CREATE VIEW $vw_navn";
        $res = $db->query($sql);
        $row = $db->fetch_assoc($res);
        $create = $row['create view'];
        fwrite($db_fil_hdl, $create.";\n");
        fwrite($db_inst_hdl, $create.";\n");
    }
}

// -------------------
// Generates sequences
// -------------------
if ($db->platform == 'oracle') {
    $txt = "\n\n";
    $txt.= "--------------------------------------------------\n";
    $txt.= "-- CREATES SEQUENCES\n";
    $txt.= "--------------------------------------------------\n\n";
    fwrite($db_fil_hdl, $txt);
    fwrite($db_inst_hdl, $txt);

    // Can't use dbms_metadata.get_ddl here,
    // because the value for "START WITH" will be wrong
    $sql = "SELECT * FROM user_sequences ORDER BY sequence_name";
    $res = $db->query($sql);
    $seqs = $db->fetch_all($res);

    foreach ($seqs as $seq) {
        $seq_name = $seq->sequence_name;
        $min = $seq->min_value;
        $max = $seq->max_value;
        $cache = $seq->cache_size;
        $inc = $seq->increment_by;
        $create = "CREATE SEQUENCE \"$db_navn\".\"$seq_name\" ";
        $create.= "MINVALUE $min MAXVALUE $max INCREMENT BY $inc ";
        // TODO: Should not always start with 1
        // TODO: Could there be alternatives to noorder and nocycle?
        $create.= "START WITH 1 CACHE $cache NOORDER NOCYCLE;\n\n";
        fwrite($db_fil_hdl, $create);
        fwrite($db_inst_hdl, $create);
    }
}


// -----------------
// Generates indexes
// -----------------
if ($db->platform == 'oracle') {
    $txt = "\n";
    $txt.= "--------------------------------------------------\n";
    $txt.= "-- CREATES INDEXES\n";
    $txt.= "--------------------------------------------------\n\n";
    fwrite($db_fil_hdl, $txt);
    fwrite($db_inst_hdl, $txt);

    if ($use_ddl) {
        $sql = "SELECT dbms_metadata.get_ddl('INDEX', i.index_name)
            FROM user_indexes i
            ORDER BY table_name, index_name";
        $res = $db->query($sql);
        if (!$res) {
            $m = oci_error($res);
            die('Feil: '.$m['message']);
        }
        while ($row = $db->fetch_array($res)) {
            $d = $row[0]->load();
            fwrite($db_fil_hdl, $d);
            fwrite($db_inst_hdl, $d);
        }
    } else {
        $sql = "SELECT * FROM user_indexes";
        $res = $db->query($sql);
        $idxs = $db->fetch_all($res);

        foreach ($idxs as $idx) {
            $idx_name = $idx->index_name;
            $tbl_name = $idx->table_name;
            $create = "CREATE INDEX \"$db_navn\".\"$idx_name\" ";
            $create.= "ON \"$db_navn\".\"$tbl_name\" ";
            $sql = "SELECT column_name FROM user_ind_columns
            WHERE index_name = '$idx_name'
            ORDER BY column_position";
            $res = $db->query($sql);
            $rows = $db->fetch_all($res);
            $cols = array();
            foreach ($rows as $row) {
                $cols[] = $row->column_name;
            }
            $create .= '("' . implode('","', $cols) . '");';
            fwrite($db_fil_hdl, $create.";\n\n");
            fwrite($db_inst_hdl, $create.";\n\n");
        }
    }
}


// ---------------------
// Generates constraints
// ---------------------

// TODO: Jeg har "NOT NULL ENABLE" i ddl for tabellen
// Behøver derfor ikke constraints for det.

if ($db->platform == 'oracle') {
    $txt = "\n";
    $txt.= "--------------------------------------------------\n";
    $txt.= "-- CREATES CONSTRAINTS\n";
    $txt.= "--------------------------------------------------\n\n";
    fwrite($db_fil_hdl, $txt);
    fwrite($db_inst_hdl, $txt);

    if ($use_ddl) {
        $sql = "SELECT dbms_metadata.get_ddl('CONSTRAINT', c.constraint_name)
            FROM user_constraints c
            WHERE table_name NOT LIKE 'BIN$%'
            AND constraint_type != 'C'
            ORDER BY table_name, constraint_name";
        $res = $db->query($sql);
        while ($row = $db->fetch_array($res)) {
            $d = $row[0]->load();
            fwrite($db_fil_hdl, $d.";\n");
            fwrite($db_inst_hdl, $d.";\n");
        }
    } else {

        $sql = "SELECT table_name
          FROM user_constraints";
        $res = $db->query($sql);
        $cons = $db->fetch_all($res);

        foreach ($cons as $con) {
            $tbl_name = $con->table_name;
            $alter = "ALTER TABLE \"$db_navn\".\"$tbl_name\" ";
            $alter = "ADD CONSTRAINT ";
        }
    }
}

// ------------------
// Generates triggers
// ------------------

if ($db->platform == 'oracle') {
    $txt = "\n";
    $txt.= "--------------------------------------------------\n";
    $txt.= "-- CREATES TRIGGERS\n";
    $txt.= "--------------------------------------------------\n\n";
    fwrite($db_fil_hdl, $txt);
    fwrite($db_inst_hdl, $txt);

    if ($use_ddl) {
        $sql = "SELECT dbms_metadata.get_ddl('TRIGGER', t.trigger_name)
            FROM user_triggers t
            ORDER BY trigger_name";
        $res = $db->query($sql);
        while ($row = $db->fetch_array($res)) {
            $d = $row[0]->load();
            fwrite($db_fil_hdl, $d.";\n");
            fwrite($db_inst_hdl, $d.";\n");
        }
    } else {
        // TODO
    }
}


// --------------------------
// Populerer strukturtabeller
// --------------------------

if ($db_navn != $config['urd_base']) {
    $insert = "INSERT INTO urd.database_ (databasenavn, platform, username, "
            ."password, vis, betegnelse, produksjon, databasemal, datoformat)";
    $values = " VALUES ('$db->tpl', '$db->platform', '$db->tpl', '$db->tpl', 1, "
            ."'$db->title', 1, '$db->tpl', 1, 'dd.mm.yy')";
    fwrite($db_struktur_hdl, $insert . $values.";\n");
}

if (!$urd_inst) {

    $tables = array('tabell', 'kolonne', 'kolonnegruppe', 'handling');

    foreach ($tables as $table) {
        // Deletes existing definitions
        $delete = "\nDELETE FROM $table WHERE databasemal = '{$db->tpl}'";
        fwrite($db_struktur_hdl, $delete.";\n");

        // Finds the columns and the column type in the table
        // This is necessary because we must have the column type
        // to decide if there should be quotes around the values in insert
        $cols = array();
        $col_types = array();
        if ($urd->platform == 'mysql') {
            $sql = "SHOW columns FROM $table";
        } else if ($urd->platform == 'oracle') {
            $table = strtoupper($table);
            $sql = "SELECT column_name as field, data_type as type
            FROM user_tab_cols
            WHERE table_name = '$table'";
        }
        $res = $urd->query($sql);
        $rader = $urd->fetch_all($res);
        foreach ($rader as $rad) {
            $field = strtolower($rad->field);
            $cols[] = $field;
            $col_types[$field] = strtolower($rad->type);
        }


        // Finner verdiene:
        $sql = "SELECT * FROM $table WHERE databasemal = '{$db->tpl}'";
        $res = $urd->query($sql);
        $rows = $urd->fetch_all($res);
        if ($db->platform == 'mysql') {
            $sql_insert = "INSERT INTO $table (".implode(', ', $cols).") VALUES ";
            $i = 0;
            foreach ($rows as $row) {
                if ($i != 0) {
                    $sql_insert .= ", \n";
                }
                foreach ($row as $field=>$value) {
                    if ($value == '') {
                        $row->$field = 'null';
                    } else if (
                        strstr($col_types[$field], 'varchar')
                        || strstr($col_types[$field], 'text')
                        || strstr($col_types[$field], 'date')
                    ) {
                        $row->$field = "'".mysqli_real_escape_string($db->conn->dbh, $value)."'";
                    } else {
                        $row->$field = mysqli_real_escape_string($db->conn->dbh, $value);
                    }
                }
                $sql_insert .= '('.implode(', ', (array) $row).')';
            }
            if ($i > 0) {
                fwrite($db_struktur_hdl, $sql_insert.";\n");
            }
        } else if ($db->platform == 'oracle') {
            $n = 0;
            foreach ($rows as $row) {
                foreach ($row as $field=>$value) {
                    $column_type = strtolower($col_types->$field);
                    if ($value == '') {
                        $row->$field = 'null';
                    } else if (
                        strstr($column_type, 'varchar2')
                        || strstr($column_type, 'date')
                        || strstr($column_type, 'char')
                    ) {
                        $row->$field = "'".str_replace("'", "''", $value)."'";
                    }
                }
                $sql_insert = "INSERT INTO $table";
                $row = array_change_key_case($row, CASE_UPPER);
                $sql_insert .= " (".implode(",", array_keys($row)).") ";
                $sql_insert .= "VALUES (".implode(", ", $row).")";
                fwrite($db_struktur_hdl, $sql_insert.";\n");
                $n++;
            }
        } // end if
    }

    // Legger inn standardsøk i lagret_sok
    fwrite($db_struktur_hdl, "\n");
    $sql = "SELECT * FROM lagret_sok
          WHERE bruker = 'urd' and mal = '$db->tpl'";
    $res = $urd->query($sql);
    $sok_arr = $urd->fetch_all($res);
    foreach ($sok_arr as $sok) {
        $insert = "INSERT INTO lagret_sok (mal, tabell, sokeverdier, betegnelse, "
                ."bruker, standard)";
        $values = " VALUES ('$db->tpl', '$sok->tabell', '$sok->sokeverdier', "
                ."'$sok->betegnelse', 'urd', 1)";
        fwrite($db_struktur_hdl, $insert . $values . ";\n");
    }

}

// ---------------
// Setter grants
// ---------------

if ($db->platform == 'oracle') {
    // Går gjennom alle registrerte baser samt urd:

    $sql = "SELECT databasenavn from database_";
    $res = $urd->query($sql);
    $baser = $urd->fetch_all($res);
    $baser[] = (object) array('databasenavn'=>$urd_base);
    foreach ($baser as $base) {
        $base_navn = $base->databasenavn;
        //echo $base_navn;
        if ($db->platform == 'oracle') {
            $sql = "
  begin
  dbms_METADATA.SET_TRANSFORM_PARAM(DBMS_METADATA.SESSION_TRANSFORM,'SQLTERMINATOR',true);
  end;";
            $res = $db->query($sql);
            if (!$res) {
                $m = oci_error($res);
                echo $m['message'];
            }
            $sql = "SELECT DBMS_METADATA.GET_GRANTED_DDL('OBJECT_GRANT','BETTY')
              FROM dual";
            $res = $db->query($sql);
            while ($row = $db->fetch_array($res)) {
                $txt = $row[0]->load();
                fwrite($db_inst_hdl, $txt);
            }
        }
    }

    $sql = "SELECT DBMS_METADATA.GET_granted_DDL('OBJECT_GRANT','BETTY') from dual";
    $res = $db->query($sql);
    $txt = $db->fetch_column($res);
    fwrite($db_inst_hdl, $txt);
}

$response->msg = 'Databasemalen er oppdatert';
$response->send();

