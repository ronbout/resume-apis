<?php
// candidates.php
// the api code for  CRUD operations of the Candidates
// table and related.  

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\UploadedFileInterface;

$app->get('/candidates', function (Request $request, Response $response) {
	$data = array();

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
		$limit_clause .= ' LIMIT ' . $q_vars['limit'] . ' ';
	}
	if (isset($q_vars['offset']) && is_numeric($q_vars['offset'])) {
		$limit_clause .= ' OFFSET ' . $q_vars['offset'] . ' ';
	}

	$query = 'select * from candidate_with_phonetypes_skills_vw  ' . $limit_clause;
	$response_data = pdo_exec($request, $response, $db, $query, array(), 'Retrieving Companies', $errCode, false, true, true, false);
	if ($errCode) {
		return $response_data;
	}

	// convert pipe-delimited skills to arrays
	foreach ($response_data as &$resp) {
		$resp['jobSkillName'] = (array_key_exists('jobSkillName', $resp) && $resp['jobSkillName']) ? explode('|', $resp['jobSkillName']) : null;
		$resp['certSkillName'] = (array_key_exists('certSkillName', $resp) && $resp['certSkillName']) ? explode('|', $resp['certSkillName']) : null;
		$resp['edSkillName'] = (array_key_exists('edSkillName', $resp) && $resp['edSkillName']) ? explode('|', $resp['edSkillName']) : null;
	}

	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});


