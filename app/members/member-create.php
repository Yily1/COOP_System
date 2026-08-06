<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
require_once '../../includes/activity-logger.php';
requireLogin();

$currentRole = $_SESSION['role'];

// Only managers can profile members (per system requirement)
if ($currentRole !== 'manager') {
    die("Access denied. Only managers can create member profiles.");
}

$message = '';
$success = false;
$newMembershipId = '';

$livestockOptions = ['Chicken', 'Pig', 'Goat', 'Cattle/Cow', 'Carabao', 'Duck'];
$cropOptions = ['Rice', 'Corn', 'Vegetables', 'Coconut', 'Banana', 'Coffee'];
$hectaresOptions = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

$form = [
    'last_name' => '',
    'first_name' => '',
    'middle_name' => '',
    'gender' => '',
    'date_of_birth' => '',
    'occupation' => '',
    'address' => '',
    'contact_number' => '',
    'membership_type' => '',
    'date_joined' => date('Y-m-d'),
    'hectares_cultivated' => '',
    'farmer_type' => '',
    'livestock' => [],
    'livestock_others' => '',
    'crops' => [],
    'crops_others' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $membership_type = $_POST['membership_type'] ?? '';
    $date_joined = $_POST['date_joined'] ?? date('Y-m-d');
    $hectares_cultivated = $_POST['hectares_cultivated'] ?? '';
    $farmer_type = $_POST['farmer_type'] ?? '';
    $livestockSelected = $_POST['livestock'] ?? [];
    $livestockOthers = trim($_POST['livestock_others'] ?? '');
    $cropsSelected = $_POST['crops'] ?? [];
    $cropsOthers = trim($_POST['crops_others'] ?? '');

    // Combine checked items + "Others" free text into one stored string
    $livestock_details = implode(', ', array_filter(array_merge(
        $livestockSelected,
        $livestockOthers !== '' ? [$livestockOthers] : []
    )));
    $crops_details = implode(', ', array_filter(array_merge(
        $cropsSelected,
        $cropsOthers !== '' ? [$cropsOthers] : []
    )));

    $form = compact('last_name', 'first_name', 'middle_name', 'gender', 'date_of_birth', 'occupation', 'address', 'contact_number', 'membership_type', 'date_joined', 'hectares_cultivated', 'farmer_type');
    $form['livestock'] = $livestockSelected;
    $form['livestock_others'] = $livestockOthers;
    $form['crops'] = $cropsSelected;
    $form['crops_others'] = $cropsOthers;

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
    // Clear the irrelevant field so we don't store stale data
    if ($farmer_type === 'Livestock') $crops_details = '';
    if ($farmer_type === 'Crops') $livestock_details = '';

    if (!empty($errors)) {
        $message = implode(' ', $errors);
        $success = false;
    } else {
        try {
            $membership_id = generateMembershipId($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO members
                    (membership_id, last_name, first_name, middle_name, gender, date_of_birth, occupation, address, contact_number, membership_type, date_joined, hectares_cultivated, farmer_type, livestock_details, crops_details, created_at)
                VALUES
                    (:membership_id, :last_name, :first_name, :middle_name, :gender, :date_of_birth, :occupation, :address, :contact_number, :membership_type, :date_joined, :hectares_cultivated, :farmer_type, :livestock_details, :crops_details, NOW())
            ");
            $stmt->execute([
                ':membership_id'   => $membership_id,
                ':last_name'       => $last_name,
                ':first_name'      => $first_name,
                ':middle_name'     => $middle_name,
                ':gender'          => $gender,
                ':date_of_birth'   => $date_of_birth,
                ':occupation'      => $occupation,
                ':address'         => $address,
                ':contact_number'  => $contact_number,
                ':membership_type' => $membership_type,
                ':date_joined'     => $date_joined,
                ':hectares_cultivated' => $hectares_cultivated,
                ':farmer_type'     => $farmer_type,
                ':livestock_details' => $livestock_details !== '' ? $livestock_details : null,
                ':crops_details'   => $crops_details !== '' ? $crops_details : null,
            ]);

            $newMembershipId = $membership_id;
            $message = "Member profile created successfully! Membership ID: {$membership_id}. Admin can now create a login account for this member.";
            $success = true;

            logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_created', 'success');

            // Reset form
            $form = [
                'last_name' => '', 'first_name' => '', 'middle_name' => '',
                'gender' => '', 'date_of_birth' => '', 'occupation' => '',
                'address' => '', 'contact_number' => '',
                'membership_type' => '', 'date_joined' => date('Y-m-d'),
                'hectares_cultivated' => '',
                'farmer_type' => '', 'livestock' => [], 'livestock_others' => '',
                'crops' => [], 'crops_others' => '',
            ];

        } catch (PDOException $e) {
            $message = "Error creating member profile: " . $e->getMessage();
            $success = false;
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'], 'member_created', 'failed');
        }
    }
}

renderHeader('Member Profiling');
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Member Profiling</h2>
        <span class="badge badge-manager">Manager</span>
    </div>

    <div class="info-box">
        Create a new cooperative member profile. Membership ID is auto-generated.
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
            <input type="text" value="Auto-generated (e.g. SJFMC-0001)" disabled style="background: #f0f0f0; color: #888;">
        </div>

        <div class="form-group">
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required placeholder="Dela Cruz" value="<?php echo htmlspecialchars($form['last_name']); ?>">
        </div>

        <div class="form-group">
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" required placeholder="Juan" value="<?php echo htmlspecialchars($form['first_name']); ?>">
        </div>

        <div class="form-group">
            <label for="middle_name">Middle Name:</label>
            <input type="text" id="middle_name" name="middle_name" required placeholder="Santos" value="<?php echo htmlspecialchars($form['middle_name']); ?>">
        </div>

        <div class="form-group">
            <label for="gender">Gender:</label>
            <select id="gender" name="gender" required>
                <option value="">-- Select Gender --</option>
                <option value="Male" <?php echo $form['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo $form['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
            </select>
        </div>

        <div class="form-group">
            <label for="date_of_birth">Date of Birth:</label>
            <input type="date" id="date_of_birth" name="date_of_birth" required value="<?php echo htmlspecialchars($form['date_of_birth']); ?>">
        </div>

        <div class="form-group">
            <label for="occupation">Occupation:</label>
            <input type="text" id="occupation" name="occupation" required placeholder="Farming" value="<?php echo htmlspecialchars($form['occupation']); ?>">
        </div>

        <div class="form-group">
            <label for="address">Complete Address:</label>
            <input type="text" id="address" name="address" required placeholder="Street, Barangay, City, Province" value="<?php echo htmlspecialchars($form['address']); ?>">
        </div>

        <div class="form-group">
            <label for="contact_number">Contact Number:</label>
            <input type="text" id="contact_number" name="contact_number" required placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($form['contact_number']); ?>">
        </div>

        <h3>Membership Information</h3>

        <div class="form-group">
            <label for="membership_type">Membership Type:</label>
            <select id="membership_type" name="membership_type" required>
                <option value="">-- Select Type --</option>
                <option value="Regular" <?php echo $form['membership_type'] === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                <option value="Associate" <?php echo $form['membership_type'] === 'Associate' ? 'selected' : ''; ?>>Associate</option>
            </select>
        </div>

        <div class="form-group">
            <label for="date_joined">Date Joined:</label>
            <input type="date" id="date_joined" name="date_joined" required value="<?php echo htmlspecialchars($form['date_joined']); ?>">
        </div>

        <div class="form-group">
            <label for="hectares_cultivated">Number of Hectares Cultivated:</label>
            <select id="hectares_cultivated" name="hectares_cultivated" required>
                <option value="">-- Select Number of Hectares --</option>
                <?php foreach ($hectaresOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $form['hectares_cultivated'] === $option ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($option); ?> hectare<?php echo $option !== '1' ? 's' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <h3>Farming Profile</h3>

        <div class="form-group">
            <label for="farmer_type">Type of Farmer:</label>
            <select id="farmer_type" name="farmer_type" required onchange="toggleFarmerFields()">
                <option value="">-- Select Type --</option>
                <option value="Livestock" <?php echo $form['farmer_type'] === 'Livestock' ? 'selected' : ''; ?>>Livestock</option>
                <option value="Crops" <?php echo $form['farmer_type'] === 'Crops' ? 'selected' : ''; ?>>Crops</option>
                <option value="Both" <?php echo $form['farmer_type'] === 'Both' ? 'selected' : ''; ?>>Both</option>
            </select>
        </div>

        <div class="form-group" id="livestock_field" style="display: none;">
            <label>Livestock Raised:</label>
            <div style="display: flex; flex-wrap: wrap; gap: 10px 20px; margin-top: 5px;">
                <?php foreach ($livestockOptions as $option): ?>
                    <label style="display: flex; align-items: center; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="livestock[]" value="<?php echo htmlspecialchars($option); ?>"
                               style="width: auto; margin-right: 6px;"
                               <?php echo in_array($option, $form['livestock']) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($option); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="livestock_others" placeholder="Others (specify)" style="margin-top: 10px;" value="<?php echo htmlspecialchars($form['livestock_others']); ?>">
        </div>

        <div class="form-group" id="crops_field" style="display: none;">
            <label>Crops Grown:</label>
            <div style="display: flex; flex-wrap: wrap; gap: 10px 20px; margin-top: 5px;">
                <?php foreach ($cropOptions as $option): ?>
                    <label style="display: flex; align-items: center; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="crops[]" value="<?php echo htmlspecialchars($option); ?>"
                               style="width: auto; margin-right: 6px;"
                               <?php echo in_array($option, $form['crops']) ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($option); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="crops_others" placeholder="Others (specify)" style="margin-top: 10px;" value="<?php echo htmlspecialchars($form['crops_others']); ?>">
        </div>

        <script>
        function toggleFarmerFields() {
            const type = document.getElementById('farmer_type').value;
            const livestockField = document.getElementById('livestock_field');
            const cropsField = document.getElementById('crops_field');

            livestockField.style.display = (type === 'Livestock' || type === 'Both') ? 'block' : 'none';
            cropsField.style.display = (type === 'Crops' || type === 'Both') ? 'block' : 'none';
        }
        // Run once on load in case the form is re-rendered with a previous selection (e.g. validation error)
        toggleFarmerFields();
        </script>

        <button type="submit">
            <span class="material-icons" style="vertical-align: middle; font-size: 18px;">person_add</span>
            Register Member
        </button>
    </form>
</div>

<?php renderFooter(); ?>