<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../config/payment-functions.php';

requireRole('manager');

header('Content-Type: application/json');

$response = ['success' => false, 'errors' => []];

$memberId = $_POST['member_id'] ?? '';
$paymentType = $_POST['payment_type'] ?? '';
$amount = $_POST['amount'] ?? '';
$paymentDate = $_POST['payment_date'] ?? '';
$notes = trim($_POST['notes'] ?? '');

if (empty($memberId)) {
    $response['errors'][] = 'Piliin ang member.';
}
if (!in_array($paymentType, ['registration', 'investment'])) {
    $response['errors'][] = 'Piliin ang tamang payment type.';
}
if (!is_numeric($amount) || $amount <= 0) {
    $response['errors'][] = 'Ilagay ang tamang amount.';
}
if (empty($paymentDate)) {
    $response['errors'][] = 'Piliin ang petsa ng payment.';
}

if (empty($response['errors'])) {
    $recordedBy = $_SESSION['user_id'];
    recordPayment($pdo, $memberId, $paymentType, $amount, $paymentDate, $recordedBy, $notes ?: null);
    $newId = $pdo->lastInsertId();

    // Kunin ulit ang buong record kasama ang member name at recorder email,
    // para maidagdag agad sa table sa frontend nang walang extra fetch
    $stmt = $pdo->prepare("
        SELECT p.*, m.first_name, m.last_name, m.membership_id, u.email as recorded_by_email
        FROM payments p
        JOIN members m ON p.member_id = m.id
        LEFT JOIN users u ON p.recorded_by = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$newId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['payment'] = $payment;
}

echo json_encode($response);