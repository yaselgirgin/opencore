<?php
// Site
$_['site_url']          = HTTP_SERVER;

// Language
$_['language_code']     = 'tr-tr';

// Template
$_['template_engine']   = 'twig';

// Error
$_['error_display']     = true;

// Session
$_['session_autostart']  = true;
$_['session_engine']     = 'file';

// Actions
$_['action_default']    = 'install/step_1';
$_['action_error']      = 'error/not_found';
$_['action_pre_action'] = [
	'startup/install'
];

// Action Events
$_['action_event']      = [];
