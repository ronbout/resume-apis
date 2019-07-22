<?php
// candidates_misc.php
// the api code for  Candidate related api's separated
// out from the main candidates.php to keep that file 
// size manageable.  

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get ( '/candidate_skills/skill_candidate_id/{skillId}', function (Request $request, Response $response) {
	// purpose of this api is to see if a candidate_skills entry exists for a given 
	// candidate id and skill id.  If so, it will return the id #, 
	// otherwise, error code 45002 (internal code for Record Not Found)
	// skillId will be part of the endpoint,  candidateid needs to be in the query
	$skill_id = $request->getAttribute ( 'skillId' );
	$data = array ();
	$query = $request->getQueryParams();

	$candidate_id = isset ($query['candidateId']) ? filter_var ( $query['candidateId'] ) : '';

	if (!$candidate_id) {
		$data ['error'] = true;
		$data ['message'] = 'Candidate Id (candidateId) query field is required.';
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
	
	$pdo_parms = array($candidate_id, $skill_id);
	$query = 'SELECT * FROM candidate_skills WHERE candidateId = ? AND skillId = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, $pdo_parms, 'Retrieving Candidate Skills', $errCode, true, false, true, false );
	if ($errCode) {
		return $response_data;
	}

	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );


$app->get ( '/candidate_skills/candidate_id/{candidateId}', function (Request $request, Response $response) {
	// purpose of this api is to see if a candidate_skills entry exists for a given 
	// candidate id and skill id.  If so, it will return the id #, 
	// otherwise, error code 45002 (internal code for Record Not Found)
	// skillId will be part of the endpoint,  candidateid needs to be in the query
	$candidate_id = $request->getAttribute ( 'candidateId' );
	$data = array ();

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}
	
	$pdo_parms = array($candidate_id);
	$query = 'SELECT * FROM candidate_skills WHERE candidateId = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, $pdo_parms, 'Retrieving Candidate Skills', $errCode, true, true, true, false );
	if ($errCode) {
		return $response_data;
	}

	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );