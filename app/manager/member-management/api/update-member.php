<?php
require_once '../../../../config/config.php';
require_once '../../../../config/functions.php';
require_once '../../../../includes/activity-logger.php';

requireRole('manager');

header('Content-Type: application/json');

$memberId = $_POST['member_id'] ?? 0;
$hectaresOptions = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    echo json_encode(['success' => false, 'errors' => ['Member not found.']]);
    exit;
}

$last_name = trim($_POST['last_name'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$gender = $_POST['gender'] ?? '';
$date_of_birth = $_POST['date_of_birth'] ?? '';
$occupation = trim($_POST['occupation'] ?? '');
$address = trim($_POST['address'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$membership_type = $_POST['membership_type'] ?? '';
$date_joined = $_POST['date_joined'] ?? '';
$hectares_cultivated = $_POST['hectares_cultivated'] ?? '';
$farmer_type = $_POST['farmer_type'] ?? '';
$livestockSelected = $_POST['livestock'] ?? [];
$livestockOthers = trim($_POST['livestock_others'] ?? '');
$cropsSelected = $_POST['crops'] ?? [];
$cropsOthers = trim($_POST['crops_others'] ?? '');

$livestock_details = implode(', ', array_filter(array_merge(
    $livestockSelected,
    $livestockOthers !== '' ? [$livestockOthers] : []
)));
$crops_details = implode(', ', array_filter(array_merge(
    $cropsSelected,
    $cropsOthers !== '' ? [$cropsOthers] : []
)));

$errors = [];
if ($last_name === '') $errors[] = "Last Name is required.";
if ($first_name === '') $errors[] = "First Name is required.";
if ($middle_name === '') $errors[] = "Middle Name is required.";
if (!in_array($gender, ['Male', 'Female'])) $errors[] = "Gender is required.";
if ($date_of_birth === '') $errors[] = "Date of Birth is required.";
if ($occupation === '') $errors[] = "Occupation is required.";
if ($address === '') $errors[] = "Complete Address is required.";
if ($contact_number === '') $errors[] = "Contact Number is required.";
if (!in_array($membership_type, ['Regular', 'Associate'])) $errors[] = "Membership Type is required.";
if ($date_joined === '') $errors[] = "Date Joined is required.";
if (!in_array($hectares_cultivated, $hectaresOptions)) $errors[] = "Number of Hectares Cultivated is required.";
if (!in_array($farmer_type, ['Livestock', 'Crops', 'Both'])) $errors[] = "Type of Farmer is required.";
if (in_array($farmer_type, ['Livestock', 'Both']) && $livestock_details === '') $errors[] = "Please select or specify the livestock raised.";
if (in_array($farmer_type, ['Crops', 'Both']) && $crops_details === '') $errors[] = "Please select or specify the crops grown.";
if ($farmer_type === 'Livestock') $crops_details = '';
if ($farmer_type === 'Crops') $livestock_details = '';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE members SET
            last_name = :last_name,
            first_name = :first_name,
            middle_name = :middle_name,
            gender = :gender,
            date_of_birth = :date_of_birth,
            occupation = :occupation,
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
    $stmt->execute([
        ':last_name'           => $last_name,
        ':first_name'          => $first_name,
        ':middle_name'         => $middle_name,
        ':gender'              => $gender,
        ':date_of_birth'       => $date_of_birth,
        ':occupation'          => $occupation,
        ':address'             => $address,
        ':contact_number'      => $contact_number,
        ':membership_type'     => $membership_type,
        ':date_joined'         => $date_joined,
        ':hectares_cultivated' => $hectares_cultivated,
        ':farmer_type'         => $farmer_type,
        ':livestock_details'   => $livestock_details !== '' ? $livestock_details : null,
        ':crops_details'       => $crops_details !== '' ? $crops_details : null,
        ':id'                  => $memberId,
    ]);

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