<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
require_once __DIR__ . '/../../../../config/funds.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
requireRole('manager');
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

function out($success, $message = '', $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
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
    $net = getNetIncomeSummary($pdo);
    $available = round((float)$net['total'], 2);
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