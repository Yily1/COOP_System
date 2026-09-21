<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

$stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$memberId = $stmt->fetchColumn();

if (!$memberId) {
    renderHeader('Loans');
    echo '<p style="color:#666;">No member profile is linked to your account yet.</p>';
    renderFooter();
    exit;
}

$totalInvestment = getMemberInvestment($pdo, $memberId);
$loanableAmount  = getLoanableAmount($pdo, $memberId);
$loanBalance     = getMemberLoanBalance($pdo, $memberId);
$availableCredit = getAvailableCredit($pdo, $memberId);
$isLoanEligible  = $totalInvestment >= LOAN_ELIGIBILITY_THRESHOLD;

$myLoans = getMemberLoans($pdo, $memberId);

renderHeader('Loans');
?>

<style>
.svc-page-head {
    display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    margin-bottom: 20px; padding-bottom: 18px; border-bottom: 2px solid #E4DCC8;
}
.svc-page-head h1 { font-size: 24px; color: #2c2c2a; margin: 0 0 4px; font-weight: 600; }
.svc-page-head p { font-size: 13px; color: #6b7280; margin: 0; }

.svc-section-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.svc-section-head h2 { font-size: 17px; margin: 0; color: #2c2c2a; }

.svc-btn {
    display: inline-flex !important; align-items: center; gap: 6px; width: auto !important; flex: 0 0 auto !important;
    border-radius: 6px !important; padding: 9px 16px !important; font-size: 13px !important; font-weight: 600 !important;
    cursor: pointer !important; border: none !important; text-transform: none !important; letter-spacing: normal !important;
}
.svc-btn-primary { background: #3B6D11 !important; color: #fff !important; }
.svc-btn-primary:hover { background: #2e5a0d !important; }
.svc-btn-primary:disabled { background: #cbd5c8 !important; cursor: not-allowed !important; }

.svc-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 24px; }
.svc-stat-card { border-radius: 10px; padding: 16px 18px; color: #fff; }
.svc-stat-card p.svc-stat-label { margin: 0 0 6px; font-size: 12.5px; opacity: 0.9; }
.svc-stat-card p.svc-stat-value { margin: 0; font-size: 22px; font-weight: 600; }
.svc-stat-investment { background: #33502F; }
.svc-stat-loanable { background: #274B81; }
.svc-stat-balance { background: #C1892B; }
.svc-stat-credit { background: #3E7A4B; }

.svc-notice { background: #fdecea; color: #c62828; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 18px; }

.svc-table-wrap { overflow-x: auto; border: 1px solid #e2e0d5; border-radius: 10px; margin-bottom: 8px; }
.svc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.svc-table th { text-align: left; padding: 12px 14px; background: #fff; border-bottom: 1px solid #e2e0d5; color: #6b7280; font-weight: 600; }
.svc-table td { padding: 12px 14px; border-bottom: 1px solid #f0f0ea; color: #2c2c2a; vertical-align: top; }
.svc-table tbody tr:last-child td { border-bottom: none; }
.svc-empty { padding: 20px; text-align: center; color: #6b7280; font-size: 13px; }

#svcLoanModalBackdrop {
    display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5);
    z-index: 100; align-items: center; justify-content: center; padding: 16px;
}
.svc-modal-box { background: #fff; border-radius: 8px; padding: 24px; width: 100%; max-width: 420px; box-sizing: border-box; position: relative; max-height: 90vh; overflow-y: auto; }
.svc-form-label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
.svc-form-input { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 14px; font-size: 14px; font-family: inherit; }
.svc-modal-errors, .svc-modal-success { display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; }
.svc-modal-errors { background: #f8d7da; color: #721c24; }
.svc-modal-success { background: #d4edda; color: #155724; }
.svc-close-btn {
    position: absolute !important; top: 16px !important; right: 16px !important; background: none !important; border: none !important;
    font-size: 20px !important; cursor: pointer; padding: 4px !important; width: 28px !important; height: 28px !important;
    color: #333 !important; line-height: 1 !important;
}

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
        <p>Check your loan eligibility and track your requests.</p>
    </div>
</div>

<div class="svc-stat-grid">
    <div class="svc-stat-card svc-stat-investment">
        <p class="svc-stat-label">Total Investment</p>
        <p class="svc-stat-value">₱<?php echo number_format($totalInvestment, 2); ?></p>
    </div>
    <div class="svc-stat-card svc-stat-loanable">
        <p class="svc-stat-label">Loanable Amount</p>
        <p class="svc-stat-value">₱<?php echo number_format($loanableAmount, 2); ?></p>
    </div>
    <div class="svc-stat-card svc-stat-balance">
        <p class="svc-stat-label">Current Loan Balance</p>
        <p class="svc-stat-value">₱<?php echo number_format($loanBalance, 2); ?></p>
    </div>
    <div class="svc-stat-card svc-stat-credit">
        <p class="svc-stat-label">Available Credit</p>
        <p class="svc-stat-value">₱<?php echo number_format($availableCredit, 2); ?></p>
    </div>
</div>

<?php if (!$isLoanEligible): ?>
    <div class="svc-notice">
        You need at least ₱<?php echo number_format(LOAN_ELIGIBILITY_THRESHOLD, 2); ?> in confirmed investment to request a loan.
        You currently have ₱<?php echo number_format($totalInvestment, 2); ?>.
    </div>
<?php endif; ?>

<div class="svc-section-head">
    <h2>My Loans</h2>
    <button type="button" class="svc-btn svc-btn-primary" id="openLoanBtn" <?php echo (!$isLoanEligible || $availableCredit <= 0) ? 'disabled' : ''; ?>>
        + Request a Loan
    </button>
</div>

<?php if (empty($myLoans)): ?>
    <p class="svc-empty">You haven't requested any loans yet.</p>
<?php else: ?>
    <div class="svc-table-wrap">
        <table class="svc-table">
            <thead>
                <tr><th>Amount</th><th>Total Due</th><th>Purpose</th><th>Requested</th><th>Due Date</th><th>Status</th><th>Manager Note</th></tr>
            </thead>
            <tbody>
                <?php foreach ($myLoans as $loan): ?>
                    <?php $totalDue = $loan['total_due'] ?? $loan['amount']; ?>
                    <tr>
                        <td data-label="Amount" style="font-weight:600;">₱<?php echo number_format($loan['amount'], 2); ?></td>
                        <td data-label="Total Due">₱<?php echo number_format($totalDue, 2); ?></td>
                        <td data-label="Purpose"><?php echo htmlspecialchars($loan['purpose'] ?: '—'); ?></td>
                        <td data-label="Requested"><?php echo date('M j, Y', strtotime($loan['created_at'])); ?></td>
                        <td data-label="Due Date"><?php echo !empty($loan['due_date']) ? date('M j, Y', strtotime($loan['due_date'])) : '—'; ?></td>
                        <td data-label="Status">
                            <?php if ($loan['status'] === 'released' && $loanBalance <= 0): ?>
                                <span style="padding:2px 10px;border-radius:4px;font-size:12px;background:#d4edda;color:#155724;white-space:nowrap;">Paid Off</span>
                            <?php elseif (isLoanOverdue($loan, $loanBalance)): ?>
                                <span style="padding:2px 10px;border-radius:4px;font-size:12px;background:#fdecea;color:#c62828;white-space:nowrap;">Overdue</span>
                            <?php else: ?>
                                <?php echo loanStatusBadge($loan['status']); ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Manager Note"><?php echo htmlspecialchars($loan['manager_note'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ============================================================
     MODAL - Request a Loan
     ============================================================ -->
<div id="svcLoanModalBackdrop">
    <div class="svc-modal-box">
        <h2 style="margin: 0 0 16px 0; font-size: 18px; text-align: center;">Request a Loan</h2>
        <button type="button" id="closeLoanModalBtn" class="svc-close-btn">&times;</button>
        <div id="loanModalErrors" class="svc-modal-errors"></div>
        <div id="loanModalSuccess" class="svc-modal-success">Loan request submitted. Waiting for manager review.</div>

        <form id="loanForm">
            <p style="font-size:13px; color:#6b7280; margin:0 0 4px;">Available credit: <strong>₱<?php echo number_format($availableCredit, 2); ?></strong></p>
            <p style="font-size:12px; color:#9ca3af; margin:0 0 14px;">Interest: <?php echo number_format(LOAN_INTEREST_RATE_MONTHLY, 2); ?>% per month, plus a due date, will be set when the manager releases your loan.</p>

            <label class="svc-form-label">Amount (₱)</label>
            <input type="number" name="amount" class="svc-form-input" step="0.01" min="1" max="<?php echo $availableCredit; ?>" required>

            <label class="svc-form-label">Purpose</label>
            <textarea name="purpose" rows="3" class="svc-form-input" placeholder="e.g. Farm inputs for next planting season" required></textarea>

            <button type="submit" class="svc-btn svc-btn-primary" style="width:100%; justify-content:center;">Submit Request</button>
        </form>
    </div>
</div>

<script>
(function() {
    const AJAX_BASE = '<?php echo BASE_URL; ?>/app/manager/loans/ajax/';

    const backdrop = document.getElementById('svcLoanModalBackdrop');
    const form = document.getElementById('loanForm');
    const errorsEl = document.getElementById('loanModalErrors');
    const successEl = document.getElementById('loanModalSuccess');
    const openBtn = document.getElementById('openLoanBtn');

    if (openBtn) {
        openBtn.addEventListener('click', function() {
            form.reset();
            errorsEl.style.display = 'none';
            successEl.style.display = 'none';
            backdrop.style.display = 'flex';
        });
    }
    document.getElementById('closeLoanModalBtn').addEventListener('click', function() { backdrop.style.display = 'none'; });
    backdrop.addEventListener('click', function(e) { if (e.target === backdrop) backdrop.style.display = 'none'; });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorsEl.style.display = 'none';
        try {
            const formData = new FormData(form);
            const res = await fetch(AJAX_BASE + 'submit-loan-request.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                successEl.style.display = 'block';
                setTimeout(() => { window.location.reload(); }, 1000);
            } else {
                errorsEl.textContent = data.message || 'Could not submit loan request.';
                errorsEl.style.display = 'block';
            }
        } catch (err) {
            errorsEl.textContent = 'Could not connect to server.';
            errorsEl.style.display = 'block';
        }
    });
})();
</script>

<?php renderFooter(); ?>cre