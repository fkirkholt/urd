<?php

namespace URD\controllers;

use URD\models\Database as DB;

class DatabaseController extends BaseController {

    public function __construct()
    {
        parent::__construct();
        $this->db_name = $this->request->params('base');
        $this->db = DB::get($this->db_name);
    }

    public function get_info()
    {
        $info = $this->db->get_info();

        $info->base->name = $this->db_name;

        return $this->response->body(json_encode(['data' => $info]));
    }

    public function get_contents()
    {

        $tables = $this->db->get_contents();

        return $this->response->body(json_encode(['data' => $tables]));
    }

    public function run_sql()
    {
        $req = json_decode($this->request->getBody());
        $db = DB::get($req->base);
        $sql = $request->getParam('sql');
        $result = $db->query($sql);
        if (!$result) {
            $this->response->getBody()->write('ERROR!');
        } else {
            $this->response->getBody()->write('SUCCESS!');
        }

        return $this->response;
    }

}
