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
$app->get('/members', function (Request $request, Response $response) {
	$data = array();
	// social array is the list of possible social media logins
	// right now, we have google and github.  this will be stored in
	// the password field.  This will give better error messages when
	// the user logins using a different method than they registered.
	$social_array = array('google', 'github');

	// check for password being sent
	$getquery = $request->getQueryParams();
	if (!isset($getquery['password']) || !isset($getquery['email'])) {
		$data['error'] = true;
		$data['message'] = 'Password and email parameters are required.';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	$password = trim($getquery['password']);
	$email = $getquery['email'];

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	$query = 'select id, fullName, email, confirmFlag, password, securityLevel, candidateId from members
	where email = ?';

	$response_data = pdo_exec($request, $response, $db, $query, array($email), 'Retrieving Member', $errCode, true, false, true, false);
	if ($errCode) {
		return $response_data;
	}

	$response_data['fullName'] =  html_entity_decode($response_data['fullName'], ENT_QUOTES);
	$db_password = $response_data['password'];

	// check password if social login as they must match
	// i.e. google must have registered with google 
	if (in_array($password, $social_array) && $password != $db_password) {
		$data['error'] = true;
		if (in_array($db_password, $social_array)) {
			$data['message'] = "User registered with " . ucfirst($db_password) . " login.";
		} else {
			$data['message'] = "User registered by email and must login with email / password.";
		}
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	// check password if not social login
	if (!in_array($password, $social_array) && md5(trim($password)) != $db_password) {
		$data['error'] = true;
		if (in_array($db_password, $social_array)) {
			$data['message'] = "User registered with " . ucfirst($db_password) . " login.";
		} else {
			$data['message'] = "Incorrect password.";
		}
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	unset($response_data['password']);

	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

/**
 * create a new user
 */

$app->post('/members', function (Request $request, Response $response) {
	$post_data = $request->getParsedBody();
	$data = array();
	// password for 1 click logins are just the site that was used
	$social_array = array('google', 'github');

	$full_name = isset($post_data['name']) ? filter_var($post_data['name'], FILTER_SANITIZE_STRING) : '';
	$email = isset($post_data['email']) ? filter_var($post_data['email'], FILTER_SANITIZE_STRING) : '';
	$password = isset($post_data['password']) ? filter_var($post_data['password'], FILTER_SANITIZE_STRING) : '';
	// if social login, do not md5 the password
	// also, social logins are already confirmed
	$confirm_flag = 1;
	$confirm_value = null;
	$confirm_value_length = 20;
	if (!in_array($password, $social_array)) {
		$password = md5($password);
		$confirm_flag = 0;
		$confirm_value = "";
		for ($i = 0; $i < $confirm_value_length; $i++) {
			$confirm_value .= chr(rand(97, 122));
		}
	}

	// all are required
	if (!$password || !$full_name || !$email) {
		$data['error'] = true;
		$data['message'] = 'User name, password, and email are required.';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	$query = 'INSERT INTO members
							(fullName, email, confirmValue, confirmFlag, password) 
							VALUES  (?, ?, ?, ?, ?)';

	$insert_data = array($full_name, $email, $confirm_value, $confirm_flag, $password);

	$response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Member', $errCode, false, false, false);
	if ($errCode) {
		return $response_data;
	}

	$return_data = array(
		'id'				=> $db->lastInsertId(),
		'fullName'				=> $full_name,
		'email'						=> $email,
		'confirmFlag'			=> $confirm_flag,
		'securityLevel'		=> 1,
		'candidateId'			=> ''
	);
	$return_data = array('data' => $return_data);
	$newResponse = $response->withJson($return_data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});


/**
 * update the member, specifically the candidateId...for now
 */

$app->put('/members/{id}', function (Request $request, Response $response) {
	$member_id = $request->getAttribute('id');
	$post_data = $request->getParsedBody();
	$data = array();

	$table_cols = array(
		'candidateId',
	);

	// make sure that at least one field exists for updating
	// return val is array with <0> = sql and <1> = array for executing prepared statement
	$sql_cols = build_update_SQL_cols($post_data, $table_cols);
	$sql_update_cols = $sql_cols[0];
	$sql_array = $sql_cols[1];

	if (!$sql_update_cols) {
		$data['error'] = true;
		$data['message'] = 'At least one column is required.';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM members WHERE id = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($member_id), 'Retrieving Member', $errCode, true);
	if ($errCode) {
		return $response_data;
	}

	// have to build SQL based on which fields were passed in_array
	$query = 'UPDATE members SET ' . $sql_update_cols . ' WHERE id = ?';
	// add id to end of execute array
	$sql_array[] = $member_id;

	$response_data = pdo_exec($request, $response, $db, $query, $sql_array, 'Updating Members', $errCode, false, false, false);
	if ($errCode) {
		return $response_data;
	}

	// everything was fine, just return the member id and candidateId 
	$return_data = array('id' => $member_id, 'candidateId' => $post_data['candidateId']);

	// wrap it in data object
	$data = array(
		'data' => $return_data
	);
	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});
