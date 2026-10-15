<?php
ob_start(); // keep stray PHP warnings from corrupting the JSON
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';
if (!function_exists('logActivity') && file_exists(__DIR__ . '/../../../../includes/activity-logger.php')) {
    require_once __DIR__ . '/../../../../includes/activity-logger.php';
}

header('Content-Type: application/json');

function ueRespond($success, $message) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}
function ueLog($pdo, $action, $status) {
    try {
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['email'] ?? '', $action, $status);
    } catch (Throwable $e) {
        error_log('logActivity failed (' . $action . '): ' . $e->getMessage());
    }
}

if (!isLoggedIn() || !hasRole('manager')) {
    http_response_code(403);
    ueRespond(false, 'Only managers can edit expenses.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ueRespond(false, 'Invalid request.');
}

// Keep this list in sync with expenses.php
$categories = ['Supplies', 'Repairs and maintenance', 'Transport', 'Utilities', 'Salaries', 'Others'];

$id          = (int)($_POST['id'] ?? 0);
$date        = trim($_POST['expense_date'] ?? '');
$category    = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$amount      = $_POST['amount'] ?? '';

if ($id <= 0) {
    ueRespond(false, 'Invalid expense.');
}
$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    ueRespond(false, 'Please choose a valid date.');
}
if (!in_array($category, $categories, true)) {
    ueRespond(false, 'Please choose a valid category.');
}
if ($description === '' || mb_strlen($description) > 255) {
    ueRespond(false, 'Please enter a description (up to 255 characters).');
}
if (!is_numeric($amount) || (float)$amount <= 0) {
    ueRespond(false, 'Please enter an amount greater than zero.');
}

try {
    $stmt = $pdo->prepare("
        UPDATE expenses
        SET expense_date = ?, category = ?, description = ?, amount = ?
        WHERE id = ?
    ");
    $stmt->execute([$date, $category, $description, round((float)$amount, 2), $id]);

    ueLog('expense_updated', 'success');
    ueRespond(true, 'Expense updated.');
} catch (PDOException $e) {
    error_log('update-expense: ' . $e->getMessage());
    ueLog('expense_updated', 'failed');
    ueRespond(false, 'Could not update the expense. Please try again.');
}