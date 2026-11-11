<?php  // Moodle configuration file for Railway

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MYSQLHOST');
$CFG->dbname    = getenv('MYSQL_DATABASE');
$CFG->dbuser    = getenv('MYSQLUSER');
$CFG->dbpass    = getenv('MYSQLPASSWORD');
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => getenv('MYSQLPORT') ?: 3306,
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'https://moodle-ssh-lms-production.up.railway.app';
$CFG->dataroot = '/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

// --- Railway SSL Proxy Fix ---
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    $_SERVER['HTTPS'] = 'on';
}

require_once(__DIR__ . '/lib/setup.php');
