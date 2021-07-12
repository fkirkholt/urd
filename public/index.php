<?php

require __DIR__ . '/../vendor/autoload.php';

function get_error_type($type) {
    switch ($type) {
    case E_ERROR:
    case E_USER_ERROR:
        return 'error';
    case E_WARNING:
        return 'warning';
    case E_NOTICE:
        return 'notice';
    default:
        return null;
    }
}

function fatal_error_handler() {
    $error = error_get_last();

    if ($error['type'] === E_ERROR) {
        error_log('fatal error');
        $log = [];
        $log['user_'] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $log['type'] = 'error';
        $log['file_'] = $error['file'];
        $log['line'] = $error['line'];
        $log['text'] = $error['message'];
        $log['time'] = new Dibi\Literal('CURRENT_TIMESTAMP');

        $test = dibi::insert('message', $log)->execute();

        echo 'Det er skjedd en feil, feilen er logget';
    }
}

$config = require __DIR__ . '/../app/config/config.default.php';
$config['templates.path'] = '../app/views';

if (file_exists(__DIR__ . '/../app/config/config.php')) {
    $local_config = include __DIR__ . '/../app/config/config.php';
    $config = array_replace_recursive($config, $local_config);
}

ini_set('session.gc_maxlifetime', $config['session_timeout']*60);

if (isset($config['session_save_path'])) {
  session_save_path($config['session_save_path']);
}

session_start();
ini_set('display_errors', 1);

// register_shutdown_function('fatal_error_handler');

$config['debug'] = true;

$app = new \Slim\Slim($config);

$app->error(function (\Exception $e) use ($app, $config) {

    $log = [];
    $log['user_'] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $log['type'] = get_error_type($e->getCode());
    $log['file_'] = $e->getFile();
    $log['line'] = $e->getLine();
    $log['trace'] = $e->getTraceAsString();
    $log['text'] = $e->getMessage();
    $log['time'] = new Dibi\Literal('CURRENT_TIMESTAMP');

    $parameters = [];

    foreach ($_REQUEST as $key=>$value) {
        if (in_array($key, ['username', 'brukernavn', 'password', 'passord'])) continue;
        $parameters[$key] = $value;
    }
    $log['parameters'] = json_encode($parameters);

    dibi::insert('message', $log)->execute();

    $mail = (object) $config['mail'];
    if (!$mail->send_errors) {
        echo "Det er skjedd en feil. Feilen er logget";
        return;
    }

    $mailer = new PHPMailer;

    // $mail->isSMTP();
    $mailer->Host = $mail->host;
    $mailer->Port = $mail->port;
    $mailer->SMTPAuth = true;
    $mailer->SMTPSecure = 'ssl'; // secure transfer enabled REQUIRED for Gmail
    $mailer->Username = $mail->username;
    $mailer->Password = $mail->password;
    $mailer->isHTML(true);

    $mailer->setFrom($mail->from_address, $mail->from_name);
    foreach ($mail->error_recipients as $address => $name) {
        $mailer->addAddress($address, $name);
    };

    $mailer->Subject = 'Feilmelding fra URD';
    $mailer->Body    = $log['text'] . ' in ' . $log['file_'] . '(' . $log['line'] . ')' . "<br>" . $log['time'] . ' ' . $log['user_'] . $log['type'] . "<br><br>" .  $log['trace'] . "<br><br>" . $log['parameters'] . "<br>";

    if (!$mailer->send()) {
        echo "Det er skjedd en feil. Feilen er logget";
    } else {
        echo "Det er skjedd en feil. Feilen er rapportert";
    }
});


// Set up dependencies
require __DIR__ . '/../app/dependencies.php';

// Register middleware
$app->add(new URD\middleware\Authentication);

// Register routes
require __DIR__ . '/../app/routes.php';

$app->run();


