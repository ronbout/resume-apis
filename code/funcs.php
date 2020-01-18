<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\UploadedFileInterface;


function get_pass_info($companyCode)
{
	$passfile = ($_SERVER['HTTP_HOST'] == 'localhost') ?
		'C:\Users\ronbo\Documents\passwords\apis\\' . $companyCode . ".txt"
		: "/home/ronbout/passwords/apis/" . $companyCode . ".txt";

	return @file($passfile);
}


function get_imgs_dir()
{
	$imgs_dir = ($_SERVER['HTTP_HOST'] == 'localhost') ?
		'C:\Users\ronbo\Documents\htdocs\3sixd\imgs'
		: "/var/www/html/3sixd/imgs";

	return $imgs_dir;
}


function build_update_SQL_cols($post_data, $table_cols)
{
	$sql_cols = '';
	$col_names = array();

	for ($i = 0; $i < count($table_cols); $i++) {
		$col_name = $table_cols[$i];
		if (array_key_exists($col_name, $post_data)) {
			$col_string = isset($post_data[$col_name]) ? trim(filter_var(
				$post_data[$col_name],
				FILTER_SANITIZE_STRING,
				FILTER_FLAG_NO_ENCODE_QUOTES
			)) : null;
			// REST cannot send null value, so using #N/A
			$col_string = ($col_string == '#N/A') ? null : $col_string;
			$sql_cols .= ($sql_cols) ? ', ' : '';
			$sql_cols .= $col_name . ' = ?';
			$col_names[] = $col_string;
		}
	}
	return array($sql_cols, $col_names);
}

