<?php
/*--------------------------------------------------------+
| SYSTOPIA CiviProxy                                      |
|  a simple proxy solution for external access to CiviCRM |
| Copyright (C) 2015-2021 SYSTOPIA                        |
| Author: B. Endres (endres -at- systopia.de)             |
| http://www.systopia.de/                                 |
+---------------------------------------------------------*/

require_once "config.php";
require_once "proxy.php";
require_once "checks.php";

// see if REST API is enabled
if (!$target_rest4) civiproxy_http_error("Feature disabled", 405);

// basic check
if (!civiproxy_security_check('rest')) {
  civiproxy_rest_error("Access denied.");
}

// check credentials
// First look for the api_key appended to the request in the old style
$credentials = civiproxy_get_parameters(array('api_key' => 'string'));
// Then check a couple of headers for it to be compatible with API4
if (empty($credentials['api_key'])) {
  foreach (['AUTHORIZATION', 'X_CIVI_AUTH'] as $header) {
    if (!empty($_SERVER['HTTP_' . $header])) {
	  foreach (['Bearer', 'Basic'] as $prefix) {
	    if (strpos($_SERVER['HTTP_' . $header], $prefix) === 0) {
		  $credentials['api_key'] = trim( str_replace( $prefix, '', $_SERVER['HTTP_' . $header] ) );
		  break 2;
		}
	  }
	}
  }
}

civiproxy_map_api_key($credentials, $api_key_map);

// check if the call itself is allowed
$action = civiproxy_get_parameters(array('entity' => 'string', 'action' => 'string'));

$valid_parameters= civiproxy_get_valid_parameters($action, $rest_allowed_actions);

// extract parameters and add action data
$parameters = civiproxy_get_parameters($valid_parameters, json_decode($_REQUEST['params'], true));

// finally execute query
civiproxy_log($target_rest4);
civiproxy_redirect4($target_rest4 . $action['entity'] . '/' . $action['action'] , $parameters, $credentials['api_key']);
