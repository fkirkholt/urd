<?php

namespace URD\models;

use URD\models\Expression;
use URD\models\Schema;
use PDO;
use dibi;
use ChromePhp as Log;

class Database {

    protected $host;
    protected $username;
    protected $password;
    protected $user;
    public $conn;
    private static $instances;

    public function __construct($db_name) {

        $db = dibi::select('name, alias, host, port, username, password,
            platform, schema_, label, log')
            ->from('database_ db')
            ->where('db.name = ?', $db_name)
            ->or('db.alias = ?', $db_name)
            ->fetch();

        // A database can be identified vied alias or name,
        // and this stores which the user is using
        $this->req_name = $db_name;

        if ($db_name != dibi::getConnection()->getConfig('name')) {
            // Establishes link to the urd-database

            if (!$db) {
                trigger_error("Database $db_name not defined", E_USER_ERROR);
            }

            $this->name = $db->name;
            $this->schema = $db->schema_;
            $this->label = $db->label;
            $this->log = $db->log;
            $this->host = $db->host;
            $this->username = $db->username;
            $this->password = $db->password;
            $this->alias = ($db->platform == 'sqlite')
                ? 'main'
                : $this->name;
            if ($db->host == 'localhost') $db->host = '127.0.0.1';

            $options = [
                PDO::ATTR_CASE => PDO::CASE_LOWER,
            ];

            if ($db->platform == 'sqlite') {
                $this->dsn = 'sqlite:' . $db->host;
            } elseif ($db->platform == 'oracle') {
                $this->dsn = 'oci:dbname=' . $db->host . ';charset=AL32UTF8';
            } else {
                $this->dsn = $db->platform . ':host=' . $db->host . (!$db->port ? '' : ';port=' . $db->port) . ';dbname=' . $db->name
                    . ';charset=utf8';
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET sql_mode = "PIPES_AS_CONCAT"';
            }
            $this->platform = $db->platform;

            if($this->platform === 'sqlite') {
                $this->conn = new Dibi\Connection([
                    'driver' => 'sqlite3',
                    'database' => $db->host
                ]);
            } else {
                $this->conn = new Dibi\Connection([
                    'driver' => 'pdo',
                    'dsn' => $this->dsn,
                    'host' => $db->host,
                    'user' => $db->username,
                    'pass' => $db->password,
                    'formatDate' => "'Y-m-d'",
                    'formatDateTime' => "'Y-m-d H-i-s'",
                    'result' => [
                        'formatDate' => "Y-m-d",
                        'formatDateTime' => "Y-m-d H-i-s"
                    ],
                    'options' => $options,
                    'resource' => $db->platform === 'oracle'
                    ? new \URD\lib\Oci8($this->dsn, $db->username, $db->password, $options)
                    : null,
                ]);
            }
        } else {
            $this->name = dibi::getConnection()->getConfig('name');
            $this->conn = dibi::getConnection();
            $this->schema = 'urd';
            $this->label = isset($db->label) ? $db->label : 'URD';
            $this->doc_repository = null;
            $platform = dibi::getConnection()->getDriver()->getResource()->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->platform = $platform == 'oci' || $platform == 'oci8' ? 'oracle' : $platform;
            $this->alias = $this->name;
            $this->log = 0; // No log table in urd base
        }

        // $this->conn->option(PDO::ATTR_CASE, PDO::CASE_LOWER);
        // Disables quoting of identifiers
        // $this->conn->setWrapperFormat('%s');
        $this->connection = $this->conn;
        // $this->conn->logQueries(true);

        $schema = Schema::get($this->schema);

        $this->tables = isset($schema->tables) ? $schema->tables : [];
        $this->relations = isset($schema->relations) ? $schema->relations : [];
        $this->reports = isset($schema->reports) ? $schema->reports : [];
        $this->contents = isset($schema->contents) ? $schema->contents : null;
    }

    public static function get($db_name=null) {
        $db_name = ($db_name) ?: dibi::getConnection()->getConfig('name');
        if (!isset(self::$instances[$db_name])) {
            self::$instances[$db_name] = new Database($db_name);
        }
        return self::$instances[$db_name];
    }

    public function get_replication_connection() {
        if ($this->platform != 'oracle') {
            return $this->connection;
        } else {
            $cfg = $this->connection->getConfig();

            return new Dibi\Connection([
                'driver' => 'oracle',
                'database' => $cfg['host'],
                'username' => $cfg['username'],
                'password' => $cfg['password'],
            ]);
        }
    }

    public function get_info() {
        if (file_exists('../../public/schemas/' . $this->schema . '/img/banner.png')) {
            $banner = 'schemas/' . $this->schema . '/img/banner.png';
        } else {
            $banner = 'img/banner.png';
        }

        $branch = exec('git rev-parse --abbrev-ref HEAD');
        if ($branch === 'master') {
            $branch = '';
        }

        $user_roles = $this->get_user_roles();

        // Finds if user have administrative rights
        if ($this->schema === 'urd') {
            $is_admin = dibi::query('
                SELECT count(*)
                FROM role_permission
                WHERE role in (?)', $user_roles ?: [0], '
                AND    admin = 1')->fetchSingle();
        } else {
            $is_admin = dibi::query('
                SELECT count(*)
                FROM   role_permission
                WHERE  role in (?)', $user_roles ?: [0], '
                AND    schema_ = ?', $this->schema, '
                AND    admin = 1')->fetchSingle();
        }

        $info = new \StdClass;
        $info->base = new \StdClass;
        $info->base->name = $this->name;
        $info->base->schema = $this->schema;
        $info->base->label = $this->label;
        $info->base->branch = $branch;
        $info->banner = $banner;
        $info->user = new \StdClass;
        $info->user->name = $_SESSION['user_name'];
        $info->user->id = $_SESSION['user_id'];
        $info->user->admin = $is_admin;


        $this->user = $info->user;


        $contents = $this->get_contents();

        $info->base->tables = $contents->tables;
        $info->base->reports = $contents->reports;
        $info->base->contents = $contents->contents;
        return $info;
    }

    function get_user_roles()
    {
        $roles = dibi::select('role')
            ->from('user_role')
            ->where('user_ = ?', $_SESSION['user_id'])
            ->fetchPairs();

        $app = \Slim\Slim::getInstance();
        $roles = array_merge($roles, $app->config('default_roles'));

        return $roles;
    }

    function get_user_admin_schemas()
    {
        $roles = $this->get_user_roles();

        $admin_schemas = dibi::select('schema_')
            ->from('role_permission')
            ->where('role')->in($roles)
            ->where('admin = 1')
            ->fetchPairs();

        return $admin_schemas;
    }

    public function get_contents()
    {

        $user_roles = $this->get_user_roles();
        $user = $_SESSION['user_id'];

        // Finds the tables the user has permission to view
        $rows = dibi::select('table_, view_')
            ->from('role_permission r')
            ->where('role')->in($user_roles)
            ->where("(schema_ = '*' or schema_ = ?)", $this->schema)
            ->fetchAssoc('table_');

        $standard_filters = dibi::select('table_, expression')
            ->from('filter f')
            ->where('schema_ = ?', $this->schema)
            ->where('user_ IN (?)', [$user, 'urd'])
            ->where('standard = ?', 1)
            ->fetchAssoc('table_');

        // Make array of tables the user has access to
        $data_arr = array();
        foreach ($this->tables as $table_name => $table) {
            $table = (object) $table;
            if ($table->label == null) {
                $table->label = ucfirst(str_replace('_', ' ', $table->name));
            }

            // Don't show tables the user doesn't have access to
            $view = 0;
            if (isset($rows[$table_name])) {
                $view = $rows[$table_name]['view_'];
            } else if (isset($rows['*'])) {
                $view = $rows['*']['view_'];
            } else if ($this->schema === 'urd' && $this->user->admin && in_array($table_name, ['filter', 'format', 'role', 'role_permission', 'user_'])) {
                $view = 1;
            }
            if (!$view) continue;

            unset($table->filter); // Don't expose this for client
            if (isset($standard_filters[$table_name])) {
                $table->default_filter = $this->expr()->replace_vars($standard_filters[$table_name]['expression']);
            }
            $data_arr[$table_name] = $table;
        }

        $data = new \StdClass;
        // $data->rapporter = $this->get_reports();
        $data->reports = isset($this->reports) ? $this->reports : [];
        $data->contents = isset($this->contents) ? $this->contents : null;
        $data->tables = $data_arr;

        return $data;
    }

    public function get_reports() {
        $rapportliste = [];
        $reports_dir = __DIR__ . '/../../public/schemas/' . $this->schema. '/rapporter';
        if (file_exists($reports_dir)) {
            $dir = getcwd();
            chdir($reports_dir);
            $rapporter = glob('*');
            chdir($dir);
            if (count($rapporter)) {
                foreach ($rapporter as $rapport) {
                    if (file_exists($rapport . '/info.txt')) {
                        $info = file_get_contents($rapport . '/info.txt');
                        $rapportliste[$rapport] = $info;
                    } else {
                        $rapportliste[$rapport] = null;
                    }
                }
            }
        }
        return $rapportliste;
    }



    /**
     * Populate reference tables
     */
    public function populate_ref_tables()
    {
        chdir(__DIR__ . '/../../schemas/' . $this->schema. '/tables');

        $files = glob('*.json');

        foreach ($files as $file) {
            $table = json_decode(file_get_contents($file));
            if (!isset($table->records)) {
                continue;
            }
            echo 'Setter inn verdier i ' . $this->name . '.' . $table->table . "\n";
            foreach ($table->records as $record) {
                $this->conn->insert($table->table, (array) $record)
                    ->execute();
            }
        }
    }


    public function get_generated_reports() {
        // Finds reports generated by report generator
        $reports = dibi::select('*')
            ->from('rapport r')
            ->where('databasemal = ?', $this->schema)
            ->fetchAssoc('id');

        return $reports;
    }

    public function expr($string = '') {
        return new Expression($this->platform, $string);
    }

    public function query($args) {
        $args = func_get_args();
        return $this->conn->query($args);
    }

    public function fetch_row($res) {
        return $this->conn->fetch_row($res);
    }

    public function fetchAssoc($args) {
        $args = func_get_args();
        return $this->conn->query($args)->fetchAssoc();
    }

    public function fetch($args) {
        $args = func_get_args();
        return $this->conn->query($args)->fetch();
    }

    public function fetchAll($args) {
        $args = func_get_args();
        return $this->conn->query($args)->fetchAll();
    }

    public function fetchSingle($args) {
        
        $args = func_get_args();
        return $this->conn->query($args)->fetchSingle();
    }

    public function fetchPairs($args) {
        $args = func_get_args();
        return $this->conn->query($args)->fetchPairs();
    }

    public function last_insert_id() {
        return $this->conn->getInsertId();
    }

    public function select($args) {
        $args = func_get_args();
        return $this->conn->select($args);
    }

    public function insert($table, $args) {
        return $this->conn->insert($table, $args);
    }

    public function update($table, $args) {
        return $this->conn->update($table, $args);
    }

    public function delete($table) {
        return $this->conn->delete($table);
    }

}
