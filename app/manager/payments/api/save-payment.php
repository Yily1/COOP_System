<?php
require_once '../../../../config/config.php';
require_once '../../../../config/functions.php';
require_once '../../../../config/payment-functions.php';
require_once '../../../../config/rental-split.php';

requireRole('manager');

header('Content-Type: application/json');

$response = ['success' => false, 'errors' => []];

$payerType = $_POST['payer_type'] ?? 'member';
$memberId = $_POST['member_id'] ?? '';
$payerName = trim($_POST['payer_name'] ?? '');
$paymentType = $_POST['payment_type'] ?? '';
$amount = $_POST['amount'] ?? '';
$paymentDate = $_POST['payment_date'] ?? '';
$notes = trim($_POST['notes'] ?? '');
$productName = trim($_POST['product_name'] ?? '');
$equipment = trim($_POST['equipment'] ?? '');
$quantity = $_POST['quantity'] ?? '';

if ($payerType === 'non_member') {
    $memberId = null;
    $allowedTypes = ['rental'];

    if ($payerName === '') {
        $response['errors'][] = 'Ilagay ang pangalan ng nagbayad.';
    } elseif (mb_strlen($payerName) > 150) {
        $response['errors'][] = 'Masyadong mahaba ang pangalan.';
    }
} else {
    $payerName = null;
    // Same list as the Payment type dropdown in the Add payment form.
    // (Loan repayments are not recorded here.)
    $allowedTypes = ['registration', 'investment', 'rental', 'product'];

    if (empty($memberId)) {
        $response['errors'][] = 'Piliin ang member.';
    } else {
        $checkMember = $pdo->prepare("SELECT id FROM members WHERE id = ?");
        $checkMember->execute([$memberId]);
        if (!$checkMember->fetch()) {
            $response['errors'][] = 'Hindi nahanap ang member.';
        }
    }
}

if (!in_array($paymentType, $allowedTypes, true)) {
    $response['errors'][] = 'Piliin ang tamang payment type.';
}

if ($paymentType === 'product') {
    if ($productName === '') {
        $response['errors'][] = 'Ilagay ang pangalan ng product.';
    } elseif (mb_strlen($productName) > 150) {
        $response['errors'][] = 'Masyadong mahaba ang pangalan ng product.';
    }
} else {
    $productName = null;
}

// Rental payments must say which equipment was rented
if ($paymentType === 'rental') {
    $rentalCheck = validateRentalInput($equipment, $quantity);
    foreach ($rentalCheck['errors'] as $err) {
        $response['errors'][] = $err;
    }
    $quantity = $rentalCheck['quantity'];
} else {
    $equipment = null;
    $quantity = null;
}

if (!is_numeric($amount) || $amount <= 0) {
    $response['errors'][] = 'Ilagay ang tamang amount.';
}

$dateObj = DateTime::createFromFormat('Y-m-d', (string) $paymentDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $paymentDate) {
    $response['errors'][] = 'Piliin ang tamang petsa ng payment.';
}

if (empty($response['errors'])) {
    try {
        // One INSERT saves the payment AND its operator / repair split,
        // so they can never be saved separately.
        $newId = recordPayment(
            $pdo,
            $memberId,
            $paymentType,
            (float) $amount,
            $paymentDate,
            $_SESSION['user_id'],
            $notes !== '' ? $notes : null,
            $productName,
            $payerName,
            $equipment,
            $quantity
        );

        if (!$newId) {
            throw new RuntimeException('Payment was not saved.');
        }

        $stmt = $pdo->prepare("
            SELECT p.*, m.first_name, m.last_name, m.membership_id, u.email as recorded_by_email
            FROM payments p
            LEFT JOIN members m ON p.member_id = m.id
            LEFT JOIN users u ON p.recorded_by = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$newId]);

        $response['success'] = true;
        $response['payment'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('save-payment: ' . $e->getMessage());
        $response['errors'][] = 'May error sa server. Subukan ulit mamaya.';
    }
}

echo json_encode($response);