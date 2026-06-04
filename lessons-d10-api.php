<?php
$url = "http://d10dev.calidev.org/jsonapi/node/lesson?filter[field_lesson_type]=CALI%20Author";
$response = file_get_contents($url);
$payload = json_decode($response, true);

// Map directly into the attributes array to extract titles
$titles = array_map(function($item) {
    return $item['attributes']['title'] ?? 'Untitled';
}, $payload['data'] ?? []);

print_r($titles);

echo "<hr><hr>";

$base_url = "http://d10dev.calidev.org/jsonapi/node/lesson";

$params = [
    'filter' => [
        'field_lesson_type' => 'CALI Author'
    ],
    'fields' => [
        'node--lesson' => 'title' // Optional: only grab titles
    ]
];

// This generates: ?filter[field_lesson_type]=CALI+Author&fields[node--lesson]=title
$query_string = http_build_query($params); 
$full_url = $base_url . '?' . $query_string;

$response = file_get_contents($full_url);
$payload = json_decode($response, true);

foreach ($payload['data'] ?? [] as $lesson) {
    echo $lesson['attributes']['title'] . "\n";
}
