<?php
// Define the base URL for the Drupal JSON:API endpoint
$base_url = "http://d10dev.calidev.org/jsonapi/node/lesson";

// -------------------------------------------------------------------------
// ROUTE 1: Interactive Lesson Page Viewer Mode
// -------------------------------------------------------------------------
if (isset($_GET['view_lesson']) && !empty($_GET['view_lesson'])) {
    $lesson_id = $_GET['view_lesson'];
    
    // Fetch only the specific lesson node by its UUID, requesting title and the XML data
    $single_lesson_url = $base_url . '/' . urlencode($lesson_id) . '?fields[node--lesson]=title,field_lesson_xml';
    $response = @file_get_contents($single_lesson_url);
    
    $title = "Interactive Lesson";
    $xml_content = "";
    $error_msg = "";

    if ($response !== false) {
        $payload = json_decode($response, true);
        if (isset($payload['data'])) {
            $title = $payload['data']['attributes']['title'] ?? 'Untitled Lesson';
            $xml_content = $payload['data']['attributes']['field_lesson_xml'] ?? '';
        } else {
            $error_msg = "Lesson records could not be read.";
        }
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
                margin: 0; padding: 0;
                background-color: #f0f2f5;
                height: 100vh;
                display: flex; flex-direction: column;
            }
            header {
                background: #fff;
                padding: 15px 30px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
                display: flex; align-items: center; justify-content: space-between;
                border-bottom: 1px solid #e1e4e8;
            }
            .nav-back { color: #0056b3; text-decoration: none; font-weight: bold; }
            h1 { margin: 0; font-size: 1.35rem; color: #111; }
            
            .app-workspace { display: flex; flex: 1; overflow: hidden; }
            
            /* Left Panel: HTML Live Document Reader */
            .html-renderer-panel {
                flex: 1; padding: 40px; overflow-y: auto;
                display: flex; flex-direction: column; justify-content: space-between;
                background: #fff;
            }
            /* Right Panel: RAW Code Inspector */
            .xml-source-panel {
                width: 38%; background: #282c34; color: #abb2bf;
                padding: 20px; box-sizing: border-box;
                display: flex; flex-direction: column; border-left: 1px solid #1e2127;
            }
            .xml-source-panel h3 { margin-top: 0; color: #e06c75; font-size: 0.9rem; letter-spacing: 1px; text-transform: uppercase;}
            .xml-source-panel pre {
                flex: 1; margin: 0; overflow: auto;
                font-family: "Courier New", Courier, monospace; font-size: 0.85rem; line-height: 1.4;
            }
            
            /* Book Component Styling */
            .book-page-canvas { max-width: 650px; margin: 0 auto; width: 100%; }
            .page-counter { font-weight: bold; color: #6a737d; text-transform: uppercase; font-size: 0.85rem; margin-bottom: 10px; }
            .rendered-title { color: #0056b3; font-size: 2rem; margin-top: 0; }
            .rendered-text { font-size: 1.15rem; line-height: 1.7; color: #24292e; }
            .rendered-text p { margin-bottom: 1.5em; }

            /* Pagination Controller Toolbar */
            .pagination-controls {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 650px; margin: 40px auto 0 auto; width: 100%;
                border-top: 1px solid #e1e4e8; padding-top: 20px;
            }
            .btn {
                background-color: #0056b3; color: white; border: none;
                padding: 10px 20px; border-radius: 6px; cursor: pointer;
                font-weight: 600; font-size: 1rem;
            }
            .btn:disabled { background-color: #cdd9e5; cursor: not-allowed; }
            .btn:hover:not(:disabled) { background-color: #004094; }
            .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px;}
        </style>
    </head>
    <body>

        <header>
            <a class="nav-back" href="?">&larr; Directory</a>
            <h1>Application Viewer: <?php echo htmlspecialchars($title); ?></h1>
            <div style="width: 80px;"></div>
        </header>

        <?php if (!empty($error_msg)): ?>
            <div class="error"><?php echo $error_msg; ?></div>
        <?php elseif (empty($xml_content) || $xml_content === '-'): ?>
            <div class="error" style="background:#fff3cd; color:#856404;">No structured XML manifest layout properties are present for this lesson entry.</div>
        <?php else: ?>
            <div class="app-workspace">
                
                <div class="html-renderer-panel">
                    <div class="book-page-canvas">
                        <div class="page-counter" id="pageNumberLabel">Page 1 of 1</div>
                        <h2 class="rendered-title" id="pageTitleCanvas">Loading...</h2>
                        <div class="rendered-text" id="pageTextCanvas"></div>
                    </div>
                    
                    <div class="pagination-controls">
                        <button class="btn" id="prevBtn" onclick="changePage(-1)">&larr; Previous Page</button>
                        <span id="pageIndicatorText" style="font-weight:600;">1 / 1</span>
                        <button class="btn" id="nextBtn" onclick="changePage(1)">Next Page &rarr;</button>
                    </div>
                </div>

                <div class="xml-source-panel">
                    <h3>Raw Source Segment Inspector</h3>
                    <pre><code><?php echo htmlspecialchars($xml_content); ?></code></pre>
                </div>
            </div>

            <script id="xmlPayloadData" type="application/xml" style="display:none;"><?php echo $xml_content; ?></script>

            <script>
                let lessonPages = [];
                let currentPageIndex = 0;

                document.addEventListener("DOMContentLoaded", () => {
                    const rawXmlText = document.getElementById("xmlPayloadData").textContent;
                    
                    try {
                        const parser = new DOMParser();
                        // Parse as XML to preserve tree-node navigation
                        const xmlDoc = parser.parseFromString(rawXmlText, "text/xml");
                        
                        // Query all actual <PAGE> elements from the structural XML document
                        const xmlPages = xmlDoc.getElementsByTagName("PAGE");
                        
                        if (xmlPages.length > 0) {
                            for (let i = 0; i < xmlPages.length; i++) {
                                const pageNode = xmlPages[i];
                                
                                // Extract the child <TITLE> text or fallback safely
                                const titleNode = pageNode.getElementsByTagName("TITLE")[0];
                                let pageTitle = titleNode ? titleNode.textContent.trim() : `Page ${i + 1}`;
                                
                                // Extract the child <TEXT> block content
                                const textNode = pageNode.getElementsByTagName("TEXT")[0];
                                let rawBodyText = textNode ? textNode.textContent : pageNode.textContent;
                                
                                // Format text markup content tokens into readable web components
                                let cleanHtml = rawBodyText
                                    .replace(/\/TITLE[^\n]*/gi, '') // Drop dangling title tags
                                    .replace(/\/P/gi, '</p><p>')     // Convert paragraph codes
                                    .replace(/\/CR/gi, '<br/>')      // Convert line-return triggers
                                    .replace(/\/AUTHORS/gi, '<strong>Authors:</strong>')
                                    .trim();

                                // Ensure text is cleanly self-contained within safe structural semantic paragraphs
                                cleanHtml = `<p>${cleanHtml.replace(/\n/g, '<br/>')}</p>`;
                                
                                lessonPages.push({
                                    title: pageTitle,
                                    body: cleanHtml
                                });
                            }
                        } else {
                            // Fallback parsing strategy if data uses capitalized tag headers alternatively (e.g. text blocks outside formal tags)
                            const infoBlock = xmlDoc.getElementsByTagName("INFO")[0] || xmlDoc.documentElement;
                            const textFallback = infoBlock.textContent || "";
                            
                            // Splitting elements dynamically if explicit <PAGE> brackets aren't returned inside flat strings
                            const rawChunks = textFallback.split(/\/TITLE/i);
                            lessonPages = rawChunks.filter(c => c.trim()).map((chunk, index) => {
                                const lines = chunk.trim().split('\n');
                                const extractedTitle = lines[0] ? lines[0].replace(/\/+/g, '').trim() : `Section ${index + 1}`;
                                const remainingBody = lines.slice(1).join('\n')
                                    .replace(/\/P/gi, '</p><p>')
                                    .replace(/\/CR/gi, '<br/>');
                                    
                                return {
                                    title: extractedTitle,
                                    body: `<p>${remainingBody}</p>`
                                };
                            });
                        }
                    } catch (e) {
                        console.error("XML Parsing runtime issue: ", e);
                    }

                    // Strict validation in case of fully unmappable XML layout values
                    if (lessonPages.length === 0) {
                        lessonPages = [{ 
                            title: "Document Manifest Core View", 
                            body: "<p>The layout context framework loaded. Review the raw XML source code inspector in the side-panel window to trace structure configurations manually.</p>" 
                        }];
                    }

                    renderCurrentPage();
                });

                function renderCurrentPage() {
                    if (lessonPages.length === 0) return;
                    const page = lessonPages[currentPageIndex];
                    
                    // Direct manipulation on the canvas layout nodes
                    document.getElementById("pageTitleCanvas").textContent = page.title;
                    document.getElementById("pageTextCanvas").innerHTML = page.body;
                    
                    // Render current paging interface stats
                    document.getElementById("pageNumberLabel").textContent = `Page Element ${currentPageIndex + 1} of ${lessonPages.length}`;
                    document.getElementById("pageIndicatorText").textContent = `${currentPageIndex + 1} / ${lessonPages.length}`;
                    
                    // Disable triggers dynamically based on contextual bounds
                    document.getElementById("prevBtn").disabled = (currentPageIndex === 0);
                    document.getElementById("nextBtn").disabled = (currentPageIndex === lessonPages.length - 1);
                }

                function changePage(direction) {
                    currentPageIndex += direction;
                    if (currentPageIndex < 0) currentPageIndex = 0;
                    if (currentPageIndex >= lessonPages.length) currentPageIndex = lessonPages.length - 1;
                    
                    renderCurrentPage();
                    
                    // Reset scroll height elegantly back to top of reading viewport frame
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
            line-height: 1.6; color: #333; max-width: 850px; margin: 40px auto; padding: 0 20px;
            background-color: #f8f9fa;
        }
        h1 { color: #111; border-bottom: 2px solid #e1e4e8; padding-bottom: 12px; margin-bottom: 30px; }
        .lesson-card { background: #fff; padding: 25px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); border: 1px solid #e1e4e8; }
        .lesson-title { margin-top: 0; color: #0056b3; font-size: 1.4rem; }
        .lesson-body { color: #444; margin-bottom: 20px; }
        .app-btn {
            display: inline-flex; align-items: center; background-color: #28a745; color: white;
            padding: 8px 16px; border-radius: 5px; text-decoration: none; font-size: 0.9rem; font-weight: 600;
            transition: background 0.2s;
        }
        .app-btn:hover { background-color: #218838; }
        .no-results { padding: 20px; background: #fff3cd; color: #856404; border-radius: 4px; }
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
                        Launch Interactive XML App &rarr;
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-results">No compatible lessons could be downloaded from the API endpoint.</p>
    <?php endif; ?>

</body>
</html>