<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';
requireLogin();

// Role-based filtering
$currentRole = $_SESSION['role'];
$currentUserId = $_SESSION['user_id'];

$pageTitle = ($currentRole === 'admin' || $currentRole === 'manager') ? 'Users' : 'My Account';

// ============================================================
// CROP / LIVESTOCK IMAGE MAPPING
// Same option lists used by member-management.php / member-create.php.
// One image file per type - reused across every member who has it.
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
// REGULAR USER - show their own full profile inline
// Account Info and Personal/Membership Info are now in
// SEPARATE cards. Only Account Info is editable, via a
// popup/modal (no page navigation, AJAX submit to
// user-update.php).
// ============================================================

if ($currentRole === 'user') {

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
    $canChangeThisRole = $user ? canChangeRole($currentRole, $currentUserId, $user['id']) : false;

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
        <strong>Your Role: <span class="badge badge-<?php echo $currentRole; ?>"><?php echo ucfirst($currentRole); ?></span></strong><br>
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

            fetch('user-update.php?user_id=<?php echo $user['id']; ?>', {
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

    <?php
    renderFooter();
    exit;
}

// ============================================================
// ADMIN / MANAGER - listing table
// Create Account and Edit User now open as modals (AJAX) instead
// of navigating to separate pages. "Your Role" banner removed.
// Delete User also opens as a modal (AJAX) instead of the native
// browser confirm() + separate delete page.
// ============================================================

$baseSelect = "SELECT u.id, u.email, u.role, u.account_status, u.is_verified, u.created_at,
                      m.membership_id, m.last_name, m.first_name, m.middle_name
               FROM users u
               LEFT JOIN members m ON u.member_id = m.id";

if ($currentRole === 'admin') {
    // Admin manages staff accounts (Admin/Manager)
    $stmt = $pdo->prepare("$baseSelect WHERE u.role IN ('admin', 'manager') ORDER BY
        CASE u.role
            WHEN 'admin' THEN 1
            WHEN 'manager' THEN 2
        END, u.created_at DESC");
    $stmt->execute();
} else {
    // Manager manages regular User (member) accounts
    $stmt = $pdo->prepare("$baseSelect WHERE u.role = 'user' ORDER BY u.created_at DESC");
    $stmt->execute();
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Members without a linked account yet - only needed for the
// manager's "+ Create account" modal dropdown.
$availableMembers = ($currentRole === 'manager') ? getAvailableMembers($pdo) : [];

// Deterministic avatar color per user, based on id so it stays the
// same across page loads instead of being random each time.
$umAvatarColors = ['#0C447C', '#3B6D11', '#712B13', '#534AB7', '#993556', '#854F0B'];
function umAvatarColor($seed, $colors) {
    $hash = crc32((string)$seed);
    return $colors[$hash % count($colors)];
}
function umInitials($name) {
    $parts = array_filter(array_map('trim', explode(',', $name)));
    $letters = array_map(function($p) { return mb_strtoupper(mb_substr($p, 0, 1)); }, $parts);
    return implode('', array_slice($letters, 0, 2));
}

renderHeader($pageTitle);
?>

<style>
    .um-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    .um-header h1 { margin: 0; }

    .um-header-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    #openCreateBtn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        width: auto !important;
        flex: none !important;
        background: #1976d2 !important;
        color: #fff !important;
        border: none !important;
        padding: 10px 18px !important;
        border-radius: 6px !important;
        font-size: 14px !important;
        cursor: pointer;
        white-space: nowrap;
    }

    .um-toolbar {
        margin-bottom: 14px;
    }
    .um-toolbar p { margin: 0; }

    #um-search-wrap {
        position: relative;
        width: 230px;
        max-width: 100%;
        flex: none;
    }
    #um-search-wrap .material-icons {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 18px;
        color: #888;
        pointer-events: none;
    }
    #umSearchInput {
        width: 100% !important;
        box-sizing: border-box !important;
        padding: 9px 14px 9px 38px !important;
        border-radius: 24px !important;
        border: 1px solid #ccc !important;
        background: #f7f8fa !important;
        font-size: 13px;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    #umSearchInput:focus {
        outline: none !important;
        border-color: #1976d2 !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.12) !important;
    }

    /* ===== Card-style table: bordered, rounded, subtle shadow ===== */
    #umTable {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }
    #umTable thead tr {
        background: #f5f6f8;
        text-align: left;
    }
    #umTable th {
        padding: 12px 14px;
        font-size: 13px;
        color: #555;
    }
    #umTable td {
        padding: 12px 14px;
        font-size: 13px;
    }
    #umTable tbody tr {
        border-top: 1px solid #eee;
    }

    /* ===== Info rows (used by the read-only View User modal) ===== */
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

    /* ===== Modal (shared styles for Create Account + Edit User + Delete User) ===== */
    .um-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .um-modal-overlay.open { display: flex; }

    .um-modal-box {
        background: #fff;
        border-radius: 14px;
        padding: 28px 24px 24px;
        width: 100%;
        max-width: 440px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        box-sizing: border-box;
    }

    .um-modal-box h2 {
        background: none !important;
        color: #1a1a1a !important;
        margin: 0 0 16px 0 !important;
        padding: 0 40px 0 0 !important;
        font-size: 20px !important;
        font-weight: 600 !important;
    }

    .um-modal-close {
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
        z-index: 10;
        user-select: none;
    }
    .um-modal-close:hover {
        background: #eef3fb !important;
        border-color: #274b81 !important;
    }

    .um-modal-message {
        display: none;
        margin-bottom: 14px;
    }
