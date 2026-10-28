<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/../../../../config/rental-split.php'; // coopIncomeSql()

if (session_status() === PHP_SESSION_NONE) { session_start(); }
requireRole('manager');
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

function out($success, $message = '', $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

/* Run a single-value query. Returns 0.0 if the table/column is missing. */
function expScalar(PDO $pdo, string $sql): float {
    try {
        $stmt = $pdo->query($sql);
        if (!$stmt) return 0.0;
        $v = $stmt->fetchColumn();
        return ($v === false || $v === null) ? 0.0 : (float) $v;
    } catch (PDOException $e) {
        return 0.0;
    }
}

/* Available Net income. Same formula as the dashboard:
   Net income = Total income (includes Investment) - Operational expenses
   Only counts what happened AFTER the last "Net Income" withdrawal.
   Total income = confirmed coop-share payments (no Registration)
                  - loans released - stock purchases. */
function currentNetIncome(PDO $pdo): float {
    $cutoffId = 0;
    $cutQ = null;
    try {
        $cut = $pdo->query("SELECT id, created_at, expense_date FROM expenses WHERE category = 'Net Income' ORDER BY id DESC LIMIT 1")
                   ->fetch(PDO::FETCH_ASSOC);
        if ($cut) {
            $cutoffId = (int) $cut['id'];
            $cutQ = $pdo->quote($cut['created_at'] ?: ($cut['expense_date'] . ' 23:59:59'));
        }
    } catch (PDOException $e) {
        // no expenses table yet: no previous withdrawal
    }

    $opCut   = $cutoffId ? "AND id > {$cutoffId}" : '';
    $payCut  = $cutQ ? "AND created_at > {$cutQ}" : '';
    $loanCut = $cutQ ? "AND COALESCE(released_at, created_at) > {$cutQ}" : '';

    $stockCut = '';
    if ($cutQ) {
        $hasCreated = false;
        try {
            $hasCreated = (bool) $pdo->query("SHOW COLUMNS FROM stock_purchases LIKE 'created_at'")->fetch();
        } catch (PDOException $e) {
            $hasCreated = false;
        }
        $stockCut = $hasCreated ? "AND created_at > {$cutQ}" : "AND purchase_date > DATE({$cutQ})";
    }

    $coop = coopIncomeSql();

    $payments = expScalar($pdo, "
        SELECT COALESCE(SUM($coop),0) FROM payments
        WHERE status = 'confirmed' AND LOWER(payment_type) <> 'registration' $payCut");
    $loans = expScalar($pdo, "
        SELECT COALESCE(SUM(amount),0) FROM loans
        WHERE status = 'released' $loanCut");
    $stock = expScalar($pdo, "
        SELECT COALESCE(SUM(total_cost),0) FROM stock_purchases
        WHERE 1=1 $stockCut");
    $operational = expScalar($pdo, "
        SELECT COALESCE(SUM(amount),0) FROM expenses
        WHERE category = 'Operational' $opCut");

    $totalIncome = $payments - $loans - $stock;
    return $totalIncome - $operational;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(false, 'Invalid request method.');
}

$allowedCategories = ['Operational', 'Repair', 'Net Income'];

$date        = trim($_POST['expense_date'] ?? '');
$category    = trim($_POST['category'] ?? 'Operational');
$amount      = $_POST['amount'] ?? '';
$description = trim($_POST['description'] ?? '');

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) out(false, 'Please enter a valid date.');
if (!in_array($category, $allowedCategories, true)) out(false, 'Please choose a valid category.');
if (!is_numeric($amount) || (float)$amount <= 0) out(false, 'Please enter an amount greater than 0.');
if ($description === '') out(false, 'Please say what the expense is for.');
if (mb_strlen($description) > 255) out(false, 'Description is too long (max 255 characters).');

$amount = round((float)$amount, 2);

if ($category === 'Net Income') {
    $available = round(currentNetIncome($pdo), 2);
    if ($available <= 0) {
        out(false, 'There is no available net income to withdraw.');
    }
    if ($amount > $available) {
        out(false, 'The withdrawal is more than the available net income (₱' . number_format($available, 2) . ').');
    }
}

$recordedBy = $_SESSION['user_id'] ?? $_SESSION['id'] ?? ($_SESSION['user']['id'] ?? null);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO expenses (expense_date, category, description, amount, recorded_by)
         VALUES (:date, :category, :description, :amount, :recorded_by)'
    );
    $stmt->execute([
        ':date'        => $date,
        ':category'    => $category,
        ':description' => $description,
        ':amount'      => $amount,
        ':recorded_by' => $recordedBy,
    ]);
    out(true, 'Expense added.', ['id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    http_response_code(500);
    out(false, 'DB error: ' . $e->getMessage());
}