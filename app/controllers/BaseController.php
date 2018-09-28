<?php

namespace URD\controllers;

class BaseController {

    public function __construct() {
        $app = \Slim\Slim::getInstance();
        $this->app = $app;
        $this->request = $app->request;
        $this->response = $app->response;
        $this->config = $app->settings;

        $this->response['Content-Type'] = 'application/json';
        $this->response->status(200);
    }
}
