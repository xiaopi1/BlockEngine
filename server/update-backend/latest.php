<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$versionFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'version.txt';
if (!is_file($versionFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'No release has been published.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$lines = file($versionFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$version = trim((string) ($lines[0] ?? ''));
$downloadUrl = trim((string) ($lines[1] ?? ''));
$signatureUrl = $downloadUrl . '.sig';
$host = strtolower((string) parse_url($downloadUrl, PHP_URL_HOST));

if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)
    || parse_url($downloadUrl, PHP_URL_SCHEME) !== 'https'
    || !($host === 'san2.top' || str_ends_with($host, '.san2.top'))) {
    http_response_code(500);
    echo json_encode(['error' => 'Published release metadata is invalid.'], JSON_UNESCAPED_SLASHES);
    exit;
}

$context = stream_context_create(['http' => ['timeout' => 8, 'follow_location' => 0]]);
$signature = @file_get_contents($signatureUrl, false, $context);
if ($signature === false || trim($signature) === '') {
    http_response_code(503);
    echo json_encode(['error' => 'The signed update artifact is not ready.'], JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'version' => $version,
    'notes' => '方块引擎官方更新',
    'pub_date' => gmdate(DATE_ATOM, filemtime($versionFile) ?: time()),
    'platforms' => [
        'windows-x86_64' => [
            'signature' => trim($signature),
            'url' => $downloadUrl,
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
