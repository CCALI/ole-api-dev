<?php
$url = "http://d10dev.calidev.org/jsonapi/node/lesson?filter[field_lesson_type]=CALI%20Author";
$response = file_get_contents($url);
$payload = json_decode($response, true);

// Map directly into the attributes array to extract titles
$titles = array_map(function($item) {
    return $item['attributes']['title'] ?? 'Untitled';
}, $payload['data'] ?? []);

print_r($titles);