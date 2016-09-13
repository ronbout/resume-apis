<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/webcontacts', function (Request $request, Response $response) {
	$data = array();
	
	// have to get company code and api key..or error
	$query = $request->getQueryParams();
	if ( !isset($query['api_cc']) || !isset($query['api_key']) ) {
		$data['error'] = true;
		$data['message'] = 'Company Code and API Key are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	
	// connect to database
 	$errCode = '';
	if (!($db = pdoConnect($query['api_cc'], $errCode, $query['api_key']))) {
		switch($errCode) {
			case -2:
				$data['error'] = true;
				$data['message'] = 'Invalid API Key.';
				$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK );
				return $newResponse;
				break;
			case -1: 
			default:
				$data['error'] = true;
				$data['message'] = 'Database Connection Error: ' . $errCode;
				$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
				return $newResponse;
		}
	}	

	$query = "SELECT * from webcontact";

	if (!$result = $db->query($query)) {
		$data['error'] = true;
		$data['message'] = 'Database SQL Error Retrieving webcontact: ' . $result->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$response_data = array();
	while (($info = $result->fetch(PDO::FETCH_ASSOC))) {
		$response_data[] = $info;
	}

	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
});

$app->post('/webcontacts', function (Request $request, Response $response) {
	$post_data = $request->getParsedBody();
	$data = array();
	
	// have to get company code and api key..or error
	$query = $request->getQueryParams();
	if ( !isset($query['api_cc']) || !isset($query['api_key']) ) {
		$data['error'] = true;
		$data['message'] = 'Company Code and API Key are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$name = isset($post_data['name']) ? filter_var($post_data['name'], FILTER_SANITIZE_STRING) : null ;
	$email = isset($post_data['email']) ? filter_var($post_data['email'], FILTER_SANITIZE_STRING) : null ;
	$phone = isset($post_data['phone']) ? filter_var($post_data['phone'], FILTER_SANITIZE_STRING) : null ;
	$message = isset($post_data['message']) ? filter_var($post_data['message'], FILTER_SANITIZE_STRING) : null ;
	$resumelink = isset($post_data['resumelink']) ? filter_var($post_data['resumelink'], FILTER_SANITIZE_STRING) : null ;

	if (!$name || !$email) {
		$data['error'] = true;
		$data['message'] = 'Name and Email are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// connect to database
	$errCode = '';
	if (!($db = pdoConnect($query['api_cc'], $errCode, $query['api_key'])))	{
		switch($errCode) {
			case -2:
				$data['error'] = true;
				$data['message'] = 'Invalid API Key.';
				$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK );
				return $newResponse;
				break;
			case -1: 
			default:
				$data['error'] = true;
				$data['message'] = 'Database Connection Error: ' . $errCode;
				$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
				return $newResponse;
		}
	}
	$stmt = $db->prepare('INSERT INTO webcontact (Name, Email, Phone, Message, ResumeLink) VALUES ( ?,?,?,?,? )');

	if (!$stmt->execute(array($name, $email, $phone, $message, $resumelink)) || ($stmt->rowCount() == 0) ) {
		$data['error'] = true;
		$data['message'] = 'Database SQL Error Inserting WebContact: ' . $stmt->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	// everything was fine.  return success
	$data['id'] = $db->lastInsertId();
	$data['name'] = $name;
	$date['email'] = $email;
	$data['phone'] = $phone;
	$data['message'] = $message;
	$data['resumelink'] = $resumelink;
	// wrap it in data object
	$data = array('data' => $data);
	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
});

$app->put('/webcontacts/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute('id');
	$post_data = $request->getParsedBody();
	$data = array();
	$table_cols = array('name', 'email', 'phone', 'message', 'resumelink');
	
	// have to get company code and api key..or error
	$query = $request->getQueryParams();
	if ( !isset($query['api_cc']) || !isset($query['api_key']) ) {
		$data['error'] = true;
		$data['message'] = 'Company Code and API Key are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// make sure that at least one field exists for updating
	// return val is array with <0> = sql and <1> = array for executing prepared statement
	$sql_cols = build_update_SQL_cols($post_data, $table_cols);
	$sql_update_cols = $sql_cols[0];
	$sql_array = $sql_cols[1];

	if (!$id || !$sql_update_cols) {
		$data['error'] = true;
		$data['message'] = 'Id and at least one column are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// connect to database
	$errCode = '';
	if (!($db = pdoConnect($query['api_cc'], $errCode, $query['api_key'])))	{
		switch($errCode) {
			case -2:
				$data['error'] = true;
				$data['message'] = 'Invalid API Key.';
				$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK );
				return $newResponse;
				break;
			case -1: 
			default:
				$data['error'] = true;
				$data['message'] = 'Database Connection Error: ' . $errCode;
				$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
				return $newResponse;
		}
	}

	// have to build SQL based on which fields were passed in_array
	$sql = 'UPDATE webcontact SET ' . $sql_cols[0] . ' WHERE id = ?';

	$stmt = $db->prepare($sql);
	// add id to end of execute array
	$sql_array[] = $id;

	if (!$stmt->execute($sql_array) || ($stmt->rowCount() == 0) ) {
		$data['error'] = true;
		$data['message'] = 'Unable to update WebContact: ' . $stmt->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	// everything was fine.  return success
	// let's get the full record and return it, just in case...may remove later
	$stmt = $db->prepare('SELECT * from webcontact WHERE Id = ?');

	if (!$stmt->execute(array($id)) ) {
		// had trouble retrieving full record so just return post data
		$data = array('data' => $post_data);
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$response_data = $stmt->fetch(PDO::FETCH_ASSOC);

	// wrap it in data object
	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
	return $newResponse;
});


$app->delete('/webcontacts/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute('id');
	$data = array();
	
	// have to get company code and api key..or error
	$query = $request->getQueryParams();
	if ( !isset($query['api_cc']) || !isset($query['api_key']) ) {
		$data['error'] = true;
		$data['message'] = 'Company Code and API Key are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	if (!$id) {
		$data['error'] = true;
		$data['message'] = 'Id is required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// connect to database
	$errCode = '';
	if (!($db = pdoConnect($query['api_cc'], $errCode, $query['api_key'])))	{
		switch($errCode) {
			case -2:
				$data['error'] = true;
				$data['message'] = 'Invalid API Key.';
				$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK );
				return $newResponse;
				break;
			case -1: 
			default:
				$data['error'] = true;
				$data['message'] = 'Database Connection Error: ' . $errCode;
				$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
				return $newResponse;
		}
	}
	$stmt = $db->prepare('DELETE FROM webcontact WHERE Id = ?');

	if (!$stmt->execute(array($id)) || ($stmt->rowCount() == 0) ) {
		$data['error'] = true;
		$data['message'] = 'Unable to delete WebContact '. $id .' : ' . $stmt->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	// everything was fine.  return success
	$data['error'] = false;
	$data['message'] = 'WebContact successfully deleted';
	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
});

$app->get('/webcontacts/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute('id');
	$data = array();
	
	// have to get company code and api key..or error
	$query = $request->getQueryParams();
	if ( !isset($query['api_cc']) || !isset($query['api_key']) ) {
		$data['error'] = true;
		$data['message'] = 'Company Code and API Key are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

 	$errCode = '';
	if (!($db = pdoConnect($query['api_cc'], $errCode, $query['api_key']))) {
		switch($errCode) {
			case -2:
				$data['error'] = true;
				$data['message'] = 'Invalid API Key.';
				$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK );
				return $newResponse;
				break;
			case -1: 
			default:
				$data['error'] = true;
				$data['message'] = 'Database Connection Error: ' . $errCode;
				$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
				return $newResponse;
		}
	}	

	$stmt = $db->prepare('SELECT * from webcontact WHERE Id = ?');

	if (!$stmt->execute(array($id)) ) {
		$data['error'] = true;
		$data['message'] = 'Database SQL Error Retrieving webcontact: ' . $result->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	if ( ($stmt->rowCount() == 0)) {
		$data['error'] = false;
		$data['message'] = 'No records Found';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$response_data = $stmt->fetch(PDO::FETCH_ASSOC);

	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
});
