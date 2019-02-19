<?php

namespace URD\controllers;

use URD\models\Database;
use dibi;

class LoginController extends BaseController {

    function login() {
        $req = (object) $this->request->params();

        $brukernavn = $req->brukernavn;
        $passord = $req->passord;

        $success = false;
        $registrert = '';

        if ($this->config['single_sign_on']) {
            $ldap = $this->config['ldap'];
            $ldapconn = ldap_connect($ldap['server'])
                      or die("Kunne ikke kople til LDAP server");
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
            if ($ldapconn && $brukernavn && $passord) {
                $user = $ldap['user_prefix'].$brukernavn;
                try {
                    $ldapbind = ldap_bind($ldapconn, $user, $passord);
                } catch(Exception $e) {
                    // Dont log this error
                }
                if ($ldapbind) {
                    $success = true;
                }
            }
            $sql = "SELECT id, name FROM user_ WHERE id='$brukernavn' and active = 1";
            $rad = dibi::fetch($sql);
            if (!$rad) {
                $rad = new \StdClass;
                $rad->id = $brukernavn;
                $rad->name = $brukernavn;
                $_SESSION['guest'] = true;
            } else {
                $_SESSION['guest'] = false;
            }
        }

        if (!$this->config['single_sign_on']) {
            $sql = "SELECT id, name, hash
                    FROM user_
                    WHERE id = ? AND active = 1";
            $rad = dibi::fetch($sql, $brukernavn);

            if ($rad && password_verify($passord, $rad['hash'])) {
                $success = true;
                $_SESSION['guest'] = false;
            }
        }

        if ($success) {
            $_SESSION['user_id'] = $brukernavn;
            $_SESSION['user_name'] = $rad->name;
            $success = true;
        }

        return $this->response->body(json_encode(['success' => $success]));
    }

    public function logout() {
        $logout = session_destroy();
        $_SESSION = array();

        return $this->response->body($logout);
    }
}