</style>

<div class="um-header">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    <div class="um-header-actions">
        <div id="um-search-wrap">
            <span class="material-icons">search</span>
            <input type="text" id="umSearchInput" placeholder="Search name or ID" autocomplete="off">
        </div>
        <?php if ($currentRole === 'manager'): ?>
            <button type="button" id="openCreateBtn" onclick="openCreateModal()">
                <span class="material-icons" style="font-size: 18px;">person_add</span>
                Create account
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="um-toolbar">
    <p>
        <?php if ($currentRole === 'admin'): ?>
            Showing all staff accounts (<span id="umVisibleCount"><?php echo count($users); ?></span> total)
        <?php else: ?>
            Showing all member accounts (<span id="umVisibleCount"><?php echo count($users); ?></span> total)
        <?php endif; ?>
    </p>
</div>

<table id="umTable">
    <thead>
        <tr>
            <th>Membership ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Verified</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <?php
            $rowName = $user['last_name'] ? ($user['last_name'] . ', ' . $user['first_name']) : '-';
            $rowSearchText = strtolower($rowName . ' ' . $user['membership_id'] . ' ' . $user['email']);
            $canEditThisUser = canEditUser($currentRole, $currentUserId, $user['role'], $user['id']);
            $canChangeThisUserRole = canChangeRole($currentRole, $currentUserId, $user['id']);
        ?>
        <tr data-search="<?php echo htmlspecialchars($rowSearchText); ?>">
            <td><?php echo $user['membership_id'] ? htmlspecialchars($user['membership_id']) : '<span style="color:#999;">Staff</span>'; ?></td>
            <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #fff; flex-shrink: 0; background: <?php echo umAvatarColor($user['id'], $umAvatarColors); ?>;">
                        <?php echo htmlspecialchars(umInitials($rowName !== '-' ? $rowName : $user['email'])); ?>
                    </div>
                    <p style="margin: 0; font-weight: 600;"><?php echo htmlspecialchars($rowName); ?></p>
                </div>
            </td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td>
                <span class="badge badge-<?php echo $user['role']; ?>">
                    <?php echo ucfirst($user['role']); ?>
                </span>
            </td>
            <td>
                <span class="badge" style="background: <?php echo $user['account_status'] === 'Active' ? '#28a745' : '#6c757d'; ?>;">
                    <?php echo htmlspecialchars($user['account_status']); ?>
                </span>
            </td>
            <td>
                <span class="badge badge-<?php echo $user['is_verified'] ? 'verified' : 'unverified'; ?>">
                    <?php echo $user['is_verified'] ? 'Yes' : 'No'; ?>
                </span>
            </td>
            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
            <td style="white-space: nowrap;">
                <?php if ($canEditThisUser): ?>
                    <a href="#"
                         onclick="openEditUserModal(<?php echo $user['id']; ?>, <?php echo htmlspecialchars(json_encode($user['email']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($user['role']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($user['account_status']), ENT_QUOTES); ?>, <?php echo $canChangeThisUserRole ? 'true' : 'false'; ?>); return false;"
                         style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:#274b81 !important; text-decoration:none !important; margin-right:4px !important; cursor:pointer;">
                        <span class="material-icons" style="font-size:14px !important;">edit</span>Edit
                    </a>
                <?php endif; ?>

                <?php if (canDeleteUser($currentRole, $currentUserId, $user['role'], $user['id'])): ?>
                    <a href="#"
                         onclick="openDeleteUserModal(<?php echo $user['id']; ?>, <?php echo htmlspecialchars(json_encode($user['email']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($user['role']), ENT_QUOTES); ?>); return false;"
                         style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:#a6322f !important; text-decoration:none !important; cursor:pointer;">
                        <span class="material-icons" style="font-size:14px !important;">delete</span>Delete
                    </a>
                <?php elseif ($user['id'] == $currentUserId): ?>
                    <span title="You cannot delete your own account"
                          style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:#999 !important;">
                        <span class="material-icons" style="font-size:14px !important;">delete</span>Delete
                    </span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p id="umNoResults" style="display:none; text-align: center; padding: 40px; color: #6c757d;">
    No accounts match your search.
