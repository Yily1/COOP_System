<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
requireRole('manager');
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

function out($success, $message = '') {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(false, 'Invalid request method.');
}

$date        = trim($_POST['expense_date'] ?? '');
$amount      = $_POST['amount'] ?? '';
$description = trim($_POST['description'] ?? '');

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) out(false, 'Please enter a valid date.');

if (!is_numeric($amount) || (float)$amount <= 0) out(false, 'Please enter an amount greater than 0.');
$amount = round((float)$amount, 2);

if ($description === '') out(false, 'Please say what the expense is for.');
if (mb_strlen($description) > 255) out(false, 'Description is too long (max 255 characters).');

$recordedBy = $_SESSION['user_id'] ?? $_SESSION['id'] ?? ($_SESSION['user']['id'] ?? null);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO expenses (expense_date, category, description, amount, recorded_by)
         VALUES (:date, 'Operational', :description, :amount, :recorded_by)"
    );
    $stmt->execute([
        ':date'        => $date,
        ':description' => $description,
        ':amount'      => $amount,
        ':recorded_by' => $recordedBy,
    ]);
    out(true, 'Expense added.');
} catch (PDOException $e) {
    http_response_code(500);
    out(false, 'DB error: ' . $e->getMessage());
}