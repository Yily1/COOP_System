<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireRole('manager');

$distributions   = getAllDistributions($pdo);
$eligibleMembers = getEligibleMembersForDistribution($pdo);

renderHeader('Resource Distribution');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --rd-ink: #2B3A2A;
    --rd-ink-soft: #5B6B57;
    --rd-paper: #FAF7EF;
    --rd-card: #FFFFFF;
    --rd-line: #E4DCC8;
    --rd-forest: #33502F;
    --rd-forest-dark: #223A20;
    --rd-danger: #B54A3C;
    --rd-success: #3E7A4B;
    --rd-success-soft: #E1EFDE;
    --rd-radius: 10px;
    --rd-shadow: 0 12px 28px rgba(43, 58, 42, 0.14);
}

.rd-page, .rd-page *, .rd-modal, .rd-modal * { box-sizing: border-box; }

.rd-page {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--rd-ink);
    width: 100%;
    max-width: 100%;
    padding: 0 0 40px;
}

.rd-page h1, .rd-page h2 {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-weight: 600;
    color: #2c2c2a;
    margin: 0;
}

.rd-muted { color: var(--rd-ink-soft); font-size: 14px; margin: 4px 0 0; }
.rd-text-center { text-align: center; }
.rd-text-right { text-align: right; }

/* HEADER */
.rd-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--rd-line);
}
.rd-header h1 { font-size: 26px; }
.rd-header > div:first-child { flex: 1; min-width: 0; }

/* BUTTONS */
.rd-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-family: inherit;
    font-size: 13.5px;
    font-weight: 600;
    padding: 9px 16px;
    border-radius: var(--rd-radius);
    border: 1.5px solid var(--rd-forest);
    background: var(--rd-forest);
    color: #fff;
    cursor: pointer;
    transition: background 0.12s ease;
}
.rd-btn .material-icons { font-size: 17px; }
.rd-btn:hover { background: var(--rd-forest-dark); }
.rd-btn-full { width: 100%; justify-content: center; padding: 11px; }

/* SECTION */
.rd-section {
    background: var(--rd-card);
    border: 1px solid var(--rd-line);
    border-radius: var(--rd-radius);
    padding: 22px 22px 8px;
}
.rd-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}
.rd-section-header h2 { font-size: 19px; }

/* TABLE */
.rd-table-wrap { overflow-x: auto; }
.rd-page table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.rd-page thead th {
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--rd-ink-soft);
    padding: 10px 8px;
    border-bottom: 1.5px solid var(--rd-line);
}
.rd-page thead th.rd-text-right { text-align: right; }
.rd-page tbody td { padding: 12px 8px; border-bottom: 1px solid var(--rd-line); }
.rd-page tbody tr:last-child td { border-bottom: none; }

/* MEMBER CELL */
.rd-member-cell { display: flex; align-items: center; gap: 10px; }
.rd-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--rd-danger);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 12px;
    flex: none;
}
.rd-member-name { margin: 0; font-weight: 600; font-size: 13.5px; color: var(--rd-ink); }
.rd-member-id { margin: 0; font-size: 11.5px; color: var(--rd-ink-soft); }

