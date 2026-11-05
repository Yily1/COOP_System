<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/../../../../config/rental-split.php'; // coopIncomeSql()

if (session_status() === PHP_SESSION_NONE) { session_start(); }
requireRole('manager');
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

// Bahagi ng net income na lalabas bilang withdrawal. Ang natitira ay carry-over.
// BABALA: kapag pinalitan ito, magbabago rin ang carry-over ng mga lumang withdrawal.
// Dapat kapareho ng $withdrawRate sa dashboard.php.
const NET_INCOME_WITHDRAW_RATE = 0.40;

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

/* Available Net income. Pareho ng formula ng dashboard:
   Net income = (Total income + carry-over) - Operational expenses
   Bumibilang lang ng nangyari PAGKATAPOS ng huling "Net Income" withdrawal.
   Total income = confirmed coop-share payments (no Registration)
                  - loans released - stock purchases.
   Carry-over = natira sa huling withdrawal = amount x (1 - rate) / rate */
function currentNetIncome(PDO $pdo): float {
    $cutoffId = 0;
    $cutQ = null;
    $carryOver = 0.0;
    try {
        $cut = $pdo->query("SELECT id, created_at, expense_date, amount FROM expenses WHERE category = 'Net Income' ORDER BY id DESC LIMIT 1")
                   ->fetch(PDO::FETCH_ASSOC);
        if ($cut) {
            $cutoffId  = (int) $cut['id'];
            $cutQ      = $pdo->quote($cut['created_at'] ?: ($cut['expense_date'] . ' 23:59:59'));
            $carryOver = (float) $cut['amount'] * (1 - NET_INCOME_WITHDRAW_RATE) / NET_INCOME_WITHDRAW_RATE;
        }
    } catch (PDOException $e) {
        // walang expenses table pa: walang naunang withdrawal
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

    $totalIncome = $payments - $loans - $stock + $carryOver;
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

$remaining = null;

if ($category === 'Net Income') {
    // Automatic: ang system ang magko-compute, hindi ang manager.
    $available = round(currentNetIncome($pdo), 2);
    if ($available <= 0) {
        out(false, 'There is no available net income to withdraw.');
    }
    $amount = round($available * NET_INCOME_WITHDRAW_RATE, 2);
    if ($amount <= 0) {
        out(false, 'The withdrawable amount is too small.');
    }
    $remaining = round($available - $amount, 2);
    if ($description === '') {
        $description = 'Net income withdrawal (' . (NET_INCOME_WITHDRAW_RATE * 100) . '%)';
    }
} else {
    if (!is_numeric($amount) || (float)$amount <= 0) out(false, 'Please enter an amount greater than 0.');
    $amount = round((float)$amount, 2);
    if ($description === '') out(false, 'Please say what the expense is for.');
}

if (mb_strlen($description) > 255) out(false, 'Description is too long (max 255 characters).');

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
    out(true, 'Expense added.', [
        'id'        => $pdo->lastInsertId(),
        'amount'    => $amount,
        'remaining' => $remaining,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    out(false, 'DB error: ' . $e->getMessage());
}