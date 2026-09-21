<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('user');
header('Content-Type: application/json');

$stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$memberId = $stmt->fetchColumn();

if (!$memberId) {
    echo json_encode(['success' => false, 'message' => 'No member profile is linked to your account.']);
    exit;
}

$amount  = $_POST['amount'] ?? '';
$purpose = trim($_POST['purpose'] ?? '');

if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid loan amount.']);
    exit;
}
if ($purpose === '') {
    echo json_encode(['success' => false, 'message' => 'Please state the purpose of the loan.']);
    exit;
}

$totalInvestment = getMemberInvestment($pdo, $memberId);
if ($totalInvestment < LOAN_ELIGIBILITY_THRESHOLD) {
    echo json_encode(['success' => false, 'message' => 'You need at least ₱' . number_format(LOAN_ELIGIBILITY_THRESHOLD, 2) . ' in confirmed investment to request a loan.']);
    exit;
}

$availableCredit = getAvailableCredit($pdo, $memberId);
if ((float)$amount > $availableCredit) {
    echo json_encode(['success' => false, 'message' => 'Requested amount exceeds your available credit of ₱' . number_format($availableCredit, 2) . '.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO loans (member_id, amount, purpose, status)
        VALUES (?, ?, ?, 'pending')
    ");
    $stmt->execute([$memberId, $amount, $purpose]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}