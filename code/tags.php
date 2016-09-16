<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/tags', function (Request $request, Response $response) {
	$data = array();

	// login to the database.  if unsuccessful, the return value is the
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

	$query = 'SELECT Id "id", name, description from techtags ORDER BY name ' . $limit_clause;

	if (!$result = $db->query($query)) {
		$data['error'] = true;
		$data['message'] = 'Database SQL Error Retrieving techtags: ' . $result->errorCode() . ' - ' . $result->errorInfo()[2];
		$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$response_data = array();
	while (($info = $result->fetch(PDO::FETCH_ASSOC))) {
		$response_data[] = $info;
	}

	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
});

$app->get('/tags/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute('id');
	$data = array();

	if (! $id) {
		$data ['error'] = true;
		$data ['message'] = 'Id is required.';
		$newResponse = $response->withJson ( $data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// login to the database.  if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}
	$stmt = $db->prepare('SELECT Id "id", name, description from techtags WHERE Id = ?');

	if (!$stmt->execute(array($id)) ) {
		$data['error'] = true;
		$data['message'] = 'Database SQL Error Retrieving techtags: ' . $stmt->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	if ( ($stmt->rowCount() == 0)) {
		$data['error'] = false;
		$data['message'] = 'No records Found';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$response_data = $stmt->fetch(PDO::FETCH_ASSOC);

	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
});

$app->get ( '/tags/search/{srch}', function (Request $request, Response $response) {
	$srch = $request->getAttribute ( 'srch' );
	$data = array ();

	if (! $srch) {
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

	// check for offset and limit and add to Select
	$q_vars = array_change_key_case($request->getQueryParams(), CASE_LOWER);
	$limit_clause = '';
	if (isset($q_vars['limit']) && is_numeric($q_vars['limit'])) {
		$limit_clause .= ' LIMIT ' . $q_vars['limit'] . ' ';
	}
	if (isset($q_vars['offset']) && is_numeric($q_vars['offset'])) {
		$limit_clause .= ' OFFSET ' . $q_vars['offset'] . ' ';
	}

	$stmt = $db->prepare ( 'SELECT Id "id", name, description from techtags WHERE name LIKE ? ORDER BY name ' . $limit_clause);

	// add wildcards to search string...may be based on parameters at some point
	// TODO: provide different types of searches with and w/o various wildcards
	$srch = '%'.$srch.'%';

	if (! $stmt->execute ( array (
			$srch
	) )) {
		$data ['error'] = true;
		$data ['message'] = 'Database SQL Error Retrieving Techtags: ' . $stmt->errorCode () . ' - ' . $stmt->errorInfo () [2];
		$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	if (($stmt->rowCount () == 0)) {
		$data ['error'] = false;
		$data ['message'] = 'No records Found';
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$response_data = $stmt->fetchAll ( PDO::FETCH_ASSOC );

	$data = array (
			'data' => $response_data
	);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
});

$app->post('/tags', function (Request $request, Response $response) {
	$post_data = $request->getParsedBody();
	$data = array();

	$name = isset($post_data['name']) ? filter_var($post_data['name'], FILTER_SANITIZE_STRING) : null ;
	$description = isset($post_data['description']) ? filter_var($post_data['description'], FILTER_SANITIZE_STRING) : null ;

	if (!$name) {
		$data['error'] = true;
		$data['message'] = 'Name is required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// login to the database.  if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}

	// need to make sure that tag name does not already exist as it must be unique
	$stmt = $db->prepare ( 'SELECT * from techtags WHERE name = ?' );
	
	if (! $stmt->execute ( array ($name) )) {
		$data ['error'] = true;
		$data ['message'] = 'Database SQL Error Accessing Techtags table: ' . $stmt->errorCode () . ' - ' . $stmt->errorInfo () [2];
		$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	
	if (($stmt->rowCount())) {
		$data ['error'] = true;
		$data ['message'] = "Tag $name already exists";
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}	
	
	$stmt = $db->prepare('INSERT INTO techtags (name, description) VALUES ( ?,? )');

	if (!$stmt->execute(array($name, $description)) || ($stmt->rowCount() == 0) ) {
		$data['error'] = true;
		$data['message'] = 'Database SQL Error Inserting Techtags: ' . $stmt->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	// everything was fine.  return success
	$data['id'] = $db->lastInsertId();
	$data['name'] = $name;
	$data['description'] = $description;
	// wrap it in data object
	$data = array('data' => $data);
	$newResponse = $response->withJson($data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
});

$app->put('/tags/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute('id');
	$post_data = $request->getParsedBody();
	$data = array();
	$table_cols = array('name', 'description');

	// make sure that at least one field exists for updating
	// return val is array with <0> = sql and <1> = array for executing prepared statement
	$sql_cols = build_update_SQL_cols($post_data, $table_cols);
	$sql_update_cols = $sql_cols[0];
	$sql_array = $sql_cols[1];

	if (!$id || !$sql_update_cols) {
		$data['error'] = true;
		$data['message'] = 'Id and at least one column are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// login to the database.  if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}
	
	// need to make sure that this record id exists to update
	$stmt = $db->prepare ( 'SELECT * from techtags WHERE Id = ?' );
	
	if (! $stmt->execute ( array ($id) )) {
		$data ['error'] = true;
		$data ['message'] = 'Database SQL Error Retrieving techtags: ' . $stmt->errorCode () . ' - ' . $stmt->errorInfo () [2];
		$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	
	if (($stmt->rowCount () == 0)) {
		$data ['error'] = false;
		$data ['message'] = "Tag $id not found in the database";
		$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// have to build SQL based on which fields were passed in_array
	$sql = 'UPDATE techtags SET ' . $sql_cols[0] . ' WHERE Id = ?';

	$stmt = $db->prepare($sql);
	// add id to end of execute array
	$sql_array[] = $id;

	if (!$stmt->execute($sql_array) || ($stmt->rowCount() == 0) ) {
		$data['error'] = true;
		$data['message'] = 'Unable to update Techtags: ' . $stmt->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	// everything was fine.  return success
	// let's get the full record and return it, just in case...may remove later
	$stmt = $db->prepare('SELECT Id "id", name, description from techtags WHERE Id = ?');

	if (!$stmt->execute(array($id)) ) {
		// had trouble retrieving full record so just return post data
		$data = array('data' => $post_data);
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$response_data = $stmt->fetch(PDO::FETCH_ASSOC);

	// wrap it in data object
	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
	return $newResponse;
});

$app->delete('/tags/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute('id');
	$data = array();

	if (!$id) {
		$data['error'] = true;
		$data['message'] = 'Id is required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// login to the database.  if unsuccessful, the return value is the
	// Response to send back, otherwise the db connection;
	$errCode = 0;
	$db = db_connect($request, $response, $errCode);
	if ($errCode) {
		return $db;
	}
	$stmt = $db->prepare('DELETE FROM techtags WHERE Id = ?');

	if (!$stmt->execute(array($id)) || ($stmt->rowCount() == 0) ) {
		$data['error'] = true;
		$data['message'] = 'Unable to delete Techtag '. $id .' : ' . $stmt->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	// everything was fine.  return success
	$data['error'] = false;
	$data['message'] = 'Techtag successfully deleted';
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
	return $newResponse;
});

$app->get('/tag_skills/{id}', function (Request $request, Response $response) {
	$id = $request->getAttribute('id');
	$data = array();

	if (!$id) {
		$data['error'] = true;
		$data['message'] = 'Id is required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	// login to the database.  if unsuccessful, the return value is the
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

	$stmt = $db->prepare('select s.Id "id", s.name, s.description, s.url
		FROM skill s, skill_tag st 
		where st.skillId = s.Id
		and st.tagId = ? ORDER BY s.name' . $limit_clause);

	if (!$stmt->execute(array($id)) ) {
		$data['error'] = true;
		$data['message'] = 'Database SQL Error Retrieving techtags: ' . $stmt->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	if ( ($stmt->rowCount() == 0)) {
		$data['error'] = false;
		$data['message'] = 'No records Found';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$response_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$data = array('data' => $response_data);
	$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK );
});

$app->delete ( '/tag_skills/{tagid}/{skillid}', function (Request $request, Response $response) {
	$skillId = $request->getAttribute ( 'skillid' );
	$tagId = $request->getAttribute('tagid');
	$data = array ();

	if (! $skillId && ! $tagId ) {
		$data ['error'] = true;
		$data ['message'] = "Skill and Tag ID's are required.";
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

	if (strtolower($skillId) == 'all') {
		$stmt = $db->prepare ( 'DELETE FROM skill_tag WHERE tagId = ?' );
		$executeArray = array( $tagId );
	} else {
		$stmt = $db->prepare ( 'DELETE FROM skill_tag WHERE skillId = ? AND tagId = ?' );
		$executeArray = array( $skillId, $tagId	);
	}

	if (! $stmt->execute ( $executeArray ) || ($stmt->rowCount () == 0 && strtolower($skillId) != 'all')) {
		$data ['error'] = true;
		$data ['message'] = 'Unable to delete Skill_Tag ' . $skillId . '-' . $tagId .' : ' . $stmt->errorCode () . ' - ' . $stmt->errorInfo () [2];
		$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	// everything was fine. return success
	$data ['error'] = false;
	$data ['message'] = 'Skill_Tag successfully deleted';
	$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
	return $newResponse;
} );

$app->post ( '/tag_skills', function (Request $request, Response $response) {
	$post_data = $request->getParsedBody ();
	$data = array ();
	// echo 'post data skill id: ', var_dump($post_data['skillid']);
	// echo 'post data tag ids: ', var_dump($post_data['tagids']);
	// die();
	$skillIds = isset ( $post_data ['skillids'] ) ? filter_var_array ( $post_data ['skillids'], FILTER_SANITIZE_STRING ) : null;
	$tagId = isset ( $post_data ['tagid'] ) ? filter_var ( $post_data ['tagid'], FILTER_SANITIZE_STRING ) : null;

	if (! $skillIds || ! $tagId ) {
		$data ['error'] = true;
		$data ['message'] = 'Tag id and Skill ids are required.';
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

	$stmt = $db->prepare ( 'INSERT IGNORE INTO skill_tag (skillid, tagid ) VALUES ( ?,? )' );

	// loop through the array of tagIds
	foreach($skillIds as $skillId) {

		if (! $stmt->execute (array ( $skillId, $tagId )) || ($stmt->rowCount () == 0)) {
			$data ['error'] = true;
			$data ['message'] = 'Database SQL Error Inserting Skill_Tag: ' . $stmt->errorCode () . ' - ' . $stmt->errorInfo () [2];
			$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
			return $newResponse;
		}
	}

	// everything was fine. return success
	$data ['error'] = false;
	$data ['message'] = 'Skill_Tags successfully created';
	$newResponse = $response->withJson ( $data, 201, JSON_NUMERIC_CHECK );
	return $newResponse;
} );