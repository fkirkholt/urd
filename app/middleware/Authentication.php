<?php

namespace URD\middleware;

class Authentication extends \Slim\Middleware
{
    public function call()
    {
        $app = \Slim\Slim::getInstance();
        $request = $app->request;
        $response = $app->response;
        $path = $request->getResourceUri();
        if (!isset($_SESSION['user_id']) && $path != '/' && $path != 'login' && $path != '/login') {
            $response->status(401);
            // return $response->write('login');
            return $response->body(json_encode(['message' => 'login']));
        } else {
            $this->next->call();
        }
    }
}
