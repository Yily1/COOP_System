<?php
// Adjust this path if your config.php (session_start, BASE_URL, $pdo) lives elsewhere
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireLogin();

// Only manager and user roles can view this page
if (!canAccessCrops($_SESSION['role'] ?? '')) {
    die("Access denied.");
}

$role = $_SESSION['role'] ?? '';

// Same account-linking pattern as getAvailableMembers(): users.member_id
// points at members.id. Adjust this line if your login flow stores it
// under a different session key.
$memberId = $_SESSION['member_id'] ?? 0;

if ($role === 'manager') {
    $crops = getAllCrops($pdo);
    $stats = getCropStats($pdo);
} else {
    $crops = getCropsForMember($pdo, $memberId);
    $stats = getCropStats($pdo, $memberId);
}

/**
 * "Dela Cruz" + "Juan" -> "DC". Falls back gracefully for blank names.
 */
function cmInitials($lastName, $firstName) {
    $l = trim((string) $lastName);
    $f = trim((string) $firstName);
    if ($l === '' && $f === '') {
        return '?';
    }
    return strtoupper(mb_substr($l, 0, 1) . mb_substr($f, 0, 1));
}

renderHeader('Crops Management');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --cm-ink: #2B3A2A;
    --cm-ink-soft: #5B6B57;
    --cm-paper: #FAF7EF;
    --cm-card: #FFFFFF;
    --cm-line: #E4DCC8;
    --cm-forest: #33502F;
    --cm-forest-dark: #223A20;
    --cm-gold: #C1892B;
    --cm-gold-soft: #F4E4C1;
    --cm-danger: #B54A3C;
    --cm-danger-soft: #F6E1DC;
    --cm-success: #3E7A4B;
    --cm-success-soft: #E1EFDE;
    --cm-info: #3B6E91;
    --cm-info-soft: #DCEAF2;
    --cm-radius: 10px;
    --cm-shadow: 0 12px 28px rgba(43, 58, 42, 0.14);
}

.cm-page,
.cm-page *,
.cm-modal,
.cm-modal * {
    box-sizing: border-box;
}

.cm-page {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--cm-ink);
    width: 100%;
    max-width: 100%;
    padding: 0 0 40px;
    text-transform: none;
    letter-spacing: normal;
}

.cm-page h1,
.cm-page h2 {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-weight: 600;
    color: #2c2c2a;
    text-transform: none;
    letter-spacing: normal;
    margin: 0;
}

.cm-muted { color: var(--cm-ink-soft); font-size: 14px; margin: 4px 0 0; }
.cm-text-center { text-align: center; }
.cm-text-right { text-align: right; }

/* HEADER */
.cm-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--cm-line);
}
.cm-header h1 { font-size: 26px; }
.cm-header > div:first-child { flex: 1; min-width: 0; }

/* BUTTONS */
.cm-btn {
    box-sizing: border-box;
    display: inline-flex;
    flex: none;
    width: auto;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 600;
    line-height: 1.2;
    text-transform: none;
    letter-spacing: normal;
    padding: 9px 16px;
    border-radius: var(--cm-radius);
    border: 1.5px solid var(--cm-forest);
    background: transparent;
    color: var(--cm-forest);
    cursor: pointer;
    box-shadow: none;
    transition: transform 0.12s ease, background 0.12s ease;
}
.cm-btn .material-icons { font-size: 17px; }
.cm-btn:hover { background: var(--cm-gold-soft); }
.cm-btn:active { transform: scale(0.97); }

.cm-btn-outline {
    border-color: var(--cm-line);
    color: var(--cm-ink);
    background: var(--cm-card);
}
.cm-btn-outline:hover { border-color: var(--cm-forest); }

.cm-btn-primary {
    background: var(--cm-forest);
    border-color: var(--cm-forest);
    color: #fff;
}
.cm-btn-primary:hover { background: var(--cm-forest-dark); }

.cm-btn-full { width: 100%; justify-content: center; padding: 11px; }

.cm-btn-link {
    width: auto !important;
    background: var(--cm-card);
    border: 1px solid var(--cm-line);
    border-radius: 20px;
    box-shadow: none;
    padding: 4px 10px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    text-transform: none !important;
    letter-spacing: normal;
    color: var(--cm-forest);
    cursor: pointer;
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
    transition: border-color 0.12s ease;
}
.cm-btn-link .material-icons { font-size: 14px; }
.cm-btn-link:hover { border-color: var(--cm-forest); text-decoration: none; }
.cm-btn-link:disabled { opacity: 0.5; cursor: not-allowed; }

