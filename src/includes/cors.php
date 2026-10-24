<?php

$origensPermitidas = [
    'http://127.0.0.1:5500',
    'http://localhost:5500',
];

$origem = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origem, $origensPermitidas, true)) {
    header("Access-Control-Allow-Origin: $origem");
    header("Vary: Origin");
}

header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}