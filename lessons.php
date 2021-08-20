<?php
/*
 * Tap the D9 API via Curl for JSON encoded data
 *
 */

 // create a new cURL resource
$ch = curl_init();

// set URL and other appropriate options
curl_setopt($ch, CURLOPT_URL, "http://durango.calidev.org/api/lessons_list?_format=hal_json");
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

// grab URL and pass it to the browser
$result = curl_exec($ch);

$lessons = (json_decode($result, true));
var_dump ($lessons);
foreach ($lessons as $lesson) {
	echo 'nid ='. $lesson["nid"][0]["value"].' Title= '.$lesson["title"][0]["value"].'</br>';
	
}

// close cURL resource, and free up system resources
curl_close($ch);
?>