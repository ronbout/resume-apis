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
	$response_data = pdo_exec( $request, $response, $db, $query, array(), 'Retrieving Companies', $errCode, false, true, true, false );
	if ($errCode) {
		return $db;
	}

	/**
	 * must clean up company vw person info as not all of that is required
	 * also, want to set it as a separate object within the company return,
	 * which is impossible to do within SQL
	 * I do not want to change company_vw, because we might need all this info 
	 * in other settings, such as individual company api's.
	 */
	$personFields = array('personId', 'personFormattedName', 'personGivenName', 'personMiddleName', 'personFamilyName', 'personAffix', 'personAddr1',
	'personAddr2', 'personMunicipality', 'personRegion', 'personPostalCode', 'personCountryCode', 'personEmail1', 'personEmail2', 'personHomePhone',
	'personMobilePhone', 'personWorkPhone', 'personWebsite');

	// personFields are all fields returned by company, personObjectFields are only the ones to be returned in a contactPerson object
	$personObjectFields = array('personId', 'personFormattedName', 'personGivenName', 'personFamilyName', 'personEmail1',	'personMobilePhone', 'personWorkPhone');
	$personNewFields = array('id', 'formattedName', 'givenName', 'familyName', 'email1', 'mobilePhone', 'workPhone');
	foreach ($response_data as &$resp) {
		$contactPerson = array();
		foreach ($personObjectFields as $key => $fld) {
			$contactPerson[$personNewFields[$key]] = isset($resp[$fld]) ? $resp[$fld] : null;
		}

		foreach ($personFields as $key => $fld) {
			unset($resp[$fld]);
		}

		$resp['contactPerson'] = $contactPerson;
	}

	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );

$app->get ( '/companies/search', function (Request $request, Response $response) {
	$data = array ();
	$query = $request->getQueryParams();
	
	$name = isset ($query['name']) ? filter_var ( $query['name'] ) : '';
	$email = isset ($query['email']) ? filter_var ( $query['email'] ) : '';

	if (!$name && !$email) {
		$data ['error'] = true;
		$data ['message'] = 'Search field is required.';
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

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

	// beause the email should be unique, test only that if it is present, otherwise test name
	$where_clause = ' WHERE ';
	if ($email) {
		$where_clause .= 'email = :email ';
		$sql_parms = array('email' => $email);
	} else {
		$where_clause .= 'jws_score(:name, name) > 0.8 ';
		$sql_parms = array('name' => $name);
	}

	$query = 'SELECT * FROM company_vw ' . $where_clause . $limit_clause;

	$response_data = pdo_exec( $request, $response, $db, $query, $sql_parms, 'Searching Companies', $errCode, false, true, true, false );
	if ($errCode) {
		return $db;
	}

	/**
	 * must clean up company vw person info as not all of that is required
	 * also, want to set it as a separate object within the company return,
	 * which is impossible to do within SQL
	 * I do not want to change company_vw, because we might need all this info 
	 * in other settings, such as individual company api's.
	 */
	
	$personFields = array('personId', 'personFormattedName', 'personGivenName', 'personMiddleName', 'personFamilyName', 'personAffix', 'personAddr1',
	'personAddr2', 'personMunicipality', 'personRegion', 'personPostalCode', 'personCountryCode', 'personEmail1', 'personEmail2', 'personHomePhone',
	'personMobilePhone', 'personWorkPhone', 'personWebsite');

	// personFields are all fields returned by company, personObjectFields are only the ones to be returned in a contactPerson object
	// the view requires "person" prefix to distinguish from company fields, but want to rename for api, so personNewFields
	$personObjectFields = array('personId', 'personFormattedName', 'personGivenName', 'personFamilyName', 'personEmail1',	'personMobilePhone', 'personWorkPhone');
	$personNewFields = array('id', 'formattedName', 'givenName', 'familyName', 'email1', 'mobilePhone', 'workPhone');
	foreach ($response_data as &$resp) {
		$contactPerson = array();
		foreach ($personObjectFields as $key => $fld) {
			$contactPerson[$personNewFields[$key]] = isset($resp[$fld]) ? $resp[$fld] : null;
		}

		foreach ($personFields as $key => $fld) {
			unset($resp[$fld]);
		}

		$resp['contactPerson'] = $contactPerson;
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
	
	$query = 'SELECT * FROM company WHERE id = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate', $errCode, true, false, true, false );
	if ($errCode) {
		return $db;
	}
	
	// now to pull out Contact Person info if it exists
	if ($response_data['contactPersonId'] !== null) {
		// read from person view with phone numbers
	
		$query = 'SELECT * FROM person_with_phonetypes_vw WHERE id = ?';
		$person_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Contact Person', $errCode, true, false, true, false );
		if ($errCode) {
			return $db;
		}
		$contactPerson = $person_data;
	} else {
		$contactPerson = null;
	}

	unset($response_data['contactPersonId']);
	$response_data['contactPerson'] = $contactPerson;

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
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
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