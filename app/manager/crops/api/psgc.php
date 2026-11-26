<?php
// PSGC proxy: nasa manager folder pero ginagamit din ng user (kailangan lang naka-login).
// Naka-cache ng 30 araw para mabilis at hindi paulit-ulit tumatawag sa labas.
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['manager', 'user'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

const PSGC_BASE = 'https://psgc.gitlab.io/api';
const PSGC_CACHE_TTL = 2592000; // 30 days

$type = $_GET['type'] ?? '';
$code = $_GET['code'] ?? '';
$path = null;

if ($type === 'regions') {
    $path = '/regions/';
} elseif (preg_match('/^\d{6,10}$/', $code)) {
    switch ($type) {
        case 'provinces':          $path = "/regions/$code/provinces/"; break;
        case 'cities_by_province': $path = "/provinces/$code/cities-municipalities/"; break;
        case 'cities_by_region':   $path = "/regions/$code/cities-municipalities/"; break;
        case 'barangays':          $path = "/cities-municipalities/$code/barangays/"; break;
    }
}

if ($path === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$cacheFile = sys_get_temp_dir() . '/psgc_' . md5($path) . '.json';

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < PSGC_CACHE_TTL) {
    $body = file_get_contents($cacheFile);
} else {
    $body = false;
    $err = '';
    if (function_exists('curl_init')) {
        $ch = curl_init(PSGC_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
        } elseif (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
            $err = 'HTTP ' . curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body = false;
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 20]]);
        $body = @file_get_contents(PSGC_BASE . $path, false, $ctx);
        if ($body === false) $err = 'file_get_contents failed';
    }

    if ($body === false) {
        if (is_file($cacheFile)) {
            $body = file_get_contents($cacheFile); // gamitin ang lumang cache
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not reach the PSGC server (' . $err . ').']);
            exit;
        }
    } else {
        @file_put_contents($cacheFile, $body);
    }
}

$rows = json_decode($body, true);
if (!is_array($rows)) {
    echo json_encode(['success' => false, 'message' => 'Unexpected PSGC response.']);
    exit;
}

$data = [];
foreach ($rows as $r) {
    if (isset($r['code'], $r['name'])) {
        $data[] = ['code' => (string)$r['code'], 'name' => (string)$r['name']];
    }
}
usort($data, fn($a, $b) => strcasecmp($a['name'], $b['name']));

echo json_encode(['success' => true, 'data' => $data]);