function json_connect(Request $request, Response $response, $configLoc, &$errCode)
{
	// if there is an error, $errCode will be set and a Response will be returned
	// otherwise, the json file

	$data = array();
	$errCode = 0;
	// have to get company code and api key..or error
	$query = $request->getQueryParams();
	if (!isset($query['api_cc']) || !isset($query['api_key'])) {
		$errCode = -3;
		$data['error'] = true;
		$data['message'] = 'Company Code and API Key are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	$passInfo = get_pass_info($query['api_cc']);
	if (!$passInfo) {
		$errCode = -1;   // error -1 could not retrieve user/password info
		$data['error'] = true;
		$data['message'] = 'Error retrieving api information.';
		$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	$apiKey = trim($passInfo[3]);

	if ($apiKey != $query['api_key']) {
		$errCode = -2; // error code -2 invalid api key
		$data['error'] = true;
		$data['message'] = 'Invalid API Key.';
		$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	if (!$configFile = @file_get_contents($configLoc)) {
		$errCode = -4; // error code -4 could not get config file
		$data['error'] = true;
		$data['message'] = 'Error retrieving config file.';
		$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	return json_decode($configFile, true);
}

function db_connect(Request $request, Response $response, &$errCode)
{
	// if there is an error, $errCode will be set and a Response will be returned
	// otherwise, the db connection will be returned

	$data = array();
	// have to get company code and api key..or error
	$query = $request->getQueryParams();
	if (!isset($query['api_cc']) || !isset($query['api_key'])) {
		$errCode = -3;
		$data['error'] = true;
		$data['message'] = 'Company Code and API Key are required.';
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	// connect to database
	$errCode = '';
	if (!($db = pdoConnect($query['api_cc'], $errCode, $query['api_key']))) {
		switch ($errCode) {
			case -2:
				$data['error'] = true;
				$data['message'] = 'Invalid API Key.';
				$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK);
				return $newResponse;
				break;
			case -1:
			default:
				$data['error'] = true;
				$data['message'] = 'Database Connection Error: ' . $errCode;
				$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK);
				return $newResponse;
		}
	}
	$errCode = 0;
	return $db;
}

function pdoConnect($companyCode, &$errorCode, $apiKeySent)
{
	// connects to database $db using PDO for mysql
	// must get user and password from passwords dir
	// returns either PDO object or error code

	$errorCode = 0;
	$passInfo = get_pass_info($companyCode);
	if (!$passInfo) {
		$errorCode = -1;   // error -1 could not retrieve user/password info
		return false;
	}

	$user = trim($passInfo[0]);
	$password = trim($passInfo[1]);
	$db = trim($passInfo[2]);
	$apiKey = trim($passInfo[3]);
	$host = trim($passInfo[4]);

	if ($apiKey != $apiKeySent) {
		$errorCode = -2; // error code -2 invalid api key
		return false;
	}

	$opts = array(
		PDO::MYSQL_ATTR_FOUND_ROWS => true,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	);
	try {
		$cn = new PDO('mysql:dbname=' . $db . ';host=' . $host, $user, $password, $opts);
	} catch (PDOException $e) {
		$errorCode = $e->getMessage();
		return false;
	}
	return $cn;
}

function pdo_exec(Request $request, Response $response, $db, $query, $execArray, $errMsg, &$errCode, $checkCount = false, $ret_array_flg = false, $return_flg = true, $filter_flg = true)
{
	$stmt = $db->prepare($query);
	$data = array();

	if (!$stmt->execute($execArray)) {
		$errCode = true;
		$data['error'] = true;
		$data['errorCode'] = $stmt->errorCode();
		$data['message'] = 'Database SQL Error ' . $errMsg . ' ' . $stmt->errorCode() . ' - ' . $stmt->errorInfo()[2];
		$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
		return $newResponse;
	}

	// the stmt rowCount only matters if we are supposed to return a value
	if ($stmt->rowCount() == 0 && ($ret_array_flg || $return_flg)) {
		if ($checkCount) {
			$errCode = true;
			$data = array();
			$data['error'] = true;
			$data['errorCode'] = 45002;  // this will just be our error code for Not Found
			$data['message'] = 'No records Found';
			$newResponse = $response->withJson($data, 200, JSON_NUMERIC_CHECK);
			return $newResponse;
		} elseif ($ret_array_flg) {
			return array();
		} else {
			return null;
		}
	}

	if ($ret_array_flg) {
		$ret_data = array();
		while (($info = $stmt->fetch(PDO::FETCH_ASSOC))) {
			$ret_data[] = array_filter($info, function ($val) use ($filter_flg) {
				return !($filter_flg) || $val !== null;
			});;
		}
		return $ret_data;
	} elseif ($return_flg) {
		return array_filter($stmt->fetch(PDO::FETCH_ASSOC), function ($val) use ($filter_flg) {
			return !$filter_flg || $val !== null;
		});;
	} else {
		return true;
	}
}

function strip_prefix($field, $prefix)
{
	return lcfirst(str_replace($prefix, '', $field));
}

function create_lower_object($orig_obj, $obj_str, $new_field = null, $new_objfields = null)
{
	// need to create sub Objects for api returns from view returns.
	// views use prefix ("person", "agency") to separate fields from
	// main fields.  Strip prefix and create new object (person: {})
	// are actually arrays, but will be objects after json conversion
	$tmp_obj = array();
	$prefix_len = strlen($obj_str);
	if (!$new_field) $new_field = $obj_str;

	$ret_obj = array_filter($orig_obj, function ($val, $key) use ($prefix_len, $new_objfields, $obj_str, &$tmp_obj) {
		if (substr($key, 0, $prefix_len) == $obj_str) {
			// only include in new object if foundin new_fields or new_fileds is null
			if ($new_objfields == null || array_search($key, $new_objfields) !== false) {
				$tmp_obj[strip_prefix($key, $obj_str)] = $val;
			}
			return false;
		} else {
			return true;
		}
	}, ARRAY_FILTER_USE_BOTH);

	$ret_obj[$new_field] = $tmp_obj;
	return $ret_obj;
}

function create_obj_from_arrays($data_arrays, $label_arrays)
{
	// the 1st parm contains arrays of data arrays.  Each top level element
	// is the data for a single field in the final object.  Each label array
	// element is the object field label for the corresponding data.
	// i.e. data_arrays = array(array(4,7,22), array('java', 'html', 'css')
	// i.e. $label_arrays = array('id', 'skillName')
	// final result = array( array('id' => 4, 'skillName' => 'java'), ...)

	if (!is_array($data_arrays) || !is_array($label_arrays)) return null;
	$fld_cnt = count($data_arrays);
	if ($fld_cnt !== count($label_arrays)) return null;

	$ret_array = array();
	$data_cnt = count($data_arrays[0]);

	for ($i = 0; $i < $data_cnt; $i++) {
		// take the i'th data from each of the data arrays
		// and put into the ret array based on the labels

		$tmp_array = array();
		for ($j = 0; $j < $fld_cnt; $j++) {
			$tmp_array[$label_arrays[$j]] = $data_arrays[$j][$i];
		}
		$ret_array[] = $tmp_array;
	}

	return $ret_array;
}

function moveUploadedFile($directory, $basename, UploadedFileInterface $uploadedFile)
{
	$extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
	$filename = $basename . '.' . $extension;

	$uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

	return $filename;
}
