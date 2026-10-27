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

// Allowed changes: pending -> approved or rejected, approved -> ready for pickup or claimed,
// ready for pickup -> claimed. Claimed and rejected are final.
// (under_review acts like pending and processing acts like approved, for older records.)
$allowedMoves = [
    'pending'          => ['approved', 'rejected'],
    'under_review'     => ['approved', 'rejected'],
    'approved'         => ['ready_for_pickup', 'claimed'],
    'processing'       => ['ready_for_pickup', 'claimed'],
    'ready_for_pickup' => ['ready_for_pickup', 'claimed'],
];

if (!$requestId || !ctype_digit((string) $requestId)) {
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
    $cur = $pdo->prepare("SELECT status, unit_price FROM product_requests WHERE id = ?");
    $cur->execute([$requestId]);
    $current = $cur->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }
    if (!isset($allowedMoves[$current['status']])) {
        echo json_encode(['success' => false, 'message' => 'This request is already finished and cannot be changed.']);
        exit;
    }
    if (!in_array($status, $allowedMoves[$current['status']], true)) {
        echo json_encode(['success' => false, 'message' => 'That status change is not allowed.']);
        exit;
    }
    if ($status === 'approved' && ($unitPrice === null || $unitPrice <= 0) && $current['unit_price'] === null) {
        echo json_encode(['success' => false, 'message' => 'Enter the price per unit before approving.']);
        exit;
    }

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