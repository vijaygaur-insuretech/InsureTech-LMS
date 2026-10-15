<?php
// Production config. Every value comes from the environment (the server's .env file), so no secret is kept in git.
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('LMS_DB_HOST') ?: 'db';
$CFG->dbname    = getenv('LMS_DB_NAME') ?: 'lms';
$CFG->dbuser    = getenv('LMS_DB_USER') ?: 'lms';
$CFG->dbpass    = getenv('LMS_DB_PASSWORD');
$CFG->prefix    = 'mdl_';
$CFG->dboptions = ['dbpersist' => false, 'dbport' => 5432];

$CFG->wwwroot   = rtrim(getenv('LMS_URL'), '/');
$CFG->dataroot  = '/var/www/lmsdata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02770;
$CFG->routerconfigured = false;

// TLS ends at the VPS web server, which forwards plain HTTP to this container.
if (str_starts_with($CFG->wwwroot, 'https://')) {
    $CFG->sslproxy = true;
}

// Production hardening.
$CFG->debug = 0;
$CFG->debugdisplay = 0;
$CFG->preventexecpath = true;
$CFG->disableupdateautodeploy = true;

require_once(__DIR__ . '/lib/setup.php');
