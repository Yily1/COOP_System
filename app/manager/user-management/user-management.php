<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
requireRole('manager');

$currentRole = $_SESSION['role'];
$currentUserId = $_SESSION['user_id'];
$pageTitle = 'Members';

// Deterministic avatar color per user, based on id so it stays the
// same across page loads instead of being random each time.
// Isang shared na listahan (pareho sa Member management); lahat pasado sa puting text.
$umAvatarColors = ['#274b81', '#2e7d32', '#8a5a0b', '#6b3fa0', '#993c1d', '#0F6E56'];
function umAvatarColor($seed, $colors) {
    $hash = crc32((string)$seed);
    return $colors[$hash % count($colors)];
}
function umInitials($name) {
    $parts = array_filter(array_map('trim', explode(',', $name)));
    $letters = array_map(function($p) { return mb_strtoupper(mb_substr($p, 0, 1)); }, $parts);
    return implode('', array_slice($letters, 0, 2));
}

// Manager manages regular User (member) accounts
$baseSelect = "SELECT u.id, u.username, u.email, u.role, u.account_status, u.is_verified, u.created_at,
                      m.membership_id, m.last_name, m.first_name, m.middle_name
               FROM users u
               LEFT JOIN members m ON u.member_id = m.id";

$stmt = $pdo->prepare("$baseSelect WHERE u.role = 'user' ORDER BY u.created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Members without a linked account yet - needed for the
// "+ Create account" modal dropdown.
$availableMembers = getAvailableMembers($pdo);

renderHeader($pageTitle);
?>

<style>
    /* ============================================================
       SHARED COLOR THEME
       (Kapag may global stylesheet na, ilipat ang :root block na ito doon
        at tanggalin dito. Pareho ang block sa 3 pages.)
       ============================================================ */
    :root {
        --c-brand:        #3B6D11;
        --c-brand-hover:  #2e5a0d;
        --c-brand-soft:   #EAF3DE;
        --c-brand-ink:    #27500A;
        --c-ink:          #1b3a24;
        --c-text:         #2c3e2c;
        --c-muted:        #5a645a;
        --c-success:      #2e7d32;
        --c-danger:       #a6322f;
        --c-danger-solid: #b02a37;
        --c-warn-bg:      #FAEEDA;
        --c-warn-ink:     #633806;
        --c-info:         #274b81;
        --c-surface:      #fff;
        --c-divider:      #e8ede0;
        --c-field-border: #7a857a;
    }

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

    /* Brand green, same as the "Add product" button */
    #openCreateBtn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        width: auto !important;
        flex: none !important;
        background: var(--c-brand) !important;
        color: #fff !important;
        border: none !important;
        padding: 10px 18px !important;
        border-radius: 6px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        cursor: pointer;
        white-space: nowrap;
    }
    #openCreateBtn:hover { background: var(--c-brand-hover) !important; }
    #openCreateBtn:focus-visible,
    .um-modal-box button:focus-visible,
    #umTable a:focus-visible {
        outline: 2px solid var(--c-brand);
        outline-offset: 2px;
    }

    .um-toolbar {
        margin-bottom: 14px;
        color: var(--c-muted);
    }
    .um-toolbar p { margin: 0; }

    #um-search-wrap {
        position: relative;
        width: 260px;
        max-width: 100%;
        flex: none;
    }
    #um-search-wrap .material-icons {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 18px;
        color: var(--c-muted);
        pointer-events: none;
    }
    #umSearchInput {
        width: 100% !important;
        box-sizing: border-box !important;
        padding: 9px 14px 9px 38px !important;
        border-radius: 24px !important;
        border: 1px solid var(--c-field-border) !important;
        background: #f7f8fa !important;
        font-size: 13px;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.04);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    #umSearchInput::placeholder,
    .um-modal-box .form-group input::placeholder { color: #6b756b; opacity: 1; }
    #umSearchInput:focus {
        outline: none !important;
        border-color: var(--c-brand) !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(59, 109, 17, 0.18) !important;
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
        color: var(--c-muted);
    }
    #umTable td {
        padding: 12px 14px;
        font-size: 13px;
        color: var(--c-text);
    }
    #umTable tbody tr {
        border-top: 1px solid #eee;
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
        padding: 22px 22px 18px;
        width: 100%;
        max-width: 620px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        box-sizing: border-box;
    }

    .um-modal-box h2 {
        background: none !important;
        color: var(--c-ink) !important;
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
        border: 1px solid var(--c-field-border) !important;
        border-radius: 50% !important;
        font-size: 16px !important;
        line-height: 1 !important;
        cursor: pointer !important;
        color: var(--c-info) !important;
        box-shadow: none !important;
        z-index: 10;
        user-select: none;
    }
    .um-modal-close:hover {
        background: #eef3fb !important;
        border-color: var(--c-info) !important;
    }
    .um-modal-close:focus-visible {
        outline: 2px solid var(--c-brand);
        outline-offset: 2px;
    }

    .um-modal-message {
        display: none;
        margin-bottom: 10px;
    }

    /* ===== Compact 2-column form layout so modals fit without scrolling ===== */
    .um-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 14px;
    }
    .um-form-grid .form-group { margin: 0 !important; min-width: 0; }
    .um-form-grid .um-span-2 { grid-column: 1 / -1; }
    .um-modal-box .form-group label {
        font-size: 13px;
        margin-bottom: 3px !important;
    }
    .um-modal-box .form-group input:not([type="checkbox"]),
    .um-modal-box .form-group select {
        padding: 8px 10px !important;
        font-size: 13px !important;
        width: 100%;
        box-sizing: border-box;
        border: 1px solid var(--c-field-border);
        border-radius: 6px;
    }
    .um-modal-box .form-group input:not([type="checkbox"]):focus,
    .um-modal-box .form-group select:focus {
        outline: none;
        border-color: var(--c-brand);
        box-shadow: 0 0 0 3px rgba(59, 109, 17, 0.18);
    }
    .um-modal-box .form-group small {
        display: block;
        font-size: 11px;
        margin-top: 2px;
        color: var(--c-muted);
    }
    .um-modal-box .um-info-compact {
        margin: 0 0 12px 0 !important;
        padding: 8px 12px !important;
        font-size: 12px !important;
    }
    .um-modal-box form button[type="submit"] {
        padding: 10px !important;
        margin-top: 2px;
    }

    /* ===== Fixed-height modal: title stays on top, only the form scrolls ===== */
    .um-modal-box {
        display: flex !important;
        flex-direction: column;
        max-height: calc(100vh - 32px) !important;
        overflow: hidden !important;
    }
    .um-modal-box h2,
    .um-modal-box .um-info-compact,
    .um-modal-box .um-modal-message {
        flex: 0 0 auto;
    }
    .um-modal-box form {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        padding-right: 6px;
    }
    /* Submit button stays visible at the bottom while the fields scroll */
    .um-modal-box form.um-form-grid button[type="submit"] {
        position: sticky;
        bottom: 0;
        z-index: 2;
        box-shadow: 0 -8px 8px -4px #fff;
    }

    /* Brand green Create account / Update user buttons
       (same as the "Add product" button). Scoped to the 2-column forms,
       so the red Delete User button is not affected. */
    .um-modal-box form.um-form-grid button[type="submit"] {
        background: var(--c-brand) !important;
        color: #fff !important;
        border: none !important;
        border-radius: 6px !important;
        font-weight: 600 !important;
        cursor: pointer;
        box-shadow: 0 -8px 8px -4px #fff, 0 2px 4px rgba(0, 0, 0, 0.2) !important;
    }
    .um-modal-box form.um-form-grid button[type="submit"]:hover {
        background: var(--c-brand-hover) !important;
    }

    /* Delete User modal buttons */
    .um-btn-danger { background: var(--c-danger-solid) !important; color: #fff !important; }
    .um-btn-danger:hover { background: #8f1f2b !important; }
    .um-btn-neutral { background: #5c6670 !important; color: #fff !important; margin-top: 8px; }

    /* Muted placeholders sa table ("Staff", "-") */
    .um-muted { color: var(--c-muted); }

    @media (max-width: 560px) {
        .um-form-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="um-header">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    <div class="um-header-actions">
        <div id="um-search-wrap">
            <span class="material-icons" aria-hidden="true">search</span>
            <input type="text" id="umSearchInput" placeholder="Search name, username or ID" aria-label="Search name, username or ID" autocomplete="off">
        </div>
        <button type="button" id="openCreateBtn" onclick="openCreateModal()">
            <span class="material-icons" style="font-size: 18px;">person_add</span>
            Create account
        </button>
    </div>
</div>

<div class="um-toolbar">
    <p>Showing all member accounts (<span id="umVisibleCount"><?php echo count($users); ?></span> total)</p>
</div>

<table id="umTable">
    <thead>
        <tr>
            <th>Membership ID</th>
            <th>Name</th>
            <th>Username</th>
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
            $rowUsername = $user['username'] ?? '';
            $rowSearchText = strtolower($rowName . ' ' . $user['membership_id'] . ' ' . $rowUsername . ' ' . $user['email']);
            $canEditThisUser = canEditUser($currentRole, $currentUserId, $user['role'], $user['id']);
            $canChangeThisUserRole = canChangeRole($currentRole, $currentUserId, $user['id']);
        ?>
        <tr data-search="<?php echo htmlspecialchars($rowSearchText); ?>">
            <td><?php echo $user['membership_id'] ? htmlspecialchars($user['membership_id']) : '<span class="um-muted">Staff</span>'; ?></td>
            <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #fff; flex-shrink: 0; background: <?php echo umAvatarColor($user['id'], $umAvatarColors); ?>;">
                        <?php echo htmlspecialchars(umInitials($rowName !== '-' ? $rowName : $user['email'])); ?>
                    </div>
                    <p style="margin: 0; font-weight: 600;"><?php echo htmlspecialchars($rowName); ?></p>
                </div>
            </td>
            <td><?php echo $rowUsername !== '' ? htmlspecialchars($rowUsername) : '<span class="um-muted">-</span>'; ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td>
                <span class="badge badge-<?php echo $user['role']; ?>">
                    <?php echo ucfirst($user['role']); ?>
                </span>
            </td>
            <td>
                <span class="badge" style="background: <?php echo $user['account_status'] === 'Active' ? '#2e7d32' : '#5c6670'; ?>; color: #fff;">
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
                         onclick="openEditUserModal(<?php echo $user['id']; ?>, <?php echo htmlspecialchars(json_encode($rowUsername), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($user['email']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($user['role']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($user['account_status']), ENT_QUOTES); ?>, <?php echo $canChangeThisUserRole ? 'true' : 'false'; ?>); return false;"
                         style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:var(--c-success) !important; text-decoration:none !important; margin-right:4px !important; cursor:pointer;">
                        <span class="material-icons" style="font-size:14px !important;">edit</span>Edit
                    </a>
                <?php endif; ?>

                <?php if (canDeleteUser($currentRole, $currentUserId, $user['role'], $user['id'])): ?>
                    <a href="#"
                         onclick="openDeleteUserModal(<?php echo $user['id']; ?>, <?php echo htmlspecialchars(json_encode($rowUsername !== '' ? $rowUsername : $user['email']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($user['email']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($user['role']), ENT_QUOTES); ?>); return false;"
                         style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:var(--c-danger) !important; text-decoration:none !important; cursor:pointer;">
                        <span class="material-icons" style="font-size:14px !important;">delete</span>Delete
                    </a>
                <?php elseif ($user['id'] == $currentUserId): ?>
                    <span title="You cannot delete your own account"
                          style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:#767676 !important;">
                        <span class="material-icons" style="font-size:14px !important;">delete</span>Delete
                    </span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p id="umNoResults" style="display:none; text-align: center; padding: 40px; color: var(--c-muted);">
    No accounts match your search.
</p>

<?php if (empty($users)): ?>
    <p style="text-align: center; padding: 40px; color: var(--c-muted);">
        No accounts to display based on your role permissions.
    </p>
<?php endif; ?>

<!-- ==================== CREATE ACCOUNT MODAL ==================== -->
<div class="um-modal-overlay" id="createModalOverlay">
    <div class="um-modal-box">
        <span class="um-modal-close" onclick="closeCreateModal()" role="button" tabindex="0" aria-label="Close">&times;</span>
        <h2>Create account</h2>

        <div class="info-box um-info-compact">
            Select a cooperative member to give them a login account. Only members without an existing account are listed.
        </div>

        <div id="createModalMessage" class="um-modal-message"></div>

        <form id="createAccountForm" class="um-form-grid" novalidate>
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
                <label>Role:</label>
                <input type="text" value="User" disabled style="background: #f0f0f0; color: #666;">
            </div>

            <div class="form-group">
                <label for="create_username">Username:</label>
                <input type="text" id="create_username" name="username" required
                       placeholder="e.g. juan.delacruz" maxlength="50"
                       autocomplete="off">
                <small>3-50 chars: letters, numbers, _ or .</small>
            </div>

            <div class="form-group">
                <label for="create_email">Email Address:</label>
                <input type="email" id="create_email" name="email" required placeholder="user@example.com" maxlength="100">
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
                <label for="create_account_status">Account Status:</label>
                <select id="create_account_status" name="account_status">
                    <option value="Active" selected>Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div class="form-group" style="align-self: end;">
                <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="send_verification_email" value="1" checked
                           style="width: auto; margin-right: 8px;">
                    <span>Send email verification</span>
                </label>
                <small style="margin-left: 26px;">
                    If unchecked, verified immediately.
                </small>
            </div>

            <button type="submit" class="um-span-2">
                <span class="material-icons" style="vertical-align: middle; font-size: 18px;">person_add</span>
                Create account
            </button>
        </form>
    </div>
</div>

<!-- ==================== EDIT USER MODAL ==================== -->
<div class="um-modal-overlay" id="editUserModalOverlay">
    <div class="um-modal-box">
        <span class="um-modal-close" onclick="closeEditUserModal()" role="button" tabindex="0" aria-label="Close">&times;</span>
        <h2>Update user</h2>

        <div id="editUserModalMessage" class="um-modal-message"></div>

        <form id="editUserForm" class="um-form-grid" novalidate>
            <div class="form-group">
                <label for="eu_username">Username:</label>
                <input type="text" id="eu_username" name="username" required
                       maxlength="50"
                       autocomplete="off">
                <small>3-50 chars: letters, numbers, _ or .</small>
            </div>

            <div class="form-group">
                <label for="eu_email">Email:</label>
                <input type="email" id="eu_email" name="email" maxlength="100">
            </div>

            <div class="form-group">
                <label for="eu_password">New Password:</label>
                <input type="password" id="eu_password" name="password" placeholder="Leave empty to keep current" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="eu_confirm_password">Confirm Password:</label>
                <input type="password" id="eu_confirm_password" name="confirm_password" placeholder="Re-enter new password" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="eu_role">Role:</label>
                <select id="eu_role" name="role">
                    <option value="user">User</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
                <small id="eu_role_hint" style="display: none;">Only admins can change roles, and not their own.</small>
            </div>

            <div class="form-group">
                <label for="eu_account_status">Account Status:</label>
                <select id="eu_account_status" name="account_status">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <button type="submit" class="um-span-2">Update user</button>
        </form>
    </div>
</div>

<!-- ==================== DELETE USER MODAL ==================== -->
<div class="um-modal-overlay" id="deleteUserModalOverlay">
    <div class="um-modal-box">
        <span class="um-modal-close" onclick="closeDeleteUserModal()" role="button" tabindex="0" aria-label="Close">&times;</span>
        <h2>Delete User</h2>

        <p>Are you sure you want to delete this user?</p>

        <div class="info-box" style="margin-bottom: 16px; font-size: 13px;">
            <strong>User Details:</strong><br>
            Username: <span id="delUserUsername"></span><br>
            Email: <span id="delUserEmail"></span><br>
            Role: <span id="delUserRole"></span>
        </div>

        <div id="deleteUserModalMessage" class="um-modal-message"></div>

        <form id="deleteUserForm">
            <button type="submit" class="um-btn-danger">Delete User</button>
            <button type="button" class="um-btn-neutral" onclick="closeDeleteUserModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
(function() {
    // ============================================================
    // INPUT RULES: letters/numbers lang sa username, walang space sa email
    // (Server-side validation pa rin ang final na bantay.)
    // ============================================================
    const RE_USERNAME = /[^a-zA-Z0-9_.]/g;   // letters, numbers, _ at .
    const RE_NOSPACE  = /\s/g;               // bawal ang space

    function bindFilter(id, re) {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function() {
            const v = el.value.replace(re, '');
            if (v !== el.value) el.value = v;
        });
    }
    bindFilter('create_username', RE_USERNAME);
    bindFilter('eu_username', RE_USERNAME);
    bindFilter('create_email', RE_NOSPACE);
    bindFilter('eu_email', RE_NOSPACE);

    // ============================================================
    // MESSAGE BOX: ipinapakita ang listahan ng errors (isang linya bawat error)
    // ============================================================
    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function showMsg(box, data) {
        box.style.display = 'block';
        box.className = 'um-modal-message ' + (data.success ? 'success' : 'error');
        if (!data.success && Array.isArray(data.errors) && data.errors.length) {
            box.innerHTML = data.errors.map(escHtml).join('<br>');
        } else {
            box.textContent = data.message;
        }
    }

    // ============================================================
    // SEARCH FILTER (client-side, filters by name / membership ID / username / email)
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

            fetch('<?php echo BASE_URL; ?>/app/manager/user-management/api/account-create.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                showMsg(document.getElementById('createModalMessage'), data);

                if (data.success) {
                    createForm.reset();
                    setTimeout(function() {
                        closeCreateModal();
                        window.location.reload();
                    }, 1200);
                }
            })
            .catch(function() {
                showMsg(document.getElementById('createModalMessage'), { success: false, message: 'Something went wrong. Please try again.' });
            })
            .finally(function() {
                submitBtn.disabled = false;
            });
        });
    }

    // ============================================================
    // EDIT USER MODAL
    // ============================================================
    let currentEditUserId = null;

    window.openEditUserModal = function(userId, username, email, role, accountStatus, canChangeRole) {
        currentEditUserId = userId;
        document.getElementById('eu_username').value = username || '';
        document.getElementById('eu_email').value = email;
        document.getElementById('eu_password').value = '';
        document.getElementById('eu_confirm_password').value = '';
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

        fetch('<?php echo BASE_URL; ?>/app/manager/user-management/api/user-update.php?user_id=' + currentEditUserId, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            showMsg(document.getElementById('editUserModalMessage'), data);

            if (data.success) {
                setTimeout(function() {
                    closeEditUserModal();
                    window.location.reload();
                }, 1000);
            }
        })
        .catch(function() {
            showMsg(document.getElementById('editUserModalMessage'), { success: false, message: 'Something went wrong. Please try again.' });
        })
        .finally(function() {
            submitBtn.disabled = false;
        });
    });

    // ============================================================
    // DELETE USER MODAL
    // ============================================================
    let currentDeleteUserId = null;

    window.openDeleteUserModal = function(userId, username, email, role) {
        currentDeleteUserId = userId;
        document.getElementById('delUserUsername').textContent = username;
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

        fetch('<?php echo BASE_URL; ?>/app/manager/user-management/api/user-delete.php?user_id=' + currentDeleteUserId, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ ajax: '1' })
        })
        .then(res => res.json())
        .then(data => {
            showMsg(document.getElementById('deleteUserModalMessage'), data);

            if (data.success) {
                setTimeout(function() {
                    closeDeleteUserModal();
                    window.location.reload();
                }, 1000);
            }
        })
        .catch(function() {
            showMsg(document.getElementById('deleteUserModalMessage'), { success: false, message: 'Something went wrong. Please try again.' });
        })
        .finally(function() {
            submitBtn.disabled = false;
        });
    });
})();
</script>

<?php renderFooter(); ?>