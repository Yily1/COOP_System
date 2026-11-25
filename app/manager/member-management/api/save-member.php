<?php
require_once '../../../../config/config.php';
require_once '../../../../config/functions.php';
require_once '../../../../includes/activity-logger.php';
require_once __DIR__ . '/member-validate.php';

requireRole('manager');

header('Content-Type: application/json');

$result = mm_validate_member($_POST);
if (!empty($result['errors'])) {
    echo json_encode(['success' => false, 'errors' => $result['errors']]);
    exit;
}
$d = $result['data'];

try {
    $membership_id = generateMembershipId($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO members
            (membership_id, last_name, first_name, middle_name, gender, date_of_birth, occupation,
             region_code, province_code, city_code, barangay_code, purok, address,
             contact_number, membership_type, date_joined, hectares_cultivated, farmer_type,
             livestock_details, crops_details, created_at)
        VALUES
            (:membership_id, :last_name, :first_name, :middle_name, :gender, :date_of_birth, :occupation,
             :region_code, :province_code, :city_code, :barangay_code, :purok, :address,
             :contact_number, :membership_type, :date_joined, :hectares_cultivated, :farmer_type,
             :livestock_details, :crops_details, NOW())
    ");

    $params = [':membership_id' => $membership_id];
    foreach ($d as $k => $v) $params[':' . $k] = $v;
    $stmt->execute($params);

    $newId = $pdo->lastInsertId();
    logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_created', 'success');

    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$newId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => "Member profile created successfully! Membership ID: {$membership_id}.",
        'member' => $member,
    ]);
} catch (PDOException $e) {
    logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_created', 'failed');
    echo json_encode(['success' => false, 'errors' => ['Error creating member profile: ' . $e->getMessage()]]);
}