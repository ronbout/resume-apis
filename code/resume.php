<!-- template.html  -->
<!DOCTYPE html>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Resume</title>
	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" 
		integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
		
	<link href="https://fonts.googleapis.com/css?family=Signika" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css?family=Cantarell" rel="stylesheet">
	<link rel="stylesheet" media="all" href="styles/resume.css"/>


</head>
<body>
<?php

set_time_limit(10 * 60);

function curl_load_file( $url, $post_string = null, $request_type = 'POST' ) {
	 // create curl resource
	$ch = curl_init();

	// set url
	curl_setopt($ch, CURLOPT_URL, $url);

	//return the transfer as a string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
    curl_setopt($ch, CURLOPT_TIMEOUT, 180); 

	curl_setopt($ch, CURLOPT_USERAGENT, 'localhost test');
	
	if ($request_type == 'POST') {
		curl_setopt($ch, CURLOPT_POST, 1);
	} else {
		// request_type could be PUT or DELETE
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
	}
	
	if ($request_type != 'DELETE') {
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_string) );
	}
	
	// set up http header fields

	$headers = array(
		'Accept: text/json',
		'Pragma: no-cache',
		'Content-Type: application/x-www-form-urlencoded',
		'Connection: keep-alive'
	);
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	
	// add code to accept https certificate
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	// $output contains the output string
	$output = curl_exec($ch);
	// close curl resource to free up system resources
	curl_close($ch); 
	return $output;
}

$url = 'http://localhost/api/candidates/7?api_cc=three&api_key=fj49fk390gfk3f50';

$ret = curl_load_file($url, array(), 'GET');

// echo  var_dump($ret);

$tmp = json_decode($ret);

$candidate = $tmp->data;

//echo '<br><h1>', $candidate->person->personFormattedName, '</h1>';

build_resume($candidate);

////*************************************************************

function build_resume( $c ) {
	?>
	<div class="container-fluid">
		<div class="row" id="resume-container">
			<div class="col-md-2"></div>
			<div class="col-md-8 full-resume">
				<div class="red-bar"></div>
				<div class="grey-bar"></div>
				<div id="resume-header-container">
					<span id="resume-header">
						<span id="header-name"><?php echo $c->person->personFormattedName; ?></span>
						<span id="header-title"><?php echo $c->jobs[0]->jobTitle; ?></span>
					</span>
				</div>
				<div class="row" id="pro-summary">
					<div class="col-md-2 pro-summary-title">
						Professional<br>Summary
					</div>
					<div class="col-md-9 pro-summary-highs">
						<ul class="highlight-list">
							<?php 
								foreach( $c->candidateHighlights as $highlight ) {
									echo '<li>', $highlight->highlight, '</li>';
								}
							
							?>
						</ul>
					</div>
				
				
				
				
				</div>
			
			
			</div>
			<div class="col-md-2"></div>
		</div>
	</div>
	<?php
}


?>
</body>
</html>
