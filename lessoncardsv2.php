<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
	<title>OLE Lessons</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</head>

<body>

<?php
/*
 * Tap the D9 API via Curl for JSON encoded data
 *
 */

 // create a new cURL resource
$ch = curl_init();

// set URL and other appropriate options
curl_setopt($ch, CURLOPT_URL, "http://durango.calidev.org/api/v2/lessons_list?_format=hal_json");
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

// grab URL and pass it to the browser
$result = curl_exec($ch);

$lessons = (json_decode($result, true));
//var_dump ($lessons);
foreach ($lessons as $lesson) {
	$title = $lesson["title"];
	$body = $lesson["body"];
	$author = $lesson["field_author_s_"];
	$topics = $lesson["field_cali_topic"];
	$time = $lesson["field_lesson_completion_time"];
	$uri = $lesson["field_start_lesson"];
	$querystring = "name=Sam%20Goshorn&runid=54321&save=/dbsavedatahere/54321"
?>
<div class="row">
  <div class="col-sm-6">
    <div class="card">
      <div class="card-body">
        <h2 class="card-title"><?php echo $title; ?></h2>
        <div class="card-text"><p><?php echo $body; ?></p>
					<p>Author: <?php echo $author; ?></p>
					<p>CALI Topics: <?php echo $topics; ?></p>
					<p>Completion Time: <?php echo $time; ?></p>
				</div>
        <a href="<?php echo $uri; ?>?<?php echo $querystring; ?>" target="_blank" class="btn btn-primary">Work on this!</a>
      </div>
    </div>
  </div>
</div>
<?php	
}

// close cURL resource, and free up system resources
curl_close($ch);
?>

</body>
</html>
