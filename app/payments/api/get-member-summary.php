<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../config/payment-functions.php';

requireRole('manager');

header('Content-Type: application/json');

$memberId = $_GET['member_id'] ?? '';

if (empty($memberId)) {
    echo json_encode(['success' => false, 'errors' => ['Walang member na napili.']]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, membership_id, last_name, first_name FROM members WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    echo json_encode(['success' => false, 'errors' => ['Hindi nahanap ang member.']]);
    exit;
}

$eligibility = getMemberEligibility($pdo, $memberId);

echo json_encode([
    'success' => true,
    'member' => $member,
    'eligibility' => $eligibility,
]);