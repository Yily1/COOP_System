<?php
/**
 * app/manager/meetings/api/members.php
 * GET ?q=juan -> search members
 */
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
header('Content-Type: application/json');

apiRequireRole('manager');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['data' => []]);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, membership_id,
            CONCAT(first_name, ' ', last_name) AS name
     FROM members
     WHERE CONCAT(first_name, ' ', last_name) LIKE :q
        OR CONCAT(last_name, ' ', first_name) LIKE :q
        OR membership_id LIKE :q
     ORDER BY last_name ASC
     LIMIT 8"
);
$stmt->execute([':q' => '%' . $q . '%']);

echo json_encode(['data' => $stmt->fetchAll()]);