.cm-btn-link-danger { color: var(--cm-danger); }
.cm-btn-link-danger:hover { border-color: var(--cm-danger); }

/* SUMMARY CARDS */
.cm-summary-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 28px;
}
.cm-metric-card { border: none; border-radius: var(--cm-radius); box-shadow: none; padding: 16px 18px; flex: 0 0 160px; width: 160px; }
.cm-metric-label { font-size: 12.5px; text-transform: none; letter-spacing: normal; margin: 0 0 6px; }
.cm-metric-value { font-family: 'Fraunces', serif; font-size: 30px; font-weight: 600; margin: 0; }

.cm-metric-card.cm-tone-forest { background: var(--cm-forest); }
.cm-metric-card.cm-tone-forest .cm-metric-label { color: #EFE9D8; }
.cm-metric-card.cm-tone-forest .cm-metric-value { color: #fff; }

.cm-metric-card.cm-tone-gold { background: var(--cm-gold-soft); }
.cm-metric-card.cm-tone-gold .cm-metric-label,
.cm-metric-card.cm-tone-gold .cm-metric-value { color: var(--cm-gold); }

.cm-metric-card.cm-tone-info { background: var(--cm-info-soft); }
.cm-metric-card.cm-tone-info .cm-metric-label,
.cm-metric-card.cm-tone-info .cm-metric-value { color: var(--cm-info); }

.cm-metric-card.cm-tone-success { background: var(--cm-success-soft); }
.cm-metric-card.cm-tone-success .cm-metric-label,
.cm-metric-card.cm-tone-success .cm-metric-value { color: var(--cm-success); }

/* SECTION */
.cm-section {
    background: var(--cm-card);
    border: 1px solid var(--cm-line);
    border-radius: var(--cm-radius);
    box-shadow: none;
    padding: 22px 22px 8px;
}
.cm-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}
.cm-section-header h2 { font-size: 19px; }

/* TABLE */
.cm-table-wrap { overflow-x: auto; }
.cm-page table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.cm-page thead th {
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    color: var(--cm-ink-soft);
    padding: 10px 8px;
    border-bottom: 1.5px solid var(--cm-line);
}
.cm-page thead th.cm-text-right { text-align: right; }
.cm-page tbody td { padding: 12px 8px; border-bottom: 1px solid var(--cm-line); }
.cm-page tbody tr:last-child td { border-bottom: none; }

/* MEMBER CELL */
.cm-member-cell { display: flex; align-items: center; gap: 10px; }
.cm-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--cm-danger);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 12px;
    flex: none;
}
.cm-member-name { margin: 0; font-weight: 600; font-size: 13.5px; color: var(--cm-ink); }
.cm-member-id { margin: 0; font-size: 11.5px; color: var(--cm-ink-soft); }

/* STATUS BADGES */
.cm-status-badge {
    display: inline-block;
    font-size: 11.5px;
    font-weight: 600;
    text-transform: none;
    padding: 3px 10px;
    border-radius: 20px;
}
.cm-status-badge.cm-pending  { background: var(--cm-gold-soft); color: var(--cm-gold); }
.cm-status-badge.cm-rejected { background: var(--cm-danger-soft); color: var(--cm-danger); }
.cm-status-badge.cm-growing  { background: var(--cm-info-soft); color: var(--cm-info); }
.cm-status-badge.cm-ready    { background: var(--cm-gold-soft); color: var(--cm-gold); }
.cm-status-badge.cm-harvested{ background: var(--cm-success-soft); color: var(--cm-success); }

/* MODALS */
.cm-modal {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(43, 58, 42, 0.45);
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}
.cm-modal.cm-open { display: flex; }

.cm-modal-card {
    background: var(--cm-card);
    border-radius: 14px;
    box-shadow: var(--cm-shadow);
    width: 100%;
    max-width: 420px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 22px 24px 24px;
    animation: cm-pop-in 0.16s ease;
}

