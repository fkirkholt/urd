<?php

namespace URD\controllers;

use dibi;

class Homepage {

    private $renderer;

    public function __construct() {
        $app = \Slim\Slim::getInstance();
        $this->app = $app;
    }

    public function show() {
		$version = '';
        $git_version = exec('git describe --tags');
        if ($git_version) {
            $version = $git_version;
        }

        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $user_name = $_SESSION['user_name'];
        } else {
            $user_id = '';
            $user_name = '';
        }

        $branch = exec('git rev-parse --abbrev-ref HEAD');
        if ($branch === 'master') {
            $branch = '';
        }

        $data = [
            // 'user_name' => $user_name,
            'version' => $version,
            'branch' => $branch,
            'urd_base' => dibi::getConnection()->getConfig('name'),
        ];
        $html = $this->app->render('urd.html', $data);
        $this->app->response->setBody($html);

        return $this->app->response;
    }
}
