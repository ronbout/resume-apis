<?php

// company.php - REST api for company config settings

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class CompanyInfo {
	public $companyCode;
	public $title;
	public $logoImage;
	public $menuImage;
	public $theme;
	public $apiKey;

	function __construct($code = '', $title = '', $logo = '', $menu = '', $theme = '', $key = '') {
		$this->companyCode = $code;
		$this->title = $title;
		$this->logoImage = $logo;
		$this->menuImage = $menu;
		$this->theme = $theme;
		$this->apiKey = $key;
	}
}

$configLoc = 'C:\Users\ron\Documents\htdocs\test\admin\config.json';
//$configLoc = '/var/www/html/test/admin/config.json';

$app->get ( '/company', function (Request $request, Response $response) {
	global $configLoc;
	
	$data = array ();
	
	// get config.json. if unsuccessful, the return value is the
	// Response to send back, otherwise the file;
	$errCode = 0;
	$config = json_connect ( $request, $response, $configLoc, $errCode );
	if ($errCode) {
		return $config;
	}
	
	$response_data = array();
	foreach($config['companyInfo'] as $c_key => $c_info) {
		$tmp_array = array('companyCode' => $c_key);
		foreach($c_info as $key => $info) {
			$tmp_array[$key] = $info;
		}
		$response_data[] = $tmp_array;
	}
	$data = array (	'data' => $response_data);
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
	return $newResponse;
} );

$app->get ( '/company/{company}', function (Request $request, Response $response) {
	global $configLoc;

	$cc = $request->getAttribute ( 'company' );
	$data = array ();
	
	// get config.json. if unsuccessful, the return value is the
	// Response to send back, otherwise the file;
	$errCode = 0;
	$config = json_connect ( $request, $response, $configLoc, $errCode );
	if ($errCode) {
		return $config;
	}
	
	$response_data = array();
	foreach($config['companyInfo'] as $c_key => $c_info) {
		if ($c_key == $cc) {
			$tmp_array = array('companyCode' => $c_key);
			foreach($c_info as $key => $info) {
				$tmp_array[$key] = $info;
			}
			$response_data[] = $tmp_array;
		}
	}
	
	if (count($response_data) == 0) {
		$data ['error'] = false;
		$data ['message'] = 'No records Found';
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	$data = array (	'data' => $response_data);
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );

$app->post ( '/company', function (Request $request, Response $response) {
	global $configLoc;
	$post_data = $request->getParsedBody ();
	$data = array ();
	
	// get config.json. if unsuccessful, the return value is the
	// Response to send back, otherwise the file;
	$errCode = 0;
	$config = json_connect( $request, $response, $configLoc, $errCode );
	if ($errCode) {
		return $config;
	}

	// check that all fields are present 
	// TODO:  make each field in the class an array with field name and
	// 'required' elements.  Now..assuming that all are required.
	$companyInfo = new CompanyInfo();	

	$fields = array();
	foreach($companyInfo as $key => $value) {
		$fields[] = $key;
	}

	$missingFields = '';
	foreach($fields as $value) {
		if (! isset( $post_data[$value] )) {
			$missingFields .= ($missingFields) ? ', ' : '';
			$missingFields .= $key;
		} else {
			$companyInfo->$value = $post_data[$value];
		}
	}

	if ( $missingFields ) {
		$data ['error'] = true;
		$data ['message'] = "The following field(s) are required: $missingFields";
		$newResponse = $response->withJson ( $data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// make sure that the company code is unique
	if (isset($config['companyInfo'][$companyInfo->companyCode])) {
		$data ['error'] = true;
		$data ['message'] = 'Company code ' . $companyInfo->companyCode . ' already exists';
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	// copy from company info into $config and write out
	$cc = $companyInfo->companyCode;
	$config['companyInfo'][$cc] = array();
	foreach($companyInfo as $key => $value) {
		// skip the company code as that is our array higher level key
		if ($key == 'companyCode') continue;
		$config['companyInfo'][$cc][$key] = $value;
	}
//echo '<pre>', var_dump(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)), '</pre>';

	file_put_contents($configLoc, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));
	
	// set up response data
	$response_data = array();
	$c_info = $config['companyInfo'][$cc];
	$tmp_array = array('companyCode' => $cc);
	foreach($c_info as $key => $info) {
		$tmp_array[$key] = $info;
	}
	$response_data[] = $tmp_array;

	$data = array (	'data' => $response_data );
	$newResponse = $response->withJson ( $data, 201, JSON_NUMERIC_CHECK );
} );

$app->put ( '/company/{code}', function (Request $request, Response $response) {
	global $configLoc;
	$cc = $request->getAttribute ( 'code' );
	$post_data = $request->getParsedBody ();
	$data = array ();
	
	// get config.json. if unsuccessful, the return value is the
	// Response to send back, otherwise the file;
	$errCode = 0;
	$config = json_connect( $request, $response, $configLoc, $errCode );
	if ($errCode) {
		return $config;
	}

	// check that all fields are present 
	// TODO:  make each field in the class an array with field name and
	// 'required' elements.  Now..assuming that all are required.
	$companyInfo = new CompanyInfo();	

	$fields = array();
	foreach($companyInfo as $key => $value) {
		$fields[] = $key;
	}

	$missingFields = '';
	foreach($fields as $value) {
		// company code may not be passed through post
		// as it is part of the url
		if ($value == 'companyCode') continue;
		if (! isset( $post_data[$value] )) {
			$missingFields .= ($missingFields) ? ', ' : '';
			$missingFields .= $key;
		} else {
			$companyInfo->$value = $post_data[$value];
		}
	}

	if ( $missingFields ) {
		$data ['error'] = true;
		$data ['message'] = "The following field(s) are required: $missingFields";
		$newResponse = $response->withJson ( $data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// does not matter whether company code exists. 
	// if not, it will be created
	
	// copy from company info into $config and write out
	$config['companyInfo'][$cc] = array();
	foreach($companyInfo as $key => $value) {
		// skip the company code as that is our array higher level key
		if ($key == 'companyCode') continue;
		$config['companyInfo'][$cc][$key] = $value;
	}
//echo '<pre>', var_dump(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)), '</pre>';

	$config_out = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
	file_put_contents($configLoc, $config_out);
	
	// set up response data
	$response_data = array();
	$c_info = $config['companyInfo'][$cc];
	$tmp_array = array('companyCode' => $cc);
	foreach($c_info as $key => $info) {
		$tmp_array[$key] = $info;
	}
	$response_data[] = $tmp_array;

	$data = array (	'data' => $response_data );
	$newResponse = $response->withJson ( $data, 201, JSON_NUMERIC_CHECK );
} );

/*
$app->delete ( '/company/{id}', function (Request $request, Response $response) {
	// currently no plans for deleting a company through api
die();
} );
*/
