<?php

namespace URD\controllers;

use URD\models\Database as DB;
use URD\models\Schema;

class SchemaController extends BaseController {

    public function get_schema()
    {
        $req = (object) $this->request->params();

        $schema = Schema::get($req->name);

        return $this->response->body(json_encode(['data' => $schema]));
    }

    public function update_schema()
    {
        $req = json_decode($this->request->getBody());
        $pk = json_decode($req->primary_key);
        $config = json_decode($req->config);
        $db_name = $pk->name;
        $schema_name = DB::get($db_name)->schema;
        $schema = new Schema($schema_name);
        $result = $schema->update_schema_from_database($db_name, $config);

        return $this->response->body(json_encode($result));
    }

    public function schema_from_urd_tables()
    {
        $req = json_decode($this->request->getBody());
        $pk = json_decode($req->primary_key);
        $db_name = $pk->name;
        $schema_name = DB::get()->conn->select('schema_')->from('database_')->where('name = ?', $db_name)->fetchSingle();
        error_log($schema_name);
        $schema = new Schema($schema_name);
        $data = $schema->update_schema_from_urd_tables();

        return $this->response->body(json_encode(['data' => $data]));
    }

    public function create_tables()
    {
        $req = json_decode($this->request->getBody());
        $pk = json_decode($req->primary_key);
        $db_name = $pk->name;
        $schema_name = DB::get()->conn->select('schema_')->from('database_')->where('name = ?', $db_name)->fetchSingle();
        $schema = new Schema($schema_name);
        $data = $schema->create_tables_from_schema($db_name);

        return $this->response->body(json_encode(['data' => $data]));
    }
}
