<?php
require_once '../../../../config/config.php';
require_once '../../../../config/functions.php';

requireRole('manager');

header('Content-Type: application/json');

$memberId = $_GET['member_id'] ?? 0;

$livestockOptions = ['Chicken', 'Duck', 'Pig', 'Goat', 'Cow', 'Carabao'];
$cropOptions = ['Corn', 'Cassava', 'Cucumber', 'Eggplant', 'Squash', 'Chayote', 'Bitter Melon', 'String Beans'];

// Splits a stored "Chicken, Goat, Turkey" string back into
// [known checkbox values, leftover "others" text] - same logic
// used by update-member.php.
function splitDetails($stored, $options) {
    if (empty($stored)) return ['selected' => [], 'others' => ''];
    $parts = array_filter(array_map('trim', explode(',', $stored)));
    $selected = [];
    $others = [];
    foreach ($parts as $p) {
        if (in_array($p, $options)) {
            $selected[] = $p;
        } else {
            $others[] = $p;
        }
    }
    return ['selected' => $selected, 'others' => implode(', ', $others)];
}

$stmt = $pdo->prepare("
    SELECT m.*, u.id as user_id, u.email
    FROM members m
    LEFT JOIN users u ON u.member_id = m.id
    WHERE m.id = ?
");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    echo json_encode(['success' => false, 'message' => 'Member not found.']);
    exit;
}

$livestock = splitDetails($member['livestock_details'] ?? '', $livestockOptions);
$crops = splitDetails($member['crops_details'] ?? '', $cropOptions);

echo json_encode([
    'success' => true,
    'member' => [
        'id' => $member['id'],
        'membership_id' => $member['membership_id'],
        'last_name' => $member['last_name'],
        'first_name' => $member['first_name'],
        'middle_name' => $member['middle_name'],
        'gender' => $member['gender'],
        'date_of_birth' => $member['date_of_birth'],
        'occupation' => $member['occupation'],
        'address' => $member['address'],
        'contact_number' => $member['contact_number'],
        'membership_type' => $member['membership_type'],
        'date_joined' => $member['date_joined'],
        'hectares_cultivated' => $member['hectares_cultivated'],
        'farmer_type' => $member['farmer_type'],
        'livestock_selected' => $livestock['selected'],
        'livestock_others' => $livestock['others'],
        'crops_selected' => $crops['selected'],
        'crops_others' => $crops['others'],
        'is_linked' => !empty($member['user_id']),
        'linked_email' => $member['email'] ?? null,
    ],
]);