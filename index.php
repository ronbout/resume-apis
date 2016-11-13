<?php


require_once("code/funcs.php");
require_once('vendor/autoload.php');

$app = new \Slim\App;

require_once('code/webcontact.php');
require_once('code/tags.php');
require_once('code/skills.php');
require_once('code/companyconfig.php');
require_once('code/candidates.php');
require_once('code/company.php');
$app->run();