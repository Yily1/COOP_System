<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';

requireRole('manager');

$loans = getAllLoans($pdo);
$pendingLoanCount = count(array_filter($loans, fn($l) => $l['status'] === 'pending'));
$stats = getLoanPortfolioStats($pdo);

renderHeader('Loans');
?>

<style>
.svc-page-head {
    display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    margin-bottom: 20px; padding-bottom: 18px; border-bottom: 2px solid #E4DCC8;
}
.svc-page-head h1 { font-size: 24px; color: #2c2c2a; margin: 0 0 4px; font-weight: 600; }
.svc-page-head p { font-size: 13px; color: #6b7280; margin: 0; }
.svc-page-head .svc-badge { background: #fdecea; color: #c62828; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }

.svc-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 24px; }
.svc-stat-card { border-radius: 10px; padding: 16px 18px; color: #fff; }
.svc-stat-card p.svc-stat-label { margin: 0 0 6px; font-size: 12.5px; opacity: 0.9; }
.svc-stat-card p.svc-stat-value { margin: 0; font-size: 22px; font-weight: 600; }
.svc-stat-pending { background: #C1892B; }
.svc-stat-awaiting { background: #274B81; }
.svc-stat-outstanding { background: #B54A3C; }
.svc-stat-repaid { background: #33502F; }

.svc-table-wrap { overflow-x: auto; border: 1px solid #e2e0d5; border-radius: 10px; margin-bottom: 8px; }
.svc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.svc-table th { text-align: left; padding: 12px 14px; background: #fff; border-bottom: 1px solid #e2e0d5; color: #6b7280; font-weight: 600; }
.svc-table td { padding: 12px 14px; border-bottom: 1px solid #f0f0ea; color: #2c2c2a; vertical-align: top; }
.svc-table tbody tr:last-child td { border-bottom: none; }
.svc-empty { padding: 20px; text-align: center; color: #6b7280; font-size: 13px; }

.svc-action-cell { display: flex; gap: 6px; flex-wrap: nowrap; }
.svc-mini-btn {
    display: inline-flex !important; align-items: center; justify-content: center; gap: 4px;
    background: #fff !important; border: 1px solid #d8d2c4 !important; border-radius: 6px !important;
    padding: 5px 10px !important; font-size: 12px !important; font-weight: 600 !important; cursor: pointer !important;
    width: auto !important; white-space: nowrap !important;
}
.svc-mini-btn-approve { color: #2e5a0d !important; }
.svc-mini-btn-reject { color: #c62828 !important; }
.svc-mini-btn-release { color: #004085 !important; }

@media (max-width: 700px) {
    .svc-table thead { display: none; }
    .svc-table, .svc-table tbody, .svc-table tr, .svc-table td { display: block; width: 100%; }
    .svc-table tr { border-bottom: 1px solid #f0f0ea; padding: 10px 0; }
    .svc-table tbody tr:last-child { border-bottom: none; }
    .svc-table td { border-bottom: none !important; padding: 4px 14px; }
    .svc-table td::before {
        content: attr(data-label); display: block; font-size: 11px; font-weight: 600;
        color: #6b7280; text-transform: uppercase; margin-bottom: 2px;
    }
}
</style>

<div class="svc-page-head">
    <div>
        <h1>Loans</h1>
        <p>Review, approve, and release member loan requests.</p>
    </div>
    <?php if ($pendingLoanCount): ?><span class="svc-badge"><?php echo $pendingLoanCount; ?> pending</span><?php endif; ?>
</div>

<div class="svc-stat-grid">
    <div class="svc-stat-card svc-stat-awaiting">
        <p class="svc-stat-label">Members with Active Loans</p>
        <p class="svc-stat-value"><?php echo $stats['active_members']; ?></p>
    </div>
    <div class="svc-stat-card svc-stat-outstanding">
        <p class="svc-stat-label">Total Amount Currently Released</p>
        <p class="svc-stat-value">₱<?php echo number_format($stats['total_outstanding'], 2); ?></p>
    </div>
    <div class="svc-stat-card svc-stat-pending">
        <p class="svc-stat-label">Pending Requests</p>
        <p class="svc-stat-value"><?php echo $stats['pending']; ?></p>
    </div>
    <div class="svc-stat-card svc-stat-repaid">
        <p class="svc-stat-label">Total Repaid</p>
        <p class="svc-stat-value">₱<?php echo number_format($stats['total_repaid'], 2); ?></p>
    </div>
</div>

<?php if (empty($loans)): ?>
    <p class="svc-empty">No loan requests yet.</p>
<?php else: ?>
    <div class="svc-table-wrap">
        <table class="svc-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Amount</th>
                    <th>Purpose</th>
                    <th>Loanable / Balance</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $loan): ?>
                    <?php
                        $loanable = getLoanableAmount($pdo, $loan['member_id']);
                        $balance = getMemberLoanBalance($pdo, $loan['member_id']);
                    ?>
                    <tr>
                        <td data-label="Member"><?php echo htmlspecialchars($loan['last_name'] . ', ' . $loan['first_name']); ?> <br><span style="color:#9ca3af;font-size:12px;"><?php echo htmlspecialchars($loan['membership_id']); ?></span></td>
                        <td data-label="Amount" style="font-weight:600;">₱<?php echo number_format($loan['amount'], 2); ?></td>
                        <td data-label="Purpose"><?php echo htmlspecialchars($loan['purpose'] ?: '—'); ?></td>
                        <td data-label="Loanable / Balance">₱<?php echo number_format($loanable, 2); ?> / ₱<?php echo number_format($balance, 2); ?></td>
                        <td data-label="Due Date">
                            <?php if (!empty($loan['due_date'])): ?>
                                <?php echo date('M j, Y', strtotime($loan['due_date'])); ?>
                                <?php if (isLoanOverdue($loan, $balance)): ?>
                                    <br><span style="padding:1px 8px;border-radius:4px;font-size:11px;background:#fdecea;color:#c62828;">Overdue</span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <?php if ($loan['status'] === 'released' && $balance <= 0): ?>
                                <span style="padding:2px 10px;border-radius:4px;font-size:12px;background:#d4edda;color:#155724;white-space:nowrap;">Paid Off</span>
                            <?php else: ?>
                                <?php echo loanStatusBadge($loan['status']); ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Requested"><?php echo date('M j, Y', strtotime($loan['created_at'])); ?></td>
                        <td data-label="Action">
                            <div class="svc-action-cell">
                                <?php if ($loan['status'] === 'pending'): ?>
                                    <button class="svc-mini-btn svc-mini-btn-approve loan-action-btn" data-id="<?php echo $loan['id']; ?>" data-action="approve" type="button">Approve</button>
                                    <button class="svc-mini-btn svc-mini-btn-reject loan-action-btn" data-id="<?php echo $loan['id']; ?>" data-action="reject" type="button">Reject</button>
                                <?php elseif ($loan['status'] === 'approved'): ?>
                                    <button class="svc-mini-btn svc-mini-btn-release loan-action-btn" data-id="<?php echo $loan['id']; ?>" data-action="release" type="button">Release</button>
                                <?php else: ?>
                                    <span style="color:#9ca3af;font-size:12px;">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
(function() {
    const AJAX_BASE = '<?php echo BASE_URL; ?>/app/manager/loans/ajax/';

    document.querySelectorAll('.loan-action-btn').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const id = btn.dataset.id;
            const action = btn.dataset.action;
            let termMonths = null;

            if (action === 'release') {
                const input = prompt('Loan term, in whole months (interest: <?php echo LOAN_INTEREST_RATE_MONTHLY; ?>% per month):', '1');
                if (input === null) return; // cancelled
                termMonths = parseInt(input, 10);
                if (!termMonths || termMonths < 1) {
                    alert('Enter a valid whole number of months (1 or more).');
                    return;
                }
            } else {
                if (!confirm('Are you sure you want to ' + action + ' this loan request?')) return;
            }

            const row = btn.closest('tr');
            row.querySelectorAll('button').forEach(b => b.disabled = true);

            try {
                const formData = new FormData();
                formData.append('loan_id', id);
                formData.append('action', action);
                if (termMonths !== null) formData.append('term_months', termMonths);
                const res = await fetch(AJAX_BASE + 'update-loan-status.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Could not update loan.');
                    row.querySelectorAll('button').forEach(b => b.disabled = false);
                }
            } catch (err) {
                alert('Could not connect to server.');
                row.querySelectorAll('button').forEach(b => b.disabled = false);
            }
        });
    });
})();
</script>

<?php renderFooter(); ?>