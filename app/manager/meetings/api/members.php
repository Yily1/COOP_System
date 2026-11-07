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

try {
    // Bawat placeholder ay dapat UNIQUE (:q1, :q2, :q3).
    // Kapag ginamit ang parehong :q nang ilang beses at naka-off ang
    // emulated prepares, magkakaroon ng error na "Invalid parameter number".
    $stmt = $pdo->prepare(
        "SELECT id, membership_id,
                CONCAT(first_name, ' ', last_name) AS name
         FROM members
         WHERE CONCAT(first_name, ' ', last_name) LIKE :q1
            OR CONCAT(last_name, ' ', first_name) LIKE :q2
            OR membership_id LIKE :q3
         ORDER BY last_name ASC
         LIMIT 8"
    );
    $like = '%' . $q . '%';
    $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);

    echo json_encode(['data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed. Please try again.']);
}