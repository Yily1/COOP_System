<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';

requireRole('manager');

// Show every member, plus whether they already have a linked account
$stmt = $pdo->query("
    SELECT m.*, u.id as user_id, u.email
    FROM members m
    LEFT JOIN users u ON u.member_id = m.id
    ORDER BY m.created_at DESC
");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalMembers = count($members);
$linkedCount = count(array_filter($members, fn($m) => !empty($m['user_id'])));

// ============================================================
// CROP / LIVESTOCK OPTIONS + IMAGE MAPPING
// Same option lists used by api/get-member.php.
// Add a new crop or livestock type here and it flows through the
// Add/Edit checkboxes AND the View-mode image badges automatically.
// One image file per type - reused across every member who has it.
// ============================================================
$livestockOptions = ['Chicken', 'Duck', 'Pig', 'Goat', 'Cow', 'Carabao'];
$cropOptions = ['Corn', 'Cassava', 'Cucumber', 'Eggplant', 'Squash', 'Chayote', 'Bitter Melon', 'String Beans'];

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

// Build { "Chicken": "https://.../chicken.jpg", ..., "__default__": "..." }
// so the JS side can look up an image for any known option, plus a
// fallback for whatever gets typed into "Others (specify)".
$livestockImageMap = ['__default__' => BASE_URL . '/assets/img/livestock/default-livestock.jpg'];
foreach ($livestockOptions as $opt) {
    $livestockImageMap[$opt] = livestockImageUrl($opt);
}
$cropImageMap = ['__default__' => BASE_URL . '/assets/img/crops/default-crop.jpg'];
foreach ($cropOptions as $opt) {
    $cropImageMap[$opt] = cropImageUrl($opt);
}

renderHeader('Member Management');
?>

<style>
.mm-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    gap: 16px;
    width: 100%;
    flex-wrap: wrap;
}
.mm-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.mm-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.mm-search-wrap .material-icons {
    position: absolute;
    left: 12px;
    font-size: 18px;
    color: #999;
    pointer-events: none;
}
.mm-search-wrap input[type="text"] {
    padding: 9px 14px 9px 38px;
    border-radius: 999px;
    border: 1px solid #cfd8e3;
    box-sizing: border-box;
    font-size: 13px;
    width: 240px;
}
.mm-type-select {
    padding: 9px 12px;
    border-radius: 999px;
    border: 1px solid #cfd8e3;
    box-sizing: border-box;
    font-size: 13px;
}
.mm-add-btn {
    background: #1976d2 !important;
    color: #fff !important;
    border: none !important;
    padding: 10px 18px !important;
    border-radius: 6px !important;
    cursor: pointer;
    font-size: 14px !important;
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
    width: auto !important;
    max-width: 220px !important;
    flex: 0 0 auto !important;
    white-space: nowrap !important;
}

.mm-summary-text {
    color: #667066;
    margin: 0 0 16px;
    font-size: 14px;
}

