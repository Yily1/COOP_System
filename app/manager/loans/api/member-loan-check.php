<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('manager');
header('Content-Type: application/json');

$memberId = (int) ($_GET['member_id'] ?? 0);
if ($memberId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Select a member.']);
    exit;
}

$check = $pdo->prepare("SELECT id FROM members WHERE id = ?");
$check->execute([$memberId]);
if (!$check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Member not found.']);
    exit;
}

$investment = (float) getMemberInvestment($pdo, $memberId);

echo json_encode([
    'success'    => true,
    'investment' => $investment,
    'loanable'   => (float) getLoanableAmount($pdo, $memberId),
    'balance'    => (float) getMemberLoanBalance($pdo, $memberId),
    'credit'     => (float) getAvailableCredit($pdo, $memberId),
    'threshold'  => (float) LOAN_ELIGIBILITY_THRESHOLD,
    'eligible'   => $investment >= LOAN_ELIGIBILITY_THRESHOLD,
]);