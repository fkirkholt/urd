<?php

namespace URD\controllers;

use URD\models\Record;

class RecordController extends BaseController {

    public function get() {
        $req = (object) $this->request->params();
        $prim_keys = json_decode($req->primary_key, true);
        $this->rec = new Record($req->base, $req->table, $prim_keys);
        $record = $this->rec->get();

        return $this->response->body(json_encode(['data' => $record]));
    }

    public function get_relations() {
        $req = (object) $this->request->params();
        $prim_keys = json_decode($req->primary_key, true);
        $this->rec = new Record($req->base, $req->table, $prim_keys);
        $alias = isset($req->alias) ? $req->alias : null;
        $req->count = filter_var($req->count, FILTER_VALIDATE_BOOLEAN);
        $relations = $this->rec->get_relations($req->count, $alias);

        return $this->response->body(json_encode(['data' => $relations]));
    }

    public function get_children() {
        $req = (object) $this->request->params();
        $prim_keys = json_decode($req->primary_key, true);
        $this->rec = new Record($req->base, $req->table, $prim_keys);
        $records = $this->rec->get_children();

        return $this->response->body(json_encode(['data' => $records]));
    }

    public function create() {
        $req = json_decode($this->request->getBody());

        $record = New Record($req->base_name, $req->table_name, $req->primary_key);
        $data = new \StdClass;
        $data->values = $record->insert($req->values);

        return $this->response->body(json_encode($data));
    }

    public function update() {
        $req = json_decode($this->request->getBody());

        $record = New Record($req->base_name, $req->table_name, $req->primary_key);
        $data = new \StdClass;
        $data->values = $record->update($req->values);

        return $this->response->body(json_encode($data));
    }

    public function delete() {
        $req = json_decode($this->request->getBody());

        $record = New Record($req->base_name, $req->table_name, $req->primary_key);
        $res = $record->delete();

        return $this->response->body(json_encode($res));
    }

}
