<?php

require_once __DIR__ . '/rental-split.php';

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

    $collected = fundsScalar($pdo, "SELECT COALESCE(SUM($coop),0) FROM payments WHERE status = 'confirmed' AND LOWER(payment_type) <> 'registration'");
    $loans     = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM loans WHERE status = 'released'");
    $stock     = fundsScalar($pdo, "SELECT COALESCE(SUM(total_cost),0) FROM stock_purchases");
    $expenses  = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses");

    return round($collected - $loans - $stock - $expenses, 2);
}

function netIncomeAddMonths(PDO $pdo, array &$months, string $sql, int $sign): void {
    try {
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($months[$r['ym']])) {
                $months[$r['ym']]['net'] += $sign * (float) $r['total'];
            }
        }
    } catch (PDOException $e) {
    }
}

function getNetIncomeSummary(PDO $pdo): array {
    $coop = coopIncomeSql();

    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $d = new DateTime('first day of this month');
        $d->modify("-$i months");
        $months[$d->format('Y-m')] = ['label' => $d->format('M'), 'net' => 0.0];
    }
    $start = array_key_first($months) . '-01';

    $incomeWhere = "status = 'confirmed' AND LOWER(payment_type) <> 'registration'";

    $sqlPayments = "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM($coop) AS total FROM payments WHERE $incomeWhere AND payment_date >= '$start' GROUP BY ym";
    $sqlLoans    = "SELECT DATE_FORMAT(COALESCE(released_at, created_at), '%Y-%m') AS ym, SUM(amount) AS total FROM loans WHERE status = 'released' AND COALESCE(released_at, created_at) >= '$start' GROUP BY ym";
    $sqlStock    = "SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS ym, SUM(total_cost) AS total FROM stock_purchases WHERE purchase_date >= '$start' GROUP BY ym";
    $sqlInvest   = "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM($coop) AS total FROM payments WHERE $incomeWhere AND LOWER(payment_type) = 'investment' AND payment_date >= '$start' GROUP BY ym";
    $sqlOper     = "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total FROM expenses WHERE category = 'Operational' AND expense_date >= '$start' GROUP BY ym";
    $sqlWithdraw = "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total FROM expenses WHERE category = 'Net Income' AND expense_date >= '$start' GROUP BY ym";

    netIncomeAddMonths($pdo, $months, $sqlPayments, 1);
    netIncomeAddMonths($pdo, $months, $sqlLoans, -1);
    netIncomeAddMonths($pdo, $months, $sqlStock, -1);
    netIncomeAddMonths($pdo, $months, $sqlInvest, -1);
    netIncomeAddMonths($pdo, $months, $sqlOper, -1);
    netIncomeAddMonths($pdo, $months, $sqlWithdraw, -1);

    $paymentsTotal  = fundsScalar($pdo, "SELECT COALESCE(SUM($coop),0) FROM payments WHERE $incomeWhere");
    $loansTotal     = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM loans WHERE status = 'released'");
    $stockTotal     = fundsScalar($pdo, "SELECT COALESCE(SUM(total_cost),0) FROM stock_purchases");
    $investTotal    = fundsScalar($pdo, "SELECT COALESCE(SUM($coop),0) FROM payments WHERE $incomeWhere AND LOWER(payment_type) = 'investment'");
    $operationTotal = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE category = 'Operational'");
    $withdrawTotal  = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE category = 'Net Income'");

    $total = $paymentsTotal - $loansTotal - $stockTotal - $investTotal - $operationTotal - $withdrawTotal;

    $list = array_values($months);
    return [
        'total'      => round($total, 2),
        'this_month' => round($list[5]['net'], 2),
        'last_month' => round($list[4]['net'], 2),
        'months'     => $list,
    ];
}