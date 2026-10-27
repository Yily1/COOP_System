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