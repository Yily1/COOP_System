<?php
/**
 * app/manager/meetings/ajax/search_member.php
 * Uses app/config/config.php which defines $pdo (PDO).
 * Members table columns: id, membership_id, last_name, first_name, middle_name, ...
 */
require_once __DIR__ . '/../../../../config/config.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
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

echo json_encode($stmt->fetchAll());