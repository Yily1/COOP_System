<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../includes/activity-logger.php';
requireLogin();

$currentRole = $_SESSION['role'];

if ($currentRole !== 'manager') {
    die("Access denied. Only managers can edit member profiles.");
}

$memberId = $_GET['member_id'] ?? 0;
$message = '';
$success = false;

$livestockOptions = ['Chicken', 'Pig', 'Goat', 'Cattle/Cow', 'Carabao', 'Duck'];
$cropOptions = ['Rice', 'Corn', 'Vegetables', 'Coconut', 'Banana', 'Coffee'];

// Splits a stored "Chicken, Goat, Turkey" string back into
// [known checkbox values, leftover "others" text]
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

// Get member details
$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("Member not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $membership_type = $_POST['membership_type'] ?? '';
    $date_joined = $_POST['date_joined'] ?? '';
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
    if (!in_array($gender, ['Male', 'Female'])) $errors[] = "Gender is required.";
    if ($address === '') $errors[] = "Complete Address is required.";
    if ($contact_number === '') $errors[] = "Contact Number is required.";
    if (!in_array($membership_type, ['Regular', 'Associate'])) $errors[] = "Membership Type is required.";
    if ($date_joined === '') $errors[] = "Date Joined is required.";
    if (!in_array($farmer_type, ['Livestock', 'Crops', 'Both'])) $errors[] = "Type of Farmer is required.";
    if (in_array($farmer_type, ['Livestock', 'Both']) && $livestock_details === '') $errors[] = "Please select or specify the livestock raised.";
    if (in_array($farmer_type, ['Crops', 'Both']) && $crops_details === '') $errors[] = "Please select or specify the crops grown.";
    if ($farmer_type === 'Livestock') $crops_details = '';
    if ($farmer_type === 'Crops') $livestock_details = '';

    // Values used to re-render checkboxes if validation fails
    $selectedLivestock = $livestockSelected;
    $selectedLivestockOthers = $livestockOthers;
    $selectedCrops = $cropsSelected;
    $selectedCropsOthers = $cropsOthers;

    if (!empty($errors)) {
        $message = implode(' ', $errors);
        $success = false;
        // Keep the submitted values visible in the form
        $member = array_merge($member, compact('last_name', 'first_name', 'middle_name', 'gender', 'address', 'contact_number', 'membership_type', 'date_joined', 'farmer_type', 'livestock_details', 'crops_details'));
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE members SET
                    last_name = :last_name,
                    first_name = :first_name,
                    middle_name = :middle_name,
                    gender = :gender,
                    address = :address,
                    contact_number = :contact_number,
                    membership_type = :membership_type,
                    date_joined = :date_joined,
                    farmer_type = :farmer_type,
                    livestock_details = :livestock_details,
                    crops_details = :crops_details
                WHERE id = :id
            ");
            $stmt->execute([
                ':last_name'         => $last_name,
                ':first_name'        => $first_name,
                ':middle_name'       => $middle_name !== '' ? $middle_name : null,
                ':gender'            => $gender,
                ':address'           => $address,
                ':contact_number'    => $contact_number,
                ':membership_type'   => $membership_type,
                ':date_joined'       => $date_joined,
                ':farmer_type'       => $farmer_type,
                ':livestock_details' => $livestock_details !== '' ? $livestock_details : null,
                ':crops_details'     => $crops_details !== '' ? $crops_details : null,
                ':id'                => $memberId,
            ]);

            $message = "Member profile updated successfully!";
            $success = true;

            logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_updated', 'success');

            // Refresh member data
            $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $message = "Error updating member profile: " . $e->getMessage();
            $success = false;
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_updated', 'failed');
        }
    }
}

// For GET requests, or right after a successful save, reconstruct
// the checkbox state from the stored comma-separated string.
if (!isset($selectedLivestock)) {
    $l = splitDetails($member['livestock_details'] ?? '', $livestockOptions);
    $selectedLivestock = $l['selected'];
    $selectedLivestockOthers = $l['others'];
    $c = splitDetails($member['crops_details'] ?? '', $cropOptions);
    $selectedCrops = $c['selected'];
    $selectedCropsOthers = $c['others'];
}

