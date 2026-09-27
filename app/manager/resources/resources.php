<?php
// Adjust this path if your config.php (session_start, BASE_URL, $pdo) lives elsewhere
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireLogin();

// Manager-only page.
if (($_SESSION['role'] ?? '') !== 'manager') {
    die("Access denied.");
}

$members = getMembersWithEligibility($pdo);
$history = getResourceDistributions($pdo);

/**
 * "Dela Cruz" + "Juan" -> "DC". Same logic as cmInitials() in crops.php,
 * duplicated here since that one is local to that file.
 */
function rmInitials($lastName, $firstName) {
    $l = trim((string) $lastName);
    $f = trim((string) $firstName);
    if ($l === '' && $f === '') {
        return '?';
    }
    return strtoupper(mb_substr($l, 0, 1) . mb_substr($f, 0, 1));
}

renderHeader('Resource Distribution');
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

.rd-page, .rd-page *, .rd-modal, .rd-modal * { box-sizing: border-box; }

.rd-page {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--cm-ink);
    width: 100%;
    max-width: 100%;
    padding: 0 0 40px;
}

.rd-page h1, .rd-page h2 { font-weight: 600; color: #2c2c2a; margin: 0; }

.rd-muted { color: var(--cm-ink-soft); font-size: 14px; margin: 4px 0 0; }
.rd-text-right { text-align: right; }

.rd-header {
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--cm-line);
}
.rd-header h1 { font-size: 26px; }

.rd-section {
    background: var(--cm-card);
    border: 1px solid var(--cm-line);
    border-radius: var(--cm-radius);
    padding: 22px 22px 8px;
    margin-bottom: 24px;
}
.rd-section-header { margin-bottom: 18px; }
.rd-section-header h2 { font-size: 19px; }

.rd-table-wrap { overflow-x: auto; }
.rd-page table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.rd-page thead th {
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--cm-ink-soft);
    padding: 10px 8px;
    border-bottom: 1.5px solid var(--cm-line);
}
.rd-page thead th.rd-text-right { text-align: right; }
.rd-page tbody td { padding: 12px 8px; border-bottom: 1px solid var(--cm-line); }
.rd-page tbody tr:last-child td { border-bottom: none; }
.rd-page tbody tr.rd-ineligible { opacity: 0.55; }

.rd-member-cell { display: flex; align-items: center; gap: 10px; }
.rd-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--cm-danger); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: 12px; flex: none;
}
.rd-member-name { margin: 0; font-weight: 600; font-size: 13.5px; }
.rd-member-id { margin: 0; font-size: 11.5px; color: var(--cm-ink-soft); }

.rd-eligible-badge {
    display: inline-block; font-size: 11.5px; font-weight: 600;
    padding: 3px 10px; border-radius: 20px;
    background: var(--cm-success-soft); color: var(--cm-success);
}
.rd-ineligible-label { font-size: 12.5px; color: var(--cm-ink-soft); }

.rd-btn-link {
    width: auto !important;
    background: var(--cm-card);
    border: 1px solid var(--cm-line);
    border-radius: 20px;
    padding: 4px 10px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    color: var(--cm-forest);
    cursor: pointer;
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
}
.rd-btn-link:hover { border-color: var(--cm-forest); }
.rd-btn-link:disabled { opacity: 0.5; cursor: not-allowed; }

/* MODAL */
.rd-modal {
    display: none; position: fixed; inset: 0;
    background: rgba(43, 58, 42, 0.45);
    align-items: center; justify-content: center;
    z-index: 1000; padding: 20px;
}
.rd-modal.rd-open { display: flex; }
.rd-modal-card {
    background: var(--cm-card);
    border-radius: 14px;
    box-shadow: var(--cm-shadow);
    width: 100%; max-width: 420px; max-height: 90vh;
    overflow-y: auto;
    padding: 22px 24px 24px;
}
.rd-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.rd-modal-header h2 { font-size: 18px; }
.rd-icon-btn {
    width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
    background: transparent; border: none; color: var(--cm-ink-soft); cursor: pointer; border-radius: 6px;
}
.rd-icon-btn:hover { background: var(--cm-paper); color: var(--cm-ink); }

