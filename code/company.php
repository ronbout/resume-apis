<?php
// company.php
// the api code for RESTful CRUD operations of the Company table

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get ( '/companies', function (Request $request, Response $response) {
	$data = array ();

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}

	// check for offset and limit and add to Select
	$q_vars = array_change_key_case($request->getQueryParams(), CASE_LOWER);
	$limit_clause = '';
	if (isset($q_vars['limit']) && is_numeric($q_vars['limit'])) {
		$limit_clause .= ' LIMIT ' . $q_vars['limit'] . ' ';
	}
	if (isset($q_vars['offset']) && is_numeric($q_vars['offset'])) {
		$limit_clause .= ' OFFSET ' . $q_vars['offset'] . ' ';
	}

	$query = 'SELECT * FROM company_vw ' . $limit_clause;
	$response_data = pdo_exec( $request, $response, $db, $query, array(), 'Retrieving Companies', $errCode, false, true );
	if ($errCode) {
		return $db;
	}

	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );

$app->get ( '/companies/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute ( 'id' );
	$data = array ();

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}
	
	$query = 'SELECT * FROM company_vw WHERE id = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate', $errCode, true );
	if ($errCode) {
		return $db;
	}
	
	// TODO:  have to pull out all the person fields to create a separate object inside the larger object
	

	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );

$app->post ( '/companies', function (Request $request, Response $response) {
	$post_data = $request->getParsedBody ();
	$data = array ();

	$name = isset ( $post_data ['name'] ) ? filter_var ( $post_data ['name'], FILTER_SANITIZE_STRING ) : null;
	$description = isset ( $post_data ['description'] ) ? filter_var ( $post_data ['description'], FILTER_SANITIZE_STRING ) : null;
	$url = isset ( $post_data ['url'] ) ? filter_var ( $post_data ['url'], FILTER_SANITIZE_STRING ) : null;

	if (! $name) {
		$data ['error'] = true;
		$data ['message'] = 'Name is required.';
		$newResponse = $response->withJson ( $data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}

/* 	// need to make sure that skill name does not already exist as it must be unique
	$stmt = $db->prepare ( 'SELECT * from skill WHERE name = ?' );

	if (! $stmt->execute ( array ($name) )) {
		$data ['error'] = true;
		$data ['message'] = 'Database SQL Error Accessing Skill table: ' . $stmt->errorCode () . ' - ' . $stmt->errorInfo () [2];
		$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	if (($stmt->rowCount())) {
		$data ['error'] = true;
		$data ['message'] = "Skill $name already exists";
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$stmt = $db->prepare ( 'INSERT INTO skill (name, description, url) VALUES ( ?,?,? )' ); 

	if (! $stmt->execute ( array (
			$name,
			$description,
			$url
	) ) || ($stmt->rowCount () == 0)) {
		$data ['error'] = true;
		$data ['message'] = 'Database SQL Error Inserting Skill: ' . $stmt->errorCode () . ' - ' . $stmt->errorInfo () [2];
		$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}*/
	// everything was fine. return success
	$data ['id'] = $db->lastInsertId ();
	$data ['name'] = $name;
	$data ['description'] = $description;
	$data ['url'] = $url;
	// wrap it in data object
	$data = array (
			'data' => $data
	);
	$newResponse = $response->withJson ( $data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
} );