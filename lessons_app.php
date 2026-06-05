<?php
// Define the base URL for the Drupal JSON:API endpoint
$base_url = "http://d10dev.calidev.org/jsonapi/node/lesson";

/**
 * lessons_app.php
 * Dynamic Drupal 10 JSON:API Client & Interactive Lesson Parser
 */

// 1. Grab the active lesson ID/URL parameter passed from your directory
$lessonId = isset($_GET['lesson_id']) ? $_GET['lesson_id'] : null;
$xmlPayload = '';
$lessonTitle = 'Interactive Lesson Viewer';

if ($lessonId) {
    // Replace this URL with your actual Drupal 10 JSON:API endpoint if needed
    $apiUrl = "http://d10dev.calidev.org/jsonapi/node/lesson/" . urlencode($lessonId);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // If working on a local dev environment with self-signed SSL:
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $jsonData = json_decode($response, true);
        
        // Extract the XML field data and title from the Drupal JSON:API schema
        // Adjust the attribute keys ('field_lesson_xml' / 'title') to match your Drupal fields
        $xmlPayload = isset($jsonData['data']['attributes']['field_lesson_xml']) 
            ? $jsonData['data']['attributes']['field_lesson_xml'] 
            : '';
        $lessonTitle = isset($jsonData['data']['attributes']['title']) 
            ? $jsonData['data']['attributes']['title'] 
            : 'Lesson Workspace';
    } else {
        $error_msg = "Failed to fetch the XML payload for this lesson.";
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Interactive Application: <?php echo htmlspecialchars($title); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                margin: 0;
                padding: 0;
                background-color: #f0f2f5;
                height: 100vh;
                display: flex;
                flex-direction: column;
            }
            header {
                background: #fff;
                padding: 15px 30px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-bottom: 1px solid #e1e4e8;
            }
            .nav-back {
                color: #0056b3;
                text-decoration: none;
                font-weight: bold;
            }
            h1 { margin: 0; font-size: 1.35rem; color: #111; }
            
            /* Split Screen Layout */
            .app-workspace {
                display: flex;
                flex: 1;
                overflow: hidden;
            }
            /* Left Panel: HTML Live Document Reader */
            .html-renderer-panel {
                flex: 1;
                padding: 40px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                background: #fff;
            }
            /* Right Panel: RAW Code Inspector */
            .xml-source-panel {
                width: 38%;
                background: #282c34;
                color: #abb2bf;
                padding: 20px;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                border-left: 1px solid #1e2127;
            }
            .xml-source-panel h3 { margin-top: 0; color: #e06c75; font-size: 0.9rem; letter-spacing: 1px; text-transform: uppercase;}
            .xml-source-panel pre {
                flex: 1;
                margin: 0;
                overflow: auto;
                font-family: "Courier New", Courier, monospace;
                font-size: 0.85rem;
                line-height: 1.4;
            }
            
            /* Book Component Styling */
            .book-page-canvas {
                max-width: 650px;
                margin: 0 auto;
                width: 100%;
            }
            .page-counter {
                font-weight: bold;
                color: #6a737d;
                text-transform: uppercase;
                font-size: 0.85rem;
                margin-bottom: 10px;
            }
            .rendered-title { color: #0056b3; font-size: 2rem; margin-top: 0; }
            .rendered-text { font-size: 1.15rem; line-height: 1.7; color: #24292e; }
            .rendered-text p { margin-bottom: 1.5em; }

            /* Pagination Controller Toolbar */
            .pagination-controls {
                display: flex;
                justify-content: space-between;
                align-items: center;
                max-width: 650px;
                margin: 40px auto 0 auto;
                width: 100%;
                border-top: 1px solid #e1e4e8;
                padding-top: 20px;
            }
            .btn {
                background-color: #0056b3;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                font-size: 1rem;
            }
            .btn:disabled { background-color: #cdd9e5; cursor: not-allowed; }
            .btn:hover:not(:disabled) { background-color: #004094; }
            .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px;}
        </style>
    </head>
    <body>

<header>
    <h1 id="lesson-title" style="margin:0; font-size:1.25rem;"><?php echo htmlspecialchars($lessonTitle); ?></h1>
    <div id="page-counter">Page 0 of 0</div>
</header>

<div class="container">
    <main id="lesson-canvas" class="panel">
        <div id="render-target"></div>
        <div class="nav-controls">
            <button id="btn-prev" onclick="navigatePage(-1)" disabled>Previous</button>
            <button id="btn-next" onclick="navigatePage(1)" disabled>Next</button>
        </div>
    </main>

    <pre id="xml-inspector" class="panel"><code>Loading XML source mapping...</code></pre>
</div>

            <script>
                // JavaScript State Machine for driving pages dynamically
                let lessonPages = [];
                let currentPageIndex = 0;

                document.addEventListener("DOMContentLoaded", () => {
                    const rawXmlText = document.getElementById("xmlPayloadData").textContent;
                    
                    try {
                        // Use Browser DOMParser to convert the raw string into an interactable XML document object
                        const parser = new DOMParser();
                        const xmlDoc = parser.parseFromString(rawXmlText, "text/xml");
                        
                        // CALI XML layout contains information nodes (often wrapped inside customized metadata blocks)
                        // For demonstration, let's treat chunks separated by text blocks or lines as sub-elements,
                        // or pull specific content patterns out. Let's parse all continuous strings.
                        const rawTextContent = xmlDoc.textContent || xmlDoc.getElementsByTagName("INFO")[0]?.textContent;
                        
                        if (rawTextContent) {
                            // Let's dynamically divide the data by custom block keywords like /TITLE or clear milestones 
                            // to mock a real page flip routine:
                            const chunks = rawTextContent.split(/(?=\/TITLE|\/BOOK)/g);
                            
                            lessonPages = chunks.map((chunk, index) => {
                                // Extract pseudo titles or formatting cleanups
                                let pageTitle = "Section Context - Part " + (index + 1);
                                if(chunk.includes("TITLE")) {
                                    const match = chunk.match(/TITLE\s+([^\n\/]+)/i);
                                    if(match && match[1]) pageTitle = match[1].trim();
                                }
                                
                                // Convert custom slashes and format tags into HTML equivalents
                                let cleanHtml = chunk
                                    .replace(/\/TITLE[^\n]*/g, '')
                                    .replace(/\/BOOK[^\n]*/g, '')
                                    .replace(/\/AUTHORS/g, '<strong>Authors:</strong>')
                                    .replace(/\/CR/g, '<br/>')
                                    .replace(/\/P/g, '</p><p>')
                                    .trim();

                                // Wrap in regular semantic paragraphs
                                cleanHtml = "<p>" + cleanHtml.replace(/\n/g, '<br/>') + "</p>";
                                
                                return { title: pageTitle, body: cleanHtml };
                            });
                        }
                    } catch (e) {
                        console.error("XML Parse issue: ", e);
                    }

                    // Fallback configuration if XML schema format parsing didn't map rows safely
                    if (lessonPages.length === 0) {
                        lessonPages = [{ 
                            title: "Document Manifest Core View", 
                            body: "<p>The layout manifest was parsed successfully. Look at the right panel to examine its raw attributes structure.</p>" 
                        }];
                    }

                    renderCurrentPage();
                });

                function renderCurrentPage() {
                    const page = lessonPages[currentPageIndex];
                    
                    // Inject updates into document nodes
                    document.getElementById("pageTitleCanvas").textContent = page.title;
                    document.getElementById("pageTextCanvas").innerHTML = page.body;
                    
                    // Update layout interface state engines
                    document.getElementById("pageNumberLabel").textContent = `Element Node ${currentPageIndex + 1} of ${lessonPages.length}`;
                    document.getElementById("pageIndicatorText").textContent = `${currentPageIndex + 1} / ${lessonPages.length}`;
                    
                    // Handle button states
                    document.getElementById("prevBtn").disabled = (currentPageIndex === 0);
                    document.getElementById("nextBtn").disabled = (currentPageIndex === lessonPages.length - 1);
                }
function renderCurrentPage() {
    if (lessonState.pages.length === 0) return;

    const page = lessonState.pages[lessonState.currentIndex];

    let htmlOutput = `<h2>${page.name}</h2>`;
    htmlOutput += `<div class="content-body">${page.processedBody}</div>`;

    if (page.interactive) {
        htmlOutput += `
            <div class="interactive-question-box">
                <strong>💡 Interactive Element [Type: ${page.questionType || 'Standard'}]</strong>
                <p>This page contains live interaction tracking properties.</p>
            </div>`;
    }
    
    document.getElementById("render-target").innerHTML = htmlOutput;
    document.getElementById("xml-inspector").textContent = page.rawXml;

    document.getElementById("page-counter").textContent = `Page ${lessonState.currentIndex + 1} of ${lessonState.pages.length}`;
    document.getElementById("btn-prev").disabled = (lessonState.currentIndex === 0);
    document.getElementById("btn-next").disabled = (lessonState.currentIndex === lessonState.pages.length - 1);
}

                function changePage(direction) {
                    currentPageIndex += direction;
                    if(currentPageIndex < 0) currentPageIndex = 0;
                    if(currentPageIndex >= lessonPages.length) currentPageIndex = lessonPages.length - 1;
                    renderCurrentPage();
                    
                    // Smooth reset scroll elevation
                    document.querySelector('.html-renderer-panel').scrollTop = 0;
                }
            </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

// -------------------------------------------------------------------------
// ROUTE 2: Main Directory Listing Mode (Default View)
// -------------------------------------------------------------------------
$params = [
    'filter' => [
        'field_lesson_type' => 'CALI Author'
    ],
    'fields' => [
        'node--lesson' => 'id,title,body'
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
    <title>CALI Author Lessons Directory</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 850px;
            margin: 40px auto;
            padding: 0 20px;
            background-color: #f8f9fa;
        }
        h1 {
            color: #111;
            border-bottom: 2px solid #e1e4e8;
            padding-bottom: 12px;
            margin-bottom: 30px;
        }
        .lesson-card {
            background: #fff;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            border: 1px solid #e1e4e8;
        }
        .lesson-title {
            margin-top: 0;
            color: #0056b3;
            font-size: 1.4rem;
        }
        .lesson-body {
            color: #444;
            margin-bottom: 20px;
        }
        .action-bar {
            display: flex;
            gap: 12px;
        }
        .app-btn {
            display: inline-flex;
            align-items: center;
            background-color: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .app-btn:hover { background-color: #218838; }
        .no-results {
            padding: 20px;
            background: #fff3cd;
            color: #856404;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <h1>CALI Author Lessons Interactive Portal</h1>

    <?php if (!empty($lessons)): ?>
        <?php foreach ($lessons as $lesson): ?>
            <?php 
                $uuid = $lesson['id'];
                $title = htmlspecialchars($lesson['attributes']['title'] ?? 'Untitled Lesson');
                $body = $lesson['attributes']['body']['processed'] ?? '<p>No description available.</p>';
            ?>
            <article class="lesson-card">
                <h2 class="lesson-title"><?php echo $title; ?></h2>
                
                <div class="lesson-body">
                    <?php echo $body; ?>
                </div>

                <div class="action-bar">
                    <a class="app-btn" href="?view_lesson=<?php echo urlencode($uuid); ?>">
                        &nbsp;Launch Interactive XML App &rarr;
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-results">No compatible lessons could be downloaded from the API endpoint.</p>
    <?php endif; ?>

</body>
</html>
