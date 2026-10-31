<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/functions.php';
require_once __DIR__ . '/../../../config/loan-interest.php';

requireRole('manager');

$loans = getAllLoans($pdo);
$pendingLoanCount = count(array_filter($loans, fn($l) => $l['status'] === 'pending'));

// Cards at the top: computed with the live interest (amount + 10% per month until paid)
$stats = loanPortfolioStatsLive($pdo);

// Members for the Add loan search bar
$memberStmt = $pdo->query("
    SELECT id, membership_id, first_name, last_name
    FROM members
    ORDER BY last_name, first_name
");
$members = $memberStmt->fetchAll(PDO::FETCH_ASSOC);

// One ledger per member, built only when needed
$ledgerCache = [];
function lnLedger(PDO $pdo, array &$cache, int $memberId): array {
    if (!isset($cache[$memberId])) {
        $cache[$memberId] = memberLoanLedger($pdo, $memberId);
    }
    return $cache[$memberId];
}

/**
 * "Dela Cruz" + "Juan" -> "DC". Falls back gracefully for blank names.
 */
function lnInitials($lastName, $firstName) {
    $l = trim((string) $lastName);
    $f = trim((string) $firstName);
    if ($l === '' && $f === '') {
        return '?';
    }
    return strtoupper(mb_substr($l, 0, 1) . mb_substr($f, 0, 1));
}

/**
 * Pill-style status badge (same look as the Crops page badges).
 * Pass $paidOff = true for a released loan with no balance left.
 */
function lnStatusBadge($status, $paidOff = false) {
    if ($status === 'released' && $paidOff) {
        return '<span class="ln-status-badge ln-paid">Paid off</span>';
    }
    $map = [
        'pending'  => ['label' => 'Pending',  'class' => 'ln-pending'],
        'approved' => ['label' => 'Approved', 'class' => 'ln-approved'],
        'released' => ['label' => 'Released', 'class' => 'ln-released'],
        'rejected' => ['label' => 'Rejected', 'class' => 'ln-rejected'],
    ];
    $m = $map[$status] ?? ['label' => ucfirst($status), 'class' => ''];
    return '<span class="ln-status-badge ' . $m['class'] . '">' . htmlspecialchars($m['label']) . '</span>';
}

renderHeader('Loans');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --ln-ink: #2B3A2A;
    --ln-ink-soft: #5B6B57;
    --ln-paper: #FAF7EF;
    --ln-card: #FFFFFF;
    --ln-line: #E4DCC8;
    --ln-forest: #33502F;
    --ln-forest-dark: #223A20;
    --ln-gold: #C1892B;
    --ln-gold-soft: #F4E4C1;
    --ln-danger: #B54A3C;
    --ln-danger-soft: #F6E1DC;
    --ln-success: #3E7A4B;
    --ln-success-soft: #E1EFDE;
    --ln-info: #3B6E91;
    --ln-info-soft: #DCEAF2;
    --ln-radius: 10px;
    --ln-shadow: 0 12px 28px rgba(43, 58, 42, 0.14);
}

.ln-page,
.ln-page *,
.ln-modal,
.ln-modal * {
    box-sizing: border-box;
}

.ln-page {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--ln-ink);
    width: 100%;
    max-width: 100%;
    padding: 0 0 40px;
    text-transform: none;
    letter-spacing: normal;
}

.ln-page h1,
.ln-page h2 {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-weight: 600;
    color: #2c2c2a;
    text-transform: none;
    letter-spacing: normal;
    margin: 0;
}

.ln-muted { color: var(--ln-ink-soft); font-size: 14px; margin: 4px 0 0; }
.ln-text-center { text-align: center; }
.ln-text-right { text-align: right; }

/* HEADER */
.ln-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: none;
}
.ln-header h1 { font-size: 26px; }
.ln-header > div:first-child { flex: 1; min-width: 0; }
.ln-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    flex: none;
}

.ln-pending-pill {
    background: var(--ln-danger-soft);
    color: var(--ln-danger);
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    white-space: nowrap;
}

/* BUTTONS */
.ln-btn {
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
    border-radius: var(--ln-radius);
    border: 1.5px solid var(--ln-forest);
    background: transparent;
    color: var(--ln-forest);
    cursor: pointer;
    box-shadow: none;
    transition: transform 0.12s ease, background 0.12s ease;
}
.ln-btn .material-icons { font-size: 17px; }
.ln-btn:hover { background: var(--ln-gold-soft); }
.ln-btn:active { transform: scale(0.97); }

