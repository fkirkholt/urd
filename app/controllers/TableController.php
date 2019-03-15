<?php

namespace URD\controllers;

use URD\models\Table;
use URD\models\Record;
use URD\models\Schema;
use dibi;

class TableController extends BaseController {

    public function get_table() {
        $req = (object) $this->request->params();

        $tbl = Table::get($req->base, $req->table);

        // Note: Accepts value 0 for limit
        $tbl->limit = isset($req->limit) ? (int) $req->limit : 30;
        $tbl->offset = isset($req->offset) ? (int) $req->offset : 0;

        $tbl->user_filtered = empty($req->filter) ? false : true;


        // If the database-table of URD is shown, we filter the tables
        // according to user permission
        if ($req->base == dibi::getConnection()->getConfig('name') && $tbl->name == 'database_') {

            $user_role_list = implode(',', $tbl->db->get_user_roles() ?: [0]);

            $user_id = $_SESSION['user_id'];
            $tbl->add_condition("
            (SELECT count(*)
            FROM role_permission rp
            WHERE rp.schema_ = database_.schema_ AND view_ = 1 AND role IN ($user_role_list) > 0)
            OR database_.schema_ = 'urd' AND (SELECT count(*) FROM role_permission rp2 WHERE rp2.admin = 1 AND role IN ($user_role_list) > 0)"
            );
        }

        // sorting
        $tbl->grid->sort_columns = !empty($req->sort)
                        ? json_decode($req->sort)
                        : $tbl->grid->sort_columns;

        // additional conditions
        if (!empty($req->condition)) {
            $tbl->add_condition(explode(';', urldecode($req->condition))[0]);
        }

        if (isset($req->csv)) {
            $tbl->csv = true;
            $tbl->limit = null;
            $tbl->grid->columns = json_decode(urldecode($req->fields));
        } else {
            $tbl->csv = false;
        }


        // Handles search
        {
            $sok_arr = !empty($req->filter)
                ? explode(' AND ', urldecode($req->filter))
                : array();

            foreach ($sok_arr as $cond) {
                $parts = preg_split("/\s*([=<>]|!=| IN| LIKE|NOT LIKE|IS NULL|IS NOT NULL)\s*/", $cond, 2,
                    PREG_SPLIT_DELIM_CAPTURE);
                $field = $parts[0];
                if (strpos($field, '.') === false) {
                    $field = "$tbl->name.$field";
                }
                $operator = trim($parts[1]);
                $value = str_replace('*', '%', $parts[2]);
                if ($operator === 'IN') {
                    $value = "('" . implode("','", explode(',', trim($value))) . "')";
                } elseif ($value !== '') {
                    $value = "'" . trim($value, " '") . "'";
                }
                $tbl->add_condition("$field $operator $value");
            }
        }

        $pk = isset($req->prim_key) ? $req->prim_key : null;
        $data = $tbl->hent_tabell($pk);

        if ($data === false) {
            $this->response->status(403);

            return $this->response->body(json_encode(['message' => 'No permission']));
        }

        if ($req->base == dibi::getConnection()->getConfig('name') && $tbl->name == 'database_') {
            $data['type'] = 'database';
        }
        else {
            $data['type'] = 'table';
        }
        // TODO: hører ikke hjemme her, men i modellen!
        $data['saved_filters'] = $tbl->get_saved_searches();

        // TODO: Vurder å legge dette til egen funksjon i /models/Table.php
        if ($tbl->csv) {
            header("Cache-Control: ");
            header("Content-type: txt/plain");
            header('Content-Disposition: attachment; filename="'.$tbl->name.'.csv"');

            // Makes heading
            $headings = array();
            foreach ($tbl->grid->columns as $field_name) {
                $headings[] = $field_name;
            }
            $content = implode(';', $headings) . "\n";
            foreach ($data['records'] as $record) {
                $verdier = array();
                foreach ($tbl->grid->columns as $field_name) {
                    $field = $data['fields'][$field_name];
                    $verdi = str_replace('"', '""', $record['columns']->$field_name);
                    if ((!empty($field->datatype) && in_array($field->datatype, ['string', 'date'])
                         || !isset($field->datatype)
                         || isset($field->view)) && $verdi != null) {
                        $verdi = '"'.$verdi.'"';
                    }
                    $verdier[] = $verdi;
                }
                $content .= implode(';', $verdier) . "\n";
            }
            return $this->response->body($content);
        } else {
            return $this->response->body(json_encode(['data' => $data]));
        }
    }

    public function get_select() {
        $req = (object) $this->request->params();
        if (empty($req->base)) {
            $schema = new Schema($req->schema);
            $req->base = $schema->get_db_name();
        }
        $tbl = Table::get($req->base, $req->table);
        $tbl->alias = isset($req->alias) ? $req->alias : $req->table;

        $data = $tbl->get_select($req);

        return $this->response->body(json_encode($data));
    }

    public function save() {
        $req = json_decode($this->request->getBody());

        $tbl = Table::get($req->base_name, $req->table_name);
        $records = json_decode(json_encode($req->records));

        $data = $tbl->save($records);

        return $this->response->body(json_encode(['data' => $data]));
    }

    public function save_filter() {
        $req = (object) $this->request->params();

        $tbl = Table::get($req->base, $req->table);
        $id = $tbl->save_search($req->filter, $req->label, $req->advanced, $req->id);

        return $this->response->body(json_encode(['data' => ['id' => $id]]));
    }

    // TODO: Hører denne hjemme her? Er det aktuelt med en Search-klasse?
    public function delete_search() {
        $req = json_decode($this->request->getBody());

        $data = Table::delete_search($req->id);

        return $this->response->body(json_encode(['data' => $data]));
    }

    public function export_sql() {
        $req = (object) $this->request->params();
        $tbl = Table::get($req->base, $req->table);
        $res = $tbl->export_sql($req->dialekt);

        // TODO: Haven't been able to do it with Slim response
        header("Cache-Control: ");
        header("Content-type: txt/plain");
        header('Content-Disposition: attachment; filename="'.$tbl->name.'.sql"');
        echo $res;
    }

}
