<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
requireRole('user');

$currentUserId = $_SESSION['user_id'];
$pageTitle = 'My Account';

// ============================================================
// CROP / LIVESTOCK IMAGE MAPPING
// Same option lists used by manager/member-management/member-management.php
// and member-create.php. One image file per type - reused across
// every member who has it.
// ============================================================
function livestockImageUrl($type) {
    $key = strtolower(trim($type));
    $map = [
        'chicken' => 'chicken.jpg',
        'duck'    => 'duck.jpg',
        'pig'     => 'pig.jpg',
        'goat'    => 'goat.jpg',
        'cow'     => 'cow.jpg',
        'carabao' => 'carabao.jpg',
    ];
    $file = $map[$key] ?? 'default-livestock.jpg';
    return BASE_URL . '/assets/img/livestock/' . $file;
}

function cropImageUrl($crop) {
    $key = strtolower(trim($crop));
    $map = [
        'corn'          => 'corn.jpg',
        'cassava'       => 'cassava.jpg',
        'cucumber'      => 'cucumber.jpg',
        'eggplant'      => 'eggplant.jpg',
        'squash'        => 'squash.jpg',
        'chayote'       => 'chayote.jpg',
        'bitter melon'  => 'bitter-melon.jpg',
        'string beans'  => 'string-beans.jpg',
    ];
    $file = $map[$key] ?? 'default-crop.jpg';
    return BASE_URL . '/assets/img/crops/' . $file;
}

// ============================================================
// Show the user's own full profile inline. Account Info and
// Personal/Membership Info are in SEPARATE cards. Only Account
// Info is editable, via a popup/modal (no page navigation, AJAX
// submit to the shared includes/handlers/user-update.php).
// ============================================================

