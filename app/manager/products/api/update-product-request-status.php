<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('manager');
header('Content-Type: application/json');

$requestId    = $_POST['request_id'] ?? '';
$status       = trim($_POST['status'] ?? '');
$managerNote  = trim($_POST['manager_note'] ?? '');
$unitPriceRaw = trim((string) ($_POST['unit_price'] ?? ''));

$validStatuses = ['pending', 'under_review', 'approved', 'processing', 'ready_for_pickup', 'claimed', 'rejected'];

if (!$requestId || !ctype_digit((string)$requestId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request reference.']);
    exit;
}
if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

// Price per unit is optional. If it is left empty, the saved price stays as it is.
$unitPrice = null;
if ($unitPriceRaw !== '') {
    if (!is_numeric($unitPriceRaw) || (float) $unitPriceRaw < 0) {
        echo json_encode(['success' => false, 'message' => 'Enter a valid price per unit.']);
        exit;
    }
    $unitPrice = round((float) $unitPriceRaw, 2);
    if ($unitPrice > 99999999.99) {
        echo json_encode(['success' => false, 'message' => 'That price is too large.']);
        exit;
    }
}

try {
    if ($unitPrice !== null) {
        $stmt = $pdo->prepare("
            UPDATE product_requests
            SET status = ?, manager_note = ?, unit_price = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $managerNote !== '' ? $managerNote : null, $unitPrice, $requestId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE product_requests
            SET status = ?, manager_note = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $managerNote !== '' ? $managerNote : null, $requestId]);
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error. Did you add the unit_price column?']);
}