renderHeader('Edit Member Profile');
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Edit Member Profile</h2>
        <span class="badge badge-manager"><?php echo htmlspecialchars($member['membership_id']); ?></span>
    </div>

    <?php if ($message): ?>
        <div class="<?php echo $success ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <h3 style="margin-top: 0;">Personal Information</h3>

        <div class="form-group">
            <label>Membership ID:</label>
            <input type="text" value="<?php echo htmlspecialchars($member['membership_id']); ?>" disabled style="background: #f0f0f0; color: #888;">
        </div>

        <div class="form-group">
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($member['last_name']); ?>">
        </div>

        <div class="form-group">
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($member['first_name']); ?>">
        </div>

        <div class="form-group">
            <label for="middle_name">Middle Name:</label>
            <input type="text" id="middle_name" name="middle_name" placeholder="(optional)" value="<?php echo htmlspecialchars($member['middle_name'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="gender">Gender:</label>
            <select id="gender" name="gender" required>
                <option value="Male" <?php echo $member['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo $member['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
            </select>
        </div>

        <div class="form-group">
            <label for="address">Complete Address:</label>
            <input type="text" id="address" name="address" required value="<?php echo htmlspecialchars($member['address']); ?>">
        </div>

        <div class="form-group">
            <label for="contact_number">Contact Number:</label>
            <input type="text" id="contact_number" name="contact_number" required value="<?php echo htmlspecialchars($member['contact_number']); ?>">
        </div>

        <h3>Membership Information</h3>

        <div class="form-group">
            <label for="membership_type">Membership Type:</label>
            <select id="membership_type" name="membership_type" required>
                <option value="Regular" <?php echo $member['membership_type'] === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                <option value="Associate" <?php echo $member['membership_type'] === 'Associate' ? 'selected' : ''; ?>>Associate</option>
            </select>
        </div>

        <div class="form-group">
            <label for="date_joined">Date Joined:</label>
            <input type="date" id="date_joined" name="date_joined" required value="<?php echo htmlspecialchars($member['date_joined']); ?>">
        </div>

        <h3>Farming Profile</h3>

        <div class="form-group">
            <label for="farmer_type">Type of Farmer:</label>
            <select id="farmer_type" name="farmer_type" required onchange="toggleFarmerFields()">
                <option value="Livestock" <?php echo $member['farmer_type'] === 'Livestock' ? 'selected' : ''; ?>>Livestock</option>
                <option value="Crops" <?php echo $member['farmer_type'] === 'Crops' ? 'selected' : ''; ?>>Crops</option>
                <option value="Both" <?php echo $member['farmer_type'] === 'Both' ? 'selected' : ''; ?>>Both</option>
            </select>
        </div>

        <div class="form-group" id="livestock_field">
            <label>Livestock Raised:</label>
            <div style="display: flex; flex-wrap: wrap; gap: 10px 20px; margin-top: 5px;">
                <?php foreach ($livestockOptions as $option): ?>
                    <label style="display: flex; align-items: center; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="livestock[]" value="<?php echo htmlspecialchars($option); ?>"
                               style="width: auto; margin-right: 6px;"
                               <?php echo in_array($option, $selectedLivestock) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($option); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="livestock_others" placeholder="Others (specify)" style="margin-top: 10px;" value="<?php echo htmlspecialchars($selectedLivestockOthers); ?>">
        </div>

        <div class="form-group" id="crops_field">
            <label>Crops Grown:</label>
            <div style="display: flex; flex-wrap: wrap; gap: 10px 20px; margin-top: 5px;">
                <?php foreach ($cropOptions as $option): ?>
                    <label style="display: flex; align-items: center; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="crops[]" value="<?php echo htmlspecialchars($option); ?>"
                               style="width: auto; margin-right: 6px;"
                               <?php echo in_array($option, $selectedCrops) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($option); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="crops_others" placeholder="Others (specify)" style="margin-top: 10px;" value="<?php echo htmlspecialchars($selectedCropsOthers); ?>">
        </div>

        <script>
        function toggleFarmerFields() {
            const type = document.getElementById('farmer_type').value;
            document.getElementById('livestock_field').style.display = (type === 'Livestock' || type === 'Both') ? 'block' : 'none';
            document.getElementById('crops_field').style.display = (type === 'Crops' || type === 'Both') ? 'block' : 'none';
        }
        toggleFarmerFields();
        </script>

        <button type="submit">Update Member Profile</button>
        <a href="<?php echo BASE_URL; ?>/app/members/member-list.php">
            <button type="button" style="background: #6c757d;">Cancel</button>
        </a>
    </form>
</div>

<?php renderFooter(); ?>
