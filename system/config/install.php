<?php
// Site
$_['site_url']          = HTTP_SERVER;

// Language
$_['language_code']     = 'tr-tr';

// Template
$_['template_engine']   = 'twig';

// Database
$_['db_autostart']       = INSTALL_CONFIGURED;
$_['db_engine']          = defined('DB_DRIVER') ? DB_DRIVER : '';
$_['db_hostname']        = defined('DB_HOSTNAME') ? DB_HOSTNAME : '';
$_['db_username']        = defined('DB_USERNAME') ? DB_USERNAME : '';
$_['db_password']        = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$_['db_database']        = defined('DB_DATABASE') ? DB_DATABASE : '';
$_['db_port']            = defined('DB_PORT') ? DB_PORT : '';
$_['db_ssl_key']         = defined('DB_SSL_KEY') ? DB_SSL_KEY : '';
$_['db_ssl_cert']        = defined('DB_SSL_CERT') ? DB_SSL_CERT : '';
$_['db_ssl_ca']          = defined('DB_SSL_CA') ? DB_SSL_CA : '';

// Error
$_['error_display']     = true;

// Session
$_['session_autostart']  = true;
$_['session_engine']     = 'file';

// Actions
$_['action_default']    = 'install/step_1';
$_['action_error']      = 'error/not_found';
$_['action_pre_action'] = [
	'startup/install',
	'startup/upgrade'
];

// Action Events
$_['action_event']      = [];
