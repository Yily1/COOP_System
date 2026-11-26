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
$cropTypes = getCropTypes($pdo);
$todayManila = cropTodayManila();

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

@keyframes cropFlashCard {
    from { background: #e3f4e1; border-color: #2e7d32; }
    to   { background: #ffffff; border-color: #eceae4; }
}
.crop-card.crop-flash { animation: cropFlashCard 2.2s ease-out; }

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

#cropModalErrors { flex: 0 0 auto; }

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
.crop-form .form-group input,
.crop-form .form-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 7px 9px !important;
    font-size: 13px !important;
}
.crop-form .form-group select:disabled { background: #f0f0ec; color: #6b756b; }

/* Farm location section */
.crop-section-label {
    margin: 6px 0 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #1b3a24;
}
.crop-addr-preview {
    background: #F1F6EA;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 12.5px;
    color: #3B6D11;
    line-height: 1.4;
    min-height: 18px;
}

.crop-info {
    display: none;
    background: #F1F6EA;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 12.5px;
    color: #3B6D11;
    line-height: 1.5;
}
.crop-info.crop-show { display: block; }

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

    .crop-form {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .crop-form .form-group label { font-size: 12px; }
    .crop-form .form-group input,
    .crop-form .form-group select {
        padding: 7px 9px !important;
        font-size: 13px !important;
    }
    .crop-form button[type="submit"] { padding: 10px !important; font-size: 13px !important; }
    #cropModalErrors { margin-bottom: 8px !important; font-size: 12.5px !important; }

    .cc-backdrop { padding: 10px 34px; }
}

@media (min-width: 600px) {
    .page-shell { max-width: 720px; }
    #cropListLive {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    #cropListLive .crop-card { margin-bottom: 0; }
    #cropListLive .empty-note { grid-column: 1 / -1; }
}

@media (min-width: 992px) {
    .page-shell { max-width: 1040px; }
    .page-title { font-size: 22px; }
    .page-sub { font-size: 14.5px; }
    .section-intro h2 { font-size: 18px; }

    .stat-card { min-width: 170px; padding: 18px 28px; }
    .stat-label { font-size: 13.5px; }
    .stat-number { font-size: 28px; }

    #cropListLive { gap: 16px; }
    .crop-card { padding: 18px 22px; }
    .crop-name { font-size: 16px; }
    .crop-location { font-size: 13px; }
    .crop-detail-grid { font-size: 13px; gap: 5px 14px; }
    .status-badge { font-size: 11.5px; }
    .crop-action-btn { font-size: 12.5px !important; padding: 6px 14px !important; }

    .crop-modal-card { max-width: 560px; }
    .cc-card { max-width: 360px; }
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

        <a href="<?php echo BASE_URL; ?>/app/user/dashboard.php" class="page-back" aria-label="Back to dashboard" title="Back to dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </a>
    </div>

    <div id="cropStatLive">
        <div class="stat-card">
            <p class="stat-label">Total plantings</p>
            <p class="stat-number"><?php echo (int) $stats['total']; ?></p>
        </div>
    </div>

    <div class="section-intro">
        <h2>Planting history</h2>
        <button id="openCropModalBtn" class="btn-primary" type="button">+ Add planting</button>
    </div>

    <div id="cropListLive">
    <?php if (empty($crops)): ?>
        <p class="empty-note">No crop plantings yet. Tap "+ Add planting" to get started.</p>
    <?php else: ?>
        <?php foreach ($crops as $c): ?>
            <?php $harvestDateReached = $c['expected_harvest_date'] <= $todayManila; ?>
            <div class="crop-card" data-crop-id="<?php echo (int) $c['id']; ?>">
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

                <?php if ($c['status'] === 'growing' && $harvestDateReached): ?>
                    <div class="crop-card-footer">
                        <button class="crop-action-btn crop-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="ready_to_harvest" data-crop="<?php echo htmlspecialchars($c['crop_name']); ?>" type="button">Ready to harvest</button>
                    </div>
                <?php elseif ($c['status'] === 'ready_to_harvest'): ?>
                    <div class="crop-card-footer">
                        <button class="crop-action-btn crop-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="harvested" data-crop="<?php echo htmlspecialchars($c['crop_name']); ?>" type="button">Mark as harvested</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

</div>

<div class="crop-modal" id="cropModalBackdrop">
    <div class="crop-modal-card">
        <span id="closeCropModalBtn" role="button" tabindex="0" aria-label="Close">&times;</span>
        <h2>Add planting</h2>

        <div id="cropModalErrors" style="display: none; background: #fbe6e6; color: #a6322f; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; text-align: left;"></div>

        <form id="cropForm" class="crop-form" autocomplete="off">
            <div class="form-group span-2">
                <label for="crop_type">Crop</label>
                <select id="crop_type" name="crop_type_id" required>
                    <option value="">-- Select crop --</option>
                    <?php foreach ($cropTypes as $t): ?>
                        <option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="crop-info span-2" id="cropInfo"></div>

            <div class="form-group">
                <label for="crop_area">Area (hectares)</label>
                <input type="text" id="crop_area" name="area_hectares" placeholder="e.g. 1.5" inputmode="decimal" maxlength="7" required>
            </div>

            <div class="form-group">
                <label for="crop_planting_date">Planting date</label>
                <input type="date" id="crop_planting_date" name="planting_date" value="<?php echo htmlspecialchars($todayManila); ?>" required>
            </div>

            <p class="crop-section-label span-2" style="grid-column: 1 / -1;">Farm location</p>

            <div class="form-group">
                <label for="crop_region">Region</label>
                <select id="crop_region"><option value="">-- Select Region --</option></select>
            </div>

            <div class="form-group">
                <label for="crop_province">Province</label>
                <select id="crop_province"><option value="">-- Select Province --</option></select>
            </div>

            <div class="form-group">
                <label for="crop_city">Municipality / City</label>
                <select id="crop_city"><option value="">-- Select Municipality / City --</option></select>
            </div>

            <div class="form-group">
                <label for="crop_barangay">Barangay</label>
                <select id="crop_barangay"><option value="">-- Select Barangay --</option></select>
            </div>

            <div class="form-group span-2">
                <label for="crop_purok">Purok / Street</label>
                <input type="text" id="crop_purok" placeholder="Purok 3, Rizal St." maxlength="100">
            </div>

            <div class="form-group span-2">
                <label>Complete location (auto-built)</label>
                <div class="crop-addr-preview" id="cropAddrPreview">&mdash;</div>
            </div>

            <button type="submit" id="cropSubmitBtn">Submit planting</button>
        </form>
    </div>
</div>

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

<!-- Shared Philippine address dropdowns (app/manager/crops/ph-address.js) -->
<script src="<?php echo BASE_URL; ?>/app/manager/crops/ph-address.js"></script>

<script>
(function() {
    const CROP_TYPES = <?php echo json_encode($cropTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const openBtn = document.getElementById('openCropModalBtn');
    const closeBtn = document.getElementById('closeCropModalBtn');
    const backdrop = document.getElementById('cropModalBackdrop');
    const form = document.getElementById('cropForm');
    const submitBtn = document.getElementById('cropSubmitBtn');
    const errorsEl = document.getElementById('cropModalErrors');
    const typeSel = document.getElementById('crop_type');
    const plantingInput = document.getElementById('crop_planting_date');
    const infoEl = document.getElementById('cropInfo');
    const areaEl = document.getElementById('crop_area');

    if (!openBtn) return;

    // ---------- Errors (isa o higit pang mensahe) ----------
    function showErrors(list) {
        errorsEl.textContent = '';
        list.forEach(function(msg, i) {
            if (i > 0) errorsEl.appendChild(document.createElement('br'));
            errorsEl.appendChild(document.createTextNode(msg));
        });
        errorsEl.style.display = 'block';
        errorsEl.scrollIntoView({ block: 'nearest' });
    }
    function showError(msg) { showErrors([msg]); }

    // ---------- Philippine address (PSGC) ----------
    const addr = PHAddress({
        api: '<?php echo BASE_URL; ?>/app/manager/crops/api/psgc.php',
        region: document.getElementById('crop_region'),
        province: document.getElementById('crop_province'),
        city: document.getElementById('crop_city'),
        barangay: document.getElementById('crop_barangay'),
        purok: document.getElementById('crop_purok'),
        preview: document.getElementById('cropAddrPreview'),
        onError: showError
    });

    // Area: numbers lang (hanggang 2 decimals)
    PHAddress.bindDecimal(areaEl);

    function findType(id) {
        for (let i = 0; i < CROP_TYPES.length; i++) {
            if (String(CROP_TYPES[i].id) === String(id)) return CROP_TYPES[i];
        }
        return null;
    }

    // Planting date + HULING araw ng range (days o months), kapareho ng ginagawa sa server
    function addToDate(dateStr, amount, unit) {
        const p = dateStr.split('-');
        const d = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
        if (unit === 'months') d.setMonth(d.getMonth() + amount);
        else d.setDate(d.getDate() + amount);
        return d;
    }

    function rangeText(t) {
        const unit = t.unit === 'months' ? 'months' : 'days';
        return (Number(t.min_value) === Number(t.max_value))
            ? t.max_value + ' ' + unit
            : t.min_value + '\u2013' + t.max_value + ' ' + unit;
    }

    function updateInfo() {
        const t = findType(typeSel.value);
        if (!t) {
            infoEl.classList.remove('crop-show');
            infoEl.textContent = '';
            return;
        }
        infoEl.textContent = '';
        const line1 = document.createElement('div');
        line1.textContent = 'Takes ' + rangeText(t);
        infoEl.appendChild(line1);

        if (plantingInput.value) {
            const exp = addToDate(plantingInput.value, Number(t.max_value), t.unit);
            const line2 = document.createElement('div');
            line2.appendChild(document.createTextNode('Expected harvest: '));
            const b = document.createElement('b');
            b.textContent = exp.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            line2.appendChild(b);
            infoEl.appendChild(line2);
        }
        infoEl.classList.add('crop-show');
    }

    typeSel.addEventListener('change', updateInfo);
    plantingInput.addEventListener('input', updateInfo);

    function openModal() {
        backdrop.classList.add('crop-open');
        addr.load(); // i-load ang listahan ng regions (isang beses lang tatawag sa server)
    }
    function closeModal() {
        backdrop.classList.remove('crop-open');
        form.reset();
        addr.reset();
        updateInfo();
        errorsEl.style.display = 'none';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    closeBtn.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); closeModal(); }
    });
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) closeModal(); });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorsEl.style.display = 'none';

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        Object.assign(data, addr.getValues());

        // Required checks sa browser (ang server pa rin ang final na bantay)
        const errs = [];
        if (!data.crop_type_id) errs.push('Please choose a crop.');
        if (!/^\d{1,4}(\.\d{1,2})?$/.test(data.area_hectares || '') || Number(data.area_hectares) <= 0) {
            errs.push('Area must be a number greater than 0 (e.g. 1.5).');
        }
        if (!data.planting_date) errs.push('Planting date is required.');
        addr.validate().forEach(function(m) { errs.push(m); });

        if (errs.length) {
            showErrors(errs);
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
                showError('Server error. Check the console (F12).');
                return;
            }

            if (response.ok && payload.success !== false) {
                window.location.reload();
            } else {
                showErrors(payload.errors && payload.errors.length ? payload.errors : [payload.message || 'Something went wrong.']);
            }
        } catch (err) {
            showError('Could not reach the server: ' + err.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit planting';
        }
    });

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
        if (e.key !== 'Escape') return;
        if (ccBackdrop.classList.contains('cc-open')) ccClose();
        else if (backdrop.classList.contains('crop-open')) closeModal();
    });
    ccOk.addEventListener('click', function() {
        if (ccOnConfirm) ccOnConfirm();
    });

    document.getElementById('cropListLive').addEventListener('click', function(e) {
        const btn = e.target.closest('.crop-advance-btn');
        if (!btn) return;

        const next = btn.dataset.next;
        const crop = btn.dataset.crop || 'this planting';
        const isHarvest = (next === 'harvested');

        const title = isHarvest ? 'Mark as harvested?' : 'Ready to harvest?';
        const text = isHarvest
            ? 'Confirm that your ' + crop + ' has been harvested.'
            : 'Confirm that your ' + crop + ' is ready to harvest.';

        ccConfirm(title, text, isHarvest ? 'Mark as harvested' : 'Ready to harvest', async function() {
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
})();
</script>

<script>
(function () {
    const LIVE_CHECK_MS = 3000;

    function itemMap(root, sel, attr) {
        const m = new Map();
        if (!sel) return m;
        root.querySelectorAll(sel).forEach(el => m.set(el.getAttribute(attr), el.outerHTML));
        return m;
    }

    const liveParts = [
        { id: 'cropStatLive' },
        { id: 'cropListLive', sel: '.crop-card', attr: 'data-crop-id' }
    ].filter(function (p) {
        p.el = document.getElementById(p.id);
        if (!p.el) return false;
        p.html = p.el.innerHTML;
        p.items = itemMap(p.el, p.sel, p.attr);
        return true;
    });

    let busy = false;
    let fails = 0;
    let timer = null;

    function anyModalOpen() {
        return !!document.querySelector('.crop-modal.crop-open, .cc-backdrop.cc-open');
    }

    async function liveCheck() {
        if (document.hidden || busy || anyModalOpen()) return;
        busy = true;
        try {
            const r = await fetch(location.href, { cache: 'no-store', credentials: 'same-origin' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
            const fresh = liveParts.map(p => doc.getElementById(p.id));

            if (fresh.every(Boolean)) {
                liveParts.forEach(function (p, i) {
                    const f = fresh[i];
                    if (f.innerHTML === p.html) return;
                    p.html = f.innerHTML;
                    const newItems = itemMap(f, p.sel, p.attr);
                    p.el.innerHTML = p.html;
                    if (p.sel) {
                        p.el.querySelectorAll(p.sel).forEach(function (el) {
                            const id = el.getAttribute(p.attr);
                            if (p.items.get(id) !== newItems.get(id)) el.classList.add('crop-flash');
                        });
                    }
                    p.items = newItems;
                });
            }
            fails = 0;
        } catch (e) {
            fails++;
        } finally {
            busy = false;
        }
    }

    function schedule() {
        clearTimeout(timer);
        const delay = Math.min(LIVE_CHECK_MS * Math.pow(2, fails), 60000);
        timer = setTimeout(async function () { await liveCheck(); schedule(); }, delay);
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) liveCheck(); });
    schedule();
})();
</script>

<?php renderFooter(); ?>