<?php

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../config/functions.php';

requireRole('manager');
header('Content-Type: application/json');

$loanId = $_POST['loan_id'] ?? '';
$action = $_POST['action'] ?? '';

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
        // release: no term. The rate is saved on the loan so an old loan never
        // changes if the rate is changed later. term_months, due_date and
        // total_due stay empty because the amount due grows every month until paid.
        if ($loan['status'] !== 'approved') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Only approved loans can be released.']);
            exit;
        }

        $interestRate = LOAN_INTEREST_RATE_MONTHLY;

        $stmt = $pdo->prepare("
            UPDATE loans
            SET status = 'released',
                released_at = NOW(),
                term_months = NULL,
                due_date = NULL,
                interest_rate = ?,
                total_due = NULL
            WHERE id = ?
        ");
        $stmt->execute([$interestRate, $loanId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}