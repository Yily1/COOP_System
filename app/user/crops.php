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

.section-intro {
    margin: 0 0 14px 0;
    display: flex;
    align-items: baseline;
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

.stat-number {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 2px;
}

.stat-label {
    font-size: 12.5px;
    color: rgba(255,255,255,0.85);
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

/* MODAL */
.crop-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; padding: 20px; }
.crop-modal.crop-open { display: flex; }
.crop-modal-card { background: #fff; border-radius: 14px; width: 100%; max-width: 400px; box-sizing: border-box; padding: 24px; position: relative; }

@media (max-width: 480px) {
    .page-title { font-size: 17px; }
    .btn-primary { padding: 8px 14px !important; font-size: 13px !important; }
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
    </div>

    <div class="section-intro" style="justify-content: flex-end; margin-bottom: 10px;">
        <button id="openCropModalBtn" class="btn-primary">+ Add planting</button>
    </div>

    <div class="stat-card">
        <p class="stat-number"><?php echo (int) $stats['total']; ?></p>
        <p class="stat-label">Total plantings</p>
    </div>

    <div class="section-intro">
        <h2>Planting history</h2>
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
                        <button class="crop-action-btn crop-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="ready_to_harvest" type="button">Mark ready</button>
                    </div>
                <?php elseif ($c['status'] === 'ready_to_harvest'): ?>
                    <div class="crop-card-footer">
                        <button class="crop-action-btn crop-advance-btn" data-id="<?php echo $c['id']; ?>" data-next="harvested" type="button">Mark harvested</button>
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
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center; color: #1b3a24;">Add planting</h2>
        <button id="closeCropModalBtn"
                style="position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important; font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important; max-width: 28px !important; flex: 0 0 auto !important; color: #667066 !important; line-height: 1 !important; display: inline-flex !important; align-items: center; justify-content: center;">&times;</button>

        <div id="cropModalErrors" style="display: none; background: #fbe6e6; color: #a6322f; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; text-align: left;"></div>
        <div id="cropModalSuccess" style="display: none; background: #e8f5e9; color: #2e7d32; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; text-align: left;">
            Your planting will be sent to a manager for approval before it starts tracking.
        </div>

        <form id="cropForm" style="text-align: left;">
            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066;">Crop</label>
            <input type="text" name="crop_name" placeholder="e.g. Rice, Corn, Eggplant" required
                   style="width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 14px; font-family: inherit; font-size: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066;">Farm location</label>
            <input type="text" name="location" placeholder="e.g. Barangay Malinis, Sitio Ilaya" required
                   style="width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 14px; font-family: inherit; font-size: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066;">Area (hectares)</label>
            <input type="number" name="area_hectares" placeholder="e.g. 1.5" min="0.01" step="0.01" required
                   style="width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 14px; font-family: inherit; font-size: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066;">Planting date</label>
            <input type="date" name="planting_date" required
                   style="width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 14px; font-family: inherit; font-size: 14px;">

            <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #667066;">Expected harvest date</label>
            <input type="date" name="expected_harvest_date" required
                   style="width: 100%; padding: 9px 11px; border-radius: 8px; border: 1px solid #eceae4; box-sizing: border-box; margin-bottom: 20px; font-family: inherit; font-size: 14px;">

            <button type="submit" id="cropSubmitBtn" class="btn-primary" style="width: 100% !important; padding: 11px !important; font-size: 14px !important;">
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

    if (!openBtn) return;

    function openModal() { backdrop.classList.add('crop-open'); }
    function closeModal() {
        backdrop.classList.remove('crop-open');
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

    document.querySelectorAll('.crop-advance-btn').forEach(function(btn) {
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