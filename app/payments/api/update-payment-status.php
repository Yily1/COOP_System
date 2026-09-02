<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../config/payment-functions.php';

requireRole('manager');

header('Content-Type: application/json');

$response = ['success' => false, 'errors' => []];

$paymentId = $_POST['payment_id'] ?? '';
$action = $_POST['action'] ?? '';

if (empty($paymentId) || !in_array($action, ['confirm', 'reject'])) {
    $response['errors'][] = 'Invalid request.';
    echo json_encode($response);
    exit;
}

if ($action === 'confirm') {
    $ok = confirmPayment($pdo, $paymentId);
} else {
    $ok = rejectPayment($pdo, $paymentId);
}

if ($ok) {
    $response['success'] = true;
} else {
    $response['errors'][] = 'Hindi na-update. Baka na-confirm/reject na ito ng iba.';
}

echo json_encode($response);