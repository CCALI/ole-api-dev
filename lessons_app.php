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
        $xmlPayload = "<LESSON><PAGE NAME='Error'><BODY>/PFailed to fetch lesson data from Drupal API. (HTTP $httpCode)</BODY></PAGE></LESSON>";
    }
} else {
    // Fallback if the user navigates directly to the app without picking a lesson from your directory
    $xmlPayload = "<LESSON><PAGE NAME='No Lesson Selected'><BODY>/PPlease return to the lesson directory and select a valid interactive lesson to begin.</BODY></PAGE></LESSON>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($lessonTitle); ?></title>
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
let lessonState = {
    pages: [],
    currentIndex: 0
};

function parseCaliTokens(rawText) {
    if (!rawText) return '';
    
    let cleanHtml = rawText
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt bridge;");

    cleanHtml = cleanHtml.replace(/\/P/gi, '<p class="cali-paragraph">');
    cleanHtml = cleanHtml.replace(/\/CR/gi, '<span class="cali-break"></span>');
    cleanHtml = cleanHtml.replace(/\/B(.*?)(?=\/[B|P|C]|$)/gi, '<span class="cali-bold">$1</span>');
    cleanHtml = cleanHtml.replace(/\/I(.*?)(?=\/[I|P|C]|$)/gi, '<span class="cali-italic">$1</span>');

    return cleanHtml;
}

function processLessonXml(xmlString) {
    const parser = new DOMParser();
    const xmlDoc = parser.parseFromString(xmlString, "text/xml");
    
    const xmlPages = xmlDoc.getElementsByTagName("PAGE");
    lessonState.pages = [];

    // If XML doesn't contain explicit PAGE components, wrap the whole response safely
    if (xmlPages.length === 0) {
        lessonState.pages.push({
            index: 0,
            name: "Lesson Content",
            rawXml: xmlString,
            processedBody: parseCaliTokens(xmlString),
            interactive: false,
            questionType: null
        });
    } else {
        for (let i = 0; i < xmlPages.length; i++) {
            const pageNode = xmlPages[i];
            const nameAttr = pageNode.getAttribute("NAME") || `Page-${i + 1}`;
            const bodyNode = pageNode.getElementsByTagName("BODY")[0];
            const rawBodyText = bodyNode ? bodyNode.textContent : '';

            const interactionNode = pageNode.getElementsByTagName("INTERACTION")[0] || null;
            const isInteractive = interactionNode !== null;

            lessonState.pages.push({
                index: i,
                name: nameAttr,
                rawXml: new XMLSerializer().serializeToString(pageNode),
                processedBody: parseCaliTokens(rawBodyText),
                interactive: isInteractive,
                questionType: isInteractive ? interactionNode.getAttribute("TYPE") : null
            });
        }
    }

    lessonState.currentIndex = 0;
    renderCurrentPage();
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

function navigatePage(direction) {
    const targetIndex = lessonState.currentIndex + direction;
    if (targetIndex >= 0 && targetIndex < lessonState.pages.length) {
        lessonState.currentIndex = targetIndex;
        renderCurrentPage();
    }
}

// Safely bridge the real PHP XML payload string directly into the JS execution loop
window.addEventListener("DOMContentLoaded", () => {
    const realXmlPayload = `<?php echo json_encode($xmlPayload); ?>`;
    // Clean outer JSON quotes added by json_encode helper safely
    const cleanXml = JSON.parse(realXmlPayload);
    processLessonXml(cleanXml);
});
</script>
</body>
</html>
