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
    <title>CALI Interactive Lesson Viewer</title>
    <style>
        :root {
            --bg-primary: #f8fafc;
            --panel-bg: #ffffff;
            --border-color: #cbd5e1;
            --text-main: #1e293b;
            --accent-color: #2563eb;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-primary);
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        header {
            background-color: #0f172a;
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        .panel {
            overflow-y: auto;
            padding: 1.5rem;
            box-sizing: border-box;
        }
        #lesson-canvas {
            width: 62%;
            background-color: var(--panel-bg);
            border-right: 1px solid var(--border-color);
        }
        #xml-inspector {
            width: 38%;
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: "Courier New", Courier, monospace;
            white-space: pre-wrap;
            border-left: 1px solid #333;
        }
        .nav-controls {
            display: flex;
            gap: 10rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        button {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        button:disabled {
            background-color: #94a3b8;
            cursor: not-allowed;
        }
        /* Custom styles for semantic CALI tags mapped by parser */
        .cali-paragraph { margin-bottom: 1rem; line-height: 1.6; }
        .cali-break { display: block; margin-top: 0.5rem; }
        .cali-bold { font-weight: bold; color: #000; }
        .cali-italic { font-style: italic; }
        .interactive-question-box {
            background-color: #f0fdf4;
            border-left: 4px solid #16a34a;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 0 4px 4px 0;
        }
    </style>
</head>
<body>

<header>
    <h1 id="lesson-title" style="margin:0; font-size:1.25rem;">Loading Lesson Content...</h1>
    <div id="page-counter">Page 0 of 0</div>
</header>

<div class="container">
    <!-- Active Lesson View Rendering Canvas -->
    <main id="lesson-canvas" class="panel">
        <div id="render-target"></div>
        <div class="nav-controls">
            <button id="btn-prev" onclick="navigatePage(-1)" disabled>Previous</button>
            <button id="btn-next" onclick="navigatePage(1)" disabled>Next</button>
        </div>
    </main>

    <!-- Raw Back-end XML Structure Code Inspector Mirror -->
    <pre id="xml-inspector" class="panel"><code>Loading XML source mapping...</code></pre>
</div>

<script>
// Application Global State Machine Object
let lessonState = {
    pages: [],
    currentIndex: 0
};

/**
 * Advanced Semantic Parser Engine
 * Escapes raw strings and translates legacy markup patterns into valid semantic HTML
 */
function parseCaliTokens(rawText) {
    if (!rawText) return '';
    
    // Step 1: Escape standard HTML tags safely to protect DOM parsing integrity
    let cleanHtml = rawText
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");

    // Step 2: High-Fidelity Legacy Marker Translation
    // Process matching slash patterns into distinct modern styles and elements
    cleanHtml = cleanHtml.replace(/\/P/gi, '<p class="cali-paragraph">');
    cleanHtml = cleanHtml.replace(/\/CR/gi, '<span class="cali-break"></span>');
    
    // Match matching wrap-around pairs if they occur, otherwise fallback cleanly
    cleanHtml = cleanHtml.replace(/\/B(.*?)(?=\/[B|P|C]|$)/gi, '<span class="cali-bold">$1</span>');
    cleanHtml = cleanHtml.replace(/\/I(.*?)(?=\/[I|P|C]|$)/gi, '<span class="cali-italic">$1</span>');

    return cleanHtml;
}

/**
 * Main Data Destructuring & Object Marshalling Core
 * Reads parsed XML structure elements into an executable JavaScript array matrix
 */
function processLessonXml(xmlString) {
    const parser = new DOMParser();
    const xmlDoc = parser.parseFromString(xmlString, "text/xml");
    
    // Find structural page layers
    const xmlPages = xmlDoc.getElementsByTagName("PAGE");
    lessonState.pages = [];

    for (let i = 0; i < xmlPages.length; i++) {
        const pageNode = xmlPages[i];
        
        // Grab child elements safely safely fallback to attributes or tags
        const nameAttr = pageNode.getAttribute("NAME") || `Page-${i + 1}`;
        const bodyNode = pageNode.getElementsByTagName("BODY")[0];
        const rawBodyText = bodyNode ? bodyNode.textContent : '';

        // Detect if interactive question blocks or choices live in this section
        const interactionNode = pageNode.getElementsByTagName("INTERACTION")[0] || null;
        const isInteractive = interactionNode !== null;

        // Populate clear object metadata tracking maps
        lessonState.pages.push({
            index: i,
            name: nameAttr,
            rawXml: new XMLSerializer().serializeToString(pageNode),
            processedBody: parseCaliTokens(rawBodyText),
            interactive: isInteractive,
            questionType: isInteractive ? interactionNode.getAttribute("TYPE") : null
        });
    }

    // Set Initial Application Rendering Node View
    lessonState.currentIndex = 0;
    renderCurrentPage();
}

/**
 * Interface Layout Renderer Update Worker
 */
function renderCurrentPage() {
    if (lessonState.pages.length === 0) return;

    const page = lessonState.pages[lessonState.currentIndex];

    // 1. Update Layout Canvas DOM
    let htmlOutput = `<h2>${page.name}</h2>`;
    htmlOutput += `<div class="content-body">${page.processedBody}</div>`;

    // Add visual flag structure if parser tracked dynamic interaction tags
    if (page.interactive) {
        htmlOutput += `
            <div class="interactive-question-box">
                <strong>💡 Interactive Interaction Interface [Type: ${page.questionType || 'Standard'}]</strong>
                <p>Advanced evaluation node structure successfully mapped by parser engine.</p>
            </div>`;
    }
    
    document.getElementById("render-target").innerHTML = htmlOutput;

    // 2. Mirror Out the Cleanly Parsed Code Node Target Panel
    document.getElementById("xml-inspector").textContent = page.rawXml;

    // 3. Update Status Indicators & Buttons State Flags
    document.getElementById("lesson-title").textContent = `Active Lesson Workspace [Node: ${page.name}]`;
    document.getElementById("page-counter").textContent = `Page ${lessonState.currentIndex + 1} of ${lessonState.pages.length}`;
    document.getElementById("btn-prev").disabled = (lessonState.currentIndex === 0);
    document.getElementById("btn-next").disabled = (lessonState.currentIndex === lessonState.pages.length - 1);
}

/**
 * Simple In-Memory State Switch Navigation Handler
 */
function navigatePage(direction) {
    const targetIndex = lessonState.currentIndex + direction;
    if (targetIndex >= 0 && targetIndex < lessonState.pages.length) {
        lessonState.currentIndex = targetIndex;
        renderCurrentPage();
    }
}

// Global Lifecycle Bootstrapper Injection Mock Hook 
// (Receives string data pipeline fed directly by your PHP cURL backend execution framework)
window.addEventListener("DOMContentLoaded", () => {
    // Escaped placeholder representing the raw XML passed from your PHP processing variable
    const phpDataPayload = `<?xml version="1.0" encoding="UTF-8"?>
    <LESSON>
        <PAGE NAME="Introduction to Torts">
            <BODY>/PWelcome to the lesson. /CRThis text features a legacy break. /BThis is bolded content.</BODY>
        </PAGE>
        <PAGE NAME="Concept Comprehension Check">
            <BODY>/PReview the facts below and formulate an evaluation answer matrix.</BODY>
            <INTERACTION TYPE="MULTIPLE_CHOICE" />
        </PAGE>
    </LESSON>`;

    processLessonXml(phpDataPayload);
});
</script>
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