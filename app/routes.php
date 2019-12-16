<?php

use URD\models\Record;

$app->get('/', 'URD\controllers\Homepage:show');
$app->get('/database', 'URD\controllers\DatabaseController:get_info');
$app->get('/table', 'URD\controllers\TableController:get_table');
$app->post('/login', 'URD\controllers\LoginController:login');
$app->get('/record', 'URD\controllers\RecordController:get');
$app->get('/relations', 'URD\controllers\RecordController:get_relations');
$app->get('/children', 'URD\controllers\RecordController:get_children');
$app->get('/contents', 'URD\controllers\DatabaseController:get_contents');
$app->get('/select', 'URD\controllers\TableController:get_select');
$app->put('/table', 'URD\controllers\TableController:save');
$app->post('/record', 'URD\controllers\RecordController:create');
$app->put('/record', 'URD\controllers\RecordController:update');
$app->delete('/record', 'URD\controllers\RecordController:delete');
$app->post('/filter', 'URD\controllers\TableController:save_filter');
$app->delete('/filter', 'URD\controllers\TableController:delete_search');
$app->get('/logout', 'URD\controllers\LoginController:logout');
$app->get('/table_sql', 'URD\controllers\TableController:export_sql');
$app->put('/run_sql', 'URD\controllers\DatabaseController:run_sql');
$app->put('/urd/update_schema', 'URD\controllers\SchemaController:update_schema');
$app->put('/urd/schema_from_urd', 'URD\controllers\SchemaController:schema_from_urd_tables');
$app->put('/urd/create_tables', 'URD\controllers\SchemaController:create_tables');

$app->get('/track_progress', function() use ($app) {

	$progress = isset($_SESSION['progress']) ? $_SESSION['progress'] : 0;
	if ($progress == 100) $_SESSION['progress'] = 0;
	return $app->response->body(json_encode(['progress' => $progress]));
});

$app->get('/printable_table', function() {
    require __DIR__ . '/../schemas/urd/actions/utskriftsvisning/utskriftsvisning.php';
});

$app->get('/file', function() use ($app) {
	$req = (object) $app->request->params();
	$prim_key = json_decode(urldecode($req->primary_key), true);

	$rec = new Record($req->base, $req->table, $prim_key);

	$filepath = $rec->get_file_path();

	if ($filepath) {
		$file = new URD\models\File($filepath);
		$file->get();
	} else {
		echo 'Det må settes fileroot i config';
	}
});

$dir = getcwd();
chdir('../schemas');
$schemas = glob('*');
foreach ($schemas as $schema) {
    if (file_exists($schema . '/routes.php')) {
        $app->group('/' . $schema, function() use ($app, $schema) {
            include $schema . '/routes.php';
        });
    }
}
chdir($dir);