.rd-modal-card label {
    display: block; font-size: 12.5px; font-weight: 600; color: var(--cm-ink-soft); margin: 12px 0 5px;
}
.rd-modal-card label:first-of-type { margin-top: 0; }
.rd-modal-card input, .rd-modal-card select {
    width: 100%; font-family: inherit; font-size: 14px; padding: 9px 11px;
    border: 1.5px solid var(--cm-line); border-radius: 8px; background: var(--cm-paper); color: var(--cm-ink);
}
.rd-modal-card input:focus, .rd-modal-card select:focus { outline: none; border-color: var(--cm-forest); background: #fff; }

.rd-btn-primary {
    width: 100%; margin-top: 16px; padding: 11px; border-radius: var(--cm-radius);
    background: var(--cm-forest); border: 1.5px solid var(--cm-forest); color: #fff;
    font-weight: 600; font-size: 13.5px; cursor: pointer;
}
.rd-btn-primary:hover { background: var(--cm-forest-dark); }

.rd-error-box, .rd-success-box {
    display: none; font-size: 13px; padding: 9px 12px; border-radius: 8px; margin-bottom: 12px;
}
.rd-error-box.rd-show { display: block; background: var(--cm-danger-soft); color: var(--cm-danger); }
.rd-success-box.rd-show { display: block; background: var(--cm-success-soft); color: var(--cm-success); }

@media (max-width: 560px) {
    .rd-modal { padding: 12px; }
    .rd-modal-card { max-width: 100%; max-height: 92vh; padding: 20px 18px 20px; }
}
</style>

<div class="rd-page">

    <div class="rd-header">
        <h1>Resource distribution</h1>
        <p class="rd-muted">DA resources go to members with an active crop planting only</p>
    </div>

    <div class="rd-section">
        <div class="rd-section-header">
            <h2>Members</h2>
        </div>
        <div class="rd-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Crop planted</th>
                        <th class="rd-text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr><td colspan="3" class="rd-muted">No members found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($members as $m): ?>
                        <?php $eligible = !empty($m['eligible_crops']); ?>
                        <tr class="<?php echo $eligible ? '' : 'rd-ineligible'; ?>">
                            <td>
                                <div class="rd-member-cell">
                                    <div class="rd-avatar"><?php echo htmlspecialchars(rmInitials($m['last_name'], $m['first_name'])); ?></div>
                                    <div>
                                        <p class="rd-member-name"><?php echo htmlspecialchars(trim($m['last_name'] . ', ' . $m['first_name'])); ?></p>
                                        <p class="rd-member-id"><?php echo htmlspecialchars($m['membership_id']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($eligible): ?>
                                    <span class="rd-eligible-badge"><?php echo htmlspecialchars($m['eligible_crops']); ?></span>
                                <?php else: ?>
                                    <span class="rd-ineligible-label">No crop planting</span>
                                <?php endif; ?>
                            </td>
                            <td class="rd-text-right">
                                <?php if ($eligible): ?>
                                    <button class="rd-btn-link rd-distribute-btn"
                                            data-id="<?php echo $m['id']; ?>"
                                            data-name="<?php echo htmlspecialchars(trim($m['last_name'] . ', ' . $m['first_name'])); ?>"
                                            type="button">
                                        <span class="material-icons">inventory_2</span> Distribute
                                    </button>
                                <?php else: ?>
                                    <span class="rd-ineligible-label">Not eligible</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="rd-section">
        <div class="rd-section-header">
            <h2>Distribution history</h2>
        </div>
        <div class="rd-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Resource</th>
                        <th>Quantity</th>
                        <th>Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="5" class="rd-muted">No distributions yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(trim($h['last_name'] . ', ' . $h['first_name'])); ?></td>
                            <td><?php echo htmlspecialchars($h['resource_name']); ?></td>
                            <td><?php echo htmlspecialchars($h['quantity']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($h['created_at'])); ?></td>
                            <td><?php echo $h['notes'] ? htmlspecialchars($h['notes']) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- DISTRIBUTE MODAL -->
<div class="rd-modal" id="distribute-modal-wrap">
    <div class="rd-modal-card">
        <div class="rd-modal-header">
            <h2>Distribute resource</h2>
            <button class="rd-icon-btn" id="close-distribute" type="button" aria-label="Close">
                <span class="material-icons">close</span>
            </button>
        </div>

        <p class="rd-muted" id="distribute-member-label" style="margin: 0 0 14px;"></p>

        <div id="distribute-error" class="rd-error-box"></div>

        <form id="distribute-form">
            <input type="hidden" name="member_id" id="distribute-member-id">

            <label>Resource</label>
            <input type="text" name="resource_name" placeholder="e.g. Fertilizer, Seeds, Farm tools" required>

            <label>Quantity</label>
            <input type="text" name="quantity" placeholder="e.g. 50kg, 10 pcs" required>

            <label>Notes (optional)</label>
            <input type="text" name="notes" placeholder="e.g. From DA Q3 allocation">

            <button type="submit" class="rd-btn-primary">Confirm distribution</button>
        </form>
    </div>
</div>

<script>
    window.RESOURCES_AJAX_BASE = "<?php echo BASE_URL; ?>/app/manager/resources/ajax/";
</script>
<script src="<?php echo BASE_URL; ?>/app/manager/resources/resources.js"></script>

<?php renderFooter(); ?>