.ln-btn-outline {
    border-color: var(--ln-line);
    color: var(--ln-ink);
    background: var(--ln-card);
}
.ln-btn-outline:hover { border-color: var(--ln-forest); }

.ln-btn-primary {
    background: var(--ln-forest);
    border-color: var(--ln-forest);
    color: #fff;
}
.ln-btn-primary:hover { background: var(--ln-forest-dark); }
.ln-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.ln-btn-full { width: 100%; justify-content: center; padding: 11px; }

.ln-btn-danger {
    background: var(--ln-danger);
    border-color: var(--ln-danger);
    color: #fff;
}
.ln-btn-danger:hover { background: #983c30; }

/* Small pill buttons in the Action column
   (!important because the site-wide style.css styles every <button>) */
.ln-btn-link {
    width: auto !important;
    background: var(--ln-card);
    border: 1px solid var(--ln-line);
    border-radius: 20px;
    box-shadow: none;
    padding: 4px 10px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    text-transform: none !important;
    letter-spacing: normal;
    color: var(--ln-forest);
    cursor: pointer;
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    transition: border-color 0.12s ease;
}
.ln-btn-link .material-icons { font-size: 14px; }
.ln-btn-link:hover { border-color: var(--ln-forest); text-decoration: none; }
.ln-btn-link:disabled { opacity: 0.5; cursor: not-allowed; }
.ln-btn-link-danger { color: var(--ln-danger); }
.ln-btn-link-danger:hover { border-color: var(--ln-danger); }
.ln-btn-link-info { color: var(--ln-info); }
.ln-btn-link-info:hover { border-color: var(--ln-info); }

/* SUMMARY CARDS */
.ln-summary-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 28px;
}
.ln-metric-card { border: none; border-radius: var(--ln-radius); box-shadow: none; padding: 16px 18px; flex: 0 0 160px; width: 160px; }
.ln-metric-label { font-size: 12.5px; text-transform: none; letter-spacing: normal; margin: 0 0 6px; }
.ln-metric-value { font-family: 'Fraunces', serif; font-size: 30px; font-weight: 600; margin: 0; }
.ln-metric-value.ln-value-sm { font-size: 22px; padding-top: 5px; }

.ln-metric-sub { font-size: 11.5px; margin: 4px 0 0; }

.ln-metric-card.ln-tone-success { background: var(--ln-success-soft); }
.ln-metric-card.ln-tone-success .ln-metric-label,
.ln-metric-card.ln-tone-success .ln-metric-value,
.ln-metric-card.ln-tone-success .ln-metric-sub { color: var(--ln-success); }

.ln-metric-card.ln-tone-forest { background: var(--ln-forest); }
.ln-metric-card.ln-tone-forest .ln-metric-label { color: #EFE9D8; }
.ln-metric-card.ln-tone-forest .ln-metric-value { color: #fff; }

.ln-metric-card.ln-tone-info { background: var(--ln-info-soft); }
.ln-metric-card.ln-tone-info .ln-metric-label,
.ln-metric-card.ln-tone-info .ln-metric-value { color: var(--ln-info); }

.ln-metric-card.ln-tone-gold { background: var(--ln-gold-soft); }
.ln-metric-card.ln-tone-gold .ln-metric-label,
.ln-metric-card.ln-tone-gold .ln-metric-value { color: var(--ln-gold); }

.ln-metric-card.ln-tone-danger { background: var(--ln-danger-soft); }
.ln-metric-card.ln-tone-danger .ln-metric-label,
.ln-metric-card.ln-tone-danger .ln-metric-value { color: var(--ln-danger); }

/* SECTION */
.ln-section {
    background: var(--ln-card);
    border: 1px solid var(--ln-line);
    border-radius: var(--ln-radius);
    box-shadow: none;
    padding: 22px 22px 8px;
}
.ln-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}
.ln-section-header h2 { font-size: 19px; }

/* TABLE */
.ln-table-wrap { overflow-x: auto; }
.ln-page table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.ln-page thead th {
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    color: var(--ln-ink-soft);
    padding: 10px 8px;
    border-bottom: 1.5px solid var(--ln-line);
    white-space: nowrap;
}
.ln-page thead th.ln-text-right { text-align: right; }
.ln-page tbody td { padding: 12px 8px; border-bottom: 1px solid var(--ln-line); vertical-align: middle; }
.ln-page tbody tr:last-child td { border-bottom: none; }
.ln-amount { font-weight: 600; }
.ln-dash { color: var(--ln-ink-soft); }
.ln-actions { display: inline-flex; gap: 6px; justify-content: flex-end; }

/* Amount due now (amount + interest) */
.ln-due-main { font-weight: 600; margin: 0; }
.ln-due-sub { font-size: 11.5px; color: var(--ln-ink-soft); margin: 2px 0 0; white-space: nowrap; }

/* MEMBER CELL */
.ln-member-cell { display: flex; align-items: center; gap: 10px; }
.ln-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--ln-danger);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 12px;
    flex: none;
}
.ln-member-name { margin: 0; font-weight: 600; font-size: 13.5px; color: var(--ln-ink); white-space: nowrap; }
.ln-member-id { margin: 0; font-size: 11.5px; color: var(--ln-ink-soft); }

