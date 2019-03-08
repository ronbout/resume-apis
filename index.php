<?php


require_once("code/funcs.php");
require_once('vendor/autoload.php');

$app = new \Slim\App;

$app->options('/{routes:.+}', function ($request, $response, $args) {
	return $response;
});

$app->add(function ($req, $res, $next) {
	$response = $next($req, $res);
	return $response
					->withHeader('Access-Control-Allow-Origin', '*')
					->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
					->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

require_once('code/webcontact.php');
require_once('code/tags.php');
require_once('code/skills.php');
require_once('code/companyconfig.php');
require_once('code/candidates.php');
require_once('code/company.php');
$app->run();