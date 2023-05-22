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

if (empty($credentials['api_key'])) {
  civiproxy_rest_error("No API key given");
} else {
  if (isset($api_key_map[$credentials['api_key']])) {
    $credentials['api_key'] = $api_key_map[$credentials['api_key']];
  } else {
    civiproxy_rest_error("Invalid api key");
  }
}

// check if the call itself is allowed
$action = civiproxy_get_parameters(array('entity' => 'string', 'action' => 'string'));

// in release 0.4, allowed entity/actions per IP were introduced. To introduce backward compatibility,
// the previous test is still used when no 'all' key is found in the array
if (isset($rest_allowed_actions['all'])) {
	// get valid key for the rest_allowed_actions
	$valid_allowed_key = civiproxy_get_valid_allowed_actions_key($action, $rest_allowed_actions);
  $valid_parameters = civiproxy_retrieve_api_parameters($valid_allowed_key, $action['entity'], $action['action'], $rest_allowed_actions);
	if (!$valid_parameters) {
		civiproxy_rest_error("Invalid entity/action.");
	}
} else {
	if (isset($rest_allowed_actions[$action['entity']]) && isset($rest_allowed_actions[$action['entity']][$action['action']])) {
		$valid_parameters = $rest_allowed_actions[$action['entity']][$action['action']];
	} else {
		civiproxy_rest_error("Invalid entity/action.");
	}
}

// extract parameters and add action data
$parameters = civiproxy_get_parameters($valid_parameters, json_decode($_REQUEST['params'], true));

// finally execute query
civiproxy_log($target_rest4);
civiproxy_redirect4($target_rest4 . $action['entity'] . '/' . $action['action'] , $parameters, $credentials['api_key']);


/**
 * generates a CiviCRM REST API compliant error
 * and ends processing
 */
function civiproxy_rest_error($message) {
  $error = array( 'is_error'      => 1,
                  'error_message' => $message);
  // TODO: Implement
  //header();
  print json_encode($error);
  exit();
}
