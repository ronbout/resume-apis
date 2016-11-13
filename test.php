<?php

echo '<pre>';
// just a quick file for testing my api code snippets in a simple environment



// testing pulling out a series of fields from an array and turning them into a lower level array
// using OBJECT as my terminology as it will become JSON object


$testObject = array(
	'name' 		=> 'fred',
	'age'		=> 16,
	'myFacebook' 	=> 'freddie',
	'myGoogle+'	=> 'mrFred',
	'myInstagram'	=> 'superFred',
);

echo var_dump($testObject);

$testObject2 = runLowerObject($testObject, 'socialMedia', function($v) {
	return 'my' . ucfirst($v);
});

//echo var_dump($testObject);

echo var_dump($testObject2);

echo '</pre>';


// ***********************************

function runLowerObject( $obj, $newObjName, $fieldList = null ) {

	$social_fields = array('facebook', 'google+', 'instagram');

	if ( is_callable($fieldList) ) {
		// we have a callback function to run against the default field list
		$flds = array_map($fieldList, $social_fields );
	} elseif ( is_array($fieldList) ) {
		$flds = $fieldList;
	} else {
		$flds = $social_fields;
	}
	
	return createLowerObject( $obj, $newObjName, $flds );
}

function createLowerObject( $obj, $newObjName, $fieldList ) {
	$tmpObj = array();
	
	foreach( $fieldList as $fld ) {
		if ( array_key_exists($fld, $obj) ) {
			$tmpObj[$fld] = $obj[$fld];
			unset($obj[$fld]);
		}
	}
	
	count($tmpObj) && $obj[$newObjName] = $tmpObj;	
	return $obj;
}