$stmt = $pdo->prepare("
    SELECT u.*, m.membership_id, m.last_name, m.first_name, m.middle_name,
           m.gender, m.date_of_birth, m.occupation, m.address, m.contact_number,
           m.membership_type, m.date_joined, m.hectares_cultivated,
           m.farmer_type, m.livestock_details, m.crops_details
    FROM users u
    LEFT JOIN members m ON u.member_id = m.id
    WHERE u.id = ?
");
$stmt->execute([$currentUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$verificationExpired = false;
if ($user && !$user['is_verified'] && $user['email_verification_expires']) {
    $verificationExpired = strtotime($user['email_verification_expires']) < time();
}

// Regular users can never change their own role - the modal
// dropdown stays disabled and posts a hidden field instead.
$canChangeThisRole = $user ? canChangeRole('user', $currentUserId, $user['id']) : false;

renderHeader($pageTitle);
?>

<style>
    .card-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        align-items: start;
    }

    @media (max-width: 900px) {
        .card-columns {
            grid-template-columns: 1fr;
        }
    }

    .card-box {
        box-sizing: border-box;
        border: 1px solid #d5d2c7;
        border-radius: 14px;
        background: #fff;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }

    .card-box-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px 12px;
        margin-bottom: 14px;
    }

    .card-box-header h3 {
        margin: 0;
        flex: 1 1 auto;
        min-width: 140px;
    }

    /* ===== Info rows (replaces the plain <table> look) =====
       Label on the left, value on the right - a site-wide default
       <table>/<th> style was rendering labels in blue/teal, so this
       avoids <table> entirely for a clean, consistent look. */
    .info-rows {
        display: flex;
        flex-direction: column;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f0efe9;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-row .info-label {
        font-size: 13px;
        font-weight: 600;
        color: #6b7280;
        flex-shrink: 0;
    }

    .info-row .info-value {
        font-size: 13.5px;
        color: #1a1a1a;
        text-align: right;
        word-break: break-word;
    }

    /* Livestock Raised / Crops Grown rows show picture cards instead
       of plain text, so they need top-alignment and room to wrap. */
    .info-row-cards {
        align-items: flex-start;
    }

    .profile-card-group {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: flex-end;
    }

    .profile-crop-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        width: 76px;
        text-align: center;
    }

    .profile-crop-card img {
        width: 68px;
        height: 68px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid #e4e2d8;
        flex-shrink: 0;
    }

    .profile-crop-card span {
        font-size: 12px;
        font-weight: 500;
        color: #333;
        line-height: 1.3;
    }

    .info-pill {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        color: #fff;
    }

    /* ===== Edit button: compact, never stretches =====
       Using #editBtn (id) for maximum specificity plus !important,
       since a site-wide "button { width: 100% }" rule elsewhere
       keeps overriding class-based rules. */
    #editBtn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 4px !important;
        flex: none !important;
        width: auto !important;
        max-width: 100px !important;
        min-width: 0 !important;
        align-self: flex-start !important;
        background: #fff !important;
        border: 1px solid #cfd8e3 !important;
        border-radius: 8px !important;
        padding: 6px 12px !important;
        font-size: 13px !important;
        color: #274b81 !important;
        cursor: pointer !important;
        white-space: nowrap !important;
        margin: 0 !important;
        box-shadow: none !important;
        text-transform: none !important;
        letter-spacing: normal !important;
    }

    #editBtn:hover {
        background: #eef3fb !important;
    }

    #editBtn .material-icons {
        font-size: 16px;
    }

    /* ===== Page header (title + back link) ===== */
    .account-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 4px;
    }

    .account-back {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        background: none;
        border: none;
        color: #2e7d32;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        padding: 4px 0;
        white-space: nowrap;
        margin-top: 8px;
    }

    .account-back svg {
        width: 15px;
        height: 15px;
        stroke: #2e7d32;
    }

    .account-back:hover {
        text-decoration: underline;
    }

    /* ===== Modal ===== */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }

    .modal-overlay.open {
        display: flex;
    }

    .modal-box {
        background: #fff;
        border-radius: 14px;
        padding: 28px 24px 24px;
        width: 100%;
        max-width: 420px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
    }

    /* Reset any global heading styling (colored background, full-bleed
       bar, etc.) that a site-wide h2 rule might apply, so the modal
       title looks like plain text again. */
    .modal-box h2 {
        background: none !important;
        color: #1a1a1a !important;
        margin: 0 0 16px 0 !important;
        padding: 0 40px 0 0 !important;
        font-size: 20px !important;
        font-weight: 600 !important;
    }

    /* ===== Close (x) button =====
       Rendered as a <span role="button">, NOT a <button> element.
       A site-wide "button { width: 100%; background: ... }" rule was
       stretching a <button>-based close icon into a full-width green
       bar even with #id + !important overrides, because it's still
       an element the global "button" selector matches. A <span>
       can never be touched by any "button { }" rule anywhere. */
    #closeBtn {
        position: absolute !important;
        top: 16px !important;
        right: 16px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 28px !important;
        height: 28px !important;
        min-width: 0 !important;
        max-width: 28px !important;
        padding: 0 !important;
        margin: 0 !important;
        background: #fff !important;
        border: 1px solid #cfd8e3 !important;
        border-radius: 50% !important;
        font-size: 16px !important;
        line-height: 1 !important;
        cursor: pointer !important;
        color: #274b81 !important;
        box-shadow: none !important;
        text-transform: none !important;
        z-index: 10;
        user-select: none;
    }

    #closeBtn:hover {
        background: #eef3fb !important;
        border-color: #274b81 !important;
    }

    #modalMessage {
        display: none;
        margin-bottom: 14px;
    }
</style>

<div class="account-header">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

</div>

<div class="info-box">
    <strong>Your Role: <span class="badge badge-user">User</span></strong><br>
    This is your account and membership profile.
</div>

