<?php
require_once '../../config/config.php';
require_once '../../config/functions.php';

requireRole('user');

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT m.id AS member_db_id
    FROM users u
    LEFT JOIN members m ON u.member_id = m.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$myProfile = $stmt->fetch(PDO::FETCH_ASSOC);

$memberId = $myProfile['member_db_id'] ?? 0;

$crops = getCropsForMember($pdo, $memberId);
$stats = getCropStats($pdo, $memberId);

function cmMobileStatusLabel($status) {
    $map = [
        'pending'          => 'Pending',
        'rejected'         => 'Rejected',
        'growing'          => 'Growing',
        'ready_to_harvest' => 'Ready',
        'harvested'        => 'Harvested',
    ];
    return $map[$status] ?? ucfirst($status);
}

renderHeader('My Crops');
?>

<style>

.page-shell {
    width: 100%;
    max-width: 420px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.page-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 22px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #e8f5e9;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.page-header-icon svg {
    width: 22px;
    height: 22px;
    stroke: #2e7d32;
}

.page-title {
    margin: 0;
    color: #1b3a24;
    font-size: 19px;
    font-weight: 600;
}

.page-sub {
    margin: 2px 0 0 0;
    color: #667066;
    font-size: 13.5px;
}

/* Back to dashboard (round arrow, right side of the header row).
   An <a> link, not a <button>, so the site-wide button rules can't touch it. */
.page-back {
    margin-left: auto;
    flex: none;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #3B6D11;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    color: #3B6D11 !important;
    text-decoration: none !important;
    box-shadow: none;
    transition: background 0.12s ease;
}

.page-back:hover {
    background: #eef5e4;
}

.page-back svg {
    width: 18px;
    height: 18px;
    stroke: currentColor;
}

