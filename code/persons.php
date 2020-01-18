<?php

// persons.php
// the api code for Person table.  This will primarily be
// serach function for testing as the api's will be shifted
// to a Multivalue NoSQL engine.

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/persons', function (Request $request, Response $response) {
    $data = [];

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    // check for offset and limit and add to Select
    $q_vars = array_change_key_case($request->getQueryParams(), CASE_LOWER);
    $limit_clause = '';
    if (isset($q_vars['limit']) && is_numeric($q_vars['limit'])) {
        $limit_clause .= ' LIMIT '.$q_vars['limit'].' ';
    }
    if (isset($q_vars['offset']) && is_numeric($q_vars['offset'])) {
        $limit_clause .= ' OFFSET '.$q_vars['offset'].' ';
    }

    $query = 'SELECT * FROM person_with_phonetypes_vw '.$limit_clause;
    $response_data = pdo_exec($request, $response, $db, $query, [], 'Retrieving Persons', $errCode, false, true, true, false);
    if ($errCode) {
        return $response_data;
    }

    $data = ['data' => $response_data];
    $newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->get('/persons/search', function (Request $request, Response $response) {
    $data = [];
    $query = $request->getQueryParams();

    $name = isset($query['name']) ? filter_var($query['name']) : '';
    $email = isset($query['email']) ? filter_var($query['email']) : '';
    $phone = isset($query['phone']) ? filter_var($query['phone']) : '';

    // if no search, just return all
    // if (!$name && !$email && !$phone) {
    // 	$data ['error'] = true;
    // 	$data ['message'] = 'Search field is required.';
    // 	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
    // 	return $newResponse;
    // }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    $query = '';
    $name_query = "SELECT * 
									FROM person_with_phonetypes_vw
									WHERE jws_score(:name, CONCAT_WS(' ', givenName, familyName)) > 0.8 
									OR jws_score(:name, CONCAT_WS(' ', familyName, givenName)) > 0.8 
									OR familyName LIKE :likename
									OR givenName LIKE :likename";
    $email_query = 'SELECT *
									FROM person_with_phonetypes_vw
									WHERE email1 = :email
									OR email2 = :email';
    $phone_query = 'SELECT * 
									FROM person_with_phonetypes_vw
									WHERE homePhone = :phone
									OR mobilePhone = :phone
									OR workPhone = :phone';
    // 6 possible combos of search criteria plus no criteria
    $sql_parms = [];
    if ($name) {
        $query = $name_query;
        $sql_parms[':name'] = $name;
        $sql_parms[':likename'] = "%{$name}%";
    }
    if ($email) {
        $query = $query ? $query.' UNION '.$email_query : $email_query;
        $sql_parms[':email'] = $email;
    }
    if ($phone) {
        $query = $query ? $query.' UNION '.$phone_query : $phone_query;
        $sql_parms[':phone'] = $phone;
    }
    if (!$name && !$email && !$phone) {
        $query = 'SELECT * FROM person_with_phonetypes_vw';
    }

    $response_data = pdo_exec($request, $response, $db, $query, $sql_parms, 'Searching Person by Name', $errCode, false, true, true, false);

    $data = ['data' => $response_data];
    $newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->get('/persons/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $data = [];

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    $query = 'SELECT * FROM person_with_phonetypes_vw WHERE id = ?';
    $response_data = pdo_exec($request, $response, $db, $query, [$id], 'Retrieving Person', $errCode, true, false, true, false);
    if ($errCode) {
        return $response_data;
    }

    $data = ['data' => $response_data];
    $newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->post('/persons', function (Request $request, Response $response) {
    $post_data = $request->getParsedBody();
    $data = [];

    $given_name = isset($post_data['givenName']) ? filter_var($post_data['givenName'], FILTER_SANITIZE_STRING) : '';
    $family_name = isset($post_data['familyName']) ? filter_var($post_data['familyName'], FILTER_SANITIZE_STRING) : '';
    $address_line1 = isset($post_data['addressLine1']) ? filter_var($post_data['addressLine1'], FILTER_SANITIZE_STRING) : '';
    $address_line2 = isset($post_data['addressLine2']) ? filter_var($post_data['addressLine2'], FILTER_SANITIZE_STRING) : '';
    $municipality = isset($post_data['municipality']) ? filter_var($post_data['municipality'], FILTER_SANITIZE_STRING) : '';
    $region = isset($post_data['region']) ? filter_var($post_data['region'], FILTER_SANITIZE_STRING) : '';
    $postal_code = isset($post_data['postalCode']) ? filter_var($post_data['postalCode'], FILTER_SANITIZE_STRING) : '';
    $country_code = isset($post_data['countryCode']) ? filter_var($post_data['countryCode'], FILTER_SANITIZE_STRING) : '';
    $email1 = isset($post_data['email1']) ? filter_var($post_data['email1'], FILTER_SANITIZE_STRING) : '';
    $website = isset($post_data['website']) ? filter_var($post_data['website'], FILTER_SANITIZE_STRING) : '';
    $work_phone = isset($post_data['workPhone']) ? filter_var($post_data['workPhone'], FILTER_SANITIZE_STRING) : '';
    $mobile_phone = isset($post_data['mobilePhone']) ? filter_var($post_data['mobilePhone'], FILTER_SANITIZE_STRING) : '';

    if (!$given_name || !$family_name) {
        $data['error'] = true;
        $data['message'] = 'First and Last Names are required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    // create person item and get insert id
    $query = 'INSERT INTO person
							(givenName, familyName, addressLine1, addressLine2, municipality, region, postalCode,
							countryCode, email1, website)
							VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $insert_data = [
        $given_name, $family_name, $address_line1, $address_line2, $municipality, $region,
        $postal_code, $country_code, $email1, $website,
    ];

    $response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Person', $errCode, false, false, false);
    if ($errCode) {
        return $response_data;
    }

    // get new person id to use for phone inserts
    if (!$person_id = $db->lastInsertId()) {
        // unknown insert error - should NOT get here
        $return_data['error'] = true;
        $return_data['errorCode'] = 45002; // unknown error
        $return_data['message'] = 'Unknown error creating Person';

        return $response->withJson($return_data, 500, JSON_NUMERIC_CHECK);
    }

    // add phones if they exist
    if ($mobile_phone) {
        $resp = update_person_phone($request, $response, $db, $errCode, 'Mobile', $mobile_phone, $person_id);
        if ($errCode) {
            return $resp;
        }
    }

    if ($work_phone) {
        $resp = update_person_phone($request, $response, $db, $errCode, 'Work', $work_phone, $person_id);
        if ($errCode) {
            return $resp;
        }
    }

    // everything was fine. return success and the4 full data object
    $data['id'] = $person_id;
    $data['formattedName'] = $given_name.' '.$family_name;
    $data['givenName'] = $given_name;
    $data['middleName'] = '';
    $data['familyName'] = $family_name;
    $data['affix'] = '';
    $data['addressLine1'] = $address_line1;
    $data['addressLine2'] = $address_line2;
    $data['municipality'] = $municipality;
    $data['region'] = $region;
    $data['postalCode'] = $postal_code;
    $data['countryCode'] = $country_code;
    $data['email1'] = $email1;
    $data['email2'] = '';
    $data['website'] = $website;
    $data['homePhone'] = '';
    $data['mobilePhone'] = $mobile_phone;
    $data['workPhone'] = $work_phone;
    // wrap it in data object
    $data = [
        'data' => $data,
    ];

    return $response->withJson($data, 201, JSON_NUMERIC_CHECK);
});

$app->put('/persons/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $post_data = $request->getParsedBody();
    $data = [];

    $table_cols = [
        'givenName',
        'middleName',
        'familyName',
        'affix',
        'addressLine1',
        'addressLine2',
        'municipality',
        'region',
        'postalCode',
        'countryCode',
        'email1',
        'email2',
        'website',
    ];

    $home_phone = isset($post_data['homePhone']) ? filter_var($post_data['homePhone'], FILTER_SANITIZE_STRING) : '';
    $work_phone = isset($post_data['workPhone']) ? filter_var($post_data['workPhone'], FILTER_SANITIZE_STRING) : '';
    $mobile_phone = isset($post_data['mobilePhone']) ? filter_var($post_data['mobilePhone'], FILTER_SANITIZE_STRING) : '';

    // make sure that at least one field exists for updating
    // return val is array with <0> = sql and <1> = array for executing prepared statement
    $sql_cols = build_update_SQL_cols($post_data, $table_cols);
    $sql_update_cols = $sql_cols[0];
    $sql_array = $sql_cols[1];

    if (!$sql_update_cols) {
        $data['error'] = true;
        $data['message'] = 'At least one column is required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    // need to make sure that this record id exists to update
    $stmt = $db->prepare('SELECT * from person WHERE id = ?');

    if (!$stmt->execute([$id])) {
        $data['error'] = true;
        $data['message'] = 'Database SQL Error Retrieving skill: '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }

    if ((0 == $stmt->rowCount())) {
        $data['error'] = false;
        $data['message'] = "Person {$id} not found in the database";

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // have to build SQL based on which fields were passed in_array
    $query = 'UPDATE person SET '.$sql_update_cols.' WHERE id = ?';
    // add id to end of execute array
    $sql_array[] = $id;

    $response_data = pdo_exec($request, $response, $db, $query, $sql_array, 'Updating Person', $errCode, false, false, false);
    if ($errCode) {
        return $response_data;
    }

    // update phones if they exist
    // in case phones were deleted, just delete all for this person
    $query = 'DELETE FROM personphone 
						WHERE personId = ?';

    $response_data = pdo_exec($request, $response, $db, $query, [$id], 'Deleting Person Phones', $errCode, false, false, false);
    if ($errCode) {
        return $response_data;
    }

    if ($home_phone) {
        $resp = update_person_phone($request, $response, $db, $errCode, 'Home', $home_phone, $id);
        if ($errCode) {
            return $resp;
        }
    }

    if ($mobile_phone) {
        $resp = update_person_phone($request, $response, $db, $errCode, 'Mobile', $mobile_phone, $id);
        if ($errCode) {
            return $resp;
        }
    }

    if ($work_phone) {
        $resp = update_person_phone($request, $response, $db, $errCode, 'Work', $work_phone, $id);
        if ($errCode) {
            return $resp;
        }
    }

    // everything was fine. return success
    // let's get the full record and return it, just in case...may remove later
    $query = 'SELECT * FROM person_with_phonetypes_vw WHERE id = ?';
    $response_data = pdo_exec($request, $response, $db, $query, [$id], 'Retrieving Candidate', $errCode, true, false, true, false);
    if ($errCode) {
        return $response_data;
    }

    // wrap it in data object
    $data = [
        'data' => $response_data,
    ];

    return $response->withJson($data, 201, JSON_NUMERIC_CHECK);
});

function update_person_phone($request, $response, $db, &$errCode, $phone_type, $phone_number, $person_id)
{
    $query = 'INSERT INTO personphone
							(personId, phoneType, phoneNumber)
							VALUES ( ?, ?, ?)';

    $insert_data = [$person_id, $phone_type, $phone_number];

    $response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Person Phone', $errCode, false, false, false);

    return $response_data;
}
