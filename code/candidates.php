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
		return $db;
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
	$response_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate', $errCode, true );
	if ($errCode) {
		return $db;
	}
	// explode out the pipe | delimited skill lists.  Will still include the skills w/ each job, education, etc
	// but this gives the api consumer a quick listing w/o having to do a lot of work
	if (array_key_exists('jobSkillName', $response_data))  $response_data['jobSkillName'] = explode('|', $response_data['jobSkillName']);
	if (array_key_exists('certSkillName', $response_data))  $response_data['certSkillName'] = explode('|', $response_data['certSkillName']);
	if (array_key_exists('edSkillName', $response_data))  $response_data['edSkillName'] = explode('|', $response_data['edSkillName']);

	$response_data = runLowerObject( $response_data, 'person');
	
	// TODO:  create lower object for agency contact if it exists
	
	$query = 'SELECT id, highlight FROM candidatehighlights WHERE candidateId = ?';
	$highlights = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate Highlights', $errCode, false, true );
	if ($errCode) {
		return $db;
	}
	$response_data['candidateHighlights'] = $highlights ? $highlights : null;
	
	// get the jobs
	$query = 'SELECT * FROM candidate_jobs_vw WHERE candidateId = ?';
	$jobs_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate Jobs', $errCode, false, true );
	if ($errCode) {
		return $db;
	}
	
	if ($jobs_data) {
		// loop through each job and explode out the pipe | delimited skill lists.
		foreach ($jobs_data as &$job_data) {
			if (array_key_exists('skillIds', $job_data))  {
				$job_data['skillIds'] = explode('|', $job_data['skillIds']);
				$job_data['skillNames'] = explode('|', $job_data['skillNames']);
				$job_data['skillPcts'] = explode('|', $job_data['skillPcts']);
				$job_data['skillTestedFlag'] = explode('|', $job_data['skillTestedFlag']);
				$job_data['skillTestResults'] = explode('|', $job_data['skillTestResults']);
				$job_data['skillTotalMonths'] = explode('|', $job_data['skillTotalMonths']);
				$job_data['skillTags'] = explode('|', $job_data['skillTags']);
				$job_data['skillTagNames'] = explode('|', $job_data['skillTagNames']);
				$temp_skills = array();
				foreach ( $job_data['skillIds'] as $key => $skillId) {
					$temp_skills[] = array('SkillId' => $skillId, 
								'skillName' => $job_data['skillNames'][$key],
								'skillPct' => $job_data['skillPcts'][$key],
								'skillTested' => (array_key_exists('skillTestedFlag', $job_data)) ? $job_data['skillTestedFlag'][$key] : 0,
								'skillTestResults' => (array_key_exists('skillTestResults', $job_data)) ? $job_data['skillTestResults'][$key] : 'NULL',
								'skillTotalMonths' => (array_key_exists('skillTotalMonths', $job_data)) ? $job_data['skillTotalMonths'][$key] : 'NULL',
								'skillTags' => (array_key_exists('skillTags', $job_data)) ? $job_data['skillTags'][$key] : 'NULL',
								'skillTagNames' => (array_key_exists('skillTagNames', $job_data)) ? $job_data['skillTagNames'][$key] : 'NULL',
								);
				}
				$job_data['jobSkills'] = $temp_skills;
				unset($job_data['skillIds']);
				unset($job_data['skillNames']);
				unset($job_data['skillPcts']);
				unset($job_data['skillTestedFlag']);
				unset($job_data['skillTestResults']);
				unset($job_data['skillTotalMonths']);
				unset($job_data['skillTags']);
				unset($job_data['skillTagNames']);
			}
			// now need to retrieve the highlights for each job
			$query = 'SELECT id, highlight FROM candidatejobhighlights WHERE jobId = ?';
			$highlights = pdo_exec( $request, $response, $db, $query, array($job_data['id']), 'Retrieving Candidate Job Highlights', 
									$errCode, false, true );
			if ($errCode) {
				return $db;
			}
			$highlights && $job_data['jobHighlights'] = $highlights;
		}
		$response_data['jobs'] = $jobs_data;
	}
	
	// get education
	$query = 'SELECT * FROM candidate_education_vw WHERE candidateId = ?';
	$ed_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate Education', $errCode, false, true );
	if ($errCode) {
		return $db;
	}
	
	if ($ed_data) {
		// loop through each job and explode out the pipe | delimited skill lists.
		foreach ($ed_data as &$ed_row) {
			if (array_key_exists('skillIds', $ed_row))  {
				$ed_row['skillIds'] = explode('|', $ed_row['skillIds']);
				$ed_row['skillNames'] = explode('|', $ed_row['skillNames']);
				$ed_row['skillPcts'] = explode('|', $ed_row['skillPcts']);
				$temp_skills = array();
				foreach ( $ed_row['skillIds'] as $key => $skillId) {
					$temp_skills[] = array('SkillId' => $skillId, 
											'skillName' => $ed_row['skillNames'][$key],
											'skillPct' => $ed_row['skillPcts'][$key]);
				}
				$ed_row['educationSkills'] = $temp_skills;
				unset($ed_row['skillIds']);
				unset($ed_row['skillNames']);
				unset($ed_row['skillPcts']);
			}
		}
		$response_data['education'] = $ed_data;
	}
	
	// get certifications
	$query = 'SELECT * FROM candidate_certifications_vw WHERE candidateId = ?';
	$cert_data = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate Certifications', $errCode, false, true );
	if ($errCode) {
		return $db;
	}
	
	if ($cert_data) {
		// loop through each job and explode out the pipe | delimited skill lists.
		foreach ($cert_data as &$cert_row) {
			if (array_key_exists('skillIds', $cert_row))  {
				$cert_row['skillIds'] = explode('|', $cert_row['skillIds']);
				$cert_row['skillNames'] = explode('|', $cert_row['skillNames']);
				$cert_row['skillPcts'] = explode('|', $cert_row['skillPcts']);
				$temp_skills = array();
				foreach ( $cert_row['skillIds'] as $key => $skillId) {
					$temp_skills[] = array('SkillId' => $skillId,
							'skillName' => $cert_row['skillNames'][$key],
							'skillPct' => $cert_row['skillPcts'][$key]);
				}
				$cert_row['certificationSkills'] = $temp_skills;
				unset($cert_row['skillIds']);
				unset($cert_row['skillNames']);
				unset($cert_row['skillPcts']);
			}
		}
		$response_data['certifications'] = $cert_data;
	}
	
	// read in social media
	$query = 'SELECT id, socialType, socialLink FROM candidatesocialmedia WHERE candidateId = ?';
	$social_media = pdo_exec( $request, $response, $db, $query, array($id), 'Retrieving Candidate Social Media', $errCode, false, true );
	if ($errCode) {
		return $db;
	}
	$social_media && $response_data['socialMedia'] = $social_media;
	
	$data = array ('data' => $response_data );
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
} );