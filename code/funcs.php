<?php 

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


function get_pass_info($companyCode) {
	return @file('C:\Users\ron\Documents\passwords\apis\\'.$companyCode.".txt");
	//return @file('/var/www/passwords/apis/'.$companyCode.".txt");
}


function build_update_SQL_cols( $post_data, $table_cols ) {
	$sql_cols = '';
	$col_names = array();

	for ($i = 0; $i < count($table_cols); $i++) {
		$col_name = $table_cols[$i];
		if (array_key_exists($col_name, $post_data)) {
			$col_string = isset($post_data[$col_name]) ? filter_var($post_data[$col_name], FILTER_SANITIZE_STRING) : null;
			// REST cannot send null value, so using #N/A
			$col_string = ($col_string == '#N/A') ? null : $col_string;
			$sql_cols .= ($sql_cols) ? ', ' : '';
			$sql_cols .= $col_name . ' = ?';
			$col_names[] = $col_string;
		}
	}	
	return array($sql_cols, $col_names);
}

function json_connect(Request $request, Response $response, $configLoc, &$errCode) {
	// if there is an error, $errCode will be set and a Response will be returned
	// otherwise, the json file

	$data = array();
	$errCode = 0;
	// have to get company code and api key..or error
	$query = $request->getQueryParams();
	if ( !isset($query['api_cc']) || !isset($query['api_key']) ) {
		$errCode = -3;
		$data['error'] = true;
		$data['message'] = 'Company Code and API Key are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	
	$passInfo = get_pass_info($query['api_cc']);
	if (!$passInfo) {
		$errCode = -1;   // error -1 could not retrieve user/password info
		$data['error'] = true;
		$data['message'] = 'Error retrieving api information.';
		$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}

	$apiKey = trim($passInfo[3]);
	
	if ( $apiKey != $query['api_key'] ) {
		$errCode = -2; // error code -2 invalid api key
		$data['error'] = true;
		$data['message'] = 'Invalid API Key.';
		$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	
	if (! $configFile = @file_get_contents($configLoc)) {
		$errCode = -4; // error code -4 could not get config file
		$data['error'] = true;
		$data['message'] = 'Error retrieving config file.';
		$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	
	return json_decode($configFile, true);
}

function db_connect(Request $request, Response $response, &$errCode) {
	// if there is an error, $errCode will be set and a Response will be returned
	// otherwise, the db connection will be returned

	$data = array();
	// have to get company code and api key..or error
	$query = $request->getQueryParams();
	if ( !isset($query['api_cc']) || !isset($query['api_key']) ) {
		$errCode = -3;
		$data['error'] = true;
		$data['message'] = 'Company Code and API Key are required.';
		$newResponse = $response->withJson($data, 400, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	
	// connect to database
 	$errCode = '';
	if (!($db = pdoConnect($query['api_cc'], $errCode, $query['api_key']))) {
		switch($errCode) {
			case -2:
				$data['error'] = true;
				$data['message'] = 'Invalid API Key.';
				$newResponse = $response->withJson($data, 401, JSON_NUMERIC_CHECK );
				return $newResponse;
				break;
			case -1: 
			default:
				$data['error'] = true;
				$data['message'] = 'Database Connection Error: ' . $errCode;
				$newResponse = $response->withJson($data, 500, JSON_NUMERIC_CHECK );
				return $newResponse;
		}
	}	
	$errCode = 0;
	return $db;
}

function pdoConnect($companyCode, &$errorCode, $apiKeySent) {
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
	
	if ( $apiKey != $apiKeySent ) {
		$errorCode = -2; // error code -2 invalid api key
		return false;
	}
	
	$opts = array(
		PDO::MYSQL_ATTR_FOUND_ROWS => true,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	);
    try {
        $cn = new PDO('mysql:dbname=' . $db . ';host=127.0.0.1', $user, $password, $opts);
    } catch (PDOException $e) {
        $errorCode = $e->getMessage();
        return false;
    }
	return $cn;
}

function pdo_exec( Request $request, Response $response, $db, $query, $execArray, $errMsg, &$errCode, $checkCount = false, $ret_array = false ) {
	$stmt = $db->prepare ( $query );
	
	if (! $stmt->execute ( $execArray )) {
		$errCode = true;
		$data ['error'] = true;
		$data ['message'] = 'Database SQL Error ' . $errMsg . ' ' . $stmt->errorCode () . ' - ' . $stmt->errorInfo () [2];
		$newResponse = $response->withJson ( $data, 500, JSON_NUMERIC_CHECK );
		return $newResponse;
	}
	
	if ( $stmt->rowCount () == 0) {
		if ( $checkCount ) {
			$errCode = true;
			$data ['error'] = false;
			$data ['message'] = 'No records Found';
			$newResponse = $response->withJson ( $data, 200, JSON_NUMERIC_CHECK );
			return $newResponse;
		} else {
			return null;
		}
	}
	
	if ( $ret_array ) {
		$ret_data = array();
		while ( ($info = $stmt->fetch ( PDO::FETCH_ASSOC )) ) {
			$ret_data [] = array_filter($info, function($val) {
				return $val !== null;
			});;
		}
		return $ret_data;
	} else {
		return array_filter($stmt->fetch ( PDO::FETCH_ASSOC ), function($val) {
			return $val !== null;
		});;
	}

	

}

function gmail($to, $subject, $body, $from="", $toName="")
{
	// function to send email using PHPMailer
	// through gmail apps account with smtp
	

	include("class.phpmailer.php");
	include("class.smtp.php");
	// get email name and password
	$passInfo = file('C:\Users\ron\Documents\passwords\gmail.txt');
	//$passInfo = file('/var/www/passwords/gmail.txt');
	if (!is_array($passInfo)) return "Could not access gmail user/password info!";
	$mail             = new PHPMailer();

	$gmailUser = trim($passInfo[0]);
	$mail->IsSMTP();
	$mail->SMTPAuth   = true;                  // enable SMTP authentication
	$mail->SMTPSecure = "ssl";                 // sets the prefix to the servier
	$mail->Host       = "smtp.gmail.com";      // sets GMAIL as the SMTP server
	$mail->Port       = 465;                   // set the SMTP port

	$mail->Username   = $gmailUser;  			 // GMAIL username
	$mail->Password   = trim($passInfo[1]);            // GMAIL password

	$mail->From       = $gmailUser;
	$mail->FromName   = $from;
	$mail->Subject    = $subject;
	$mail->AltBody    = $body; //Text Body
	$mail->WordWrap   = 50; // set word wrap

	$mail->MsgHTML($body);

	$mail->AddReplyTo($gmailUser,$from);

	//$mail->AddAttachment("/path/to/file.zip");             // attachment
	//$mail->AddAttachment("/path/to/image.jpg", "new.jpg"); // attachment

	$mail->AddAddress($to,$toName);

	$mail->IsHTML(true); // send as HTML

	if(!$mail->Send())
	{
		return $mail->ErrorInfo;
	} 
	else 
	{
		return 0;
	}		
}