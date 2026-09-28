<?php
require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('manager');
header('Content-Type: application/json');

$loanId = $_POST['loan_id'] ?? '';
$action = $_POST['action'] ?? '';
$termMonths = $_POST['term_months'] ?? '';

if (!$loanId || !ctype_digit((string)$loanId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid loan reference.']);
    exit;
}
if (!in_array($action, ['approve', 'reject', 'release'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? FOR UPDATE");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Loan request not found.']);
        exit;
    }

    if ($action === 'approve' || $action === 'reject') {
        if ($loan['status'] !== 'pending') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'This loan was already handled.']);
            exit;
        }
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE loans SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->execute([$newStatus, $_SESSION['user_id'], $loanId]);
    } else {
        // release: requires a loan term (in months) so we can compute
        // the due date and the total amount due (principal + interest).
        if ($loan['status'] !== 'approved') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Only approved loans can be released.']);
            exit;
        }
        if ($termMonths === '' || !ctype_digit((string)$termMonths) || (int)$termMonths < 1) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Enter a valid loan term (whole number of months).']);
            exit;
        }

        $termMonths = (int) $termMonths;
        $interestRate = LOAN_INTEREST_RATE_MONTHLY;
        $principal = (float) $loan['amount'];
        $totalDue = round($principal + ($principal * $interestRate / 100 * $termMonths), 2);

        $stmt = $pdo->prepare("
            UPDATE loans
            SET status = 'released',
                released_at = NOW(),
                term_months = ?,
                due_date = DATE_ADD(CURDATE(), INTERVAL ? MONTH),
                interest_rate = ?,
                total_due = ?
            WHERE id = ?
        ");
        $stmt->execute([$termMonths, $termMonths, $interestRate, $totalDue, $loanId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}