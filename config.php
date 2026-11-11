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

$CFG->wwwroot   = 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN');
$CFG->dataroot  = '/tmp/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;
require_once(__DIR__ . '/lib/setup.php');