.mm-name-cell { display: flex; align-items: center; gap: 10px; }
.mm-avatar {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: 12px; color: #fff;
}
.mm-table-wrap {
    overflow: hidden;
    border: 1px solid #e4e2d8;
    border-radius: 14px;
}
.mm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.mm-table th {
    text-align: left; padding: 12px 14px; background: #fff;
    border-bottom: 1px solid #e4e2d8;
    color: #555; font-size: 13px;
}
.mm-table td { padding: 12px 14px; border-bottom: 1px solid #eee; vertical-align: middle; }
.mm-table tbody tr:last-child td { border-bottom: none; }
.mm-table tr.mm-hidden { display: none; }

.mm-badge { font-size: 11px; padding: 2px 9px; border-radius: 20px; font-weight: 600; white-space: nowrap; }
.mm-badge.linked { background: #e8f5e9; color: #2e7d32; }
.mm-badge.not-linked { background: #eee; color: #777; }

.mm-actions { font-size: 13px; white-space: nowrap; }

.mm-empty { text-align: center; padding: 40px; color: #6c757d; }

/* ===== Member modal (Add / Edit / View) ===== */
.mm-modal-backdrop {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 100; align-items: flex-start;
    justify-content: center; padding: 30px 16px; overflow-y: auto;
}
.mm-modal-box {
    background: #fff; border-radius: 10px; padding: 24px 28px; width: 100%;
    max-width: 640px; box-sizing: border-box; position: relative;
}
.mm-modal-close {
    position: absolute !important; top: 16px !important; right: 16px !important;
    background: none !important; border: none !important; font-size: 20px !important;
    cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important;
    max-width: 28px !important; flex: 0 0 auto !important; color: #333 !important;
    line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;
}
.mm-modal-box h2 { margin: 0 0 4px; font-size: 19px; padding-right: 30px; }
.mm-modal-subtext { margin: 0 0 18px; font-size: 13px; color: #667066; }
.mm-modal-box h3 { font-size: 14px; color: #444; margin: 18px 0 10px; }
.mm-modal-box h3:first-of-type { margin-top: 0; }

.mm-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 14px; }
.mm-form-grid .span-2 { grid-column: span 2; }
@media (max-width: 600px) {
    .mm-form-grid { grid-template-columns: 1fr; }
    .mm-form-grid .span-2 { grid-column: span 1; }
}
.mm-field label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 13px; }
.mm-field input[type="text"],
.mm-field input[type="date"],
.mm-field select {
    width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; font-size: 14px;
}
.mm-field input:disabled, .mm-field select:disabled {
    background: #f5f5f5; color: #666;
}

.mm-checkbox-group { display: flex; flex-wrap: wrap; gap: 8px 18px; margin-top: 4px; margin-bottom: 8px; }
.mm-checkbox-group label { display: flex; align-items: center; font-weight: normal; font-size: 13px; cursor: pointer; }
.mm-checkbox-group input[type="checkbox"] { width: auto; margin-right: 6px; }

/* View-mode image badges (Crops Grown / Livestock Raised) */
.mm-badge-view { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 8px; }
.mm-badge-view .mm-crop-card {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    width: 90px; text-align: center;
}
.mm-badge-view .mm-crop-card img {
    width: 84px; height: 84px; border-radius: 14px; object-fit: cover;
    border: 1px solid #e4e2d8; flex-shrink: 0;
}
.mm-badge-view .mm-crop-card span {
    font-size: 13px; font-weight: 500; color: #333; line-height: 1.3;
}

#mmErrors, #mmSuccess { display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; }
#mmErrors { background: #f8d7da; color: #721c24; }
#mmSuccess { background: #d4edda; color: #155724; }

.mm-modal-footer { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; }
.mm-btn-secondary {
    padding: 10px 18px !important; border-radius: 6px !important; border: 1px solid #ccc !important;
    background: #fff !important; color: #333 !important; cursor: pointer; font-size: 14px !important;
    width: auto !important; flex: 0 0 auto !important;
}
.mm-btn-primary {
    padding: 10px 18px !important; border-radius: 6px !important; border: none !important;
    background: #1976d2 !important; color: #fff !important; cursor: pointer; font-size: 14px !important;
    width: auto !important; flex: 0 0 auto !important;
}

/* ===== Delete confirmation modal ===== */
.mm-confirm-box {
    background: #fff; border-radius: 10px; padding: 24px; width: 100%; max-width: 380px; box-sizing: border-box;
}
.mm-confirm-box h3 { margin: 0 0 8px; font-size: 17px; }
.mm-confirm-box p { margin: 0 0 18px; font-size: 14px; color: #444; }
</style>

<div class="mm-header">
    <h1 style="margin: 0;">Members</h1>
    <div class="mm-header-actions">
        <div class="mm-search-wrap">
            <span class="material-icons">search</span>
            <input type="text" id="mmSearchInput" placeholder="Search by name or membership ID">
        </div>
        <button type="button" class="mm-add-btn" id="mmAddBtn">
            <span class="material-icons" style="font-size: 18px;">person_add</span>
            Add member
        </button>
    </div>
</div>

<p class="mm-summary-text">Showing all cooperative members (<?php echo $totalMembers; ?> total, <?php echo $linkedCount; ?> linked)</p>

<?php if (!empty($members)): ?>
<div class="mm-table-wrap">
    <table class="mm-table">
        <thead>
            <tr>
                <th>Membership ID</th>
                <th>Name</th>
                <th>Type</th>
                <th>Farmer Type</th>
                <th>Date Joined</th>
                <th>Account</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody id="mmTableBody">
            <?php foreach ($members as $m): ?>
            <tr class="mm-row"
                data-id="<?php echo $m['id']; ?>"
                data-search="<?php echo htmlspecialchars(strtolower($m['membership_id'] . ' ' . $m['last_name'] . ' ' . $m['first_name'])); ?>"
                data-type="<?php echo htmlspecialchars($m['membership_type']); ?>">
                <td><?php echo htmlspecialchars($m['membership_id']); ?></td>
                <?php
                $avatarColors = ['#274b81', '#2e7d32', '#a06b16', '#7f4ab7', '#993c1d'];
                $avatarColor = $avatarColors[$m['id'] % count($avatarColors)];
                $initials = strtoupper(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1));
                ?>
                <td>
                    <div class="mm-name-cell">
                        <div class="mm-avatar" style="background: <?php echo $avatarColor; ?>;"><?php echo htmlspecialchars($initials); ?></div>
                        <span><?php echo htmlspecialchars($m['last_name'] . ', ' . $m['first_name']); ?></span>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($m['membership_type']); ?></td>
                <td><?php echo htmlspecialchars($m['farmer_type'] ?? '-'); ?></td>
                <td><?php echo date('M j, Y', strtotime($m['date_joined'])); ?></td>
                <td>
                    <?php if ($m['user_id']): ?>
                        <span class="mm-badge linked">Linked</span>
                    <?php else: ?>
                        <span class="mm-badge not-linked">Not linked</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;">
                    <div class="mm-actions">
                        <a class="mm-view-link" data-action="view"
                           style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:#274b81 !important; text-decoration:none !important; margin-left:4px !important; cursor:pointer;">
                            <span class="material-icons" style="font-size:14px !important;">visibility</span>View
                        </a>
                        <a class="mm-edit-link" data-action="edit"
                           style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:#2e7d32 !important; text-decoration:none !important; margin-left:4px !important; cursor:pointer;">
                            <span class="material-icons" style="font-size:14px !important;">edit</span>Edit
                        </a>
                        <?php if ($m['user_id']): ?>
                        <span class="mm-action-disabled" title="Has a linked account - cannot be deleted"
                              style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:#999 !important; margin-left:4px !important;">
                            <span class="material-icons" style="font-size:14px !important;">delete</span>Delete
                        </span>
                        <?php else: ?>
                        <a class="mm-delete-link" data-action="delete"
                           style="display:inline-flex !important; align-items:center !important; gap:5px !important; padding:5px 9px !important; font-size:12px !important; border-radius:5px !important; background:#fff !important; border:1px solid #ddd !important; color:#dc3545 !important; text-decoration:none !important; margin-left:4px !important; cursor:pointer;">
                            <span class="material-icons" style="font-size:14px !important;">delete</span>Delete
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p id="mmNoResults" class="mm-empty" style="display: none;">No members match your search.</p>
<?php else: ?>
<p class="mm-empty">No members yet. Click "Add member" to create the first one.</p>
<?php endif; ?>

<!-- ============================================================
     MEMBER MODAL (Add / Edit / View - same form, different mode)
     ============================================================ -->
<div class="mm-modal-backdrop" id="mmModalBackdrop">
    <div class="mm-modal-box">
        <button type="button" class="mm-modal-close" id="mmModalClose">&times;</button>
        <h2 id="mmModalTitle">Add Member</h2>
        <p class="mm-modal-subtext" id="mmModalSubtext">Create a new cooperative member profile. Membership ID is auto-generated.</p>

        <div id="mmErrors"></div>
        <div id="mmSuccess"></div>

        <form id="mmForm">
            <input type="hidden" id="mm_member_id" name="member_id" value="">

            <h3>Personal Information</h3>
            <div class="mm-form-grid">
                <div class="mm-field span-2">
                    <label>Membership ID</label>
                    <input type="text" id="mm_membership_id" disabled value="Auto-generated (e.g. SJFMC-0001)">
                </div>
                <div class="mm-field">
                    <label for="mm_last_name">Last Name</label>
                    <input type="text" id="mm_last_name" name="last_name" placeholder="Dela Cruz">
                </div>
                <div class="mm-field">
                    <label for="mm_first_name">First Name</label>
                    <input type="text" id="mm_first_name" name="first_name" placeholder="Juan">
                </div>
                <div class="mm-field">
                    <label for="mm_middle_name">Middle Name</label>
                    <input type="text" id="mm_middle_name" name="middle_name" placeholder="Santos">
                </div>
                <div class="mm-field">
                    <label for="mm_gender">Gender</label>
                    <select id="mm_gender" name="gender">
                        <option value="">-- Select Gender --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="mm-field">
                    <label for="mm_date_of_birth">Date of Birth</label>
                    <input type="date" id="mm_date_of_birth" name="date_of_birth">
                </div>
                <div class="mm-field">
                    <label for="mm_occupation">Occupation</label>
                    <input type="text" id="mm_occupation" name="occupation" placeholder="Farming">
                </div>
                <div class="mm-field span-2">
                    <label for="mm_address">Complete Address</label>
                    <input type="text" id="mm_address" name="address" placeholder="Street, Barangay, City, Province">
                </div>
                <div class="mm-field span-2">
                    <label for="mm_contact_number">Contact Number</label>
                    <input type="text" id="mm_contact_number" name="contact_number" placeholder="09XXXXXXXXX">
                </div>
            </div>

            <h3>Membership Information</h3>
            <div class="mm-form-grid">
                <div class="mm-field">
                    <label for="mm_membership_type">Membership Type</label>
                    <select id="mm_membership_type" name="membership_type">
                        <option value="">-- Select Type --</option>
                        <option value="Regular">Regular</option>
                        <option value="Associate">Associate</option>
                    </select>
                </div>
                <div class="mm-field">
                    <label for="mm_date_joined">Date Joined</label>
                    <input type="date" id="mm_date_joined" name="date_joined">
                </div>
                <div class="mm-field span-2">
                    <label for="mm_hectares_cultivated">Number of Hectares Cultivated</label>
                    <select id="mm_hectares_cultivated" name="hectares_cultivated">
                        <option value="">-- Select Number of Hectares --</option>
                        <?php for ($h = 1; $h <= 10; $h++): ?>
                            <option value="<?php echo $h; ?>"><?php echo $h; ?> hectare<?php echo $h !== 1 ? 's' : ''; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <h3>Farming Profile</h3>
            <div class="mm-form-grid">
                <div class="mm-field span-2">
                    <label for="mm_farmer_type">Type of Farmer</label>
                    <select id="mm_farmer_type" name="farmer_type">
                        <option value="">-- Select Type --</option>
                        <option value="Livestock">Livestock</option>
                        <option value="Crops">Crops</option>
                        <option value="Both">Both</option>
                    </select>
                </div>

                <div class="mm-field span-2" id="mm_livestock_field" style="display: none;">
                    <label>Livestock Raised</label>
                    <div class="mm-checkbox-group" id="mm_livestock_checkboxes">
                        <?php foreach ($livestockOptions as $opt): ?>
                            <label><input type="checkbox" name="livestock[]" value="<?php echo $opt; ?>"><?php echo $opt; ?></label>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" id="mm_livestock_others" name="livestock_others" placeholder="Others (specify)">
                    <div class="mm-badge-view" id="mm_livestock_badges" style="display: none;"></div>
                </div>

                <div class="mm-field span-2" id="mm_crops_field" style="display: none;">
                    <label>Crops Grown</label>
                    <div class="mm-checkbox-group" id="mm_crops_checkboxes">
                        <?php foreach ($cropOptions as $opt): ?>
                            <label><input type="checkbox" name="crops[]" value="<?php echo $opt; ?>"><?php echo $opt; ?></label>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" id="mm_crops_others" name="crops_others" placeholder="Others (specify)">
                    <div class="mm-badge-view" id="mm_crops_badges" style="display: none;"></div>
                </div>
            </div>

            <div class="mm-modal-footer">
                <button type="button" class="mm-btn-secondary" id="mmCancelBtn">Close</button>
                <button type="submit" class="mm-btn-primary" id="mmSubmitBtn">Register Member</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     DELETE CONFIRMATION MODAL
     ============================================================ -->
<div class="mm-modal-backdrop" id="mmDeleteBackdrop">
    <div class="mm-confirm-box">
        <h3>Delete member?</h3>
        <p id="mmDeleteText">Are you sure you want to delete this member profile? This cannot be undone.</p>
        <div id="mmDeleteErrors" style="display:none; background:#f8d7da; color:#721c24; padding:10px 14px; border-radius:6px; margin-bottom:14px; font-size:13px;"></div>
        <div class="mm-modal-footer">
            <button type="button" class="mm-btn-secondary" id="mmDeleteCancelBtn">Cancel</button>
            <button type="button" class="mm-btn-primary" id="mmDeleteConfirmBtn" style="background:#dc3545 !important;">Delete</button>
        </div>
    </div>
</div>

<script type="application/json" id="mmLivestockImageMap"><?php echo json_encode($livestockImageMap); ?></script>
<script type="application/json" id="mmCropImageMap"><?php echo json_encode($cropImageMap); ?></script>

<script>
(function() {
    const BASE = '<?php echo BASE_URL; ?>';
    const livestockImageMap = JSON.parse(document.getElementById('mmLivestockImageMap').textContent);
    const cropImageMap = JSON.parse(document.getElementById('mmCropImageMap').textContent);

    // ---------- Search / filter ----------
    const searchInput = document.getElementById('mmSearchInput');
    const rows = document.querySelectorAll('.mm-row');
    const noResults = document.getElementById('mmNoResults');

    function applyFilters() {
        const q = (searchInput?.value || '').toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(row => {
            const show = !q || row.dataset.search.includes(q);
            row.classList.toggle('mm-hidden', !show);
            if (show) visibleCount++;
        });

        if (noResults) noResults.style.display = visibleCount === 0 ? 'block' : 'none';
    }
    searchInput?.addEventListener('input', applyFilters);

    // ---------- Member modal (Add / Edit / View) ----------
    const modalBackdrop = document.getElementById('mmModalBackdrop');
    const modalTitle = document.getElementById('mmModalTitle');
    const modalSubtext = document.getElementById('mmModalSubtext');
    const form = document.getElementById('mmForm');
    const errorsBox = document.getElementById('mmErrors');
    const successBox = document.getElementById('mmSuccess');
    const submitBtn = document.getElementById('mmSubmitBtn');
    const cancelBtn = document.getElementById('mmCancelBtn');
    const closeBtn = document.getElementById('mmModalClose');
    const membershipIdField = document.getElementById('mm_membership_id');
    const memberIdField = document.getElementById('mm_member_id');

    const livestockCheckboxes = document.getElementById('mm_livestock_checkboxes');
    const livestockOthersInput = document.getElementById('mm_livestock_others');
    const livestockBadges = document.getElementById('mm_livestock_badges');
    const cropsCheckboxes = document.getElementById('mm_crops_checkboxes');
    const cropsOthersInput = document.getElementById('mm_crops_others');
    const cropsBadges = document.getElementById('mm_crops_badges');

    const allFormControls = () => form.querySelectorAll('input, select, textarea, button[type="submit"]');

    function toggleFarmerFields() {
        const type = document.getElementById('mm_farmer_type').value;
        document.getElementById('mm_livestock_field').style.display = (type === 'Livestock' || type === 'Both') ? 'block' : 'none';
        document.getElementById('mm_crops_field').style.display = (type === 'Crops' || type === 'Both') ? 'block' : 'none';
    }
    document.getElementById('mm_farmer_type').addEventListener('change', toggleFarmerFields);

    // Builds the read-only "picture + label" pills shown in View mode.
    // Falls back to the __default__ image for anything typed into
    // "Others (specify)" that isn't one of the known checkbox options.
    function buildBadgeHtml(selected, othersText, imageMap) {
        const items = [...selected];
        if (othersText) {
            othersText.split(',').map(s => s.trim()).filter(Boolean).forEach(o => items.push(o));
        }
        if (items.length === 0) {
            return '<p style="color:#888; font-size:13px; margin:0;">None specified.</p>';
        }
        return items.map(item => {
            const src = imageMap[item] || imageMap['__default__'];
            return `<div class="mm-crop-card"><img src="${src}" alt="${item}"><span>${item}</span></div>`;
        }).join('');
    }

    function resetForm() {
        form.reset();
        memberIdField.value = '';
        membershipIdField.value = 'Auto-generated (e.g. SJFMC-0001)';
        errorsBox.style.display = 'none';
        successBox.style.display = 'none';
        livestockBadges.innerHTML = '';
        cropsBadges.innerHTML = '';
        toggleFarmerFields();
    }

    function setMode(mode) {
        // mode: 'add' | 'edit' | 'view'
        const readOnly = mode === 'view';
        allFormControls().forEach(el => {
            if (el === submitBtn) return;
            if (el.id === 'mm_membership_id') return; // always disabled
            el.disabled = readOnly;
        });
        submitBtn.style.display = readOnly ? 'none' : 'inline-flex';

        // View mode: show picture badges instead of the checkbox grid
        livestockCheckboxes.style.display = readOnly ? 'none' : 'flex';
        livestockOthersInput.style.display = readOnly ? 'none' : 'block';
        livestockBadges.style.display = readOnly ? 'flex' : 'none';
        cropsCheckboxes.style.display = readOnly ? 'none' : 'flex';
        cropsOthersInput.style.display = readOnly ? 'none' : 'block';
        cropsBadges.style.display = readOnly ? 'flex' : 'none';

        if (mode === 'add') {
            modalTitle.textContent = 'Add Member';
            modalSubtext.textContent = 'Create a new cooperative member profile. Membership ID is auto-generated.';
            submitBtn.textContent = 'Register Member';
        } else if (mode === 'edit') {
            modalTitle.textContent = 'Edit Member';
            modalSubtext.textContent = 'Update this cooperative member\'s profile.';
            submitBtn.textContent = 'Update Member';
        } else {
            modalTitle.textContent = 'Member Details';
            modalSubtext.textContent = 'Viewing this member\'s profile (read-only).';
        }
    }

    function openModal() { modalBackdrop.style.display = 'flex'; }
    function closeModal() { modalBackdrop.style.display = 'none'; resetForm(); }

    document.getElementById('mmAddBtn').addEventListener('click', function() {
        resetForm();
        setMode('add');
        openModal();
    });
    cancelBtn.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);
    modalBackdrop.addEventListener('click', function(e) { if (e.target === modalBackdrop) closeModal(); });

    function populateForm(member) {
        memberIdField.value = member.id;
        membershipIdField.value = member.membership_id;
        document.getElementById('mm_last_name').value = member.last_name || '';
        document.getElementById('mm_first_name').value = member.first_name || '';
        document.getElementById('mm_middle_name').value = member.middle_name || '';
        document.getElementById('mm_gender').value = member.gender || '';
        document.getElementById('mm_date_of_birth').value = member.date_of_birth || '';
        document.getElementById('mm_occupation').value = member.occupation || '';
        document.getElementById('mm_address').value = member.address || '';
        document.getElementById('mm_contact_number').value = member.contact_number || '';
        document.getElementById('mm_membership_type').value = member.membership_type || '';
        document.getElementById('mm_date_joined').value = member.date_joined || '';
        document.getElementById('mm_hectares_cultivated').value = member.hectares_cultivated || '';
        document.getElementById('mm_farmer_type').value = member.farmer_type || '';

        form.querySelectorAll('input[name="livestock[]"]').forEach(cb => {
            cb.checked = (member.livestock_selected || []).includes(cb.value);
        });
        livestockOthersInput.value = member.livestock_others || '';
        livestockBadges.innerHTML = buildBadgeHtml(member.livestock_selected || [], member.livestock_others || '', livestockImageMap);

        form.querySelectorAll('input[name="crops[]"]').forEach(cb => {
            cb.checked = (member.crops_selected || []).includes(cb.value);
        });
        cropsOthersInput.value = member.crops_others || '';
        cropsBadges.innerHTML = buildBadgeHtml(member.crops_selected || [], member.crops_others || '', cropImageMap);

        toggleFarmerFields();
    }

    async function loadMemberIntoModal(memberId, mode) {
        resetForm();
        openModal();
        setMode(mode);
        modalTitle.textContent = mode === 'edit' ? 'Loading edit form...' : 'Loading member...';

        try {
            const res = await fetch(`${BASE}/app/manager/member-management/api/get-member.php?member_id=${memberId}`);
            const data = await res.json();
            if (!data.success) {
                errorsBox.textContent = data.message || 'Could not load member.';
                errorsBox.style.display = 'block';
                return;
            }
            populateForm(data.member);
            setMode(mode);
        } catch (err) {
            errorsBox.textContent = 'Could not connect to the server.';
            errorsBox.style.display = 'block';
        }
    }

    document.getElementById('mmTableBody')?.addEventListener('click', function(e) {
        const link = e.target.closest('[data-action]');
        if (!link) return;
        const row = link.closest('.mm-row');
        const memberId = row.dataset.id;
        const action = link.dataset.action;

        if (action === 'view') loadMemberIntoModal(memberId, 'view');
        if (action === 'edit') loadMemberIntoModal(memberId, 'edit');
        if (action === 'delete') openDeleteModal(memberId, row);
    });

    // ---------- Add / Edit submit ----------
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorsBox.style.display = 'none';
        successBox.style.display = 'none';
        submitBtn.disabled = true;
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';

        const isEdit = !!memberIdField.value;
        const endpoint = isEdit
            ? `${BASE}/app/manager/member-management/api/update-member.php`
            : `${BASE}/app/manager/member-management/api/save-member.php`;

        try {
            const formData = new FormData(form);
            const res = await fetch(endpoint, { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                successBox.textContent = data.message || 'Saved successfully.';
                successBox.style.display = 'block';
                setTimeout(() => { window.location.reload(); }, 900);
            } else {
                errorsBox.innerHTML = (data.errors || ['Something went wrong.']).join('<br>');
                errorsBox.style.display = 'block';
            }
        } catch (err) {
            errorsBox.textContent = 'Could not connect to the server.';
            errorsBox.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    // ---------- Delete confirmation ----------
    const deleteBackdrop = document.getElementById('mmDeleteBackdrop');
    const deleteText = document.getElementById('mmDeleteText');
    const deleteErrors = document.getElementById('mmDeleteErrors');
    const deleteCancelBtn = document.getElementById('mmDeleteCancelBtn');
    const deleteConfirmBtn = document.getElementById('mmDeleteConfirmBtn');
    let memberIdToDelete = null;

    function openDeleteModal(memberId, row) {
        memberIdToDelete = memberId;
        const name = row.children[1]?.textContent?.trim() || 'this member';
        deleteText.textContent = `Are you sure you want to delete ${name}'s profile? This cannot be undone.`;
        deleteErrors.style.display = 'none';
        deleteBackdrop.style.display = 'flex';
    }
    function closeDeleteModal() {
        deleteBackdrop.style.display = 'none';
        memberIdToDelete = null;
    }
    deleteCancelBtn.addEventListener('click', closeDeleteModal);
    deleteBackdrop.addEventListener('click', function(e) { if (e.target === deleteBackdrop) closeDeleteModal(); });

    deleteConfirmBtn.addEventListener('click', async function() {
        if (!memberIdToDelete) return;
        deleteErrors.style.display = 'none';
        deleteConfirmBtn.disabled = true;
        deleteConfirmBtn.textContent = 'Deleting...';

        try {
            const formData = new FormData();
            formData.append('member_id', memberIdToDelete);
            const res = await fetch(`${BASE}/app/manager/member-management/api/delete-member.php`, { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                window.location.reload();
            } else {
                deleteErrors.textContent = data.message || 'Could not delete this member.';
                deleteErrors.style.display = 'block';
            }
        } catch (err) {
            deleteErrors.textContent = 'Could not connect to the server.';
            deleteErrors.style.display = 'block';
        } finally {
            deleteConfirmBtn.disabled = false;
            deleteConfirmBtn.textContent = 'Delete';
        }
    });
})();
</script>

<?php renderFooter(); ?>