</p>

<?php if (empty($users)): ?>
    <p style="text-align: center; padding: 40px; color: #6c757d;">
        No accounts to display based on your role permissions.
    </p>
<?php endif; ?>

<?php if ($currentRole === 'manager'): ?>
<!-- ==================== CREATE ACCOUNT MODAL ==================== -->
<div class="um-modal-overlay" id="createModalOverlay">
    <div class="um-modal-box">
        <span class="um-modal-close" onclick="closeCreateModal()" role="button" tabindex="0" aria-label="Close">&times;</span>
        <h2>Create account</h2>

        <div class="info-box" style="margin-bottom: 16px; font-size: 13px;">
            Select a cooperative member to give them a login account. Only members without an existing account are listed.
        </div>

        <div id="createModalMessage" class="um-modal-message"></div>

        <form id="createAccountForm">
            <div class="form-group">
                <label for="create_member_id">Select Member:</label>
                <select id="create_member_id" name="member_id" required>
                    <option value="">-- Select a member --</option>
                    <?php foreach ($availableMembers as $m): ?>
                        <option value="<?php echo $m['id']; ?>">
                            <?php echo htmlspecialchars($m['membership_id'] . ' - ' . $m['last_name'] . ', ' . $m['first_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="create_email">Email Address:</label>
                <input type="email" id="create_email" name="email" required placeholder="user@example.com">
            </div>

            <div class="form-group">
                <label for="create_password">Password:</label>
                <input type="password" id="create_password" name="password" required placeholder="Enter password">
            </div>

            <div class="form-group">
                <label for="create_confirm_password">Confirm Password:</label>
                <input type="password" id="create_confirm_password" name="confirm_password" required placeholder="Re-enter password">
            </div>

            <div class="form-group">
                <label>Role:</label>
                <input type="text" value="User" disabled style="background: #f0f0f0; color: #888;">
                <small style="color: #666;">Managers can only create regular User accounts.</small>
            </div>

            <div class="form-group">
                <label for="create_account_status">Account Status:</label>
                <select id="create_account_status" name="account_status">
                    <option value="Active" selected>Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="send_verification_email" value="1" checked
                           style="width: auto; margin-right: 10px;">
                    <span>Send email verification (recommended)</span>
                </label>
                <small style="color: #666; margin-left: 30px;">
                    If unchecked, user will be verified immediately without email confirmation.
                </small>
            </div>

            <button type="submit">
                <span class="material-icons" style="vertical-align: middle; font-size: 18px;">person_add</span>
                Create account
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ==================== EDIT USER MODAL (admin/manager) ==================== -->
<div class="um-modal-overlay" id="editUserModalOverlay">
    <div class="um-modal-box">
        <span class="um-modal-close" onclick="closeEditUserModal()" role="button" tabindex="0" aria-label="Close">&times;</span>
        <h2>Update user</h2>

        <div id="editUserModalMessage" class="um-modal-message"></div>

        <form id="editUserForm">
            <div class="form-group">
                <label for="eu_email">Email:</label>
                <input type="email" id="eu_email" name="email">
            </div>

            <div class="form-group">
                <label for="eu_password">Password (leave empty to keep current):</label>
                <input type="password" id="eu_password" name="password">
            </div>

            <div class="form-group">
                <label for="eu_role">Role:</label>
                <select id="eu_role" name="role">
                    <option value="user">User</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
                <small id="eu_role_hint" style="color: #666; display: none;">Only admins can change roles, and not their own.</small>
            </div>

            <div class="form-group">
                <label for="eu_account_status">Account Status:</label>
                <select id="eu_account_status" name="account_status">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <button type="submit">Update user</button>
        </form>
    </div>
</div>

<!-- ==================== DELETE USER MODAL (admin/manager) ==================== -->
<div class="um-modal-overlay" id="deleteUserModalOverlay">
    <div class="um-modal-box">
        <span class="um-modal-close" onclick="closeDeleteUserModal()" role="button" tabindex="0" aria-label="Close">&times;</span>
        <h2>Delete User</h2>

        <p>Are you sure you want to delete this user?</p>

        <div class="info-box" style="margin-bottom: 16px; font-size: 13px;">
            <strong>User Details:</strong><br>
            Email: <span id="delUserEmail"></span><br>
            Role: <span id="delUserRole"></span>
        </div>

        <div id="deleteUserModalMessage" class="um-modal-message"></div>

        <form id="deleteUserForm">
            <button type="submit" style="background:#dc3545 !important;">Delete User</button>
            <button type="button" onclick="closeDeleteUserModal()" style="background:#6c757d !important; margin-top:8px;">Cancel</button>
        </form>
    </div>
</div>

<script>
(function() {
    // ============================================================
    // SEARCH FILTER (client-side, filters by name / membership ID / email)
    // ============================================================
    const searchInput = document.getElementById('umSearchInput');
    const tableRows = Array.from(document.querySelectorAll('#umTable tbody tr'));
    const visibleCount = document.getElementById('umVisibleCount');
    const noResults = document.getElementById('umNoResults');
    const table = document.getElementById('umTable');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = searchInput.value.trim().toLowerCase();
            let shown = 0;
            tableRows.forEach(function(row) {
                const match = row.dataset.search.indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            if (visibleCount) visibleCount.textContent = shown;
            if (table) table.style.display = shown === 0 ? 'none' : '';
            if (noResults) noResults.style.display = shown === 0 ? 'block' : 'none';
        });
    }

    // ============================================================
    // CREATE ACCOUNT MODAL
    // ============================================================
    window.openCreateModal = function() {
        const overlay = document.getElementById('createModalOverlay');
        if (overlay) overlay.classList.add('open');
    };
    window.closeCreateModal = function() {
        const overlay = document.getElementById('createModalOverlay');
        if (!overlay) return;
        overlay.classList.remove('open');
        const msg = document.getElementById('createModalMessage');
        msg.style.display = 'none';
    };

    const createOverlay = document.getElementById('createModalOverlay');
    if (createOverlay) {
        createOverlay.addEventListener('click', function(e) {
            if (e.target === this) closeCreateModal();
        });
    }

    const createForm = document.getElementById('createAccountForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = createForm.querySelector('button[type="submit"]');
            const formData = new FormData(createForm);
            formData.append('ajax', '1');
            submitBtn.disabled = true;

            fetch('<?php echo BASE_URL; ?>/app/members/account-create.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const msgBox = document.getElementById('createModalMessage');
                msgBox.style.display = 'block';
                msgBox.className = 'um-modal-message ' + (data.success ? 'success' : 'error');
                msgBox.textContent = data.message;

                if (data.success) {
                    createForm.reset();
                    setTimeout(function() {
                        closeCreateModal();
                        window.location.reload();
                    }, 1200);
                }
            })
            .catch(function() {
                const msgBox = document.getElementById('createModalMessage');
                msgBox.style.display = 'block';
                msgBox.className = 'um-modal-message error';
                msgBox.textContent = 'Something went wrong. Please try again.';
            })
            .finally(function() {
                submitBtn.disabled = false;
            });
        });
    }

    // ============================================================
    // EDIT USER MODAL (admin/manager editing another account)
    // ============================================================
    let currentEditUserId = null;

    window.openEditUserModal = function(userId, email, role, accountStatus, canChangeRole) {
        currentEditUserId = userId;
        document.getElementById('eu_email').value = email;
        document.getElementById('eu_password').value = '';
        document.getElementById('eu_account_status').value = accountStatus;

        const roleSelect = document.getElementById('eu_role');
        const roleHint = document.getElementById('eu_role_hint');
        roleSelect.value = role;
        roleSelect.disabled = !canChangeRole;
        roleHint.style.display = canChangeRole ? 'none' : 'block';

        document.getElementById('editUserModalMessage').style.display = 'none';
        document.getElementById('editUserModalOverlay').classList.add('open');
    };

    window.closeEditUserModal = function() {
        document.getElementById('editUserModalOverlay').classList.remove('open');
    };

    const editUserOverlay = document.getElementById('editUserModalOverlay');
    editUserOverlay.addEventListener('click', function(e) {
        if (e.target === this) closeEditUserModal();
    });

    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!currentEditUserId) return;

        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);
        formData.append('ajax', '1');
        submitBtn.disabled = true;

        fetch('user-update.php?user_id=' + currentEditUserId, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            const msgBox = document.getElementById('editUserModalMessage');
            msgBox.style.display = 'block';
            msgBox.className = 'um-modal-message ' + (data.success ? 'success' : 'error');
            msgBox.textContent = data.message;

            if (data.success) {
                setTimeout(function() {
                    closeEditUserModal();
                    window.location.reload();
                }, 1000);
            }
        })
        .catch(function() {
            const msgBox = document.getElementById('editUserModalMessage');
            msgBox.style.display = 'block';
            msgBox.className = 'um-modal-message error';
            msgBox.textContent = 'Something went wrong. Please try again.';
        })
        .finally(function() {
            submitBtn.disabled = false;
        });
    });

    // ============================================================
    // DELETE USER MODAL
    // ============================================================
    let currentDeleteUserId = null;

    window.openDeleteUserModal = function(userId, email, role) {
        currentDeleteUserId = userId;
        document.getElementById('delUserEmail').textContent = email;
        document.getElementById('delUserRole').textContent = role;
        document.getElementById('deleteUserModalMessage').style.display = 'none';
        document.getElementById('deleteUserModalOverlay').classList.add('open');
    };

    window.closeDeleteUserModal = function() {
        document.getElementById('deleteUserModalOverlay').classList.remove('open');
    };

    const deleteUserOverlay = document.getElementById('deleteUserModalOverlay');
    deleteUserOverlay.addEventListener('click', function(e) {
        if (e.target === this) closeDeleteUserModal();
    });

    document.getElementById('deleteUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!currentDeleteUserId) return;

        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        fetch('user-delete.php?user_id=' + currentDeleteUserId, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ ajax: '1' })
        })
        .then(res => res.json())
        .then(data => {
            const msgBox = document.getElementById('deleteUserModalMessage');
            msgBox.style.display = 'block';
            msgBox.className = 'um-modal-message ' + (data.success ? 'success' : 'error');
            msgBox.textContent = data.message;

            if (data.success) {
                setTimeout(function() {
                    closeDeleteUserModal();
                    window.location.reload();
                }, 1000);
            }
        })
        .catch(function() {
            const msgBox = document.getElementById('deleteUserModalMessage');
            msgBox.style.display = 'block';
            msgBox.className = 'um-modal-message error';
            msgBox.textContent = 'Something went wrong. Please try again.';
        })
        .finally(function() {
            submitBtn.disabled = false;
        });
    });
})();
</script>

<?php renderFooter(); ?>