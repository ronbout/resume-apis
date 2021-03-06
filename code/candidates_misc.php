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
	$candidate_id = $request->getAttribute ( 'candidateId' );
	$data = array ();

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}

	// get basic candidate info to return with skills
	$query = 'SELECT id, personId, personFormattedName FROM candidate_basic_vw WHERE id = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, array($candidate_id), 'Retrieving Candidate', $errCode, true, false, true, false );
	if ($errCode) {
		return $response_data;
	}

	$return_data = array(
		'id' => $response_data['id'],
		'person' => array('id' => $response_data['personId'], 'formattedName' => $response_data['personFormattedName'])
	);
	
	$pdo_parms = array($candidate_id);
	$query = 'SELECT cs.*, t.name AS resumeTechtagName, t.description AS resumeTechtagDescription,
				s.name AS skillName, s.description AS skillDescription
				FROM `candidate_skills` cs 
				LEFT JOIN techtag t ON t.id = cs.resumeTechtagId 
				JOIN skill s ON s.id = cs.skillId
				WHERE candidateId = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, $pdo_parms, 'Retrieving Candidate Skills', $errCode, true, true, true, false );
	if ($errCode) {
		return $response_data;
	}

	$return_data['skills'] = $response_data;

	$data = array ('data' => $return_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );


$app->put ( '/candidates/{id}/candidate_skills', function (Request $request, Response $response) {
	$cand_id = $request->getAttribute ( 'id' );
	$post_data = $request->getParsedBody ();
	$data = array ();
	
	if (! isset($post_data['skills']) || !is_array($post_data['skills'])) {
		$data ['error'] = true;
		$data ['message'] = 'An array of skills is required';
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$skills = $post_data['skills'];

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM candidate WHERE id = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, array($cand_id), 'Retrieving Candidate', $errCode, true);
	if ($errCode) {
		return $response_data;
	}

	foreach($skills as $skill) {
		$query = 'UPDATE candidate_skills
		SET resumeTechtagId = ?
		WHERE id = ?';


		$skill['resumeTechtagId'] = isset ( $skill['resumeTechtagId']) && $skill['resumeTechtagId'] ? $skill['resumeTechtagId'] : null;

		$insert_data = array( $skill['resumeTechtagId'], $skill['id']);

		$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Updating Candidate Skills', $errCode, false, false, false );
		if ($errCode) {
			return $response_data;
		}
	}

	// all went well, just return the post data as there is nothing to update
	$data = array('data' => $post_data);

	$newResponse = $response->withJson ( $data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
} );
