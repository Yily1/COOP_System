<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../config/payment-functions.php';

requireRole('user');

header('Content-Type: application/json');

$response = ['success' => false, 'errors' => []];

$paymentType = $_POST['payment_type'] ?? '';
$amount = $_POST['amount'] ?? '';

if (!in_array($paymentType, ['registration', 'investment'])) {
    $response['errors'][] = 'Piliin ang tamang payment type.';
}
if (!is_numeric($amount) || $amount <= 0) {
    $response['errors'][] = 'Ilagay ang tamang amount.';
}

if (empty($response['errors'])) {
    // Kunin ang member_id ng kasalukuyang naka-login na user
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $memberId = $stmt->fetch(PDO::FETCH_ASSOC)['member_id'] ?? null;

    if (empty($memberId)) {
        $response['errors'][] = 'Walang naka-link na member profile sa account mo.';
    } else {
        submitPendingPayment($pdo, $memberId, $paymentType, $amount, $userId);
        $response['success'] = true;
    }
}

echo json_encode($response);