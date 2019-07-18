<?php
// candidates.php
// the api code for  CRUD operations of the Candidates
// table and related.  

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get ( '/candidates', function (Request $request, Response $response) {
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

	$query = 'select * from candidate_with_phonetypes_skills_vw  ' . $limit_clause;
	$response_data = pdo_exec( $request, $response, $db, $query, array(), 'Retrieving Companies', $errCode, false, true, true, false );
	if ($errCode) {
		return $response_data;
	}

	// convert pipe-delimited skills to arrays
	foreach ($response_data as &$resp) {
		$resp['jobSkillName'] = (array_key_exists('jobSkillName', $resp) && $resp['jobSkillName']) ? explode('|', $resp['jobSkillName']) : null;
		$resp['certSkillName'] = (array_key_exists('certSkillName', $resp) && $resp['certSkillName']) ? explode('|', $resp['certSkillName']) : null;
		$resp['edSkillName'] = (array_key_exists('edSkillName', $resp) && $resp['edSkillName']) ? explode('|', $resp['edSkillName']) : null;
	}
	
	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );


$app->get ( '/candidates/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute ( 'id' );
	$data = array ();

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}
	
	$query = 'SELECT * FROM candidate_basic_vw WHERE id = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate', $errCode, true, false, true, false );
	if ($errCode) {
		return $response_data;
	}

	// explode out the pipe | delimited skill lists.  Will still include the skills w/ each job, education, etc
	// but this gives the api consumer a quick listing w/o having to do a lot of work
	$response_data['jobSkillName'] = (array_key_exists('jobSkillName', $response_data) && $response_data['jobSkillName']) ? explode('|', $response_data['jobSkillName']) : null;
	$response_data['certSkillName'] = (array_key_exists('certSkillName', $response_data) && $response_data['certSkillName']) ? explode('|', $response_data['certSkillName']) : null;
	$response_data['edSkillName'] = (array_key_exists('edSkillName', $response_data) && $response_data['edSkillName']) ? explode('|', $response_data['edSkillName']) : null;

	$response_data = create_lower_object( $response_data, 'person');
	
	$response_data = create_lower_object( $response_data, 'agency');
	
	$query = 'SELECT id, highlight, sequence, skillIds, skillNames FROM candidate_highlights_skills_vw WHERE candidateId = ?';
	$highlights = process_highlights($request, $response, $db, $query, array($id), $errCode);
	if ($errCode) {
		return $highlights;
	}

	$response_data['candidateHighlights'] = $highlights ? $highlights : null;
	
	// get the jobs
	$query = 'SELECT * FROM candidate_jobs_vw WHERE candidateId = ?';
	$jobs_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate Jobs', $errCode, false, true, true, false );
	if ($errCode) {
		return $jobs_data;
	}
	
	if ($jobs_data) {
		// loop through each job and explode out the pipe | delimited skill lists.
		foreach ($jobs_data as &$job_data) {
				
			$job_data['skillIds'] = (array_key_exists('skillIds', $job_data) && $job_data['skillIds']) ? explode('|', $job_data['skillIds']) : null;
			$job_data['skillNames'] = (array_key_exists('skillNames', $job_data) && $job_data['skillNames']) ? explode('|', $job_data['skillNames']) : null;
			$job_data['skillPcts'] = (array_key_exists('skillPcts', $job_data) && $job_data['skillPcts']) ? explode('|', $job_data['skillPcts']) : null;
			$job_data['skillTags'] = (array_key_exists('skillTags', $job_data) && $job_data['skillTags']) ? explode('|', $job_data['skillTags']) : null;
			$job_data['skillTagNames'] = (array_key_exists('skillTagNames', $job_data) && $job_data['skillTagNames']) ? explode('|', $job_data['skillTagNames']) : null;


			/**
			 * 
			 * add skillTags and skillTagnames back in
			 * 
			 */

			if ($job_data['skillIds']) {
				$data_array = array($job_data['skillIds'], $job_data['skillNames'], $job_data['skillPcts'], $job_data['skillTags'], $job_data['skillTagNames']);
				$job_data['skills'] = create_obj_from_arrays($data_array, array('id', 'name', 'usePct', 'skillTag', 'skillTagName'));
			} else {
				$job_data['skills'] = array();
			}
			unset($job_data['skillIds']);
			unset($job_data['skillNames']);
			unset($job_data['skillPcts']);
			unset($job_data['skillTestedFlag']);
			unset($job_data['skillTestResults']);
			unset($job_data['skillTotalMonths']);
			unset($job_data['skillTags']);
			unset($job_data['skillTagNames']);

			// break out the contactPerson and company data into sub objects
			$job_data = create_lower_object( $job_data, 'contactPerson');
			$job_data = create_lower_object( $job_data, 'company');

			$query = 'SELECT id, highlight, sequence, includeInSummary, skillIds, skillNames FROM candidate_job_highlights_skills_vw WHERE jobId = ?';
			$highlights = process_highlights($request, $response, $db, $query, array($job_data['id']), $errCode);
			if ($errCode) {
				return $highlights;
			}
			$job_data['jobHighlights'] = $highlights ? $highlights : null;
		}
	}
	$response_data['experience'] = $jobs_data ? $jobs_data : null;
	
	// get education
	$query = 'SELECT * FROM candidate_education_vw WHERE candidateId = ?';
	$eds_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate Education', $errCode, false, true, true, false );
	if ($errCode) {
		return $eds_data;
	}
	
	if ($eds_data) {
		// loop through each education and explode out the pipe | delimited skill lists.
		foreach ($eds_data as &$ed_data) {

			$ed_data['skillIds'] = (array_key_exists('skillIds', $ed_data) && $ed_data['skillIds']) ? explode('|', $ed_data['skillIds']) : null;
			$ed_data['skillNames'] = (array_key_exists('skillNames', $ed_data) && $ed_data['skillNames']) ? explode('|', $ed_data['skillNames']) : null;
			$ed_data['skillPcts'] = (array_key_exists('skillPcts', $ed_data) && $ed_data['skillPcts']) ? explode('|', $ed_data['skillPcts']) : null;

			if ($ed_data['skillIds']) {
				$data_array = array($ed_data['skillIds'], $ed_data['skillNames'], $ed_data['skillPcts']);
				$ed_data['skills'] = create_obj_from_arrays($data_array, array('id', 'name', 'usePct'));
			} else {
				$ed_data['skills'] = array();
			}

			unset($ed_data['skillIds']);
			unset($ed_data['skillNames']);
			unset($ed_data['skillPcts']);
		}
	}
	$response_data['education'] = $eds_data ? $eds_data : null;
	
	// get certifications
	$query = 'SELECT * FROM candidate_certifications_vw WHERE candidateId = ?';
	$certs_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate Certifications', $errCode, false, true, true, false );
	if ($errCode) {
		return $certs_data;
	}
	
	if ($certs_data) {
		// loop through each certification and explode out the pipe | delimited skill lists.
		foreach ($certs_data as &$cert_data) {

			$cert_data['skillIds'] = (array_key_exists('skillIds', $cert_data) && $cert_data['skillIds']) ? explode('|', $cert_data['skillIds']) : null;
			$cert_data['skillNames'] = (array_key_exists('skillNames', $cert_data) && $cert_data['skillNames']) ? explode('|', $cert_data['skillNames']) : null;
			$cert_data['skillPcts'] = (array_key_exists('skillPcts', $cert_data) && $cert_data['skillPcts']) ? explode('|', $cert_data['skillPcts']) : null;

			if ($cert_data['skillIds']) {
				$data_array = array($cert_data['skillIds'], $cert_data['skillNames'], $cert_data['skillPcts']);
				$cert_data['skills'] = create_obj_from_arrays($data_array, array('id', 'name', 'usePct'));
			} else {
				$cert_data['skills'] = array();
			}

			unset($cert_data['skillIds']);
			unset($cert_data['skillNames']);
			unset($cert_data['skillPcts']);
		}
	}
	$response_data['certifications'] = $certs_data ? $certs_data : null;
	
	// read in social media
	$query = 'SELECT id, socialType, socialLink FROM candidatesocialmedia WHERE candidateId = ?';
	$social_media = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate Social Media', $errCode, false, true );
	if ($errCode) {
		return $social_media;
	}
	$response_data['socialMedia'] = $social_media ? $social_media : null;

	// var_dump($response_data);
	// die();

	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );


