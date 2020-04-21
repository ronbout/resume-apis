<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/skills', function (Request $request, Response $response) {
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

    $query = 'SELECT Id "id", name, description, url from skill ORDER BY name '.$limit_clause;

    if (!$result = $db->query($query)) {
        $data['error'] = true;
        $data['message'] = 'Database SQL Error Retrieving skills: '.$result->errorCode().' - '.$result->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }

    $response_data = [];
    while (($info = $result->fetch(PDO::FETCH_ASSOC))) {
        $response_data[] = $info;
    }

    $data = ['data' => $response_data];
    $newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->get('/skills/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $data = [];

    if (!$id) {
        $data['error'] = true;
        $data['message'] = 'Id is required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }
    $stmt = $db->prepare('SELECT Id "id", name, description, url from skill WHERE Id = ?');

    if (!$stmt->execute([$id])) {
        $data['error'] = true;
        $data['message'] = 'Database SQL Error Retrieving skill: '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }

    if ((0 == $stmt->rowCount())) {
        $data['error'] = false;
        $data['message'] = 'No records Found';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    $response_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // get the related Techtags
    $tag_data = get_skill_techtags($request, $response, $db, $id, $errCode, false);
    if ($errCode) {
        return $tag_data;
    }

    $response_data['techtags'] = $tag_data;

    // get the Parent Skills
    $parent_data = get_parent_skills($request, $response, $db, $id, $errCode, false);
    if ($errCode) {
        return $tag_data;
    }

    $response_data['parentSkills'] = $parent_data;

    // get the Child Skills
    $child_data = get_child_skills($request, $response, $db, $id, $errCode, false);
    if ($errCode) {
        return $tag_data;
    }

    $response_data['childSkills'] = $child_data;

    // need entire trees as well for preventing the addition of related
    // skills that cause a circular relationship
    $parents = get_skill_tree($request, $response, $db, $id, $errCode, 'get_parent_skills', 'parents');
    if ($errCode) {
        return $parents;
    }
    $response_data['parentTree'] = $parents;
    $children = get_skill_tree($request, $response, $db, $id, $errCode, 'get_child_skills', 'children');
    if ($errCode) {
        return $children;
    }
    $response_data['childTree'] = $children;

    $data = ['data' => $response_data];
    $newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->get('/skills/search/{srch}', function (Request $request, Response $response) {
    $srch = $request->getAttribute('srch');
    $data = [];

    $query = $request->getQueryParams();

    $techtag_id = isset($query['techtag']) ? filter_var($query['techtag']) : '';

    if (!$srch) {
        $data['error'] = true;
        $data['message'] = 'Search field is required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

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

    if ('' !== $techtag_id) {
        $query = 'SELECT s.id, s.name, s.description, s.url 
		FROM skill s, skills_techtags st
		WHERE name LIKE ? 
		AND s.id = st.SkillId
		AND st.techtagId = '.$techtag_id.'
		ORDER BY name ';
    } else {
        $query = 'SELECT id, name, description, url 
							FROM skill 
							WHERE name LIKE ? 
							ORDER BY name ';
    }

    $stmt = $db->prepare($query.$limit_clause);

    // add wildcards to search string...may be based on parameters at some point
    // TODO: provide different types of searches with and w/o various wildcards
    $srch = '%'.$srch.'%';

    if (!$stmt->execute([
        $srch,
    ])) {
        $data['error'] = true;
        $data['message'] = 'Database SQL Error Retrieving skill: '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }

    if ((0 == $stmt->rowCount())) {
        // this is not an error so just return an empty data array
        $data['data'] = [];

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    $response_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [
        'data' => $response_data,
    ];
    $newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->post('/skills', function (Request $request, Response $response) {
    $post_data = $request->getParsedBody();
    $data = [];

    $name = isset($post_data['name']) ? filter_var(trim($post_data['name']), FILTER_SANITIZE_STRING) : null;
    $description = isset($post_data['description']) ? filter_var($post_data['description'], FILTER_SANITIZE_STRING) : null;
    $url = isset($post_data['url']) ? filter_var($post_data['url'], FILTER_SANITIZE_STRING) : null;
    $techtags = isset($post_data['techtags']) ? $post_data['techtags'] : [];
    $parent_skills = isset($post_data['parentSkills']) ? $post_data['parentSkills'] : [];
    $child_skills = isset($post_data['childSkills']) ? $post_data['childSkills'] : [];

    if (!$name) {
        $data['error'] = true;
        $data['message'] = 'Name is required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    // need to make sure that skill name does not already exist as it must be unique
    $stmt = $db->prepare('SELECT * from skill WHERE name = ?');

    if (!$stmt->execute([$name])) {
        $data['error'] = true;
        $data['message'] = 'Database SQL Error Accessing Skill table: '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }

    if (($stmt->rowCount())) {
        $data['error'] = true;
        $data['errorCode'] = 45001; // just a custom error for duplicate value
        $data['message'] = "Skill {$name} already exists";

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    $stmt = $db->prepare('INSERT INTO skill (name, description, url) VALUES ( ?,?,? )');

    if (!$stmt->execute([
        $name,
        $description,
        $url,
    ]) || (0 == $stmt->rowCount())) {
        $data['error'] = true;
        $data['message'] = 'Database SQL Error Inserting Skill: '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }

    // get new skill id to use for related inserts
    $skill_id = $db->lastInsertId();

    // add skill techtags if they exist
    if (count($techtags)) {
        $insert_data = [];

        $query = 'INSERT INTO skills_techtags VALUES ';
        foreach ($techtags as $tag) {
            $query .= ' (?, ?),';
            $insert_data[] = $skill_id;
            $insert_data[] = $tag['id'];
        }

        // have to remove final comma
        $query = trim($query, ',');

        $response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Skill Techtags', $errCode, false, false, false);
        if ($errCode) {
            return $response_data;
        }
    }

    // add parent skills to skill_relations if they exist
    if (count($parent_skills)) {
        $insert_data = [];

        $query = 'INSERT INTO skill_relations 
							(parentSkillId, childSkillId) 
							VALUES ';
        foreach ($parent_skills as $pskill) {
            $query .= ' (?, ?),';
            $insert_data[] = $pskill['id'];
            $insert_data[] = $skill_id;
        }

        // have to remove final comma
        $query = trim($query, ',');

        $response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Parent Skills', $errCode, false, false, false);
        if ($errCode) {
            return $response_data;
        }
    }

    // add child skills to skill_relations
    if (count($child_skills)) {
        $insert_data = [];

        $query = 'INSERT INTO skill_relations 
							(parentSkillId, childSkillId) 
							VALUES ';
        foreach ($child_skills as $cskill) {
            $query .= ' (?, ?),';
            $insert_data[] = $skill_id;
            $insert_data[] = $cskill['id'];
        }

        // have to remove final comma
        $query = trim($query, ',');

        $response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Child Skills', $errCode, false, false, false);
        if ($errCode) {
            return $response_data;
        }
    }

    // everything was fine. return success
    $data['id'] = $skill_id;
    $data['name'] = $name;
    $data['description'] = $description;
    $data['url'] = $url;
    // wrap it in data object
    $data = [
        'data' => $data,
    ];

    return $response->withJson($data, 201, JSON_NUMERIC_CHECK);
});

$app->put('/skills/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $post_data = $request->getParsedBody();
    $data = [];
    $table_cols = [
        'name',
        'description',
        'url',
    ];

    // make sure that at least one field exists for updating
    // return val is array with <0> = sql and <1> = array for executing prepared statement
    $sql_cols = build_update_SQL_cols($post_data, $table_cols);
    $sql_update_cols = $sql_cols[0];
    $sql_array = $sql_cols[1];

    if (!$id || !$sql_update_cols) {
        $data['error'] = true;
        $data['message'] = 'Id and at least one column are required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // this api assumes that techtags and skill relations are always included, if they exist.
    $techtags = isset($post_data['techtags']) ? $post_data['techtags'] : [];
    $parent_skills = isset($post_data['parentSkills']) ? $post_data['parentSkills'] : [];
    $child_skills = isset($post_data['childSkills']) ? $post_data['childSkills'] : [];

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    // need to make sure that this record id exists to update
    $stmt = $db->prepare('SELECT * from skill WHERE Id = ?');

    if (!$stmt->execute([$id])) {
        $data['error'] = true;
        $data['message'] = 'Database SQL Error Retrieving skill: '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }

    if ((0 == $stmt->rowCount())) {
        $data['error'] = false;
        $data['message'] = "Skill {$id} not found in the database";

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // have to build SQL based on which fields were passed in_array
    $sql = 'UPDATE skill SET '.$sql_cols[0].' WHERE Id = ?';

    $stmt = $db->prepare($sql);
    // add id to end of execute array
    $sql_array[] = $id;

    if (!$stmt->execute($sql_array) || (0 == $stmt->rowCount())) {
        $data['error'] = true;
        $data['message'] = 'Unable to update Skill: '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }

    // update techtags, delete original and create new ones
    $query = 'DELETE FROM skills_techtags 
						WHERE skillId = ?';

    $response_data = pdo_exec($request, $response, $db, $query, [$id], 'Deleting Skill Techtags', $errCode, false, false, false);
    if ($errCode) {
        return $response_data;
    }

    if (count($techtags)) {
        $insert_data = [];

        $query = 'INSERT INTO skills_techtags VALUES ';
        foreach ($techtags as $tag) {
            $query .= ' (?, ?),';
            $insert_data[] = $id;
            $insert_data[] = $tag['id'];
        }

        // have to remove final comma
        $query = trim($query, ',');

        $response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Skill Techtags', $errCode, false, false, false);
        if ($errCode) {
            return $response_data;
        }
    }

    // remove all skill relations, both parent and child in one SQL
    $query = 'DELETE FROM skill_relations 
	WHERE childSkillId = ?
	OR parentSkillId = ?';

    $response_data = pdo_exec($request, $response, $db, $query, [$id, $id], 'Deleting Skill Relations', $errCode, false, false, false);
    if ($errCode) {
        return $response_data;
    }

    // update parent skills to skill_relations
    if (count($parent_skills)) {
        $insert_data = [];

        $query = 'INSERT INTO skill_relations 
							(parentSkillId, childSkillId) 
							VALUES ';
        foreach ($parent_skills as $pskill) {
            $query .= ' (?, ?),';
            $insert_data[] = $pskill['id'];
            $insert_data[] = $id;
        }

        // have to remove final comma
        $query = trim($query, ',');

        $response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Parent Skills', $errCode, false, false, false);
        if ($errCode) {
            return $response_data;
        }
    }

    // update child skills to skill_relations
    if (count($child_skills)) {
        $insert_data = [];

        $query = 'INSERT INTO skill_relations 
							(parentSkillId, childSkillId) 
							VALUES ';
        foreach ($child_skills as $cskill) {
            $query .= ' (?, ?),';
            $insert_data[] = $id;
            $insert_data[] = $cskill['id'];
        }

        // have to remove final comma
        $query = trim($query, ',');

        $response_data = pdo_exec($request, $response, $db, $query, $insert_data, 'Creating Child Skills', $errCode, false, false, false);
        if ($errCode) {
            return $response_data;
        }
    }

    // everything was fine. return success
    // let's get the full record and return it, just in case...may remove later
    $stmt = $db->prepare('SELECT Id "id", name, description, url from skill WHERE Id = ?');

    if (!$stmt->execute([$id])) {
        // had trouble retrieving full record so just return post data
        $data = ['data' => $post_data];

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    $response_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // wrap it in data object
    $data = ['data' => $response_data];

    return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->delete('/skills/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $data = [];

    if (!$id) {
        $data['error'] = true;
        $data['message'] = 'Id is required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }
    $stmt = $db->prepare('DELETE FROM skill WHERE Id = ?');

    if (!$stmt->execute([
        $id,
    ]) || (0 == $stmt->rowCount())) {
        $data['error'] = true;
        $data['message'] = 'Unable to delete Skill '.$id.' : '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }
    // everything was fine. return success
    $data['error'] = false;
    $data['message'] = 'Skill successfully deleted';

    return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->get('/skill_techtags/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $data = [];

    if (!$id) {
        $data['error'] = true;
        $data['message'] = 'Id is required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    $response_data = get_skill_techtags($request, $response, $db, $id, $errCode, true);
    if ($errCode) {
        return $response_data;
    }

    $data = ['data' => $response_data];
    $newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->delete('/skill_techtags/{skillid}/{techtagId}', function (Request $request, Response $response) {
    $skillId = $request->getAttribute('skillid');
    $techtagId = $request->getAttribute('techtagId');
    $data = [];

    if (!$skillId && !$techtagId) {
        $data['error'] = true;
        $data['message'] = "Skill and Tag ID's are required.";

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    if ('all' == strtolower($techtagId)) {
        $stmt = $db->prepare('DELETE FROM skills_techtags WHERE skillId = ?');
        $executeArray = [$skillId];
    } else {
        $stmt = $db->prepare('DELETE FROM skills_techtags WHERE skillId = ? AND techtagId = ?');
        $executeArray = [$skillId, $techtagId];
    }

    if (!$stmt->execute($executeArray) || (0 == $stmt->rowCount() && 'all' != strtolower($techtagId))) {
        $data['error'] = true;
        $data['message'] = 'Unable to delete skills_techtags '.$skillId.'-'.$techtagId.' : '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

        return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
    }
    // everything was fine. return success
    $data['error'] = false;
    $data['message'] = 'skills_techtags successfully deleted';

    return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->post('/skill_techtags', function (Request $request, Response $response) {
    $post_data = $request->getParsedBody();
    $data = [];

    $skillId = isset($post_data['skillid']) ? filter_var($post_data['skillid'], FILTER_SANITIZE_STRING) : null;
    $techtagIds = isset($post_data['techtagIds']) ? filter_var_array($post_data['techtagIds'], FILTER_SANITIZE_STRING) : null;

    if (!$skillId || !$techtagIds) {
        $data['error'] = true;
        $data['message'] = 'Skill id and Tag ids are required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    $stmt = $db->prepare('INSERT IGNORE INTO skills_techtags (skillid, techtagId ) VALUES ( ?,? )');

    // loop through the array of techtagIds
    foreach ($techtagIds as $techtagId) {
        if (!$stmt->execute([$skillId, $techtagId]) || (0 == $stmt->rowCount())) {
            $data['error'] = true;
            $data['message'] = 'Database SQL Error Inserting skills_techtags: '.$stmt->errorCode().' - '.$stmt->errorInfo()[2];

            return $response->withJson($data, 500, JSON_NUMERIC_CHECK);
        }
    }

    // everything was fine. return success
    $data['error'] = false;
    $data['message'] = 'skills_techtagss successfully created';

    return $response->withJson($data, 201, JSON_NUMERIC_CHECK);
});

$app->get('/skill_parentskills/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $data = [];

    if (!$id) {
        $data['error'] = true;
        $data['message'] = 'Id is required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    $response_data = get_parent_skills($request, $response, $db, $id, $errCode, true);
    if ($errCode) {
        return $response_data;
    }

    $data = ['data' => $response_data];
    $newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

$app->get('/skill_childskills/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $data = [];

    if (!$id) {
        $data['error'] = true;
        $data['message'] = 'Id is required.';

        return $response->withJson($data, 200, JSON_NUMERIC_CHECK);
    }

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
    }

    $response_data = get_child_skills($request, $response, $db, $id, $errCode, true);
    if ($errCode) {
        return $response_data;
    }

    $data = ['data' => $response_data];
    $newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

// returns parent and child skill trees all the way up and down
$app->get('/skill/relatedtree/{id}', function (Request $request, Response $response) {
		$ids = $request->getAttribute('id');
		$query = $request->getQueryParams();
    $data = [];

    // login to the database. if unsuccessful, the return value is the
    // Response to send back, otherwise the db connection;
    $errCode = 0;
    $db = db_connect($request, $response, $errCode);
    if ($errCode) {
        return $db;
		}
		
		// add in ability to request only child or parent tree with
		// query var ttype=parent | ttype=child
		$ttype = isset($query['ttype']) && ($query['ttype'] === 'parent' || $query['ttype'] === 'child') ? $query['ttype'] : '';

		// id can be comma-delimited list so convert to array and loop
		$idList = explode(',', $ids);

		foreach($idList as $id) {
			$retTree = array('skillId' => $id);
			if (!$ttype || $ttype === 'parent') {
				$parents = get_skill_tree($request, $response, $db, $id, $errCode, 'get_parent_skills', 'parents');
				if ($errCode) {
						return $parents;
				}
	
				$retTree['parentTree'] = $parents;
			}

			if (!$ttype || $ttype === 'child') {
				$children = get_skill_tree($request, $response, $db, $id, $errCode, 'get_child_skills', 'children');
				if ($errCode) {
						return $children;
				}
				$retTree['childTree'] = $children;
			}

			$data[] = $retTree;
		}

    $data = ['data' => $data];
    $response->withJson($data, 200, JSON_NUMERIC_CHECK);
});

/**
 * subroutine for getting techtags for a single skill
 * which is used in two api endpoints (skill and skill_techtags).
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $db
 * @param mixed $skill_id
 * @param mixed $errCode
 * @param mixed $check_count
 */
function get_skill_techtags($request, $response, $db, $skill_id, &$errCode = 0, $check_count = false)
{
    $parms = [];
    $parms['main_id'] = $skill_id;
    $parms['rel_table'] = 'skills_techtags';
    $parms['rel_main_id'] = 'skillId';
    $parms['rel_secondary_id'] = 'techtagId';
    $parms['disp_table'] = 'techtag';
    $parms['disp_table_id'] = 'id';
    $parms['disp_fields'] = ['id', 'name', 'description'];
    $parms['order_name'] = 'name';

    return get_one_to_many($request, $response, $db, $parms, $errCode, false);
}

/**
 * subroutine for getting techtags for a single skill
 * which is used in two api endpoints (skill and skill_techtags).
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $db
 * @param mixed $skill_id
 * @param mixed $errCode
 * @param mixed $check_count
 */
function get_parent_skills($request, $response, $db, $skill_id, &$errCode = 0, $check_count = false)
{
    $parms = [];
    $parms['main_id'] = $skill_id;
    $parms['rel_table'] = 'skill_relations';
    $parms['rel_main_id'] = 'childSkillId';
    $parms['rel_secondary_id'] = 'parentSkillId';
    $parms['disp_table'] = 'skill';
    $parms['disp_table_id'] = 'id';
    $parms['disp_fields'] = ['id', 'name', 'description'];
    $parms['order_name'] = 'name';

    return get_one_to_many($request, $response, $db, $parms, $errCode, false);
}

/**
 * subroutine for getting techtags for a single skill
 * which is used in two api endpoints (skill and skill_techtags).
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $db
 * @param mixed $skill_id
 * @param mixed $errCode
 * @param mixed $check_count
 */
function get_child_skills($request, $response, $db, $skill_id, &$errCode = 0, $check_count = false)
{
    $parms = [];
    $parms['main_id'] = $skill_id;
    $parms['rel_table'] = 'skill_relations';
    $parms['rel_main_id'] = 'parentSkillId';
    $parms['rel_secondary_id'] = 'childSkillId';
    $parms['disp_table'] = 'skill';
    $parms['disp_table_id'] = 'id';
    $parms['disp_fields'] = ['id', 'name', 'description'];
    $parms['order_name'] = 'name';

    return get_one_to_many($request, $response, $db, $parms, $errCode, false);
}

/**
 * recursive subroutine for building a multi-dimensional array of
 * parent skills going from the current skill to the highest levels of
 * each branch.
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $db
 * @param mixed $skill_id
 * @param mixed $errCode
 * @param mixed $skill_fn
 * @param mixed $skill_prop_name
 * @param mixed $skill_list
 */
function get_skill_tree($request, $response, $db, $skill_id, &$errCode = 0, $skill_fn, $skill_prop_name, $skill_list = [])
{
    // need to keep a list of skills so that if their is any circular references,
    // we do not get caught in an endless loop.  We will stop processing that branch
    $tree = [];

    $parent_skills = $skill_fn($request, $response, $db, $skill_id, $errCode);
    if ($errCode) {
        return $parent_skills;
    }

    $tree = [];
    foreach ($parent_skills as $pskill) {
        $id = $pskill['id'];
        $tmp = ['id' => $id, 'name' => $pskill['name']];
        if (in_array($id, $skill_list)) {
            // *** CHANGED *** if already in the tree, put parents as null so will know it is a repeat
            // looks like I need to have the full tree with redundancies so that the front end can
            // handle deleting a related skill w/o having to rebuild the tree
            // going to keep structure in place for now in case that changes.
            $next_tree = get_skill_tree($request, $response, $db, $id, $errCode, $skill_fn, $skill_prop_name, $skill_list);
            if ($next_tree) {
                $tmp[$skill_prop_name] = $next_tree;
            }
        } else {
            $skill_list[] = $id;
            $next_tree = get_skill_tree($request, $response, $db, $id, $errCode, $skill_fn, $skill_prop_name, $skill_list);
            if ($next_tree) {
                $tmp[$skill_prop_name] = $next_tree;
            }
        }
        $tree[] = $tmp;
    }

    return $tree;
}

/**
 * subroutine for getting data from a one to many relationship
 * need to pass in relation table, fields to select on and
 * fields to retrieve (i.e. techtags need to get info from techtag)
 * parms :
 * 0 - main_id value
 * 1 - rel_table name
 * 2 - rel_main_id name
 * 3 - rel_secondary_id name
 * 4 - disp_table name
 * 5 - disp_table_id name
 * 6 - disp_fields array of field names
 * 7 - order_name.
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $db
 * @param mixed $parms
 * @param mixed $errCode
 * @param mixed $check_count
 */
function get_one_to_many($request, $response, $db, $parms, &$errCode = 0, $check_count = false)
{
    extract($parms);

    // check for offset and limit and add to Select
    $q_vars = array_change_key_case($request->getQueryParams(), CASE_LOWER);
    $limit_clause = '';
    if (isset($q_vars['limit']) && is_numeric($q_vars['limit'])) {
        $limit_clause .= ' LIMIT '.$q_vars['limit'].' ';
    }
    if (isset($q_vars['offset']) && is_numeric($q_vars['offset'])) {
        $limit_clause .= ' OFFSET '.$q_vars['offset'].' ';
    }

    $display_fields_sql = '';
    foreach ($disp_fields as $fname) {
        $display_fields_sql .= ' dt.'.$fname.',';
    }
    // have to remove final comma
    $display_fields_sql = trim($display_fields_sql, ',');

    $query = 'SELECT '.$display_fields_sql.'
						FROM '.$rel_table.' rt, '.$disp_table.' dt
						WHERE rt.'.$rel_secondary_id.' = dt.'.$disp_table_id.'
							AND rt.'.$rel_main_id.' = ? 
						ORDER BY dt.'.$order_name.$limit_clause;

    return pdo_exec($request, $response, $db, $query, [$main_id], 'Retrieving Skill Techtags', $errCode, $check_count, true, true, false);
}
