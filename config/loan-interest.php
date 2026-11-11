<?php


if (!defined('LOAN_INTEREST_RATE_MONTHLY')) {
    define('LOAN_INTEREST_RATE_MONTHLY', 10);
}

/**
 * COMPLETED months from $fromDate to $toDate (Y-m-d).
 * 0 on the release day, 1 once a full month has passed, and so on.
 * (Oct 26 -> Nov 25 = 0, Nov 26 = 1, Dec 26 = 2)
 */
function loanMonthsBetween(string $fromDate, string $toDate): int {
    $a = new DateTime(substr($fromDate, 0, 10));
    $b = new DateTime(substr($toDate, 0, 10));
    if ($b <= $a) {
        return 0;
    }
    $diff = $a->diff($b);
    return max(0, $diff->y * 12 + $diff->m);
}

/** The date a loan was released (older rows without released_at fall back to created_at). */
function loanReleaseDate(array $loan): string {
    $d = !empty($loan['released_at']) ? $loan['released_at'] : $loan['created_at'];
    return substr((string) $d, 0, 10);
}

/**
 * Ledger of every released loan of one member.
 * Returns [loan_id => [
 *   'principal', 'interest', 'due', 'paid', 'balance', 'months', 'closed', 'legacy'
 * ]]
 *  due     = amount + interest (up to today, or up to the day it was fully paid)
 *  balance = due minus what was paid
 */
function memberLoanLedger(PDO $pdo, int $memberId, ?string $today = null): array {
    $today = $today ?: date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT * FROM loans
        WHERE member_id = ? AND status = 'released'
        ORDER BY COALESCE(released_at, created_at), id");
    $stmt->execute([$memberId]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$loans) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT payment_date, amount FROM payments
        WHERE member_id = ? AND payment_type = 'loan_repayment' AND status = 'confirmed'
        ORDER BY payment_date, id");
    $stmt->execute([$memberId]);
    $events = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $events[] = ['date' => substr((string) $p['payment_date'], 0, 10), 'left' => (float) $p['amount']];
    }

    $ledger = [];
    foreach ($loans as $loan) {
        $principal = (float) $loan['amount'];
        $rate      = $loan['interest_rate'] !== null ? (float) $loan['interest_rate'] : (float) LOAN_INTEREST_RATE_MONTHLY;
        $from      = loanReleaseDate($loan);
        $legacy    = $loan['total_due'] !== null;   // old loan with a fixed total

        $paid = 0.0;
        $closed = false;
        $due = 0.0;
        $months = 0;

        foreach ($events as &$ev) {
            if ($ev['left'] <= 0.004 || $ev['date'] < $from) {
                continue;
            }
            if ($legacy) {
                $months = (int) ($loan['term_months'] ?? 0);
                $dueAt  = (float) $loan['total_due'];
            } else {
                $months = loanMonthsBetween($from, $ev['date']);
                $dueAt  = round($principal + $principal * $rate / 100 * $months, 2);
            }
            $take = min($ev['left'], max(0.0, $dueAt - $paid));
            $paid += $take;
            $ev['left'] -= $take;
            if ($paid >= $dueAt - 0.004) {
                $closed = true;
                $due = $dueAt;
                break;
            }
        }
        unset($ev);

        if (!$closed) {
            if ($legacy) {
                $months = (int) ($loan['term_months'] ?? 0);
                $due = (float) $loan['total_due'];
            } else {
                $months = loanMonthsBetween($from, $today);
                $due = round($principal + $principal * $rate / 100 * $months, 2);
            }
        }

        $ledger[(int) $loan['id']] = [
            'principal' => $principal,
            'interest'  => max(0.0, $due - $principal),
            'due'       => $due,
            'paid'      => $paid,
            'balance'   => max(0.0, $due - $paid),
            'months'    => $months,
            'closed'    => $closed,
            'legacy'    => $legacy,
        ];
    }
    return $ledger;
}

/** What a member still owes on all released loans (amount + interest until today, minus payments). */
function memberLoanBalanceLive(PDO $pdo, int $memberId): float {
    $sum = 0.0;
    foreach (memberLoanLedger($pdo, $memberId) as $row) {
        $sum += $row['balance'];
    }
    return round($sum, 2);
}

/**
 * Numbers for the summary cards on the Loans page.
 * "Total repaid" now counts only what the ledger actually applied to loans,
 * so it always matches the balances shown per loan.
 */
function loanPortfolioStatsLive(PDO $pdo): array {
    $pending = (int) $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn();

    // Everything ever lent out (amount only, no interest) = loans that were released
    $loaned = (float) $pdo->query("
        SELECT COALESCE(SUM(amount),0) FROM loans WHERE status = 'released'")->fetchColumn();
    $loanedThisMonth = (float) $pdo->query("
        SELECT COALESCE(SUM(amount),0) FROM loans
        WHERE status = 'released'
          AND DATE_FORMAT(COALESCE(released_at, created_at), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();

    $repaid = 0.0;
    $active = 0;
    $outstanding = 0.0;
    $ids = $pdo->query("SELECT DISTINCT member_id FROM loans WHERE status = 'released'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $memberId) {
        $ledger  = memberLoanLedger($pdo, (int) $memberId);
        $balance = array_sum(array_column($ledger, 'balance'));
        $repaid += array_sum(array_column($ledger, 'paid'));
        if ($balance > 0.004) {
            $active++;
            $outstanding += $balance;
        }
    }

    return [
        'total_loaned'      => round($loaned, 2),
        'loaned_this_month' => round($loanedThisMonth, 2),
        'active_members'    => $active,
        'total_outstanding' => round($outstanding, 2),
        'pending'           => $pending,
        'total_repaid'      => round($repaid, 2),
    ];
}