@keyframes cm-pop-in {
    from { opacity: 0; transform: translateY(8px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.cm-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 16px;
    margin-bottom: 16px;
}
.cm-modal-header h2 { flex: 1; min-width: 0; margin: 0; font-size: 18px; line-height: 1.2; }

.cm-icon-btn {
    flex: 0 0 32px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    padding: 0;
    background: transparent;
    border: none;
    box-shadow: none;
    color: var(--cm-ink-soft);
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
}
.cm-icon-btn svg { width: 20px; height: 20px; display: block; }
.cm-icon-btn:hover { background: var(--cm-paper); color: var(--cm-ink); }
.cm-icon-btn:active { transform: scale(0.94); }

/* MODAL FORM */
.cm-modal-card label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    color: var(--cm-ink-soft);
    margin: 12px 0 5px;
}
.cm-modal-card label:first-of-type { margin-top: 0; }

.cm-modal-card input,
.cm-modal-card select {
    width: 100%;
    font-family: inherit;
    font-size: 14px;
    padding: 9px 11px;
    border: 1.5px solid var(--cm-line);
    border-radius: 8px;
    background: var(--cm-paper);
    color: var(--cm-ink);
    box-shadow: none;
    box-sizing: border-box;
}
.cm-modal-card input:focus,
.cm-modal-card select:focus {
    outline: none;
    border-color: var(--cm-forest);
    background: #fff;
}

.cm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* MESSAGES */
.cm-error-box,
.cm-success-box {
    display: none;
    font-size: 13px;
    padding: 9px 12px;
    border-radius: 8px;
    margin-bottom: 12px;
}
.cm-error-box.cm-show { display: block; background: var(--cm-danger-soft); color: var(--cm-danger); }
.cm-success-box.cm-show { display: block; background: var(--cm-success-soft); color: var(--cm-success); }

/* RESPONSIVE */
@media (max-width: 560px) {
    .cm-page { padding: 0 0 40px; }
    .cm-header { flex-direction: column; gap: 12px; }
    .cm-header > div:first-child { width: 100%; }
    .cm-header .cm-btn { width: 100%; }
    .cm-form-row { grid-template-columns: 1fr; }
    .cm-section-header { flex-direction: column; align-items: flex-start; }
    .cm-modal { padding: 12px; }
    .cm-modal-card { max-width: 100%; max-height: 92vh; padding: 20px 18px 20px; }
}
</style>

<div class="cm-page">

    <!-- PAGE HEADER -->
    <div class="cm-header">
        <div>
            <h1>Crops Management</h1>
            <p class="cm-muted">
                <?php echo $role === 'manager'
                    ? 'Review and track every member\'s crop plantings'
                    : 'Track your own crop plantings from planting to harvest'; ?>
            </p>
        </div>

        <?php if ($role === 'user'): ?>
            <button class="cm-btn cm-btn-outline" id="add-planting-btn" type="button">
                <span class="material-icons">add</span> Add planting
            </button>
        <?php endif; ?>
    </div>

    <!-- SUMMARY -->
    <div class="cm-summary-grid">
        <div class="cm-metric-card cm-tone-forest">
            <p class="cm-metric-label">Total plantings</p>
            <p class="cm-metric-value"><?php echo $stats['total']; ?></p>
        </div>
        <div class="cm-metric-card cm-tone-info">
            <p class="cm-metric-label">Growing</p>
            <p class="cm-metric-value"><?php echo $stats['growing']; ?></p>
        </div>
        <div class="cm-metric-card cm-tone-gold">
            <p class="cm-metric-label">Ready to harvest</p>
            <p class="cm-metric-value"><?php echo $stats['ready_to_harvest']; ?></p>
        </div>
        <div class="cm-metric-card cm-tone-success">
            <p class="cm-metric-label">Harvested</p>
            <p class="cm-metric-value"><?php echo $stats['harvested']; ?></p>
        </div>
    </div>

    <!-- PLANTINGS TABLE -->
    <div class="cm-section">
        <div class="cm-section-header">
            <h2><?php echo $role === 'manager' ? 'All plantings' : 'My plantings'; ?></h2>
        </div>

        <div class="cm-table-wrap">
            <table>
                <thead>
                    <tr>
                        <?php if ($role === 'manager'): ?><th>Member</th><?php endif; ?>
                        <th>Crop</th>
                        <th>Location</th>
                        <th>Area (ha)</th>
                        <th>Planted</th>
                        <th>Expected harvest</th>
                        <th>Actual harvest</th>
                        <th>Status</th>
                        <th class="cm-text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($crops)): ?>
                        <tr><td colspan="9" class="cm-muted cm-text-center">No crop plantings yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($crops as $c): ?>
                        <?php $memberName = trim($c['last_name'] . ', ' . $c['first_name']); ?>
                        <tr>
                            <?php if ($role === 'manager'): ?>
                                <td>
                                    <div class="cm-member-cell">
                                        <div class="cm-avatar"><?php echo htmlspecialchars(cmInitials($c['last_name'], $c['first_name'])); ?></div>
                                        <div>
                                            <p class="cm-member-name"><?php echo htmlspecialchars($memberName); ?></p>
                                            <p class="cm-member-id"><?php echo htmlspecialchars($c['membership_id']); ?></p>
                                        </div>
                                    </div>
                                </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($c['crop_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['location']); ?></td>
                            <td><?php echo rtrim(rtrim(number_format($c['area_hectares'], 2), '0'), '.'); ?> ha</td>
                            <td><?php echo date('M j, Y', strtotime($c['planting_date'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($c['expected_harvest_date'])); ?></td>
                            <td><?php echo $c['actual_harvest_date'] ? date('M j, Y', strtotime($c['actual_harvest_date'])) : '—'; ?></td>
                            <td><?php echo cropStatusBadge($c['status']); ?></td>
                            <td class="cm-text-right">
                                <?php if ($role === 'manager' && $c['status'] === 'pending'): ?>
                                    <button class="cm-btn-link cm-approve-btn" data-id="<?php echo $c['id']; ?>" type="button">
                                        <span class="material-icons">check</span> Approve
                                    </button>
                                    <button class="cm-btn-link cm-btn-link-danger cm-reject-btn" data-id="<?php echo $c['id']; ?>" type="button">
                                        <span class="material-icons">close</span> Reject
                                    </button>
                                <?php elseif ($role === 'user' && (int) $c['member_id'] === (int) $memberId): ?>
                                    <?php if ($c['status'] === 'growing'): ?>
                                        <button class="cm-btn-link cm-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="ready_to_harvest" type="button">
                                            <span class="material-icons">check_circle</span> Mark ready
                                        </button>
                                    <?php elseif ($c['status'] === 'ready_to_harvest'): ?>
                                        <button class="cm-btn-link cm-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="harvested" type="button">
                                            <span class="material-icons">agriculture</span> Mark harvested
                                        </button>
                                    <?php elseif ($c['status'] === 'pending'): ?>
                                        <span class="cm-muted">Awaiting approval</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>


<?php if ($role === 'user'): ?>
<!-- ============================================================
     ADD PLANTING MODAL
     ============================================================ -->
<div class="cm-modal" id="add-planting-modal-wrap">
    <div class="cm-modal-card">
        <div class="cm-modal-header">
            <h2>Add planting</h2>
            <button class="cm-icon-btn" id="close-add-planting" type="button" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="cm-success-box cm-show" style="margin-bottom:14px;">
            Your planting will be sent to a manager for approval before it starts tracking.
        </div>

        <div id="add-planting-error" class="cm-error-box"></div>

        <form id="add-planting-form">
            <label>Crop</label>
            <input type="text" name="crop_name" placeholder="e.g. Rice, Corn, Eggplant" required>

            <label>Farm location</label>
            <input type="text" name="location" placeholder="e.g. Barangay Malinis, Sitio Ilaya" required>

            <label>Area (hectares)</label>
            <input type="number" name="area_hectares" placeholder="e.g. 1.5" min="0.01" step="0.01" required>

            <div class="cm-form-row">
                <div>
                    <label>Planting date</label>
                    <input type="date" name="planting_date" required>
                </div>
                <div>
                    <label>Expected harvest date</label>
                    <input type="date" name="expected_harvest_date" required>
                </div>
            </div>

            <button type="submit" class="cm-btn cm-btn-primary cm-btn-full" style="margin-top:16px;">Submit planting</button>
        </form>
    </div>
</div>
<?php endif; ?>


<script>
    window.CROPS_AJAX_BASE = "<?php echo BASE_URL; ?>/app/manager/crops/api/";
</script>

<script src="<?php echo BASE_URL; ?>/app/manager/crops/crops.js"></script>

<?php renderFooter(); ?>