<?php
ob_start(); // keep stray PHP warnings from corrupting the JSON
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
if (!function_exists('logActivity') && file_exists(__DIR__ . '/../../../../includes/activity-logger.php')) {
    require_once __DIR__ . '/../../../../includes/activity-logger.php';
}

header('Content-Type: application/json');

function aeRespond($success, $message) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}
// Logging must never break the response
function aeLog($pdo, $action, $status) {
    try {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'] ?? '', $action, $status);
    } catch (Throwable $e) {
        error_log('logActivity failed (' . $action . '): ' . $e->getMessage());
    }
}

if (!isLoggedIn() || !hasRole('manager')) {
    http_response_code(403);
    aeRespond(false, 'Only managers can add expenses.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    aeRespond(false, 'Invalid request.');
}

// Keep this list in sync with expenses.php
$categories = ['Supplies', 'Repairs and maintenance', 'Transport', 'Utilities', 'Salaries', 'Others'];

$date        = trim($_POST['expense_date'] ?? '');
$category    = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$amount      = $_POST['amount'] ?? '';

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    aeRespond(false, 'Please choose a valid date.');
}
if (!in_array($category, $categories, true)) {
    aeRespond(false, 'Please choose a valid category.');
}
if ($description === '' || mb_strlen($description) > 255) {
    aeRespond(false, 'Please enter a description (up to 255 characters).');
}
if (!is_numeric($amount) || (float)$amount <= 0) {
    aeRespond(false, 'Please enter an amount greater than zero.');
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO expenses (expense_date, category, description, amount, recorded_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$date, $category, $description, round((float)$amount, 2), $_SESSION['user_id']]);

    aeLog('expense_added', 'success');
    aeRespond(true, 'Expense added.');
} catch (PDOException $e) {
    error_log('add-expense: ' . $e->getMessage());
    aeLog('expense_added', 'failed');
    aeRespond(false, 'Could not save the expense. Please try again.');
}