$app->post ( '/candidates', function (Request $request, Response $response) {
	$post_data = $request->getParsedBody ();
	$data = array ();

	$person_id = isset ( $post_data ['personId'] ) ? filter_var ( $post_data ['personId'], FILTER_SANITIZE_STRING ) : '';
	
	if ( !$person_id ) {
		$data ['error'] = true;
		$data ['message'] = 'Person Id is required.';
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
	$query = 'INSERT INTO candidate
							(personId)
							VALUES ( ?)';

	$insert_data = array($person_id);

	$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Creating Candidate', $errCode, false, false, false );
	if ($errCode) {
		return $response_data;
	}


	// get new candidate id to return
	if (! $candidate_id = $db->lastInsertId() ) {
		// unknown insert error - should NOT get here
		$return_data ['error'] = true;
		$return_data ['errorCode'] = 45002; // unknown error
		$return_data ['message'] = 'Unknown error creating Candidate';
		$newResponse = $response->withJson ( $return_data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}


	// everything was fine. return success and the full data object
	$data ['id'] = $candidate_id;
	$data ['personId'] = $person_id;
	
	// wrap it in data object
	$data = array (
			'data' => $data 
	);
	$newResponse = $response->withJson ( $data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
} );


function process_highlights($request, $response, $db, $query, $id_parm, &$errCode) {
	$highlights = pdo_exec( $request, $response, $db, $query, $id_parm, 'Retrieving Candidate Highlights', $errCode, false, true, true, false );
	if ($errCode) {
		return $highlights;
	}

	foreach ($highlights as &$highlight) {
		// 2 step process.  first explode out '|' delimited fields
		// then, convert the 2 fields into a single array of objects
		$highlight['skillIds'] = (array_key_exists('skillIds', $highlight) && $highlight['skillIds']) ? explode('|', $highlight['skillIds']) : null;
		$highlight['skillNames'] = (array_key_exists('skillNames', $highlight) && $highlight['skillNames']) ? explode('|', $highlight['skillNames']) : null;
		
		if ($highlight['skillIds']) {
			$highlight['skills'] = create_obj_from_arrays(array($highlight['skillIds'], $highlight['skillNames']), array('id', 'name'));
		} else {
			$highlight['skills'] = array();
		}
		unset($highlight['skillIds']);
		unset($highlight['skillNames']);
	}

	return $highlights;
}