<?php if ($user): ?>
    <div class="card-columns">

        <!-- ==================== ACCOUNT INFORMATION (editable) ==================== -->
        <div class="card-box">
            <div class="card-box-header">
                <h3>Account Information</h3>
            </div>
            <div class="info-rows">
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value" id="acctEmail"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-pill" style="background: #274b81;">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Account Status</span>
                    <span class="info-pill" id="acctStatusBadge" style="background: <?php echo $user['account_status'] === 'Active' ? '#28a745' : '#6c757d'; ?>;">
                        <?php echo htmlspecialchars($user['account_status']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Verified</span>
                    <span class="info-value">
                        <span class="info-pill" style="background: <?php echo $user['is_verified'] ? '#2e7d32' : '#6c757d'; ?>;">
                            <?php echo $user['is_verified'] ? 'Yes' : 'No'; ?>
                        </span>
                        <?php if (!$user['is_verified'] && $verificationExpired): ?>
                            <span class="info-pill" style="background: #f44336; margin-left: 6px;">Expired</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
                <button type="button" id="editBtn" class="edit-icon-btn" onclick="openEditModal()" style="display:inline-flex!important;width:auto!important;max-width:100px!important;min-width:0!important;flex:none!important;align-items:center!important;justify-content:center!important;gap:4px!important;background:#fff!important;border:1px solid #cfd8e3!important;border-radius:8px!important;padding:6px 12px!important;font-size:13px!important;color:#274b81!important;cursor:pointer!important;white-space:nowrap!important;margin:0!important;box-shadow:none!important;text-transform:none!important;">
                    <span class="material-icons">edit</span> Edit
                </button>
            </div>
        </div>

        <!-- ==================== PERSONAL & MEMBERSHIP INFO (read-only here) ==================== -->
        <div class="card-box">
            <?php if ($user['membership_id']): ?>
                <div class="card-box-header">
                    <h3>Personal & Membership Information</h3>
                </div>
                <div class="info-rows">
                    <div class="info-row">
                        <span class="info-label">Membership ID</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['membership_id']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['last_name'] . ', ' . $user['first_name'] . ' ' . $user['middle_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Gender</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['gender'] ?? '-'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date of Birth</span>
                        <span class="info-value"><?php echo !empty($user['date_of_birth']) ? date('F j, Y', strtotime($user['date_of_birth'])) : '-'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Occupation</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['occupation'] ?? '-'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['address'] ?? '-'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['contact_number'] ?? '-'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Membership Type</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['membership_type'] ?? '-'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date Joined</span>
                        <span class="info-value"><?php echo !empty($user['date_joined']) ? date('F j, Y', strtotime($user['date_joined'])) : '-'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Hectares Cultivated</span>
                        <span class="info-value"><?php echo !empty($user['hectares_cultivated']) ? htmlspecialchars($user['hectares_cultivated']) . ' hectare' . ($user['hectares_cultivated'] !== '1' ? 's' : '') : '-'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Type of Farmer</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['farmer_type'] ?? '-'); ?></span>
                    </div>
                    <?php if (!empty($user['livestock_details'])): ?>
                    <div class="info-row info-row-cards">
                        <span class="info-label">Livestock Raised</span>
                        <div class="info-value profile-card-group">
                            <?php foreach (array_filter(array_map('trim', explode(',', $user['livestock_details']))) as $item): ?>
                                <div class="profile-crop-card">
                                    <img src="<?php echo livestockImageUrl($item); ?>" alt="<?php echo htmlspecialchars($item); ?>">
                                    <span><?php echo htmlspecialchars($item); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['crops_details'])): ?>
                    <div class="info-row info-row-cards">
                        <span class="info-label">Crops Grown</span>
                        <div class="info-value profile-card-group">
                            <?php foreach (array_filter(array_map('trim', explode(',', $user['crops_details']))) as $item): ?>
                                <div class="profile-crop-card">
                                    <img src="<?php echo cropImageUrl($item); ?>" alt="<?php echo htmlspecialchars($item); ?>">
                                    <span><?php echo htmlspecialchars($item); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h3>Personal & Membership Information</h3>
                <div class="info-box" style="margin-top: 10px;">
                    No member profile linked to this account yet.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ==================== EDIT MODAL (Account Info only) ==================== -->
    <div class="modal-overlay" id="editModalOverlay">
        <div class="modal-box">
            <span id="closeBtn" class="modal-close" onclick="closeEditModal()" role="button" tabindex="0" aria-label="Close" style="position:absolute!important;top:16px!important;right:16px!important;display:flex!important;align-items:center!important;justify-content:center!important;width:28px!important;height:28px!important;min-width:0!important;max-width:28px!important;padding:0!important;margin:0!important;background:#fff!important;border:1px solid #cfd8e3!important;border-radius:50%!important;font-size:16px!important;line-height:1!important;cursor:pointer!important;color:#274b81!important;box-shadow:none!important;z-index:10;">&times;</span>
            <h2>Update Account</h2>

            <div id="modalMessage"></div>

            <form id="editAccountForm">
                <div class="form-group">
                    <label for="modal_email">Email:</label>
                    <input type="email" id="modal_email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                </div>

                <div class="form-group">
                    <label for="modal_password">Password (leave empty to keep current):</label>
                    <input type="password" id="modal_password" name="password">
                </div>

                <div class="form-group">
                    <label for="modal_role">Role:</label>
                    <select id="modal_role" name="role" <?php echo $canChangeThisRole ? '' : 'disabled'; ?>>
                        <option value="user" selected>User</option>
                    </select>
                    <?php if (!$canChangeThisRole): ?>
                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">
                        <small style="color: #666;">Only admins can change roles, and not their own.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="modal_account_status">Account Status:</label>
                    <select id="modal_account_status" name="account_status">
                        <option value="Active" <?php echo $user['account_status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $user['account_status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <button type="submit">Update User</button>
            </form>
        </div>
    </div>

    <script>
    function openEditModal() {
        document.getElementById('editModalOverlay').classList.add('open');
    }
    function closeEditModal() {
        document.getElementById('editModalOverlay').classList.remove('open');
        document.getElementById('modalMessage').style.display = 'none';
    }

    // Close modal when clicking outside the box
    document.getElementById('editModalOverlay').addEventListener('click', function (e) {
        if (e.target === this) closeEditModal();
    });

    // The close (x) is a <span role="button">, not a real <button>,
    // so it needs a manual keyboard handler for accessibility (Enter/Space).
    document.getElementById('closeBtn').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            closeEditModal();
        }
    });

    document.getElementById('editAccountForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);
        formData.append('ajax', '1');

        submitBtn.disabled = true;

        // NOTE: user-update.php is now a shared handler under
        // app/includes/handlers/ instead of app/users/.
        fetch('<?php echo BASE_URL; ?>/app/includes/handlers/user-update.php?user_id=<?php echo $user['id']; ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            const msgBox = document.getElementById('modalMessage');
            msgBox.style.display = 'block';
            msgBox.className = data.success ? 'success' : 'error';
            msgBox.textContent = data.message;

            if (data.success && data.user) {
                // Update the Account Information card in place - no reload
                document.getElementById('acctEmail').textContent = data.user.email;

                const statusBadge = document.getElementById('acctStatusBadge');
                statusBadge.textContent = data.user.account_status;
                statusBadge.style.background = data.user.account_status === 'Active' ? '#28a745' : '#6c757d';

                document.getElementById('modal_password').value = '';

                setTimeout(closeEditModal, 1200);
            }
        })
        .catch(() => {
            const msgBox = document.getElementById('modalMessage');
            msgBox.style.display = 'block';
            msgBox.className = 'error';
            msgBox.textContent = 'Something went wrong. Please try again.';
        })
        .finally(() => {
            submitBtn.disabled = false;
        });
    });
    </script>

<?php else: ?>
    <div class="error">Account not found.</div>
<?php endif; ?>

<?php renderFooter(); ?>