.section-intro {
    margin: 0 0 14px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.section-intro h2 {
    margin: 0;
    color: #1b3a24;
    font-size: 16px;
    font-weight: 600;
}

.btn-primary {
    background: #2e7d32 !important;
    color: #fff !important;
    border: none !important;
    padding: 9px 16px !important;
    border-radius: 10px !important;
    cursor: pointer !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    text-transform: none !important;
    letter-spacing: normal !important;
    line-height: 1.4 !important;
    white-space: nowrap !important;
    width: auto !important;
    flex: 0 0 auto !important;
    box-sizing: border-box !important;
}

.stat-card {
    display: inline-block;
    min-width: 130px;
    padding: 14px 18px;
    box-sizing: border-box;
    border-radius: 14px;
    background: #2e7d32;
    margin-bottom: 22px;
    text-align: center;
}

.stat-label {
    font-size: 12.5px;
    color: rgba(255,255,255,0.85);
    margin: 0 0 2px;
}

.stat-number {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.crop-card {
    background: #ffffff;
    border: 1px solid #eceae4;
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 10px;
}

.crop-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.crop-name {
    margin: 0;
    font-size: 14.5px;
    font-weight: 600;
    color: #1b3a24;
}

.crop-location {
    margin: 2px 0 0;
    font-size: 12px;
    color: #667066;
}

.status-badge {
    font-size: 10.5px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
    white-space: nowrap;
    flex: none;
}
.status-badge.pending           { background: #fdf1de; color: #a06b16; }
.status-badge.rejected          { background: #fbe6e6; color: #a6322f; }
.status-badge.growing           { background: #e3edfb; color: #274b81; }
.status-badge.ready_to_harvest  { background: #fdf1de; color: #a06b16; }
.status-badge.harvested         { background: #e8f5e9; color: #2e7d32; }

.crop-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3px 10px;
    font-size: 12px;
    color: #667066;
    margin-bottom: 8px;
}
.crop-detail-grid b { color: #1b3a24; }

.crop-card-footer {
    border-top: 1px dashed #eceae4;
    padding-top: 8px;
    display: flex;
    justify-content: flex-end;
}

.crop-action-btn {
    background: #f7f8f5 !important;
    border: 1px solid #eceae4 !important;
    border-radius: 20px !important;
    padding: 5px 11px !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    color: #2e7d32 !important;
    cursor: pointer;
}

.empty-note { color: #667066; font-size: 13px; text-align: center; padding: 20px 0; }

@media (max-width: 480px) {
    .page-title { font-size: 17px; }
    .btn-primary { padding: 8px 14px !important; font-size: 13px !important; }
}

/* ============================================================
   ADD PLANTING MODAL
   Same look as the Update Account modal on My Account: fixed title,
   only the fields scroll, button always visible, 2 columns on
   desktop and one compact column on phones.
   ============================================================ */
.crop-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}
.crop-modal.crop-open { display: flex; }

.crop-modal-card {
    background: #fff;
    border-radius: 14px;
    padding: 28px 24px 24px;
    width: 100%;
    max-width: 480px;
    box-sizing: border-box;
    position: relative;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 32px);
    overflow: hidden;
}

.crop-modal-card h2 {
    background: none !important;
    color: #1b3a24 !important;
    margin: 0 0 14px 0 !important;
    padding: 0 40px 0 0 !important;
    font-size: 20px !important;
    font-weight: 600 !important;
    text-align: left !important;
    flex: 0 0 auto;
}

/* Close (x): a <span role="button"> so no global "button { }" rule can stretch it */
#closeCropModalBtn {
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
#closeCropModalBtn:hover {
    background: #eef3fb !important;
    border-color: #274b81 !important;
}

#cropModalErrors,
#cropModalSuccess { flex: 0 0 auto; }

.crop-form {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding-right: 4px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 12px;
    text-align: left;
}
.crop-form .form-group { margin: 0 !important; min-width: 0; }
.crop-form .span-2 { grid-column: 1 / -1; }

.crop-form .form-group label {
    display: block;
    font-size: 13px;
    margin-bottom: 3px !important;
}
.crop-form .form-group input {
    width: 100%;
    box-sizing: border-box;
    padding: 7px 9px !important;
    font-size: 13px !important;
}

.crop-form button[type="submit"] {
    grid-column: 1 / -1;
    position: sticky;
    bottom: 0;
    z-index: 2;
    width: 100% !important;
    box-sizing: border-box;
    margin: 0 !important;
    padding: 10px !important;
    background: #639a20 !important;
    color: #fff !important;
    border: none !important;
    border-radius: 6px !important;
    cursor: pointer;
    font-size: 14px !important;
    box-shadow: 0 -8px 8px -4px #fff;
}

/* ============================================================
   CONFIRM MODAL (replaces the browser's confirm() / alert())
   ============================================================ */
.cc-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 1100;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}
.cc-backdrop.cc-open { display: flex; }

.cc-card {
    background: #fff;
    border-radius: 14px;
    padding: 22px 20px 18px;
    width: 100%;
    max-width: 320px;
    box-sizing: border-box;
    text-align: center;
}

.cc-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #e8f5e9;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}
.cc-icon svg { width: 22px; height: 22px; stroke: #2e7d32; }
.cc-icon.cc-error { background: #fbe6e6; }
.cc-icon.cc-error svg { stroke: #a6322f; }

.cc-title { margin: 0 0 6px; color: #1b3a24; font-size: 16px; font-weight: 600; }
.cc-text { margin: 0 0 16px; color: #667066; font-size: 13.5px; line-height: 1.5; }

.cc-actions { display: flex; gap: 10px; }

button.cc-btn {
    flex: 1 1 0 !important;
    width: auto !important;
    min-width: 0 !important;
    margin: 0 !important;
    padding: 10px 12px !important;
    border-radius: 10px !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    text-transform: none !important;
    letter-spacing: normal !important;
    cursor: pointer !important;
    box-shadow: none !important;
    box-sizing: border-box !important;
}
button.cc-btn-cancel { background: #fff !important; color: #1b3a24 !important; border: 1px solid #eceae4 !important; }
button.cc-btn-ok { background: #2e7d32 !important; color: #fff !important; border: none !important; }
button.cc-btn:disabled { opacity: 0.6; cursor: not-allowed !important; }

@media (max-width: 560px) {
    /* Wider side margins = narrower modal */
    .crop-modal { padding: 10px 34px; }
    .crop-modal-card {
        padding: 18px 16px 14px;
        max-width: 100%;
        border-radius: 12px;
        max-height: calc(100vh - 20px);
    }
    .crop-modal-card h2 {
        font-size: 17px !important;
        margin: 0 0 10px 0 !important;
    }
    #closeCropModalBtn { top: 12px !important; right: 12px !important; }

    /* One field per row, stacked top to bottom */
    .crop-form {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .crop-form .form-group label { font-size: 12px; }
    .crop-form .form-group input {
        padding: 7px 9px !important;
        font-size: 13px !important;
    }
    .crop-form button[type="submit"] { padding: 10px !important; font-size: 13px !important; }
    #cropModalErrors, #cropModalSuccess { margin-bottom: 8px !important; font-size: 12.5px !important; }

    .cc-backdrop { padding: 10px 34px; }
}
</style>

<div class="page-shell">

    <div class="page-header">
        <div class="page-header-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22V12"/><path d="M12 12C12 6 6 5 4 5c0 6 2 9 8 7z"/><path d="M12 12c0-6 6-7 8-7 0 6-2 9-8 7z"/></svg>
        </div>
        <div style="flex:1;">
            <p class="page-title">My crops</p>
            <p class="page-sub">Track your plantings from planting to harvest</p>
        </div>

        <!-- Back to dashboard: adjust the href if your user dashboard file is named differently -->
        <a href="<?php echo BASE_URL; ?>/app/user/dashboard.php" class="page-back" aria-label="Back to dashboard" title="Back to dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
    </div>

    <div class="stat-card">
        <p class="stat-label">Total plantings</p>
        <p class="stat-number"><?php echo (int) $stats['total']; ?></p>
    </div>

    <div class="section-intro">
        <h2>Planting history</h2>
        <button id="openCropModalBtn" class="btn-primary" type="button">+ Add planting</button>
    </div>

    <?php if (empty($crops)): ?>
        <p class="empty-note">No crop plantings yet. Tap "+ Add planting" to get started.</p>
    <?php else: ?>
        <?php foreach ($crops as $c): ?>
            <div class="crop-card">
                <div class="crop-card-top">
                    <div>
                        <p class="crop-name"><?php echo htmlspecialchars($c['crop_name']); ?></p>
                        <p class="crop-location"><?php echo htmlspecialchars($c['location']); ?></p>
                    </div>
                    <span class="status-badge <?php echo htmlspecialchars($c['status']); ?>">
                        <?php echo htmlspecialchars(cmMobileStatusLabel($c['status'])); ?>
                    </span>
                </div>

                <div class="crop-detail-grid">
                    <span>Area: <b><?php echo rtrim(rtrim(number_format($c['area_hectares'], 2), '0'), '.'); ?> ha</b></span>
                    <span>Planted: <b><?php echo date('M j, Y', strtotime($c['planting_date'])); ?></b></span>
                    <span>Expected: <b><?php echo date('M j, Y', strtotime($c['expected_harvest_date'])); ?></b></span>
                    <span>Harvested: <b><?php echo $c['actual_harvest_date'] ? date('M j, Y', strtotime($c['actual_harvest_date'])) : '—'; ?></b></span>
                </div>

                <?php if ($c['status'] === 'growing'): ?>
                    <div class="crop-card-footer">
                        <button class="crop-action-btn crop-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="ready_to_harvest" data-crop="<?php echo htmlspecialchars($c['crop_name']); ?>" type="button">Mark ready</button>
                    </div>
                <?php elseif ($c['status'] === 'ready_to_harvest'): ?>
                    <div class="crop-card-footer">
                        <button class="crop-action-btn crop-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="harvested" data-crop="<?php echo htmlspecialchars($c['crop_name']); ?>" type="button">Mark harvested</button>
                    </div>
                <?php elseif ($c['status'] === 'pending'): ?>
                    <div class="crop-card-footer">
                        <span style="color:#a9a79f; font-size: 12px;">Awaiting manager approval</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<!-- MODAL - Add planting -->
<div class="crop-modal" id="cropModalBackdrop">
    <div class="crop-modal-card">
        <span id="closeCropModalBtn" role="button" tabindex="0" aria-label="Close">&times;</span>
        <h2>Add planting</h2>

        <div id="cropModalErrors" style="display: none; background: #fbe6e6; color: #a6322f; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; text-align: left;"></div>
        <div id="cropModalSuccess" style="display: none; background: #e8f5e9; color: #2e7d32; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; text-align: left;">
            Your planting will be sent to a manager for approval before it starts tracking.
        </div>

        <form id="cropForm" class="crop-form">
            <div class="form-group">
                <label for="crop_name">Crop</label>
                <input type="text" id="crop_name" name="crop_name" placeholder="e.g. Rice, Corn, Eggplant" required>
            </div>

            <div class="form-group">
                <label for="crop_area">Area (hectares)</label>
                <input type="number" id="crop_area" name="area_hectares" placeholder="e.g. 1.5" min="0.01" step="0.01" required>
            </div>

            <div class="form-group span-2">
                <label for="crop_location">Farm location</label>
                <input type="text" id="crop_location" name="location" placeholder="e.g. Barangay Malinis, Sitio Ilaya" required>
            </div>

            <div class="form-group">
                <label for="crop_planting_date">Planting date</label>
                <input type="date" id="crop_planting_date" name="planting_date" required>
            </div>

            <div class="form-group">
                <label for="crop_harvest_date">Expected harvest date</label>
                <input type="date" id="crop_harvest_date" name="expected_harvest_date" required>
            </div>

            <button type="submit" id="cropSubmitBtn">Submit planting</button>
        </form>
    </div>
</div>

<!-- MODAL - Confirm (replaces the browser confirm() / alert()) -->
<div class="cc-backdrop" id="ccBackdrop">
    <div class="cc-card" role="dialog" aria-modal="true" aria-labelledby="ccTitle">
        <div class="cc-icon" id="ccIcon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p class="cc-title" id="ccTitle">Confirm</p>
        <p class="cc-text" id="ccText"></p>
        <div class="cc-actions">
            <button type="button" class="cc-btn cc-btn-cancel" id="ccCancel">Cancel</button>
            <button type="button" class="cc-btn cc-btn-ok" id="ccOk">Confirm</button>
        </div>
    </div>
</div>

<script>
(function() {
    const openBtn = document.getElementById('openCropModalBtn');
    const closeBtn = document.getElementById('closeCropModalBtn');
    const backdrop = document.getElementById('cropModalBackdrop');
    const form = document.getElementById('cropForm');
    const submitBtn = document.getElementById('cropSubmitBtn');
    const errorsEl = document.getElementById('cropModalErrors');

    if (!openBtn) return;

    function openModal() { backdrop.classList.add('crop-open'); }
    function closeModal() {
        backdrop.classList.remove('crop-open');
        form.reset();
        errorsEl.style.display = 'none';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    // The close (x) is a <span role="button">, so it needs a manual keyboard handler
    closeBtn.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); closeModal(); }
    });
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorsEl.style.display = 'none';

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        if (new Date(data.expected_harvest_date) < new Date(data.planting_date)) {
            errorsEl.textContent = 'Expected harvest date cannot be before the planting date.';
            errorsEl.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        try {
            const response = await fetch('<?php echo BASE_URL; ?>/app/user/api/add-planting.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const rawText = await response.text();
            let payload;
            try {
                payload = JSON.parse(rawText);
            } catch (parseErr) {
                console.error('Non-JSON response:', rawText);
                errorsEl.textContent = 'Server error. Check the console (F12).';
                errorsEl.style.display = 'block';
                return;
            }

            if (response.ok && payload.success !== false) {
                window.location.reload();
            } else {
                errorsEl.textContent = payload.message || 'Something went wrong.';
                errorsEl.style.display = 'block';
            }
        } catch (err) {
            errorsEl.textContent = 'Could not reach the server: ' + err.message;
            errorsEl.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit planting';
        }
    });

    /* ============================================================
       CONFIRM MODAL
       ============================================================ */
    const ccBackdrop = document.getElementById('ccBackdrop');
    const ccIcon = document.getElementById('ccIcon');
    const ccTitle = document.getElementById('ccTitle');
    const ccText = document.getElementById('ccText');
    const ccOk = document.getElementById('ccOk');
    const ccCancel = document.getElementById('ccCancel');

    const ICON_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    const ICON_ERROR = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

    let ccOnConfirm = null;

    function ccClose() {
        ccBackdrop.classList.remove('cc-open');
        ccOnConfirm = null;
        ccOk.disabled = false;
        ccCancel.disabled = false;
    }

    // Confirm dialog: title, message, then runs onConfirm when OK is tapped
    function ccConfirm(title, text, okLabel, onConfirm) {
        ccIcon.className = 'cc-icon';
        ccIcon.innerHTML = ICON_CHECK;
        ccTitle.textContent = title;
        ccText.textContent = text;
        ccOk.textContent = okLabel || 'Confirm';
        ccOk.style.display = '';
        ccCancel.textContent = 'Cancel';
        ccOnConfirm = onConfirm;
        ccBackdrop.classList.add('cc-open');
    }

    // Error dialog: replaces alert(), only one button
    function ccError(text) {
        ccIcon.className = 'cc-icon cc-error';
        ccIcon.innerHTML = ICON_ERROR;
        ccTitle.textContent = 'Something went wrong';
        ccText.textContent = text;
        ccOk.style.display = 'none';
        ccCancel.textContent = 'Close';
        ccOnConfirm = null;
        ccBackdrop.classList.add('cc-open');
    }

    ccCancel.addEventListener('click', ccClose);
    ccBackdrop.addEventListener('click', function(e) { if (e.target === ccBackdrop) ccClose(); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && ccBackdrop.classList.contains('cc-open')) ccClose();
    });
    ccOk.addEventListener('click', function() {
        if (ccOnConfirm) ccOnConfirm();
    });

    document.querySelectorAll('.crop-advance-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const next = btn.dataset.next;
            const crop = btn.dataset.crop || 'this planting';
            const isHarvest = (next === 'harvested');

            const title = isHarvest ? 'Mark as harvested?' : 'Mark as ready?';
            const text = isHarvest
                ? 'Confirm that your ' + crop + ' has been harvested.'
                : 'Confirm that your ' + crop + ' is ready to harvest.';

            ccConfirm(title, text, isHarvest ? 'Mark harvested' : 'Mark ready', async function() {
                ccOk.disabled = true;
                ccCancel.disabled = true;
                ccOk.textContent = 'Saving...';
                try {
                    const response = await fetch('<?php echo BASE_URL; ?>/app/user/api/planting-action.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'advance', id: btn.dataset.id, status: next })
                    });
                    const payload = await response.json();
                    if (response.ok && payload.success !== false) {
                        window.location.reload();
                    } else {
                        ccClose();
                        ccError(payload.message || 'Something went wrong.');
                    }
                } catch (err) {
                    ccClose();
                    ccError('Could not reach the server: ' + err.message);
                }
            });
        });
    });
})();
</script>

<?php renderFooter(); ?>