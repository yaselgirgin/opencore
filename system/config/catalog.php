<?php
// Site
$_['site_url']           = HTTP_SERVER;

// Database
$_['db_autostart']       = true;
$_['db_engine']          = DB_DRIVER; // mysqli, pdo or pgsql
$_['db_hostname']        = DB_HOSTNAME;
$_['db_username']        = DB_USERNAME;
$_['db_password']        = DB_PASSWORD;
$_['db_database']        = DB_DATABASE;
$_['db_port']            = DB_PORT;
//$_['db_ssl_key']         = DB_SSL_KEY;
//$_['db_ssl_cert']        = DB_SSL_CERT;
//$_['db_ssl_ca']          = DB_SSL_CA;

// Session
$_['session_autostart']  = false;
$_['session_engine']     = 'db'; // db or file

// Response
$_['response_header']     = ['Content-Type: application/json; charset=utf-8'];

// Actions
$_['action_pre_action']  = [
	'startup/setting',
	'startup/error',
	'startup/api'
];

$_['action_default']     = 'api/system';
$_['action_error']       = 'api/system.notFound';
