<?php
// 1. Define the base JSON:API endpoint for lessons
$base_url = "http://d10dev.calidev.org/jsonapi/node/lesson";

// 2. Set up query parameters: Filter by type AND restrict fields to title & body
$params = [
    'filter' => [
        'field_lesson_type' => 'CALI Author'
    ],
    'fields' => [
        'node--lesson' => 'title,body' // Requesting both title and body attributes
    ]
];

// 3. Build the encoded URL string
$full_url = $base_url . '?' . http_build_query($params);

// 4. Fetch and decode the payload
$response = @file_get_contents($full_url);
$lessons = [];

if ($response !== false) {
    $payload = json_decode($response, true);
    $lessons = $payload['data'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CALI Author Lessons</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
            background-color: #f9f9f9;
        }
        h1 {
            color: #111;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .lesson-card {
            background: #fff;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #eef0f2;
        }
        .lesson-title {
            margin-top: 0;
            color: #0056b3;
            font-size: 1.5rem;
        }
        .lesson-body {
            color: #444;
        }
        .no-results {
            padding: 20px;
            background: #fff3cd;
            color: #856404;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <h1>CALI Author Lessons Directory</h1>

    <?php if (!empty($lessons)): ?>
        <?php foreach ($lessons as $lesson): ?>
            <?php 
                // Safely extract attributes
                $title = htmlspecialchars($lesson['attributes']['title'] ?? 'Untitled Lesson');
                
                // Drupal processes HTML formatting inside 'processed' key of long text fields
                $body = $lesson['attributes']['body']['processed'] ?? '<p>No description available.</p>';
            ?>
            <article class="lesson-card">
                <h2 class="lesson-title"><?php echo $title; ?></h2>
                <div class="lesson-body">
                    <?php echo $body; // outputting raw processed HTML from Drupal ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-results">No lessons found or failed to connect to the API endpoint.</p>
    <?php endif; ?>

</body>
</html>