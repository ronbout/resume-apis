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
	
	$query = 'SELECT id, highlight, sequence, skillIds, skillNames, candidateSkillIds 
							FROM candidate_highlights_skills_vw 
							WHERE candidateId = ?
							ORDER BY sequence';
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

			$query = 'SELECT id, highlight, sequence, includeInSummary, skillIds, skillNames, candidateSkillIds 
									FROM candidate_job_highlights_skills_vw 
									WHERE jobId = ?';
			$highlights = process_highlights($request, $response, $db, $query, array($job_data['id']), $errCode);
			if ($errCode) {
				return $highlights;
			}
			$job_data['highlights'] = $highlights ? $highlights : array();
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
	$response_data['education'] = $eds_data ? $eds_data : array();
	
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


$app->put ( '/candidates/{id}/highlights', function (Request $request, Response $response) {

	$id = $request->getAttribute ( 'id' );
	$post_data = $request->getParsedBody ();
	$data = array ();
	
	if (! isset($post_data['highlights']) || !is_array($post_data['highlights'])) {
		$data ['error'] = true;
		$data ['message'] = 'An array of highlights is required';
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$highlights = $post_data['highlights'];
	
	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect ( $request, $response, $errCode );
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM candidate WHERE id = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate', $errCode, true);
	if ($errCode) {
		return $response_data;
	}

	/**
	 * 
	 * 1) delete the original highlights
	 * 
	 * 2) loop through highlights building separate sql for those
	 * 		with id's and those w/o
	 * 		-- also build a list of the skills that have attached candidateskill Ids
	 * 		-- that way I have to do as few lookups as possible
	 * 
	 * 3) run the sql for the highlights with id's
	 * 
	 * 4) run the sql for the highlights w/o id's and insert them,
	 * 		getting the new id's and placing them in the post data
	 * 
	 * 5) Loop through the newly id'd highlights and build the sql
	 *  	to insert highlight skills using the highlight id's from steps 3,4
	 * 
	 * 6) return the post_data with the new id's
	 * 
	 */

	// 1)
	// because CASCADE is set up in the foreign key, deleting the candidatehighlights will 
	// result in the candidatehighlights_skills also being deleted.
	$query = 'DELETE FROM candidatehighlights WHERE candidateId = ?';
	$response_data = pdo_exec( $request, $response, $db, $query, array($id), 'Deleting Candidate Highlights', $errCode, false, false, false);
	if ($errCode) {
		return $response_data;
	}

	// 2)
	$query_with_ids =	'INSERT INTO candidatehighlights
												(id, candidateId, highlight, sequence)
										 VALUES ';
	$query_wo_ids = 'INSERT INTO candidatehighlights
											(candidateId, highlight, sequence)
									 VALUES ';
	$insert_data_w = array();
	$insert_data_wo = array();

	$candidate_skills =  array();

	foreach($highlights as $highlight) {
		if ($highlight['id'] === '') {
			$query_wo_ids .= ' (?, ?, ?),';
			$insert_data_wo[] = $id;
			$insert_data_wo[] = $highlight['highlight'];
			$insert_data_wo[] = $highlight['sequence'];
		} else {
			$query_with_ids .= ' (?, ?, ?, ?),';
			$insert_data_w[] = $highlight['id'];
			$insert_data_w[] = $id;
			$insert_data_w[] = $highlight['highlight'];
			$insert_data_w[] = $highlight['sequence'];
		}

		// check for skills and add to candidate_skills array
		// if a candidate skills id is present, else create it
		if ($highlight['skills']) {
			$candidate_skills = build_candidate_skills($request, $response, $db, $errCode, $candidate_skills, $highlight['skills'], $id);
			if ($errCode) {
				return $candidate_skills;
			}
		}
	}

	// 3)
	if ($insert_data_w) {
		$query_with_ids = trim($query_with_ids, ',');
		$highlight_resp = pdo_exec( $request, $response, $db, $query_with_ids, $insert_data_w, 'Updating Candidate Highlights', $errCode, false, false, false );
		if ($errCode) {
			return $highlight_resp;
		}
	}

	// 4)
	if ($insert_data_wo) {
		$query_wo_ids = trim($query_wo_ids, ',');
		$highlight_resp = pdo_exec( $request, $response, $db, $query_wo_ids, $insert_data_wo, 'Updating Candidate Highlights', $errCode, false, false, false );
		if ($errCode) {
			return $highlight_resp;
		}
		// need to get insert id.  lastInsertId is actually the first of
		// the group, so can just increment by 1 from there.
		if (! $insert_id = $db->lastInsertId() ) {
			// unknown insert error - should NOT get here
			$return_data ['error'] = true;
			$return_data ['errorCode'] = 45002; // unknown error
			$return_data ['message'] = "Unknown error updating Candidate Highlight.  Could not retrieve inserted id's";
			$newResponse = $response->withJson ( $return_data, 500, JSON_NUMERIC_CHECK );
			return $newResponse;
		}
		// loop through the highlights inserting the new id's by incrementing from insert_id
		foreach($highlights as &$highlight) {
			if ($highlight['id'] === '') {
				$highlight['id'] = $insert_id++;
			}
		}

	}

	// 5)
	$query = 'INSERT INTO candidatehighlights_skills
								(candidateHighlightsId, candidateSkillId)
						VALUES ';
	$insert_array = array();
	foreach($highlights as &$highlight) {
		if ($highlight['skills']) {
			$highlight_id = $highlight['id'];
			foreach ($highlight['skills'] as &$skill) {
				$query .= ' (?, ?),';
				$insert_array[] = $highlight_id;
				if ($skill['candidateSkillId']) {
					$insert_array[] = $skill['candidateSkillId'];
				} else {
					$insert_array[] = $candidate_skills[$skill['id']];
					$skill['candidateSkillId'] = $candidate_skills[$skill['id']];
				}

			}
		}
	}

	if ($insert_array) {
		$query = trim($query, ',');
		$ret = pdo_exec( $request, $response, $db, $query, $insert_array, 'Inserting Candidate Highlight Skill', $errCode, false, false, false, false );
		if ($errCode) {
			return $ret;
		}
	}

	// everything was fine. return success and
	// the original post data with the id's added
	$data = array (
			'data' => $highlights
	);
	$newResponse = $response->withJson ( $data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
} );


$app->put ( '/candidates/{id}/objective', function (Request $request, Response $response) {

	$cand_id = $request->getAttribute ( 'id' );
	$post_data = $request->getParsedBody ();
	$data = array ();
	
	if (! isset($post_data['objective']) || !isset($post_data['executiveSummary'])) {
		$data ['error'] = true;
		$data ['message'] = 'Objective and Executive Summary are required';
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$objective = isset ( $post_data ['objective'] ) ? filter_var ( $post_data ['objective'], FILTER_SANITIZE_STRING ) : '';
	$executive_summary = isset ( $post_data ['executiveSummary'] ) ? filter_var ( $post_data ['executiveSummary'], FILTER_SANITIZE_STRING ) : '';
	
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

	$query = 'UPDATE candidate
							SET objective = ?,
									executiveSummary = ?
							WHERE id = ?';

	$insert_data = array($objective, $executive_summary, $cand_id);

	$response_data = pdo_exec( $request, $response, $db, $query, $insert_data, 'Updating Candidate', $errCode, false, false, false );
	if ($errCode) {
		return $response_data;
	}

	$data = array(
		'id' => $cand_id,
		'objective' => $objective,
		'executiveSummary' => $executive_summary
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
		$highlight['candidateSkillIds'] = (array_key_exists('candidateSkillIds', $highlight) && $highlight['candidateSkillIds']) ? explode('|', $highlight['candidateSkillIds']) : null;
		
		if ($highlight['skillIds']) {
			$highlight['skills'] = create_obj_from_arrays(array($highlight['skillIds'], $highlight['skillNames'], $highlight['candidateSkillIds']), array('id', 'name', 'candidateSkillId'));
		} else {
			$highlight['skills'] = array();
		}
		unset($highlight['skillIds']);
		unset($highlight['skillNames']);
		unset($highlight['candidateSkillIds']);
	}

	return $highlights;
}

function build_candidate_skills($request, $response, $db, &$errCode, $cand_skills_list, $skill_list, $cand_id) {
	$query = 'SELECT get_candidate_skill_id(?, ?)  AS csid';

	foreach($skill_list as $skill) {
		if (!array_key_exists($skill['id'], $cand_skills_list)) {
			$insert_array = array($cand_id, $skill['id']);
			$cs_id = pdo_exec( $request, $response, $db, $query, $insert_array, 'Retrieving/Inserting Candidate Skill', $errCode, false, false, true, false );
			if ($errCode) {
				return $cs_id;
			}
			$cand_skills_list[$skill['id']] = $cs_id['csid'];
		}
	}

	return $cand_skills_list;
}