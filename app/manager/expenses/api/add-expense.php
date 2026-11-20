<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
// JSON version ng requireRole(): kapag expired ang session o hindi manager,
// JSON + tamang status code ang sagot (hindi HTML redirect).
apiRequireRole('manager');
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
// Kung walang category na ipinadala (lumang form), Operational ang default.
$category    = trim($_POST['category'] ?? 'Operational');

// Category: dapat nasa listahan lang (functions.php -> getExpenseCategories())
if (!in_array($category, getExpenseCategories(), true)) {
    out(false, 'Please choose a valid category.');
}

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
         VALUES (:date, :category, :description, :amount, :recorded_by)"
    );
    $stmt->execute([
        ':date'        => $date,
        ':category'    => $category,
        ':description' => $description,
        ':amount'      => $amount,
        ':recorded_by' => $recordedBy,
    ]);
    out(true, 'Expense added.');
} catch (PDOException $e) {
    // I-log lang ang detalye; huwag ipakita sa user.
    error_log('add-expense: ' . $e->getMessage());
    http_response_code(500);
    out(false, 'Could not save the expense. Please try again.');
}