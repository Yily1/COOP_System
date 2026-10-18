<?php
require_once '../../../../config/config.php';
require_once '../../../../config/functions.php';
require_once '../../../../config/payment-functions.php';

requireRole('manager');

header('Content-Type: application/json');

$response = ['success' => false, 'errors' => []];

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($paymentId <= 0 || !in_array($action, ['confirm', 'reject'], true)) {
    $response['errors'][] = 'Invalid request.';
    echo json_encode($response);
    exit;
}

try {
    if ($action === 'confirm') {
        $ok = confirmPayment($pdo, $paymentId);
    } else {
        $ok = rejectPayment($pdo, $paymentId);
    }

    if ($ok) {
        $response['success'] = true;
    } else {
        // No row changed: it was already confirmed or removed
        $response['errors'][] = 'Hindi na-update. Baka na-confirm/reject na ito ng iba.';
    }
} catch (Throwable $e) {
    error_log('update-payment-status: ' . $e->getMessage());
    $response['errors'][] = 'May error sa server. Subukan ulit mamaya.';
}

echo json_encode($response);