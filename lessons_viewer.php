<?php
// Define the base URL for the Drupal JSON:API endpoint
$base_url = "http://d10dev.calidev.org/jsonapi/node/lesson";

// -------------------------------------------------------------------------
// SCENARIO A: XML Source Viewer Mode
// -------------------------------------------------------------------------
if (isset($_GET['view_xml']) && !empty($_GET['view_xml'])) {
    $lesson_id = $_GET['view_xml'];
    
    // Fetch only the specific lesson node via its UUID to get its XML field
    $single_lesson_url = $base_url . '/' . urlencode($lesson_id) . '?fields[node--lesson]=title,field_lesson_xml';
    $response = @file_get_contents($single_lesson_url);
    
    $title = "XML Viewer";
    $xml_content = "";
    $error_msg = "";

    if ($response !== false) {
        $payload = json_decode($response, true);
        if (isset($payload['data'])) {
            $title = $payload['data']['attributes']['title'] ?? 'Untitled Lesson';
            $xml_content = $payload['data']['attributes']['field_lesson_xml'] ?? '';
        } else {
            $error_msg = "Lesson data structure could not be read.";
        }
    } else {
        $error_msg = "Failed to fetch the XML payload for this lesson.";
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>XML Source: <?php echo htmlspecialchars($title); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                margin: 40px;
                background-color: #f4f6f8;
                color: #333;
            }
            .viewer-container {
                max-width: 1000px;
                margin: 0 auto;
                background: #fff;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .nav-back {
                display: inline-block;
                margin-bottom: 20px;
                color: #0056b3;
                text-decoration: none;
                font-weight: bold;
            }
            .nav-back:hover { text-decoration: underline; }
            h1 { margin-top: 0; color: #111; font-size: 1.75rem; border-bottom: 2px solid #eaeaea; padding-bottom: 10px;}
            pre {
                background: #282c34;
                color: #abb2bf;
                padding: 20px;
                border-radius: 6px;
                overflow-x: auto;
                font-family: "Courier New", Courier, monospace;
                font-size: 0.95rem;
                line-height: 1.5;
                border: 1px solid #1e2127;
            }
            .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="viewer-container">
            <a class="nav-back" href="?">&larr; Back to Lessons Directory</a>
            <h1>XML Content Document for: <?php echo htmlspecialchars($title); ?></h1>
            
            <?php if (!empty($error_msg)): ?>
                <p class="error"><?php echo $error_msg; ?></p>
            <?php elseif (empty($xml_content) || $xml_content === '-'): ?>
                <p style="color: #666; font-style: italic;">No valid XML manifest content is available for this lesson record (Field value is empty or '-').</p>
            <?php else: ?>
                <pre><code><?php echo htmlspecialchars($xml_content); ?></code></pre>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit; // Halt execution so the main listing page does not render underneath
}

// -------------------------------------------------------------------------
// SCENARIO B: Main Directory Listing Mode (Default View)
// -------------------------------------------------------------------------
$params = [
    'filter' => [
        'field_lesson_type' => 'CALI Author'
    ],
    'fields' => [
        'node--lesson' => 'id,title,body' // Grab the UUID ('id') alongside title and body
    ]
];

$full_url = $base_url . '?' . http_build_query($params);
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
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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
            position: relative;
        }
        .lesson-title {
            margin-top: 0;
            color: #0056b3;
            font-size: 1.5rem;
            padding-right: 140px; /* Make space for action button */
        }
        .lesson-body {
            color: #444;
            margin-bottom: 15px;
        }
        .xml-btn {
            display: inline-block;
            background-color: #eef2f7;
            color: #337ab7;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #dcdfe6;
            transition: all 0.2s ease;
        }
        .xml-btn:hover {
            background-color: #337ab7;
            color: #fff;
            border-color: #337ab7;
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
                $uuid = $lesson['id']; // Unique API Resource ID used to fetch this specific entity
                $title = htmlspecialchars($lesson['attributes']['title'] ?? 'Untitled Lesson');
                $body = $lesson['attributes']['body']['processed'] ?? '<p>No description available.</p>';
            ?>
            <article class="lesson-card">
                <h2 class="lesson-title"><?php echo $title; ?></h2>
                
                <div class="lesson-body">
                    <?php echo $body; ?>
                </div>

                <a class="xml-btn" href="?view_xml=<?php echo urlencode($uuid); ?>">
                    &lt;/&gt; View XML Source
                </a>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-results">No lessons found or failed to connect to the API endpoint.</p>
    <?php endif; ?>

</body>
</html>