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

/**
 * Reuses the same status badge logic/labels as the desktop view.
 */
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
.crop-page {
    padding: 18px 16px 40px;
    box-sizing: border-box;
    max-width: 600px;
    margin: 0 auto;
}

.crop-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: nowrap;
}
.crop-title-row h1 {
    margin: 0;
    font-size: 18px;
    font-weight: 500;
    color: #2e7d32;
    white-space: nowrap;
}

.add-crop-btn {
    background: #2e7d32 !important;
    color: #fff !important;
    border: none !important;
    padding: 8px 12px !important;
    border-radius: 6px !important;
    cursor: pointer !important;
    font-size: 12.5px !important;
    font-weight: 500 !important;
    text-transform: none !important;
    letter-spacing: normal !important;
    line-height: 1.4 !important;
    white-space: nowrap !important;
    width: auto !important;
    flex: 0 0 auto !important;
    box-sizing: border-box !important;
}

.crop-summary-card {
    display: inline-block;
    min-width: 100px;
    text-align: center;
    background: #2e7d32;
    border-radius: 8px;
    padding: 10px 16px;
    color: #fff;
    margin-bottom: 16px;
}
.crop-summary-label { font-size: 11px; margin: 0 0 3px; opacity: 0.85; }
.crop-summary-value { font-size: 20px; font-weight: 600; margin: 0; }

.crop-section-title { font-size: 14px; font-weight: 500; color: #333; margin: 4px 0 10px; }

.crop-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}
.crop-card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.crop-name { font-size: 14px; font-weight: 600; margin: 0; color: #222; }
.crop-location { font-size: 11.5px; color: #777; margin: 2px 0 0; }

.crop-status-badge { font-size: 10.5px; font-weight: 600; padding: 3px 9px; border-radius: 12px; white-space: nowrap; }
.crop-status-badge.pending           { background: #fff3cd; color: #856404; }
.crop-status-badge.rejected          { background: #f8d7da; color: #721c24; }
.crop-status-badge.growing           { background: #cce5ff; color: #004085; }
.crop-status-badge.ready_to_harvest  { background: #fff3cd; color: #856404; }
.crop-status-badge.harvested         { background: #d4edda; color: #155724; }

.crop-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 10px; font-size: 12px; color: #555; margin-bottom: 8px; }
.crop-detail-grid b { color: #222; }

.crop-card-footer { border-top: 1px dashed #eee; padding-top: 8px; display: flex; justify-content: flex-end; }
.crop-action-btn {
    background: #f5f5f5 !important;
    border: 1px solid #ddd !important;
    border-radius: 14px !important;
    padding: 5px 10px !important;
    font-size: 11.5px !important;
    font-weight: 500 !important;
    color: #2e7d32 !important;
    cursor: pointer;
}
</style>

<div class="crop-page">

<div class="crop-title-row">
    <h1>My crops</h1>
    <button id="openCropModalBtn" class="add-crop-btn">+ Add planting</button>
</div>

<div class="crop-summary-card">
    <p class="crop-summary-label">Total plantings</p>
    <p class="crop-summary-value"><?php echo (int) $stats['total']; ?></p>
</div>

<p class="crop-section-title">Planting history</p>

<?php if (empty($crops)): ?>
    <p style="color: #666; text-align: center; padding: 20px 0;">No crop plantings yet. Tap "+ Add planting" to get started.</p>
<?php else: ?>
    <?php foreach ($crops as $c): ?>
        <div class="crop-card">
            <div class="crop-card-top">
                <div>
                    <p class="crop-name"><?php echo htmlspecialchars($c['crop_name']); ?></p>
                    <p class="crop-location"><?php echo htmlspecialchars($c['location']); ?></p>
                </div>
                <span class="crop-status-badge <?php echo htmlspecialchars($c['status']); ?>">
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
                    <button class="crop-action-btn crop-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="ready_to_harvest" type="button">Mark ready</button>
                </div>
            <?php elseif ($c['status'] === 'ready_to_harvest'): ?>
                <div class="crop-card-footer">
                    <button class="crop-action-btn crop-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="harvested" type="button">Mark harvested</button>
                </div>
            <?php elseif ($c['status'] === 'pending'): ?>
                <div class="crop-card-footer">
                    <span style="color:#999; font-size: 12px;">Awaiting manager approval</span>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>

<!-- ============================================================
     MODAL - Add planting (same pattern as the Payments modal)
     ============================================================ -->
<div id="cropModalBackdrop" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center;">
    <div style="background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 400px; box-sizing: border-box; position: relative;">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Add planting</h2>
        <button id="closeCropModalBtn"
                style="position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #333 !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;">&times;</button>

        <div id="cropModalErrors" style="display: none; background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; text-align: left;"></div>
        <div id="cropModalSuccess" style="display: none; background: #d4edda; color: #155724; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; text-align: left;">
            Your planting will be sent to a manager for approval before it starts tracking.
        </div>

        <form id="cropForm" style="text-align: left;">
            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Crop</label>
            <input type="text" name="crop_name" placeholder="e.g. Rice, Corn, Eggplant" required
                   style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Farm location</label>
            <input type="text" name="location" placeholder="e.g. Barangay Malinis, Sitio Ilaya" required
                   style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Area (hectares)</label>
            <input type="number" name="area_hectares" placeholder="e.g. 1.5" min="0.01" step="0.01" required
                   style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Planting date</label>
            <input type="date" name="planting_date" required
                   style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Expected harvest date</label>
            <input type="date" name="expected_harvest_date" required
                   style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 20px;">

            <button type="submit" id="cropSubmitBtn"
                    style="width: 100%; box-sizing: border-box; background: #2e7d32 !important; color: #fff !important; border: none !important; padding: 10px !important; border-radius: 6px !important; cursor: pointer; font-size: 14px !important;">
                Submit planting
            </button>
        </form>
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
    const successEl = document.getElementById('cropModalSuccess');

    if (!openBtn) return;

    function openModal() { backdrop.style.display = 'flex'; }
    function closeModal() {
        backdrop.style.display = 'none';
        form.reset();
        errorsEl.style.display = 'none';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
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

    document.querySelectorAll('.crop-advance-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const next = btn.dataset.next;
            const label = next === 'harvested' ? 'harvested' : 'ready to harvest';
            if (!confirm('Mark this planting as ' + label + '?')) return;
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
                    alert(payload.message || 'Something went wrong.');
                }
            } catch (err) {
                alert('Could not reach the server: ' + err.message);
            }
        });
    });
})();
</script>

<?php renderFooter(); ?>