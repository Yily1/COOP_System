<?php

require_once __DIR__ . '/rental-split.php';

/* ============================================================
   HELPERS
   ============================================================ */

function fundsScalar(PDO $pdo, string $sql): float {
    try {
        $stmt = $pdo->query($sql);
        if (!$stmt) return 0.0;
        $v = $stmt->fetchColumn();
        return ($v === false || $v === null) ? 0.0 : (float) $v;
    } catch (PDOException $e) {
        return 0.0;
    }
}

function fundsRows(PDO $pdo, string $sql): array {
    try {
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        return [];
    }
}

/* ============================================================
   AVAILABLE FUNDS (hindi ginalaw)
   ============================================================ */

function getAvailableFunds(PDO $pdo): float {
    $coop = coopIncomeSql();

    $collected = fundsScalar($pdo, "SELECT COALESCE(SUM($coop),0) FROM payments WHERE status = 'confirmed' AND LOWER(payment_type) <> 'registration'");
    $loans     = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM loans WHERE status = 'released'");
    $stock     = fundsScalar($pdo, "SELECT COALESCE(SUM(total_cost),0) FROM stock_purchases");
    $expenses  = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses");

    return round($collected - $loans - $stock - $expenses, 2);
}

/* ============================================================
   NET INCOME SUMMARY: PAREHO NG MANAGER DASHBOARD

   Net income = (Coop income - Loans released - Stock purchases)
                - Operational expenses

   Nagre-reset pagkatapos ng huling "Net Income" withdrawal
   (expenses.category = 'Net Income'). Ang investment ay kasama
   sa income (hindi ibinabawas).

   Returns:
     'total'      => float (cycle total, galing sa huling withdrawal)
     'this_month' => float
     'last_month' => float
     'months'     => [ ['key'=>'2026-05','label'=>'May','net'=>float], ... ] (6 buwan)
   ============================================================ */

function getNetIncomeSummary(PDO $pdo): array {
    $coop = coopIncomeSql();

    // ---- Huling 6 na buwan ----
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $d = new DateTime('first day of this month');
        $d->modify("-$i months");
        $months[] = ['key' => $d->format('Y-m'), 'label' => $d->format('M')];
    }
    $keys = array_column($months, 'key');
    $windowStart = $months[0]['key'] . '-01';

    // ---- Cutoff: huling "Net Income" withdrawal ----
    $expensesReady = !empty(fundsRows($pdo, "SHOW TABLES LIKE 'expenses'"));

    $cutoffId = 0;
    $cutoffAt = null;
    if ($expensesReady) {
        $cut = fundsRows($pdo, "SELECT id, created_at, expense_date FROM expenses WHERE category = 'Net Income' ORDER BY id DESC LIMIT 1");
        if ($cut) {
            $cutoffId = (int) $cut[0]['id'];
            $cutoffAt = $cut[0]['created_at'] ?: ($cut[0]['expense_date'] . ' 23:59:59');
        }
    }
    $cutQ = $cutoffAt ? $pdo->quote($cutoffAt) : null;

    $opCut   = $cutoffId ? "AND id > {$cutoffId}" : '';
    $payCut  = $cutQ ? "AND created_at > {$cutQ}" : '';
    $loanCut = $cutQ ? "AND COALESCE(released_at, created_at) > {$cutQ}" : '';

    $stockHasCreated = !empty(fundsRows($pdo, "SHOW COLUMNS FROM stock_purchases LIKE 'created_at'"));
    $stockCut = $cutQ ? ($stockHasCreated ? "AND created_at > {$cutQ}" : "AND purchase_date > DATE({$cutQ})") : '';

    // ---- Loans released ----
    $loanMonthSql = "DATE_FORMAT(COALESCE(released_at, created_at), '%Y-%m')";
    $loanTotal = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM loans WHERE status = 'released' $loanCut");
    $loanByMonth = array_fill_keys($keys, 0.0);
    foreach (fundsRows($pdo, "
        SELECT $loanMonthSql AS ym, SUM(amount) AS total
        FROM loans
        WHERE status = 'released' $loanCut AND COALESCE(released_at, created_at) >= '{$windowStart}'
        GROUP BY ym") as $r) {
        if (isset($loanByMonth[$r['ym']])) $loanByMonth[$r['ym']] = (float) $r['total'];
    }

    // ---- Stock purchases ----
    $stockMonthSql = "DATE_FORMAT(purchase_date, '%Y-%m')";
    $stockTotal = fundsScalar($pdo, "SELECT COALESCE(SUM(total_cost),0) FROM stock_purchases WHERE 1=1 $stockCut");
    $stockByMonth = array_fill_keys($keys, 0.0);
    foreach (fundsRows($pdo, "
        SELECT $stockMonthSql AS ym, SUM(total_cost) AS total
        FROM stock_purchases
        WHERE purchase_date >= '{$windowStart}' $stockCut
        GROUP BY ym") as $r) {
        if (isset($stockByMonth[$r['ym']])) $stockByMonth[$r['ym']] = (float) $r['total'];
    }

    // ---- Coop income (kasama ang investment, walang registration) ----
    $incomeWhere = "status = 'confirmed' AND LOWER(payment_type) <> 'registration' $payCut";

    $paymentsTotal = fundsScalar($pdo, "SELECT COALESCE(SUM($coop),0) FROM payments WHERE $incomeWhere");
    $totalIncome = $paymentsTotal - $loanTotal - $stockTotal;

    $incomeByMonth = array_fill_keys($keys, 0.0);
    foreach (fundsRows($pdo, "
        SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM($coop) AS total
        FROM payments
        WHERE $incomeWhere AND payment_date >= '{$windowStart}'
        GROUP BY ym") as $r) {
        if (isset($incomeByMonth[$r['ym']])) $incomeByMonth[$r['ym']] = (float) $r['total'];
    }
    foreach ($keys as $ym) {
        $incomeByMonth[$ym] -= $loanByMonth[$ym] + $stockByMonth[$ym];
    }

    // ---- Operational expenses ----
    $totalOperational = 0.0;
    $expenseByMonth = array_fill_keys($keys, 0.0);
    if ($expensesReady) {
        $totalOperational = fundsScalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE category = 'Operational' $opCut");
        foreach (fundsRows($pdo, "
            SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total
            FROM expenses
            WHERE category = 'Operational' $opCut AND expense_date >= '{$windowStart}'
            GROUP BY ym") as $r) {
            if (isset($expenseByMonth[$r['ym']])) $expenseByMonth[$r['ym']] = (float) $r['total'];
        }
    }

    // ---- Net ----
    $list = [];
    foreach ($months as $m) {
        $list[] = [
            'key'   => $m['key'],
            'label' => $m['label'],
            'net'   => round($incomeByMonth[$m['key']] - $expenseByMonth[$m['key']], 2),
        ];
    }

    return [
        'total'      => round($totalIncome - $totalOperational, 2),
        'this_month' => $list[5]['net'],
        'last_month' => $list[4]['net'],
        'months'     => $list,
    ];
}