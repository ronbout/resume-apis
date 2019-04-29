<?php
// persons.php
// the api code for Person table.  This will primarily be 
// serach function for testing as the api's will be shifted
// to a Multivalue NoSQL engine.

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get ( '/persons/search', function (Request $request, Response $response) {
	$data = array ();
	$query = $request->getQueryParams();
	
	$name = isset ($query['name']) ? filter_var ( $query['name'] ) : '';
	$email = isset ($query['email']) ? filter_var ( $query['email'] ) : '';
	$phone = isset ($query['phone']) ? filter_var ( $query['phone'] ) : '';

	if (!$name && !$email && !$phone) {
		$data ['error'] = true;
		$data ['message'] = 'Search field is required.';
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

	$query = '';
	$name_query =	"SELECT * 
									FROM person
									WHERE jws_score(:name, CONCAT_WS(' ', givenName, familyName)) > 0.8 
									OR jws_score(:name, CONCAT_WS(' ', familyName, givenName)) > 0.8 ";
	$email_query = "SELECT *
									FROM person
									WHERE email1 = :email
									OR email2 = :email";
	$phone_query = "SELECT p.* 
									FROM person p, personphone pp
									WHERE pp.phoneNumber = :phone
									AND pp.personId = p.id";
	// 6 possible combos of search criteria
	$sql_parms = array();
	if ($name) {
		$query = $name_query;
		$sql_parms[':name'] = $name;
	}
	if ($email) {
		$query = $query ? $query . ' UNION ' . $email_query : $email_query;
		$sql_parms[':email'] = $email;
	}
	if ($phone) {
		$query = $query ? $query . ' UNION ' . $phone_query : $phone_query;
		$sql_parms[':phone'] = $phone;
	}
				
/* 	echo $query;
	var_dump($sql_parms);
	die(); */
	$response_data = pdo_exec( $request, $response, $db, $query, $sql_parms, 'Searching Person by Name', $errCode, false, true, true, false );

	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
});