<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('manager');
header('Content-Type: application/json');

$memberId = (int) ($_POST['member_id'] ?? 0);
$amount   = $_POST['amount'] ?? '';
$purpose  = trim($_POST['purpose'] ?? '');

if ($memberId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Select a member.']);
    exit;
}
if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid loan amount.']);
    exit;
}
if ($purpose === '') {
    echo json_encode(['success' => false, 'message' => 'Please state the purpose of the loan.']);
    exit;
}

$check = $pdo->prepare("SELECT id FROM members WHERE id = ?");
$check->execute([$memberId]);
if (!$check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Member not found.']);
    exit;
}

$totalInvestment = getMemberInvestment($pdo, $memberId);
if ($totalInvestment < LOAN_ELIGIBILITY_THRESHOLD) {
    echo json_encode(['success' => false, 'message' => 'This member needs at least ₱' . number_format(LOAN_ELIGIBILITY_THRESHOLD, 2) . ' in confirmed investment to get a loan.']);
    exit;
}

$availableCredit = getAvailableCredit($pdo, $memberId);
if ((float) $amount > $availableCredit) {
    echo json_encode(['success' => false, 'message' => 'Amount exceeds the available credit of ₱' . number_format($availableCredit, 2) . '.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO loans (member_id, amount, purpose, status)
        VALUES (?, ?, ?, 'approved')
    ");
    $stmt->execute([$memberId, $amount, $purpose]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}