$app->get('/candidates/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute('id');
	$data = array();

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	$query = 'SELECT * FROM candidate_basic_vw WHERE id = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($id), 'Retrieving Candidate', $errCode, true, false, true, false);
	if ($errCode) {
		return $response_data;
	}

	// explode out the pipe | delimited skill lists.  Will still include the skills w/ each job, education, etc
	// but this gives the api consumer a quick listing w/o having to do a lot of work
	$response_data['jobSkillName'] = (array_key_exists('jobSkillName', $response_data) && $response_data['jobSkillName']) ? explode('|', $response_data['jobSkillName']) : null;
	$response_data['certSkillName'] = (array_key_exists('certSkillName', $response_data) && $response_data['certSkillName']) ? explode('|', $response_data['certSkillName']) : null;
	$response_data['edSkillName'] = (array_key_exists('edSkillName', $response_data) && $response_data['edSkillName']) ? explode('|', $response_data['edSkillName']) : null;

	$response_data = create_lower_object($response_data, 'person');

	$response_data = create_lower_object($response_data, 'agency');

	$query = 'SELECT id, highlight, sequence, skillIds, skillNames, candidateSkillIds 
							FROM candidate_highlights_skills_vw 
							WHERE candidateId = ?
							ORDER BY sequence';
	$highlights = process_highlights($request, $response, $db, $query, array($id), $errCode);
	if ($errCode) {
		return $highlights;
	}

	$response_data['candidateHighlights'] = $highlights ? $highlights : array();

	// get the jobs
	$query = 'SELECT * FROM candidate_jobs_vw WHERE candidateId = ?';
	$jobs_data = pdo_exec($request, $response, $db, $query, array($id), 'Retrieving Candidate Jobs', $errCode, false, true, true, false);
	if ($errCode) {
		return $jobs_data;
	}

	if ($jobs_data) {
		// loop through each job and explode out the pipe | delimited skill lists.
		foreach ($jobs_data as &$job_data) {

			$job_data['skillIds'] = (array_key_exists('skillIds', $job_data) && $job_data['skillIds']) ? explode('|', $job_data['skillIds']) : null;
			$job_data['skillNames'] = (array_key_exists('skillNames', $job_data) && $job_data['skillNames']) ? explode('|', $job_data['skillNames']) : null;
			$job_data['skillPcts'] = (array_key_exists('skillPcts', $job_data) && $job_data['skillPcts']) ? explode('|', $job_data['skillPcts']) : null;
			$job_data['candidateSkillIds'] = (array_key_exists('candidateSkillIds', $job_data) && $job_data['candidateSkillIds']) ? explode('|', $job_data['candidateSkillIds']) : null;
			$job_data['resumeTechtags'] = (array_key_exists('resumeTechtags', $job_data) && $job_data['resumeTechtags']) ? explode('|', $job_data['resumeTechtags']) : null;
			$job_data['resumeTechtagNames'] = (array_key_exists('resumeTechtagNames', $job_data) && $job_data['resumeTechtagNames']) ? explode('|', $job_data['resumeTechtagNames']) : null;

			if ($job_data['skillIds']) {
				$data_array = array($job_data['skillIds'], $job_data['skillNames'], $job_data['skillPcts'], $job_data['candidateSkillIds'], $job_data['resumeTechtags'], $job_data['resumeTechtagNames']);
				$job_data['skills'] = create_obj_from_arrays($data_array, array('id', 'name', 'usePct', 'candidateSkillId', 'techtagId', 'techtagName'));
			} else {
				$job_data['skills'] = array();
			}
			unset($job_data['skillIds']);
			unset($job_data['skillNames']);
			unset($job_data['skillPcts']);
			unset($job_data['candidateSkillIds']);
			unset($job_data['skillTestedFlag']);
			unset($job_data['skillTestResults']);
			unset($job_data['skillTotalMonths']);
			unset($job_data['resumeTechtags']);
			unset($job_data['resumeTechtagNames']);

			// break out the contactPerson and company data into sub objects
			$job_data = create_lower_object($job_data, 'contactPerson');
			$job_data = create_lower_object($job_data, 'company');

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
	$response_data['experience'] = $jobs_data ? $jobs_data : array();

	// get education
	$query = 'SELECT * FROM candidate_education_vw WHERE candidateId = ?';
	$eds_data = pdo_exec($request, $response, $db, $query, array($id), 'Retrieving Candidate Education', $errCode, false, true, true, false);
	if ($errCode) {
		return $eds_data;
	}

	if ($eds_data) {
		// loop through each education and explode out the pipe | delimited skill lists.
		foreach ($eds_data as &$ed_data) {

			$ed_data['skillIds'] = (array_key_exists('skillIds', $ed_data) && $ed_data['skillIds']) ? explode('|', $ed_data['skillIds']) : null;
			$ed_data['skillNames'] = (array_key_exists('skillNames', $ed_data) && $ed_data['skillNames']) ? explode('|', $ed_data['skillNames']) : null;
			$ed_data['skillPcts'] = (array_key_exists('skillPcts', $ed_data) && $ed_data['skillPcts']) ? explode('|', $ed_data['skillPcts']) : null;
			$ed_data['candidateSkillIds'] = (array_key_exists('candidateSkillIds', $ed_data) && $ed_data['candidateSkillIds']) ? explode('|', $ed_data['candidateSkillIds']) : null;

			if ($ed_data['skillIds']) {
				$data_array = array($ed_data['skillIds'], $ed_data['skillNames'], $ed_data['skillPcts'], $ed_data['candidateSkillIds']);
				$ed_data['skills'] = create_obj_from_arrays($data_array, array('id', 'name', 'usePct', 'candidateSkillId'));
			} else {
				$ed_data['skills'] = array();
			}

			unset($ed_data['skillIds']);
			unset($ed_data['skillNames']);
			unset($ed_data['skillPcts']);
			unset($ed_data['candidateSkillIds']);
		}
	}
	$response_data['education'] = $eds_data ? $eds_data : array();

	// get certifications
	$query = 'SELECT * FROM candidate_certifications_vw WHERE candidateId = ?';
	$certs_data = pdo_exec($request, $response, $db, $query, array($id), 'Retrieving Candidate Certifications', $errCode, false, true, true, false);
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
	$response_data['certifications'] = $certs_data ? $certs_data : array();

	// read in social media
	$query = 'SELECT socialType, socialLink FROM candidatesocialmedia WHERE candidateId = ?';
	$social_media = pdo_exec($request, $response, $db, $query, array($id), 'Retrieving Candidate Social Media', $errCode, false, true);
	if ($errCode) {
		return $social_media;
	}
	$response_data['socialMedia'] = $social_media ? $social_media : [];
	// front end requires default value for LinkedIn and Github
	if (array_search('LinkedIn', array_column($social_media, 'socialType')) === false) {
		$response_data['socialMedia'][] = array('socialType' => 'LinkedIn', 'socialLink' => null);
	}
	if (array_search('Github', array_column($social_media, 'socialType')) === false) {
		$response_data['socialMedia'][] = array('socialType' => 'Github', 'socialLink' => null);
	}

	// var_dump($response_data);
	// die();

	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});


$app->post('/candidates', function (Request $request, Response $response) {
	$post_data = $request->getParsedBody();
	$data = array();

	$person_id = isset($post_data['personId']) ? filter_var($post_data['personId'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : '';

	if (!$person_id) {
		$data['error'] = true;
		$data['message'] = 'Person Id is required.';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// create person item and get insert id
	$query = 'INSERT INTO candidate
							(personId)
							VALUES ( ?)';

	$insert_data = array($person_id);

	$response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Candidate', $errCode, false, false, false);
	if ($errCode) {
		return $response_data;
	}


	// get new candidate id to return
	if (!$candidate_id = $db->lastInsertId()) {
		// unknown insert error - should NOT get here
		$return_data['error'] = true;
		$return_data['errorCode'] = 45002; // unknown error
		$return_data['message'] = 'Unknown error creating Candidate';
		$newResponse = $response->withJson($return_data, 500, JSON_NUMERIC_CHECK);
		return $newResponse;
	}


	// everything was fine. return success and the full data object
	$data['id'] = $candidate_id;
	$data['personId'] = $person_id;

	// wrap it in data object
	$data = array(
		'data' => $data
	);
	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});


$app->put('/candidates/{id}/highlights', function (Request $request, Response $response) {

	$id = $request->getAttribute('id');
	$post_data = $request->getParsedBody();
	$data = array();

	if (!isset($post_data['highlights']) || !is_array($post_data['highlights'])) {
		$data['error'] = true;
		$data['message'] = 'An array of highlights is required';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	$highlights = $post_data['highlights'];

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM candidate WHERE id = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($id), 'Retrieving Candidate', $errCode, true);
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
	$response_data = pdo_exec($request, $response, $db, $query, array($id), 'Deleting Candidate Highlights', $errCode, false, false, false);
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

	foreach ($highlights as $highlight) {
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
		$highlight_resp = pdo_exec($request, $response, $db, $query_with_ids, $insert_data_w, 'Updating Candidate Highlights', $errCode, false, false, false);
		if ($errCode) {
			return $highlight_resp;
		}
	}

	// 4)
	if ($insert_data_wo) {
		$query_wo_ids = trim($query_wo_ids, ',');
		$highlight_resp = pdo_exec($request, $response, $db, $query_wo_ids, $insert_data_wo, 'Updating Candidate Highlights', $errCode, false, false, false);
		if ($errCode) {
			return $highlight_resp;
		}
		// need to get insert id.  lastInsertId is actually the first of
		// the group, so can just increment by 1 from there.
		if (!$insert_id = $db->lastInsertId()) {
			// unknown insert error - should NOT get here
			$return_data['error'] = true;
			$return_data['errorCode'] = 45002; // unknown error
			$return_data['message'] = "Unknown error updating Candidate Highlight.  Could not retrieve inserted id's";
			$newResponse = $response->withJson($return_data, 500, JSON_NUMERIC_CHECK);
			return $newResponse;
		}
		// loop through the highlights inserting the new id's by incrementing from insert_id
		foreach ($highlights as &$highlight) {
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
	foreach ($highlights as &$highlight) {
		if ($highlight['skills']) {
			$highlight_id = $highlight['id'];
			foreach ($highlight['skills'] as &$skill) {
				$query .= ' (?, ?),';
				$insert_array[] = $highlight_id;
				if (isset($skill['candidateSkillId']) && $skill['candidateSkillId']) {
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
		$ret = pdo_exec($request, $response, $db, $query, $insert_array, 'Inserting Candidate Highlight Skill', $errCode, false, false, false, false);
		if ($errCode) {
			return $ret;
		}
	}

	// everything was fine. return success and
	// the original post data with the id's added
	$data = array(
		'data' => $highlights
	);
	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});


$app->put('/candidates/{id}/objective', function (Request $request, Response $response) {

	$cand_id = $request->getAttribute('id');
	$post_data = $request->getParsedBody();
	$data = array();

	if (!isset($post_data['objective']) || !isset($post_data['executiveSummary']) || !isset($post_data['jobTitle'])) {
		$data['error'] = true;
		$data['message'] = 'Title, Objective and Executive Summary are required';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	$job_title = isset($post_data['jobTitle']) ? filter_var($post_data['jobTitle'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : '';
	$objective = isset($post_data['objective']) ? filter_var($post_data['objective'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : '';
	$executive_summary = isset($post_data['executiveSummary']) ? filter_var($post_data['executiveSummary'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : '';

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM candidate WHERE id = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Retrieving Candidate', $errCode, true);
	if ($errCode) {
		return $response_data;
	}

	$query = 'UPDATE candidate
							SET jobTitle = ?, 
									objective = ?,
									executiveSummary = ?
							WHERE id = ?';

	$insert_data = array($job_title, $objective, $executive_summary, $cand_id);

	$response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Updating Candidate', $errCode, false, false, false);
	if ($errCode) {
		return $response_data;
	}

	$data = array(
		'data' => array(
			'id' => $cand_id,
			'jobTitle' => $job_title,
			'objective' => $objective,
			'executiveSummary' => $executive_summary
		)
	);

	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});

$app->put('/candidates/{id}/social', function (Request $request, Response $response) {

	$cand_id = $request->getAttribute('id');
	$post_data = $request->getParsedBody();
	$data = array();

	$linkedin = isset($post_data['linkedIn']) ? filter_var($post_data['linkedIn'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : '';
	$github = isset($post_data['github']) ? filter_var($post_data['github'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : '';

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM candidate WHERE id = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Retrieving Candidate', $errCode, true);
	if ($errCode) {
		return $response_data;
	}

	// update social media if they exist
	// in case social links were deleted, just delete all for this candidate
	// *** only delete linkedIn and github as they are currently only 2 on form
	$query = 'DELETE FROM candidatesocialmedia 
						WHERE candidateId = ?
						AND socialType in ("LinkedIn", "Github")';

	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Deleting Candidate Social Media', $errCode, false, false, false);
	if ($errCode) {
		return $response_data;
	}

	if ($linkedin) {
		$resp = update_candidate_social($request, $response, $db, $errCode, "LinkedIn", $linkedin, $cand_id);
		if ($errCode) {
			return $resp;
		}
	}

	if ($github) {
		$resp = update_candidate_social($request, $response, $db, $errCode, "Github", $github, $cand_id);
		if ($errCode) {
			return $resp;
		}
	}

	$data = array(
		'data' => array(
			'id' => $cand_id,
			'LinkedIn' => $linkedin,
			'Github' => $github
		)
	);

	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});


$app->put('/candidates/{id}/education', function (Request $request, Response $response) {

	$cand_id = $request->getAttribute('id');
	$post_data = $request->getParsedBody();
	$data = array();

	if (!isset($post_data['education']) || !is_array($post_data['education'])) {
		$data['error'] = true;
		$data['message'] = 'An array of education is required';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	$education = $post_data['education'];

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM candidate WHERE id = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Retrieving Candidate', $errCode, true);
	if ($errCode) {
		return $response_data;
	}

	/**
	 * 
	 * 1) delete the original education
	 * 
	 * 2) loop through education building separate sql for those
	 * 		with id's and those w/o
	 * 		-- also build a list of the skills that have attached candidateskill Ids
	 * 		-- that way I have to do as few lookups as possible
	 * 
	 * 3) run the sql for the education with id's
	 * 
	 * 4) run the sql for the education w/o id's and insert them,
	 * 		getting the new id's and placing them in the post data
	 * 
	 * 5) Loop through the newly id'd education and build the sql
	 *  	to insert skills using the education id's from steps 3,4
	 * 
	 * 6) return the post_data with the new id's
	 * 
	 */

	// 1)
	// because CASCADE is set up in the foreign key, deleting the candidateeducation will 
	// result in the candidatehighlights_skills also being deleted.
	$query = 'DELETE FROM candidateeducation WHERE candidateId = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Deleting Candidate Education', $errCode, false, false, false);
	if ($errCode) {
		return $response_data;
	}

	// 2)
	$query_wo_ids = 'INSERT INTO candidateeducation
											(candidateId, schoolName, schoolMunicipality, schoolRegion, schoolCountry,
											degreeName, degreeType, degreeMajor, degreeMinor, startDate, endDate)
										VALUES ';
	$query_with_ids =	'INSERT INTO candidateeducation
												(id, candidateId, schoolName, schoolMunicipality, schoolRegion, schoolCountry,
												 degreeName, degreeType, degreeMajor, degreeMinor, startDate, endDate)
										 VALUES ';
	$insert_data_w = array();
	$insert_data_wo = array();

	$candidate_skills =  array();

	foreach ($education as $ed) {
		$school_name = isset($ed['schoolName']) ? filter_var($ed['schoolName'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : '';
		$school_municipality = isset($ed['schoolMunicipality']) ? filter_var($ed['schoolMunicipality'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$school_region = isset($ed['schoolRegion']) ? filter_var($ed['schoolRegion'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$school_country = isset($ed['schoolCountry']) ? filter_var($ed['schoolCountry'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$degree_name = isset($ed['degreeName']) ? filter_var($ed['degreeName'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : '';
		$degree_type = isset($ed['degreeType']) ? filter_var($ed['degreeType'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : 'non-Degree';
		$degree_major = isset($ed['degreeMajor']) ? filter_var($ed['degreeMajor'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$degree_minor = isset($ed['degreeMinor']) ? filter_var($ed['degreeMinor'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$start_date = isset($ed['startDate']) && $ed['startDate'] ? filter_var($ed['startDate'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$end_date = isset($ed['endDate']) && $ed['endDate'] ? filter_var($ed['endDate'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;

		if ($ed['id'] === '') {
			$query_wo_ids .= ' (?,?,?,?,?,?,?,?,?,?,?),';
			$insert_data_wo[] = $cand_id;
			$insert_data_wo[] = $school_name;
			$insert_data_wo[] = $school_municipality;
			$insert_data_wo[] = $school_region;
			$insert_data_wo[] = $school_country;
			$insert_data_wo[] = $degree_name;
			$insert_data_wo[] = $degree_type;
			$insert_data_wo[] = $degree_major;
			$insert_data_wo[] = $degree_minor;
			$insert_data_wo[] = $start_date;
			$insert_data_wo[] = $end_date;
		} else {
			$query_with_ids .= ' (?,?,?,?,?,?,?,?,?,?,?,?),';
			$insert_data_w[] = $ed['id'];
			$insert_data_w[] = $cand_id;
			$insert_data_w[] = $school_name;
			$insert_data_w[] = $school_municipality;
			$insert_data_w[] = $school_region;
			$insert_data_w[] = $school_country;
			$insert_data_w[] = $degree_name;
			$insert_data_w[] = $degree_type;
			$insert_data_w[] = $degree_major;
			$insert_data_w[] = $degree_minor;
			$insert_data_w[] = $start_date;
			$insert_data_w[] = $end_date;
		}

		// check for skills and add to candidate_skills array
		// if a candidate skills id is present, else create it
		if (isset($ed['skills']) && count($ed['skills'])) {
			$candidate_skills = build_candidate_skills($request, $response, $db, $errCode, $candidate_skills, $ed['skills'], $cand_id);
			if ($errCode) {
				return $candidate_skills;
			}
		}
	}

	// 3)
	if ($insert_data_w) {
		$query_with_ids = trim($query_with_ids, ',');
		$ed_resp = pdo_exec($request, $response, $db, $query_with_ids, $insert_data_w, 'Updating Candidate Education', $errCode, false, false, false);
		if ($errCode) {
			return $ed_resp;
		}
	}

	// 4)
	if ($insert_data_wo) {
		$query_wo_ids = trim($query_wo_ids, ',');
		$ed_resp = pdo_exec($request, $response, $db, $query_wo_ids, $insert_data_wo, 'Inserting Candidate Education', $errCode, false, false, false);
		if ($errCode) {
			return $ed_resp;
		}
		// need to get insert id.  lastInsertId is actually the first of
		// the group, so can just increment by 1 from there.
		if (!$insert_id = $db->lastInsertId()) {
			// unknown insert error - should NOT get here
			$return_data['error'] = true;
			$return_data['errorCode'] = 45002; // unknown error
			$return_data['message'] = "Unknown error updating Candidate Education.  Could not retrieve inserted id's";
			$newResponse = $response->withJson($return_data, 500, JSON_NUMERIC_CHECK);
			return $newResponse;
		}
		// loop through the highlights inserting the new id's by incrementing from insert_id
		foreach ($education as &$ed) {
			if ($ed['id'] === '') {
				$ed['id'] = $insert_id++;
			}
		}
	}

	// 5)
	$query = 'INSERT INTO candidateeducation_skills
								(educationId, candidateSkillId)
						VALUES ';
	$insert_array = array();
	foreach ($education as &$ed) {
		if (isset($ed['skills']) && count($ed['skills'])) {
			$ed_id = $ed['id'];
			foreach ($ed['skills'] as &$skill) {
				$query .= ' (?, ?),';
				$insert_array[] = $ed_id;
				if (isset($skill['candidateSkillId']) && $skill['candidateSkillId']) {
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
		$ret = pdo_exec($request, $response, $db, $query, $insert_array, 'Inserting Candidate Education Skill', $errCode, false, false, false, false);
		if ($errCode) {
			return $ret;
		}
	}

	// everything was fine. return success and
	// the original post data with the id's added
	$data = array(
		'data' => $education
	);
	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});


$app->put('/candidates/{id}/certifications', function (Request $request, Response $response) {

	$cand_id = $request->getAttribute('id');
	$post_data = $request->getParsedBody();
	$data = array();

	if (!isset($post_data['certifications']) || !is_array($post_data['certifications'])) {
		$data['error'] = true;
		$data['message'] = 'An array of certifications is required';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	$certifications = $post_data['certifications'];

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM candidate WHERE id = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Retrieving Candidate', $errCode, true);
	if ($errCode) {
		return $response_data;
	}

	/**
	 * 
	 * 1) delete the original certifications
	 * 
	 * 2) loop through certifications building separate sql for those
	 * 		with id's and those w/o
	 * 		-- also build a list of the skills that have attached candidateskill Ids
	 * 		-- that way I have to do as few lookups as possible
	 * 
	 * 3) run the sql for the certifications with id's
	 * 
	 * 4) run the sql for the certifications w/o id's and insert them,
	 * 		getting the new id's and placing them in the post data
	 * 
	 * 5) Loop through the newly id'd certifications and build the sql
	 *  	to insert skills using the certifications id's from steps 3,4
	 * 
	 * 6) return the post_data with the new id's
	 * 
	 */

	// 1)
	$query = 'DELETE FROM candidatecertifications WHERE candidateId = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Deleting Candidate Certifications', $errCode, false, false, false);
	if ($errCode) {
		return $response_data;
	}

	// 2)
	$query_wo_ids = 'INSERT INTO candidatecertifications
											(candidateId, name, description, issueDate, certificateImage)
										VALUES ';
	$query_with_ids =	'INSERT INTO candidatecertifications
												(id, candidateId, name, description, issueDate, certificateImage)
										 VALUES ';
	$insert_data_w = array();
	$insert_data_wo = array();

	$candidate_skills =  array();

	foreach ($certifications as $cert) {
		$name = isset($cert['name']) ? filter_var($cert['name'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : '';
		$description = isset($cert['description']) ? filter_var($cert['description'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$issue_date = isset($cert['issueDate']) && $cert['issueDate'] ? filter_var($cert['issueDate'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$cert_image = isset($cert['certificateImage']) ? filter_var($cert['certificateImage'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;

		if ($cert['id'] === '') {
			$query_wo_ids .= ' (?,?,?,?,?),';
			$insert_data_wo[] = $cand_id;
			$insert_data_wo[] = $name;
			$insert_data_wo[] = $description;
			$insert_data_wo[] = $issue_date;
			$insert_data_wo[] = $cert_image;
		} else {
			$query_with_ids .= ' (?,?,?,?,?,?),';
			$insert_data_w[] = $cert['id'];
			$insert_data_w[] = $cand_id;
			$insert_data_w[] = $name;
			$insert_data_w[] = $description;
			$insert_data_w[] = $issue_date;
			$insert_data_w[] = $cert_image;
		}

		// check for skills and add to candidate_skills array
		// if a candidate skills id is present, else create it
		if (isset($cert['skills']) && count($cert['skills'])) {
			$candidate_skills = build_candidate_skills($request, $response, $db, $errCode, $candidate_skills, $cert['skills'], $cand_id);
			if ($errCode) {
				return $candidate_skills;
			}
		}
	}

	// 3)
	if ($insert_data_w) {
		$query_with_ids = trim($query_with_ids, ',');
		$cert_resp = pdo_exec($request, $response, $db, $query_with_ids, $insert_data_w, 'Updating Candidate Certifications', $errCode, false, false, false);
		if ($errCode) {
			return $cert_resp;
		}
	}

	// 4)
	if ($insert_data_wo) {
		$query_wo_ids = trim($query_wo_ids, ',');
		$cert_resp = pdo_exec($request, $response, $db, $query_wo_ids, $insert_data_wo, 'Inserting Candidate Certifications', $errCode, false, false, false);
		if ($errCode) {
			return $cert_resp;
		}
		// need to get insert id.  lastInsertId is actually the first of
		// the group, so can just increment by 1 from there.
		if (!$insert_id = $db->lastInsertId()) {
			// unknown insert error - should NOT get here
			$return_data['error'] = true;
			$return_data['errorCode'] = 45002; // unknown error
			$return_data['message'] = "Unknown error updating Candidate Certifications.  Could not retrieve inserted id's";
			$newResponse = $response->withJson($return_data, 500, JSON_NUMERIC_CHECK);
			return $newResponse;
		}
		// loop through the Certifications inserting the new id's by incrementing from insert_id
		foreach ($certifications as &$cert) {
			if ($cert['id'] === '') {
				$cert['id'] = $insert_id++;
			}
		}
	}

	// 5)
	$query = 'INSERT INTO candidatecertifications_skills
								(certificationId, candidateSkillId)
						VALUES ';
	$insert_array = array();
	foreach ($certifications as &$cert) {
		if (isset($cert['skills']) && count($cert['skills'])) {
			$cert_id = $cert['id'];
			foreach ($cert['skills'] as &$skill) {
				$query .= ' (?, ?),';
				$insert_array[] = $cert_id;
				if (isset($skill['candidateSkillId']) && $skill['candidateSkillId']) {
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
		$ret = pdo_exec($request, $response, $db, $query, $insert_array, 'Inserting Candidate Certifications Skill', $errCode, false, false, false, false);
		if ($errCode) {
			return $ret;
		}
	}

	// everything was fine. return success and
	// the original post data with the id's added
	$data = array(
		'data' => $certifications
	);
	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});

$app->put('/candidates/{id}/experience', function (Request $request, Response $response) {
	$cand_id = $request->getAttribute('id');
	$post_data = $request->getParsedBody();
	$data = array();

	if (!isset($post_data['experience']) || !is_array($post_data['experience'])) {
		$data['error'] = true;
		$data['message'] = 'An array of experience is required';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	$experience = $post_data['experience'];

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM candidate WHERE id = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Retrieving Candidate', $errCode, true);
	if ($errCode) {
		return $response_data;
	}

	/**
	 * 
	 * 1) delete the original experience
	 * 
	 * 2) loop through each experience and process separately
	 * 			--  keep running list of candidate skills to db lookup to a minumum
	 * 
	 * EACH EXPERIENCE: 
	 * 3) check for jobTitleId and get if necessary
	 * 
	 * 4)	build and execute sql for insert using id, if present
	 * 
	 * 5) skills
	 * 
	 * 6) highlights 
	 * 
	 */

	// 1)
	// because CASCADE is set up in the foreign key, deleting the candidateeducation will 
	// result in the related table entries also being deleted
	$query = 'DELETE FROM candidatejobs WHERE candidateId = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Deleting Candidate Experience', $errCode, false, false, false);
	if ($errCode) {
		return $response_data;
	}

	// 2)
	$candidate_skills =  array();
	$insert_fields = ' candidateId, companyId, startDate, endDate, contactPersonId, payType, startPay, endPay, jobTitleId, summary';
	foreach ($experience as &$exp) {
		$exp['companyId'] = isset($exp['companyId'])  && $exp['companyId'] ? filter_var($exp['companyId'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$exp['startDate'] = isset($exp['startDate']) && $exp['startDate'] ? filter_var($exp['startDate'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$exp['endDate'] = isset($exp['endDate']) && $exp['endDate'] ? filter_var($exp['endDate'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$exp['contactPersonId'] = isset($exp['contactPersonId']) && $exp['contactPersonId'] ? filter_var($exp['contactPersonId'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$exp['payType'] = isset($exp['payType']) ? filter_var($exp['payType'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$exp['startPay'] = isset($exp['startPay']) && $exp['startPay'] !== '' ? filter_var($exp['startPay'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$exp['endPay'] = isset($exp['endPay']) && $exp['endPay'] !== '' ? filter_var($exp['endPay'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$exp['jobTitleId'] = isset($exp['jobTitleId'])  && $exp['jobTitleId'] ? filter_var($exp['jobTitleId'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$exp['summary'] = isset($exp['summary']) ? filter_var($exp['summary'], FILTER_SANITIZE_STRING,	FILTER_FLAG_NO_ENCODE_QUOTES) : null;
		$exp['skills'] = isset($exp['skills'])  && is_array($exp['skills']) && $exp['skills'] ? $exp['skills'] : array();
		$exp['highlights'] = isset($exp['highlights'])  && is_array($exp['highlights']) && $exp['highlights'] ? $exp['highlights'] : array();

		// 3)
		// if jobTitleId exists, just use that, otherwise check jobTitleDescription against table
		if (!$exp['jobTitleId']) {
			if ($exp['jobTitle']) {
				$query = 'SELECT get_candidate_job_title(?, ?)  AS jtid';
				$insert_array = array($cand_id, $exp['jobTitle']);
				$jt_resp = pdo_exec($request, $response, $db, $query, $insert_array, 'Retrieving/Inserting Job Title', $errCode, false, false, true, false);
				if ($errCode) {
					return $jt_resp;
				}
				$exp['jobTitleId'] = $jt_resp['jtid'];
			} else {
				$exp['jobTitleId'] = null;
			}
		}

		// 4)
		$insert_array = array(
			$cand_id, $exp['companyId'], $exp['startDate'], $exp['endDate'], $exp['contactPersonId'],
			$exp['payType'], $exp['startPay'], $exp['endPay'], $exp['jobTitleId'], $exp['summary']
		);
		// if already has id, use that, otherwise let autoinc set it and get it afterwards
		if ($exp['id']) {
			$query = 'INSERT INTO candidatejobs 
									( id, ' . $insert_fields . ') 
									VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ';
			array_unshift($insert_array, $exp['id']);
		} else {
			$query = 'INSERT INTO candidatejobs 
									( ' . $insert_fields . ') 
									VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ';
		}

		$exp_resp = pdo_exec($request, $response, $db, $query, $insert_array, 'Inserting Candidate Experience', $errCode, 		false, false, false);
		if ($errCode) {
			return $exp_resp;
		}
		if (!$exp['id']) {
			if (!$insert_id = $db->lastInsertId()) {
				// unknown insert error - should NOT get here
				$return_data['error'] = true;
				$return_data['errorCode'] = 45002; // unknown error
				$return_data['message'] = "Unknown error inserting Candidate Experience.  Could not retrieve inserted id's";
				$newResponse = $response->withJson($return_data, 500, JSON_NUMERIC_CHECK);
				return $newResponse;
			}
			$exp['id'] = $insert_id;
		}
		$job_id = $exp['id'];

		// 5)
		// check for skills and add to candidate_skills array
		// if a candidate skills id is present, else create it
		if ($exp['skills']) {
			$candidate_skills = build_candidate_skills($request, $response, $db, $errCode, $candidate_skills, $exp['skills'], $cand_id);
			if ($errCode) {
				return $candidate_skills;
			}

			$query = 'INSERT INTO candidatejob_skills
									(jobId, candidateSkillId)
								VALUES ';
			$insert_array = array();
			foreach ($exp['skills'] as &$skill) {
				$query .= ' (?, ?),';
				$insert_array[] = $job_id;
				if ($skill['candidateSkillId']) {
					$insert_array[] = $skill['candidateSkillId'];
				} else {
					$insert_array[] = $candidate_skills[$skill['id']];
					$skill['candidateSkillId'] = $candidate_skills[$skill['id']];
				}
			}
			if ($insert_array) {
				$query = trim($query, ',');
				$ret = pdo_exec($request, $response, $db, $query, $insert_array, 'Inserting Candidate Job Skill', $errCode, false, false, 	false, false);
				if ($errCode) {
					return $ret;
				}
			}
		}

		// 6)
		if ($exp['highlights']) {

			/***
			 * 
			 *  create a highlights routine using candidate highlights
			 *  or just a shitload of copy/paste. 
			 *  Big difference, besides table name, will be the include in summary 
			 *  BUT that is not going in!!
			 * 
			 */
			update_highlights($request, $response, $db, $errCode, $exp['id'], $exp['highlights'], $candidate_skills, 'candidatejobhighlights', 'jobId', 'candidatejobhighlights_skills', 'candidateJobHighlightsId');
		}
	}

	// everything was fine. return success and
	// the original post data with the id's added
	$data = array(
		'data' => $experience
	);
	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});


$app->post('/candidates/{id}/image', function (Request $request, Response $response) {

	$cand_id = $request->getAttribute('id');
	$post_data = $request->getUploadedFiles();
	$data = array();

	if (!isset($post_data['image'])) {
		$data['error'] = true;
		$data['message'] = 'Image file is required';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	// login to the database. if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// need to make sure that this record id exists to update
	$query = 'SELECT * FROM candidate WHERE id = ?';
	$response_data = pdo_exec($request, $response, $db, $query, array($cand_id), 'Retrieving Candidate', $errCode, true);
	if ($errCode) {
		return $response_data;
	}

	$upload_dir = get_imgs_dir();
	$basename = "candidate$cand_id";
	$image_file = $post_data['image'];

	$img_err = $image_file->getError();

	if ($img_err === UPLOAD_ERR_OK) {
		try {
			$filename = moveUploadedFile($upload_dir, $basename, $image_file);
		} catch (Exception $e) {
			$data['error'] = true;
			$data['message'] = $e->getMessage();
			$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK);
			return $newResponse;
		}
	} else {
		$data['error'] = true;
		$data['message'] = $img_err;
		$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	$data = array(
		'data' => array(
			'id' => $cand_id,
			'filename' => $filename
		)
	);

	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK);
	return $newResponse;
});


function process_highlights($request, $response, $db, $query, $id_parm, &$errCode)
{
	$highlights = pdo_exec($request, $response, $db, $query, $id_parm, 'Retrieving Candidate Highlights', $errCode, false, true, true, false);
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

function update_highlights($request, $response, $db, &$errCode, $cand_id, &$highlights, &$candidate_skills, $hl_table, $hl_link_id, $hl_skill_table, $hl_skill_link_id)
{
	$query_with_ids =	'INSERT INTO ' . $hl_table . '
											(id, ' . $hl_link_id . ', includeInSummary, highlight, sequence)
										 VALUES ';
	$query_wo_ids = 'INSERT INTO ' . $hl_table . '
										(' . $hl_link_id . ', includeInSummary, highlight, sequence)
								   VALUES ';
	$insert_data_w = array();
	$insert_data_wo = array();


	foreach ($highlights as &$highlight) {
		if ($highlight['id'] === '') {
			$query_wo_ids .= ' (?, ?, ?, ?),';
			$insert_data_wo[] = $cand_id;
			$insert_data_wo[] = $highlight['includeInSummary'];
			$insert_data_wo[] = $highlight['highlight'];
			$insert_data_wo[] = $highlight['sequence'];
		} else {
			$query_with_ids .= ' (?, ?, ?, ?, ?),';
			$insert_data_w[] = $highlight['id'];
			$insert_data_w[] = $cand_id;
			$insert_data_w[] = $highlight['includeInSummary'];
			$insert_data_w[] = $highlight['highlight'];
			$insert_data_w[] = $highlight['sequence'];
		}
		// check for skills and add to candidate_skills array
		// if a candidate skills id is present, else create it
		$highlight['skills'] = isset($highlight['skills'])  && is_array($highlight['skills']) && $highlight['skills'] ? $highlight['skills'] : array();
		if ($highlight['skills']) {
			$candidate_skills = build_candidate_skills($request, $response, $db, $errCode, $candidate_skills, $highlight['skills'], $cand_id);
			if ($errCode) {
				return $candidate_skills;
			}
		}
	}

	// 3)
	if ($insert_data_w) {
		$query_with_ids = trim($query_with_ids, ',');
		$highlight_resp = pdo_exec($request, $response, $db, $query_with_ids, $insert_data_w, '1 Updating ' . $hl_table, $errCode, false, false, false);
		if ($errCode) {
			return $highlight_resp;
		}
	}

	// 4)
	if ($insert_data_wo) {
		$query_wo_ids = trim($query_wo_ids, ',');
		$highlight_resp = pdo_exec($request, $response, $db, $query_wo_ids, $insert_data_wo, '2 Updating ' . $hl_table, $errCode, false, false, false);
		if ($errCode) {
			return $highlight_resp;
		}

		// need to get insert id.  lastInsertId is actually the first of
		// the group, so can just increment by 1 from there.
		if (!$insert_id = $db->lastInsertId()) {
			// unknown insert error - should NOT get here
			$return_data['error'] = true;
			$return_data['errorCode'] = 45002; // unknown error
			$return_data['message'] = "Unknown error updating " . $hl_table . ".  Could not retrieve inserted id's";
			$newResponse = $response->withJson($return_data, 500, JSON_NUMERIC_CHECK);
			return $newResponse;
		}
		// loop through the highlights inserting the new id's by incrementing from insert_id
		foreach ($highlights as &$highlight) {
			if ($highlight['id'] === '') {
				$highlight['id'] = $insert_id++;
			}
		}
	}

	// 5)
	$query = 'INSERT INTO ' . $hl_skill_table . '
							(' . $hl_skill_link_id . ', candidateSkillId)
						VALUES ';
	$insert_array = array();
	foreach ($highlights as &$highlight) {
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
		$ret = pdo_exec($request, $response, $db, $query, $insert_array, 'Inserting ' . $hl_skill_table, $errCode, false, false, false, false);
		if ($errCode) {
			return $ret;
		}
	}
}

function build_candidate_skills($request, $response, $db, &$errCode, $cand_skills_list, $skill_list, $cand_id)
{
	$query = 'SELECT get_candidate_skill_id(?, ?)  AS csid';

	foreach ($skill_list as $skill) {
		if (!array_key_exists($skill['id'], $cand_skills_list)) {
			$insert_array = array($cand_id, $skill['id']);
			$cs_id = pdo_exec($request, $response, $db, $query, $insert_array, 'Retrieving/Inserting Candidate Skill', $errCode, false, false, true, false);
			if ($errCode) {
				return $cs_id;
			}
			$cand_skills_list[$skill['id']] = $cs_id['csid'];
		}
	}

	return $cand_skills_list;
}

function update_candidate_social($request, $response, $db, &$errCode, $social_type, $social_link, $candidate_id)
{
	$query = 'INSERT INTO candidatesocialmedia
							(candidateId, socialType, socialLink)
							VALUES ( ?, ?, ?)';

	$insert_data = array($candidate_id, $social_type, $social_link);

	$response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Candidate Social Media', $errCode, false, false, false);
	return $response_data;
}