/* STATUS BADGES */
.ln-status-badge {
    display: inline-block;
    font-size: 11.5px;
    font-weight: 600;
    text-transform: none;
    padding: 3px 10px;
    border-radius: 20px;
    white-space: nowrap;
}
.ln-status-badge.ln-pending  { background: var(--ln-gold-soft); color: var(--ln-gold); }
.ln-status-badge.ln-approved { background: var(--ln-info-soft); color: var(--ln-info); }
.ln-status-badge.ln-released { background: var(--ln-gold-soft); color: var(--ln-gold); }
.ln-status-badge.ln-paid     { background: var(--ln-success-soft); color: var(--ln-success); }
.ln-status-badge.ln-rejected { background: var(--ln-danger-soft); color: var(--ln-danger); }
.ln-status-badge.ln-overdue  { background: var(--ln-danger-soft); color: var(--ln-danger); margin-top: 4px; }

/* MODAL (confirm / release / add loan) */
.ln-modal {
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
.ln-modal.ln-open { display: flex; }

.ln-modal-card {
    background: var(--ln-card);
    border-radius: 14px;
    box-shadow: var(--ln-shadow);
    width: 100%;
    max-width: 380px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 22px 24px 24px;
    animation: ln-pop-in 0.16s ease;
}
.ln-modal-card.ln-modal-wide { max-width: 440px; }

@keyframes ln-pop-in {
    from { opacity: 0; transform: translateY(8px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.ln-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 16px;
    margin-bottom: 14px;
}
.ln-modal-header h2 { flex: 1; min-width: 0; margin: 0; font-size: 18px; line-height: 1.2; }

.ln-icon-btn {
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
    color: var(--ln-ink-soft);
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
}
.ln-icon-btn svg { width: 20px; height: 20px; display: block; }
.ln-icon-btn:hover { background: var(--ln-paper); color: var(--ln-ink); }
.ln-icon-btn:active { transform: scale(0.94); }

.ln-confirm-text {
    font-size: 14px;
    line-height: 1.5;
    color: var(--ln-ink-soft);
    margin: 0 0 18px;
}

/* Interest note shown when releasing a loan */
.ln-interest-note {
    display: none;
    background: var(--ln-success-soft);
    color: var(--ln-success);
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 12.5px;
    line-height: 1.55;
    margin: 0 0 18px;
}
.ln-interest-note.ln-show { display: block; }
.ln-interest-note strong { font-weight: 600; }

.ln-error-box {
    display: none;
    font-size: 13px;
    padding: 9px 12px;
    border-radius: 8px;
    margin-bottom: 14px;
    background: var(--ln-danger-soft);
    color: var(--ln-danger);
}
.ln-error-box.ln-show { display: block; }

.ln-confirm-actions { display: flex; justify-content: flex-end; gap: 10px; }

/* ADD LOAN FORM */
.ln-modal-card label.ln-label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    color: var(--ln-ink-soft);
    margin: 12px 0 5px;
}
.ln-modal-card label.ln-label:first-of-type { margin-top: 0; }

.ln-modal-card .ln-input {
    width: 100%;
    font-family: inherit;
    font-size: 14px;
    padding: 9px 11px;
    border: 1.5px solid var(--ln-line);
    border-radius: 8px;
    background: var(--ln-paper);
    color: var(--ln-ink);
    box-shadow: none;
    box-sizing: border-box;
}
.ln-modal-card .ln-input:focus {
    outline: none;
    border-color: var(--ln-forest);
    background: #fff;
}
.ln-hint { font-size: 12px; color: var(--ln-ink-soft); margin: 4px 0 0; }

/* Member search */
.ln-search-wrap { position: relative; }
.ln-search-results {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 5;
    background: var(--ln-card);
    border: 1.5px solid var(--ln-line);
    border-radius: 8px;
    box-shadow: var(--ln-shadow);
    max-height: 210px;
    overflow-y: auto;
}
.ln-search-results.ln-show { display: block; }
.ln-search-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid var(--ln-line);
}
.ln-search-item:last-child { border-bottom: none; }
.ln-search-item:hover { background: var(--ln-paper); }
.ln-search-item-name { margin: 0; font-size: 13.5px; font-weight: 600; color: var(--ln-ink); }
.ln-search-item-id { margin: 0; font-size: 12px; color: var(--ln-ink-soft); }
.ln-search-empty { padding: 10px 12px; font-size: 13px; color: var(--ln-ink-soft); }

/* Investment check panel */
.ln-check {
    display: none;
    border: 1px solid var(--ln-line);
    border-radius: var(--ln-radius);
    padding: 12px;
    margin-top: 12px;
}
.ln-check.ln-show { display: block; }
.ln-check-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.ln-check-title { margin: 0; font-size: 13px; font-weight: 600; color: var(--ln-ink); }
.ln-check-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ln-check-cell { background: var(--ln-paper); border-radius: 8px; padding: 8px 10px; }
.ln-check-cell.ln-check-credit { background: var(--ln-success-soft); }
.ln-check-label { margin: 0; font-size: 12px; color: var(--ln-ink-soft); }
.ln-check-value { margin: 2px 0 0; font-size: 15px; font-weight: 600; color: var(--ln-ink); }
.ln-check-credit .ln-check-label,
.ln-check-credit .ln-check-value { color: var(--ln-success); }
.ln-check-note { margin: 10px 0 0; font-size: 12.5px; color: var(--ln-danger); display: none; }
.ln-check-note.ln-show { display: block; }

/* RESPONSIVE */
@media (max-width: 560px) {
    .ln-header { flex-direction: column; gap: 12px; }
    .ln-header > div:first-child { width: 100%; }
    .ln-header-actions { width: 100%; }
    .ln-header .ln-btn { width: 100%; }
    .ln-section-header { flex-direction: column; align-items: flex-start; }
    .ln-modal { padding: 12px; }
    .ln-modal-card { max-width: 100%; padding: 20px 18px; }
}
</style>

<div class="ln-page">

    <!-- PAGE HEADER -->
    <div class="ln-header">
        <div>
            <h1>Loans</h1>
        </div>

        <div class="ln-header-actions">
            <?php if ($pendingLoanCount): ?>
                <span class="ln-pending-pill"><?php echo $pendingLoanCount; ?> pending</span>
            <?php endif; ?>

            <button class="ln-btn ln-btn-primary" id="add-loan-btn" type="button">
                <span class="material-icons">add</span> Add loan
            </button>
        </div>
    </div>

    <!-- SUMMARY -->
    <div class="ln-summary-grid">
        <div class="ln-metric-card ln-tone-success">
            <p class="ln-metric-label">Total loaned</p>
            <p class="ln-metric-value ln-value-sm">₱<?php echo number_format($stats['total_loaned'], 2); ?></p>
            <p class="ln-metric-sub">₱<?php echo number_format($stats['loaned_this_month'], 2); ?> this month</p>
        </div>
        <div class="ln-metric-card ln-tone-info">
            <p class="ln-metric-label">Members with active loans</p>
            <p class="ln-metric-value"><?php echo $stats['active_members']; ?></p>
        </div>
        <div class="ln-metric-card ln-tone-danger">
            <p class="ln-metric-label">Outstanding balance</p>
            <p class="ln-metric-value ln-value-sm">₱<?php echo number_format($stats['total_outstanding'], 2); ?></p>
        </div>
        <div class="ln-metric-card ln-tone-gold">
            <p class="ln-metric-label">Pending requests</p>
            <p class="ln-metric-value"><?php echo $stats['pending']; ?></p>
        </div>
        <div class="ln-metric-card ln-tone-forest">
            <p class="ln-metric-label">Total repaid</p>
            <p class="ln-metric-value ln-value-sm">₱<?php echo number_format($stats['total_repaid'], 2); ?></p>
        </div>
    </div>

    <!-- LOANS TABLE -->
    <div class="ln-section">
        <div class="ln-section-header">
            <h2>All loan requests</h2>
        </div>

        <div class="ln-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Amount</th>
                        <th>Purpose</th>
                        <th>Loanable / Balance</th>
                        <th>Amount due now</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th class="ln-text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($loans)): ?>
                        <tr><td colspan="8" class="ln-muted ln-text-center">No loan requests yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                            $memberId   = (int) $loan['member_id'];
                            $ledger     = lnLedger($pdo, $ledgerCache, $memberId);
                            $row        = $ledger[(int) $loan['id']] ?? null;
                            $loanable   = getLoanableAmount($pdo, $memberId);
                            $balance    = array_sum(array_column($ledger, 'balance'));
                            $memberName = trim($loan['last_name'] . ', ' . $loan['first_name']);
                            $paidOff    = ($loan['status'] === 'released' && $row && ($row['closed'] || $row['balance'] <= 0.004));
                            $interestPerMonth = (float) $loan['amount'] * LOAN_INTEREST_RATE_MONTHLY / 100;
                        ?>
                        <tr>
                            <td>
                                <div class="ln-member-cell">
                                    <div class="ln-avatar"><?php echo htmlspecialchars(lnInitials($loan['last_name'], $loan['first_name'])); ?></div>
                                    <div>
                                        <p class="ln-member-name"><?php echo htmlspecialchars($memberName); ?></p>
                                        <p class="ln-member-id"><?php echo htmlspecialchars($loan['membership_id']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="ln-amount">₱<?php echo number_format($loan['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($loan['purpose'] ?: '—'); ?></td>
                            <td>₱<?php echo number_format($loanable, 2); ?> / ₱<?php echo number_format($balance, 2); ?></td>
                            <td>
                                <?php if ($row): ?>
                                    <?php if ($paidOff): ?>
                                        <p class="ln-due-main">₱0.00</p>
                                        <p class="ln-due-sub">Paid ₱<?php echo number_format($row['due'], 2); ?></p>
                                    <?php else: ?>
                                        <p class="ln-due-main">₱<?php echo number_format($row['balance'], 2); ?></p>
                                        <p class="ln-due-sub">
                                            ₱<?php echo number_format($row['principal'], 2); ?>
                                            + ₱<?php echo number_format($row['interest'], 2); ?> interest
                                            (<?php echo (int) $row['months']; ?> mo)
                                        </p>
                                        <?php if ($row['legacy'] && !empty($loan['due_date'])): ?>
                                            <p class="ln-due-sub">Due <?php echo date('M j, Y', strtotime($loan['due_date'])); ?></p>
                                            <?php if (isLoanOverdue($loan, $balance)): ?>
                                                <span class="ln-status-badge ln-overdue">Overdue</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php elseif ($loan['status'] === 'approved'): ?>
                                    <span class="ln-dash">+ ₱<?php echo number_format($interestPerMonth, 2); ?> / month once released</span>
                                <?php else: ?>
                                    <span class="ln-dash">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo lnStatusBadge($loan['status'], $paidOff); ?></td>
                            <td><?php echo date('M j, Y', strtotime($loan['created_at'])); ?></td>
                            <td class="ln-text-right">
                                <div class="ln-actions">
                                    <?php if ($loan['status'] === 'pending'): ?>
                                        <button class="ln-btn-link loan-action-btn"
                                                data-id="<?php echo $loan['id']; ?>" data-action="approve"
                                                data-member="<?php echo htmlspecialchars($memberName); ?>"
                                                data-amount="<?php echo number_format($loan['amount'], 2); ?>"
                                                data-interest="<?php echo number_format($interestPerMonth, 2); ?>" type="button">
                                            <span class="material-icons">check</span> Approve
                                        </button>
                                        <button class="ln-btn-link ln-btn-link-danger loan-action-btn"
                                                data-id="<?php echo $loan['id']; ?>" data-action="reject"
                                                data-member="<?php echo htmlspecialchars($memberName); ?>"
                                                data-amount="<?php echo number_format($loan['amount'], 2); ?>"
                                                data-interest="<?php echo number_format($interestPerMonth, 2); ?>" type="button">
                                            <span class="material-icons">close</span> Reject
                                        </button>
                                    <?php elseif ($loan['status'] === 'approved'): ?>
                                        <button class="ln-btn-link ln-btn-link-info loan-action-btn"
                                                data-id="<?php echo $loan['id']; ?>" data-action="release"
                                                data-member="<?php echo htmlspecialchars($memberName); ?>"
                                                data-amount="<?php echo number_format($loan['amount'], 2); ?>"
                                                data-interest="<?php echo number_format($interestPerMonth, 2); ?>" type="button">
                                            <span class="material-icons">payments</span> Release
                                        </button>
                                    <?php else: ?>
                                        <span class="ln-dash">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>


<!-- ============================================================
     CONFIRM MODAL (shared: approve / reject / release)
     ============================================================ -->
<div class="ln-modal" id="loan-modal-wrap">
    <div class="ln-modal-card">
        <div class="ln-modal-header">
            <h2 id="loan-modal-title">Are you sure?</h2>
            <button class="ln-icon-btn" id="loan-modal-close" type="button" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <p class="ln-confirm-text" id="loan-modal-message"></p>

        <!-- Release only: how the interest works (no loan term to enter) -->
        <div class="ln-interest-note" id="loan-interest-note">
            <strong>Interest:</strong> <?php echo LOAN_INTEREST_RATE_MONTHLY; ?>% per month
            (₱<span class="loan-interest-amt"></span>), counted from today until the loan is fully paid.<br>
            The member pays on the month they choose. The amount due grows by
            ₱<span class="loan-interest-amt"></span> each month.
        </div>

        <div id="loan-modal-error" class="ln-error-box"></div>

        <div class="ln-confirm-actions">
            <button class="ln-btn ln-btn-outline" id="loan-modal-cancel" type="button">Cancel</button>
            <button class="ln-btn ln-btn-primary" id="loan-modal-ok" type="button">Confirm</button>
        </div>
    </div>
</div>


<!-- ============================================================
     ADD LOAN MODAL (member search + investment check)
     ============================================================ -->
<div class="ln-modal" id="add-loan-modal">
    <div class="ln-modal-card ln-modal-wide">
        <div class="ln-modal-header">
            <h2>Add loan</h2>
            <button class="ln-icon-btn" id="add-loan-close" type="button" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div id="add-loan-error" class="ln-error-box"></div>

        <form id="add-loan-form" autocomplete="off">
            <input type="hidden" name="member_id" id="al-member-id" value="">

            <label class="ln-label">Member</label>
            <div class="ln-search-wrap">
                <input type="text" class="ln-input" id="al-member-search" placeholder="Search by name or membership ID">
                <div class="ln-search-results" id="al-member-results"></div>
            </div>

            <div class="ln-check" id="al-check">
                <div class="ln-check-head">
                    <p class="ln-check-title">Investment check</p>
                    <span class="ln-status-badge" id="al-check-badge"></span>
                </div>
                <div class="ln-check-grid">
                    <div class="ln-check-cell">
                        <p class="ln-check-label">Total investment</p>
                        <p class="ln-check-value" id="al-investment">₱0.00</p>
                    </div>
                    <div class="ln-check-cell">
                        <p class="ln-check-label">Loanable amount</p>
                        <p class="ln-check-value" id="al-loanable">₱0.00</p>
                    </div>
                    <div class="ln-check-cell">
                        <p class="ln-check-label">Current loan balance</p>
                        <p class="ln-check-value" id="al-balance">₱0.00</p>
                    </div>
                    <div class="ln-check-cell ln-check-credit">
                        <p class="ln-check-label">Available credit</p>
                        <p class="ln-check-value" id="al-credit">₱0.00</p>
                    </div>
                </div>
                <p class="ln-check-note" id="al-check-note"></p>
            </div>

            <label class="ln-label">Amount (₱)</label>
            <input type="number" class="ln-input" name="amount" id="al-amount" step="0.01" min="1" required>
            <p class="ln-hint" id="al-amount-hint">Select a member to see the limit.</p>

            <label class="ln-label">Purpose</label>
            <input type="text" class="ln-input" name="purpose" placeholder="e.g. Farm inputs for next planting season" required>

            <button type="submit" id="add-loan-submit" class="ln-btn ln-btn-primary ln-btn-full" style="margin-top:16px;">Add loan</button>
        </form>
    </div>
</div>


<script>
(function () {
    const AJAX_BASE = '<?php echo BASE_URL; ?>/app/manager/loans/api/';

    const modal      = document.getElementById('loan-modal-wrap');
    const titleEl    = document.getElementById('loan-modal-title');
    const messageEl  = document.getElementById('loan-modal-message');
    const noteEl     = document.getElementById('loan-interest-note');
    const errorBox   = document.getElementById('loan-modal-error');
    const okBtn      = document.getElementById('loan-modal-ok');
    const cancelBtn  = document.getElementById('loan-modal-cancel');
    const closeBtn   = document.getElementById('loan-modal-close');

    let current = null; // { btn, id, action }

    const COPY = {
        approve: { title: 'Approve loan',  verb: 'Approve', danger: false },
        reject:  { title: 'Reject loan',   verb: 'Reject',  danger: true  },
        release: { title: 'Release loan',  verb: 'Release', danger: false }
    };

    function openModal() {
        modal.classList.add('ln-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('ln-open');
        document.body.style.overflow = '';
        current = null;
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.add('ln-show');
    }

    document.querySelectorAll('.loan-action-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const action = btn.dataset.action;
            const copy = COPY[action];
            if (!copy) return;

            current = { btn: btn, id: btn.dataset.id, action: action };

            const who = btn.dataset.member + ' (₱' + btn.dataset.amount + ')';
            titleEl.textContent = copy.title;
            messageEl.textContent = copy.verb + ' the loan of ' + who + '?'
                + (action === 'reject' ? ' This cannot be undone.' : '');

            errorBox.classList.remove('ln-show');

            noteEl.classList.toggle('ln-show', action === 'release');
            if (action === 'release') {
                document.querySelectorAll('.loan-interest-amt').forEach(function (el) {
                    el.textContent = btn.dataset.interest;
                });
            }

            okBtn.textContent = copy.verb;
            okBtn.classList.toggle('ln-btn-danger', copy.danger);
            okBtn.classList.toggle('ln-btn-primary', !copy.danger);
            okBtn.disabled = false;

            openModal();
        });
    });

    okBtn.addEventListener('click', async function () {
        if (!current) return;
        errorBox.classList.remove('ln-show');
        okBtn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('loan_id', current.id);
            formData.append('action', current.action);

            const res = await fetch(AJAX_BASE + 'update-loan-status.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                window.location.reload();
            } else {
                showError(data.message || 'Could not update loan.');
                okBtn.disabled = false;
            }
        } catch (err) {
            showError('Could not connect to server.');
            okBtn.disabled = false;
        }
    });

    cancelBtn.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>

<script>
(function () {
    const AJAX_BASE = '<?php echo BASE_URL; ?>/app/manager/loans/api/';
    const MEMBERS = <?php echo json_encode($members, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const openBtn    = document.getElementById('add-loan-btn');
    const modal      = document.getElementById('add-loan-modal');
    const closeBtn   = document.getElementById('add-loan-close');
    const form       = document.getElementById('add-loan-form');
    const errorBox   = document.getElementById('add-loan-error');
    const submitBtn  = document.getElementById('add-loan-submit');
    const searchEl   = document.getElementById('al-member-search');
    const resultsEl  = document.getElementById('al-member-results');
    const memberEl   = document.getElementById('al-member-id');
    const checkEl    = document.getElementById('al-check');
    const badgeEl    = document.getElementById('al-check-badge');
    const noteEl     = document.getElementById('al-check-note');
    const amountEl   = document.getElementById('al-amount');
    const hintEl     = document.getElementById('al-amount-hint');

    let check = null; // result of member-loan-check.php for the selected member

    function peso(n) {
        return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.add('ln-show');
    }
    function clearError() {
        errorBox.textContent = '';
        errorBox.classList.remove('ln-show');
    }

    function resetCheck() {
        check = null;
        checkEl.classList.remove('ln-show');
        noteEl.classList.remove('ln-show');
        amountEl.removeAttribute('max');
        hintEl.textContent = 'Select a member to see the limit.';
    }

    function openModal() {
        modal.classList.add('ln-open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.remove('ln-open');
        document.body.style.overflow = '';
        form.reset();
        memberEl.value = '';
        resultsEl.classList.remove('ln-show');
        resetCheck();
        clearError();
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('ln-open')) closeModal();
    });

    function memberLabel(m) {
        return (m.last_name + ', ' + m.first_name).trim();
    }

    function renderResults(term) {
        const q = term.trim().toLowerCase();
        resultsEl.innerHTML = '';

        if (q === '') {
            resultsEl.classList.remove('ln-show');
            return;
        }

        const matches = MEMBERS.filter(function (m) {
            const hay = (m.first_name + ' ' + m.last_name + ' ' + m.last_name + ' ' + m.first_name + ' ' + m.membership_id).toLowerCase();
            return hay.indexOf(q) !== -1;
        }).slice(0, 8);

        if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'ln-search-empty';
            empty.textContent = 'No members found.';
            resultsEl.appendChild(empty);
        } else {
            matches.forEach(function (m) {
                const item = document.createElement('div');
                item.className = 'ln-search-item';

                const name = document.createElement('p');
                name.className = 'ln-search-item-name';
                name.textContent = memberLabel(m);

                const id = document.createElement('p');
                id.className = 'ln-search-item-id';
                id.textContent = m.membership_id;

                item.appendChild(name);
                item.appendChild(id);

                item.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    memberEl.value = m.id;
                    searchEl.value = memberLabel(m) + ' (' + m.membership_id + ')';
                    resultsEl.classList.remove('ln-show');
                    clearError();
                    loadCheck(m.id);
                });

                resultsEl.appendChild(item);
            });
        }

        resultsEl.classList.add('ln-show');
    }

    async function loadCheck(memberId) {
        resetCheck();
        hintEl.textContent = 'Checking investment...';

        try {
            const res = await fetch(AJAX_BASE + 'member-loan-check.php?member_id=' + encodeURIComponent(memberId));
            const data = await res.json();

            if (!data.success) {
                hintEl.textContent = 'Select a member to see the limit.';
                showError(data.message || 'Could not check this member.');
                return;
            }

            check = data;

            document.getElementById('al-investment').textContent = peso(data.investment);
            document.getElementById('al-loanable').textContent   = peso(data.loanable);
            document.getElementById('al-balance').textContent    = peso(data.balance);
            document.getElementById('al-credit').textContent     = peso(data.credit);

            badgeEl.textContent = data.eligible ? 'Eligible' : 'Not eligible';
            badgeEl.className = 'ln-status-badge ' + (data.eligible ? 'ln-paid' : 'ln-rejected');

            if (!data.eligible) {
                noteEl.textContent = 'Needs at least ' + peso(data.threshold) + ' in confirmed investment before a loan can be added.';
                noteEl.classList.add('ln-show');
                hintEl.textContent = 'Not eligible for a loan yet.';
            } else if (data.credit <= 0) {
                noteEl.textContent = 'No available credit left for this member.';
                noteEl.classList.add('ln-show');
                hintEl.textContent = 'No available credit.';
            } else {
                amountEl.max = data.credit;
                hintEl.textContent = 'Up to ' + peso(data.credit);
            }

            checkEl.classList.add('ln-show');
        } catch (err) {
            hintEl.textContent = 'Select a member to see the limit.';
            showError('Could not connect to server.');
        }
    }

    searchEl.addEventListener('input', function () {
        memberEl.value = '';
        resetCheck();
        renderResults(searchEl.value);
    });
    searchEl.addEventListener('focus', function () {
        if (memberEl.value === '') renderResults(searchEl.value);
    });
    searchEl.addEventListener('blur', function () {
        setTimeout(function () { resultsEl.classList.remove('ln-show'); }, 120);
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearError();

        if (!memberEl.value) {
            showError('Select a member from the search results.');
            return;
        }
        if (!check) {
            showError('Wait for the investment check to finish.');
            return;
        }
        if (!check.eligible) {
            showError('This member is not eligible for a loan yet.');
            return;
        }
        const amount = parseFloat(amountEl.value);
        if (!(amount > 0)) {
            showError('Enter a valid loan amount.');
            return;
        }
        if (amount > check.credit) {
            showError('Amount exceeds the available credit of ' + peso(check.credit) + '.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        try {
            const res = await fetch(AJAX_BASE + 'add-loan.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();

            if (data.success) {
                window.location.reload();
            } else {
                showError(data.message || 'Could not add the loan.');
            }
        } catch (err) {
            showError('Could not connect to server.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add loan';
        }
    });
})();
</script>

<?php renderFooter(); ?>