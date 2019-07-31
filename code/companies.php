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

	// personObjectFields are only the ones to be returned in a contactPerson object
	$personObjectFields = array('personId', 'personFormattedName', 'personGivenName', 'personFamilyName', 'personEmail1',	'personMobilePhone', 'personWorkPhone');
	foreach ($response_data as &$resp) {
		$resp = create_lower_object( $resp, 'person', 'contactPerson', $personObjectFields);
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

	// personObjectFields are only the ones to be returned in a contactPerson object
	$personObjectFields = array('personId', 'personFormattedName', 'personGivenName', 'personFamilyName', 'personEmail1',	'personMobilePhone', 'personWorkPhone');
	foreach ($response_data as &$resp) {
		$resp = create_lower_object( $resp, 'person', 'contactPerson', $personObjectFields);
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

	$name = isset ( $post_data ['name'] ) ? filter_var ( $post_data ['name'], FILTER_SANITIZE_STRING ) : '';
	$description = isset ( $post_data ['description'] ) ? filter_var ( $post_data ['description'], FILTER_SANITIZE_STRING ) : null;	
	$company_phone = isset ( $post_data ['companyPhone'] ) ? filter_var ( $post_data ['companyPhone'], FILTER_SANITIZE_STRING ) : null;
	$address_line1 = isset ( $post_data ['addressLine1'] ) ? filter_var ( $post_data ['addressLine1'], FILTER_SANITIZE_STRING ) : null;
	$address_line2 = isset ( $post_data ['addressLine2'] ) ? filter_var ( $post_data ['addressLine2'], FILTER_SANITIZE_STRING ) : null;
	$municipality = isset ( $post_data ['municipality'] ) ? filter_var ( $post_data ['municipality'], FILTER_SANITIZE_STRING ) : null;
	$region = isset ( $post_data ['region'] ) ? filter_var ( $post_data ['region'], FILTER_SANITIZE_STRING ) : null;
	$postal_code = isset ( $post_data ['postalCode'] ) ? filter_var ( $post_data ['postalCode'], FILTER_SANITIZE_STRING ) : null;
	$country_code = isset ( $post_data ['countryCode'] ) ? filter_var ( $post_data ['countryCode'], FILTER_SANITIZE_STRING ) : null;
	$email = isset ( $post_data ['email'] ) ? filter_var ( $post_data ['email'], FILTER_SANITIZE_STRING ) : null;
	$website = isset ( $post_data ['website'] ) ? filter_var ( $post_data ['website'], FILTER_SANITIZE_STRING ) : null;

	$contact_person_id = isset ( $post_data ['contactPersonId'] ) ? filter_var ( $post_data ['contactPersonId'], FILTER_SANITIZE_STRING ) : null;
	
	if ( !$name ) {
		$data ['error'] = true;
		$data ['message'] = 'Company name is required.';
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
	
	// create person item and get insert id
	$query = 'INSERT INTO company
							(name, description, companyPhone, contactPersonId, addressLine1, addressLine2, municipality, 
							  region, postalCode, countryCode, email, website)
							VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

	$insert_data = array($name, $description, $company_phone, $contact_person_id, $address_line1, $address_line2, 
												$municipality, $region,	$postal_code, $country_code, $email, $website );

	$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Company', $errCode, false, false, false );
	if ($errCode) {
		return $response_data;
	}


	// get new company id to send back
	if (! $company_id = $db->lastInsertId() ) {
		// unknown insert error - should NOT get here
		$return_data ['error'] = true;
		$return_data ['errorCode'] = 45002; // unknown error
		$return_data ['message'] = 'Unknown error creating Person';
		$newResponse = $response->withJson ( $return_data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// everything was fine. return success and the full data object
	$data = $post_data;
	$data ['id'] = $company_id;
	// wrap it in data object
	$data = array (
			'data' => $data 
	);
	$newResponse = $response->withJson ( $data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
} );