/* STATUS BADGE */
.rd-status-badge {
    display: inline-block;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
}
.rd-status-badge.rd-released { background: var(--rd-success-soft); color: var(--rd-success); }
.rd-status-badge.rd-not-released { background: #F6E1DC; color: var(--rd-danger); }

/* CONFIRM / DECLINE ACTIONS */
.rd-action-btn {
    width: auto !important;
    background: var(--rd-card);
    border: 1px solid var(--rd-line);
    border-radius: 20px;
    padding: 4px 10px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    color: var(--rd-forest);
    cursor: pointer;
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
    transition: border-color 0.12s ease;
}
.rd-action-btn .material-icons { font-size: 14px; }
.rd-action-btn:hover { border-color: var(--rd-forest); }
.rd-action-btn-danger { color: var(--rd-danger); }
.rd-action-btn-danger:hover { border-color: var(--rd-danger); }

/* MODAL */
.rd-modal {
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
.rd-modal.rd-open { display: flex; }
.rd-modal-card {
    background: var(--rd-card);
    border-radius: 14px;
    box-shadow: var(--rd-shadow);
    width: 100%;
    max-width: 420px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 22px 24px 24px;
}
.rd-modal-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.rd-modal-header h2 { flex: 1; min-width: 0; font-size: 18px; }
.rd-icon-btn {
    flex: 0 0 32px; width: 32px; height: 32px;
    display: inline-flex; align-items: center; justify-content: center;
    background: transparent; border: none; color: var(--rd-ink-soft);
    cursor: pointer; border-radius: 6px;
}
.rd-icon-btn:hover { background: var(--rd-paper); color: var(--rd-ink); }
.rd-icon-btn svg { width: 20px; height: 20px; display: block; }

.rd-modal-card label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--rd-ink-soft);
    margin: 12px 0 5px;
}
.rd-modal-card label:first-of-type { margin-top: 0; }
.rd-modal-card input,
.rd-modal-card select,
.rd-modal-card textarea {
    width: 100%;
    font-family: inherit;
    font-size: 14px;
    padding: 9px 11px;
    border: 1.5px solid var(--rd-line);
    border-radius: 8px;
    background: var(--rd-paper);
    color: var(--rd-ink);
    resize: vertical;
}
.rd-modal-card input:focus,
.rd-modal-card select:focus,
.rd-modal-card textarea:focus { outline: none; border-color: var(--rd-forest); background: #fff; }

.rd-error-box, .rd-success-box {
    display: none;
    font-size: 13px;
    padding: 9px 12px;
    border-radius: 8px;
    margin-bottom: 12px;
}
.rd-error-box.rd-show { display: block; background: #F6E1DC; color: var(--rd-danger); }
.rd-success-box.rd-show { display: block; background: var(--rd-success-soft); color: var(--rd-success); }

@media (max-width: 560px) {
    .rd-header { flex-direction: column; gap: 12px; }
    .rd-header > div:first-child { width: 100%; }
    .rd-header .rd-btn { width: 100%; }
    .rd-section-header { flex-direction: column; align-items: flex-start; }
    .rd-modal { padding: 12px; }
    .rd-modal-card { max-width: 100%; max-height: 92vh; padding: 20px 18px 20px; }
}
</style>

<div class="rd-page">

    <div class="rd-header">
        <div>
            <h1>Resource Distribution</h1>
            <p class="rd-muted">DA resources go to members with an active crop planting only</p>
        </div>
        <button class="rd-btn" id="add-distribution-btn" type="button">
            <span class="material-icons">add</span> Add distribution
        </button>
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
                        <th>Status</th>
                        <th class="rd-text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($distributions)): ?>
                        <tr><td colspan="7" class="rd-muted rd-text-center">No distributions recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($distributions as $d): ?>
                        <?php
                            $memberName = trim($d['last_name'] . ', ' . $d['first_name']);
                            $initials = strtoupper(mb_substr($d['last_name'], 0, 1) . mb_substr($d['first_name'], 0, 1));
                            $isReleased = $d['status'] === 'released';
                        ?>
                        <tr>
                            <td>
                                <div class="rd-member-cell">
                                    <div class="rd-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                    <div>
                                        <p class="rd-member-name"><?php echo htmlspecialchars($memberName); ?></p>
                                        <p class="rd-member-id"><?php echo htmlspecialchars($d['membership_id']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($d['resource_name']); ?></td>
                            <td><?php echo htmlspecialchars($d['quantity']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($d['distribution_date'])); ?></td>
                            <td><?php echo $d['notes'] ? htmlspecialchars($d['notes']) : '—'; ?></td>
                            <td><?php echo distributionStatusBadge($d['status']); ?></td>
                            <td class="rd-text-right">
                                <?php if (!$isReleased): ?>
                                    <button class="rd-action-btn rd-confirm-btn" data-id="<?php echo $d['id']; ?>" type="button">
                                        <span class="material-icons">check</span> Confirm
                                    </button>
                                    <button class="rd-action-btn rd-action-btn-danger rd-decline-btn" data-id="<?php echo $d['id']; ?>" type="button">
                                        <span class="material-icons">close</span> Decline
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>


<div class="rd-modal" id="add-distribution-modal-wrap">
    <div class="rd-modal-card">
        <div class="rd-modal-header">
            <h2>Add distribution</h2>
            <button class="rd-icon-btn" id="close-add-distribution" type="button" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div id="add-distribution-error" class="rd-error-box"></div>

        <form id="add-distribution-form">
            <label>Member</label>
            <select name="member_id" required>
                <option value="">Select a member</option>
                <?php foreach ($eligibleMembers as $m): ?>
                    <?php $mName = trim($m['last_name'] . ', ' . $m['first_name']); ?>
                    <option value="<?php echo $m['id']; ?>">
                        <?php echo htmlspecialchars($mName); ?> — <?php echo htmlspecialchars($m['membership_id']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($eligibleMembers)): ?>
                <p class="rd-muted" style="margin-top:4px;">No members currently have an active crop planting.</p>
            <?php endif; ?>

            <label>Resource</label>
            <input type="text" name="resource_name" placeholder="e.g. Fertilizer, Seeds" required>

            <label>Quantity</label>
            <input type="text" name="quantity" placeholder="e.g. 5 sacks, 2 kg" required>

            <label>Distribution date</label>
            <input type="date" name="distribution_date" required>

            <label>Notes</label>
            <textarea name="notes" rows="2" placeholder="Optional"></textarea>

            <button type="submit" class="rd-btn rd-btn-full" style="margin-top:16px;">Save distribution</button>
        </form>
    </div>
</div>


<script>
    window.RESOURCES_AJAX_BASE = "<?php echo BASE_URL; ?>/app/manager/resources/ajax/";
</script>

<script src="<?php echo BASE_URL; ?>/app/manager/resources/resources.js"></script>

<?php renderFooter(); ?>