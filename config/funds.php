<?php

require_once __DIR__ . '/rental-split.php';

/** One number from the database. A missing table counts as 0. */
function fundsScalar(PDO $pdo, string $sql): float {
    try {
        $v = $pdo->query($sql)->fetchColumn();
        return ($v === false || $v === null) ? 0.0 : (float) $v;
    } catch (PDOException $e) {
        return 0.0;
    }
}

function getAvailableFunds(PDO $pdo): float {
    $coop = coopIncomeSql();

    $collected = fundsScalar($pdo, "
        SELECT COALESCE(SUM($coop),0) FROM payments
        WHERE status = 'confirmed' AND LOWER(payment_type) <> 'registration'");
    $loans = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM loans WHERE status = 'released'");
    $stock = fundsScalar($pdo, "SELECT COALESCE(SUM(total_cost),0) FROM stock_purchases");
    $expenses = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses");

    return round($collected - $loans - $stock - $expenses, 2);
}

/**
 * Net income summary for the member home page (same numbers as the manager dashboard):
 *   net income = confirmed payments (coop share, without registration)
 *                - loans released - stock purchases - operational expenses
 *
 * Returns:
 *   total       net income of all time
 *   this_month  net income of this month
 *   last_month  net income of last month
 *   months      last 6 months, oldest first: [['label' => 'May', 'net' => 10500.0], ...]
 */
function getNetIncomeSummary(PDO $pdo): array {
    $coop = coopIncomeSql();

    // The last 6 months (oldest first)
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $d = new DateTime('first day of this month');
        $d->modify("-$i months");
        $months[$d->format('Y-m')] = ['label' => $d->format('M'), 'net' => 0.0];
    }
    $start = array_key_first($months) . '-01';

    // Adds the monthly totals of one query into the months list (sign = +1 or -1)
    $addMonths = function (string $sql, int $sign) use ($pdo, &$months) {
        try {
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (isset($months[$r['ym']])) {
                    $months[$r['ym']]['net'] += $sign * (float) $r['total'];
                }
            }
        } catch (PDOException $e) {
            // a missing table counts as 0
        }
    };

    $addMonths("
        SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM($coop) AS total
        FROM payments
        WHERE status = 'confirmed' AND LOWER(payment_type) <> 'registration' AND payment_date >= '$start'
        GROUP BY ym", +1);
    $addMonths("
        SELECT DATE_FORMAT(COALESCE(released_at, created_at), '%Y-%m') AS ym, SUM(amount) AS total
        FROM loans
        WHERE status = 'released' AND COALESCE(released_at, created_at) >= '$start'
        GROUP BY ym", -1);
    $addMonths("
        SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS ym, SUM(total_cost) AS total
        FROM stock_purchases WHERE purchase_date >= '$start' GROUP BY ym", -1);
    $addMonths("
        SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total
        FROM expenses WHERE expense_date >= '$start' GROUP BY ym", -1);

    $list = array_values($months);
    return [
        'total'      => getAvailableFunds($pdo),
        'this_month' => round($list[5]['net'], 2),
        'last_month' => round($list[4]['net'], 2),
        'months'     => $list,
    ];
}