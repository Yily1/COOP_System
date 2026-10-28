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

    $cutoffId = 0;
    $cutoffDate = null;
    try {
        $cutSql = "SELECT id, expense_date FROM expenses WHERE category = 'Net Income' ORDER BY id DESC LIMIT 1";
        $cut = $pdo->query($cutSql)->fetch(PDO::FETCH_ASSOC);
        if ($cut) {
            $cutoffId = (int) $cut['id'];
            $cutoffDate = $cut['expense_date'];
        }
    } catch (PDOException $e) {
    }

    $incomeWhere = "status = 'confirmed' AND LOWER(payment_type) <> 'registration'";

    $keepCond = '1=1';
    $loanCut = '';
    $stockCut = '';
    $opCut = '';
    if ($cutoffDate) {
        $keepCond = "(LOWER(payment_type) = 'investment' OR payment_date > '$cutoffDate')";
        $loanCut  = "AND DATE(COALESCE(released_at, created_at)) > '$cutoffDate'";
        $stockCut = "AND purchase_date > '$cutoffDate'";
    }
    if ($cutoffId) {
        $opCut = "AND id > $cutoffId";
    }

    $sqlPayments = "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM($coop) AS total FROM payments WHERE $incomeWhere AND $keepCond AND payment_date >= '$start' GROUP BY ym";
    $sqlLoans    = "SELECT DATE_FORMAT(COALESCE(released_at, created_at), '%Y-%m') AS ym, SUM(amount) AS total FROM loans WHERE status = 'released' $loanCut AND COALESCE(released_at, created_at) >= '$start' GROUP BY ym";
    $sqlStock    = "SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS ym, SUM(total_cost) AS total FROM stock_purchases WHERE purchase_date >= '$start' $stockCut GROUP BY ym";
    $sqlInvest   = "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM($coop) AS total FROM payments WHERE $incomeWhere AND LOWER(payment_type) = 'investment' AND payment_date >= '$start' GROUP BY ym";
    $sqlOper     = "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total FROM expenses WHERE category = 'Operational' $opCut AND expense_date >= '$start' GROUP BY ym";

    netIncomeAddMonths($pdo, $months, $sqlPayments, 1);
    netIncomeAddMonths($pdo, $months, $sqlLoans, -1);
    netIncomeAddMonths($pdo, $months, $sqlStock, -1);
    netIncomeAddMonths($pdo, $months, $sqlInvest, -1);
    netIncomeAddMonths($pdo, $months, $sqlOper, -1);

    $paymentsTotal    = fundsScalar($pdo, "SELECT COALESCE(SUM($coop),0) FROM payments WHERE $incomeWhere AND $keepCond");
    $loansTotal       = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM loans WHERE status = 'released' $loanCut");
    $stockTotal       = fundsScalar($pdo, "SELECT COALESCE(SUM(total_cost),0) FROM stock_purchases WHERE 1=1 $stockCut");
    $investTotal      = fundsScalar($pdo, "SELECT COALESCE(SUM($coop),0) FROM payments WHERE $incomeWhere AND LOWER(payment_type) = 'investment'");
    $operationalTotal = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE category = 'Operational' $opCut");

    $total = $paymentsTotal - $loansTotal - $stockTotal - $investTotal - $operationalTotal;

    $list = array_values($months);
    return [
        'total'      => round($total, 2),
        'this_month' => round($list[5]['net'], 2),
        'last_month' => round($list[4]['net'], 2),
        'months'     => $list,
    ];
}