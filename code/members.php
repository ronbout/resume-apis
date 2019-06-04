<?php
// candidates.php
// the api code for RESTful CRUD operations of the Candidates
// table and related.  This version will be done with my own
// SQL code.  Then, it will be refactored with an ORM

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

/**
 * get member info by email
 */
$app->get ( '/members', function (Request $request, Response $response) {
	$data = array ();

	// check for password being sent
	$getquery = $request->getQueryParams();
	if (  !isset($getquery['password']) || !isset($getquery['email']) ) {
		$data['error'] = true;
		$data['message'] = 'Password and email parameters are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$password = $getquery['password'];
	$email = $getquery['email'];

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}

	$query = 'select id, fullName, email, confirmFlag, password, securityLevel, candidateId from members
	where email = ?' ;

	$response_data = pdo_exec( $request, $response, $db, $query, array($email), 'Retrieving Member', $errCode, true );
	if ($errCode) {
		return $response_data;
	}
	
	$response_data['fullName'] =  html_entity_decode($response_data['fullName'], ENT_QUOTES);

	// check password if not social login
	// social logins are already password protected
	if ($password != 'social' && md5(trim($password)) != $response_data['password']) {
		$data ['error'] = true;
		$data ['message'] = 'Incorrect password ';
		$newResponse = $response->withJson ( $data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	unset($response_data['password']);
	
	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );

/**
 * create a new user
 */
/*
$app->post ( '/members', function (Request $request, Response $response) {
	$data = $request->getParsedBody();

	$user_name = isset($data['userName']) ? filter_var($data['userName'], FILTER_SANITIZE_STRING) : '' ;
	$first_name = isset($data['firstName']) ? filter_var($data['firstName'], FILTER_SANITIZE_STRING) : '' ;
	$last_name = isset($data['lastName']) ? filter_var($data['lastName'], FILTER_SANITIZE_STRING) : '' ;
	$email = isset($data['email']) ? filter_var($data['email'], FILTER_SANITIZE_STRING) : '' ;
	$api = isset($data['apiKey']) ? filter_var($data['apiKey'], FILTER_SANITIZE_STRING) : '' ;
	$password = isset($data['password']) ? md5($data['password']) : '';

	// all but password are required.  Password is not
	// because it could be a social login, which handles
	// uses its own password
	if (!$user_name || !$first_name || !$last_name || !$email ) {
		$data['error'] = true;
		$data['message'] = 'Username, firstname, lastname, and email are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode, $api );
	if ($errCode) {
		return $db;
	}

	$query = 'INSERT INTO member 
							(first_name, last_name, user_name, email, confirm_flag, password) 
							VALUES  (?, ?, ?, ?, ?, ?)';

	$insert_data = array($first_name, $last_name, $user_name, $email, 1, $password);							

	$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Member', $errCode, false, false, false );
	if ($errCode) {
		return $response_data;
	}

	$return_data = array(
		'memberid'				=> $db->lastInsertId(),
		'username'				=> $user_name,
		'firstName'				=> $first_name,
		'lastName'				=> $last_name,
		'email'						=> $email,
		'totalCalsToday'	=> 0
	);
	$return_data = array('data' => $return_data);
	$newResponse = $response->withJson($return_data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
});*/
