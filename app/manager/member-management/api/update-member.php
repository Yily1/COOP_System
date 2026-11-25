<?php
require_once '../../../../config/config.php';
require_once '../../../../config/functions.php';
require_once '../../../../includes/activity-logger.php';
require_once __DIR__ . '/member-validate.php';

requireRole('manager');

header('Content-Type: application/json');

$memberId = $_POST['member_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    echo json_encode(['success' => false, 'errors' => ['Member not found.']]);
    exit;
}

$result = mm_validate_member($_POST);
if (!empty($result['errors'])) {
    echo json_encode(['success' => false, 'errors' => $result['errors']]);
    exit;
}
$d = $result['data'];

try {
    $stmt = $pdo->prepare("
        UPDATE members SET
            last_name = :last_name,
            first_name = :first_name,
            middle_name = :middle_name,
            gender = :gender,
            date_of_birth = :date_of_birth,
            occupation = :occupation,
            region_code = :region_code,
            province_code = :province_code,
            city_code = :city_code,
            barangay_code = :barangay_code,
            purok = :purok,
            address = :address,
            contact_number = :contact_number,
            membership_type = :membership_type,
            date_joined = :date_joined,
            hectares_cultivated = :hectares_cultivated,
            farmer_type = :farmer_type,
            livestock_details = :livestock_details,
            crops_details = :crops_details
        WHERE id = :id
    ");

    $params = [':id' => $memberId];
    foreach ($d as $k => $v) $params[':' . $k] = $v;
    $stmt->execute($params);

    logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_updated', 'success');

    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$memberId]);
    $updatedMember = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Member profile updated successfully!',
        'member' => $updatedMember,
    ]);
} catch (PDOException $e) {
    logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_updated', 'failed');
    echo json_encode(['success' => false, 'errors' => ['Error updating member profile: ' . $e->getMessage()]]);
}