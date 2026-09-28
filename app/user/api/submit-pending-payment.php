<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../config/payment-functions.php';

requireRole('user');

header('Content-Type: application/json');

$response = ['success' => false, 'errors' => []];

$paymentType = $_POST['payment_type'] ?? '';
$amount = $_POST['amount'] ?? '';

if (!in_array($paymentType, ['registration', 'investment', 'rental', 'loan_repayment'])) {
    $response['errors'][] = 'Piliin ang tamang payment type.';
}
if (!is_numeric($amount) || $amount <= 0) {
    $response['errors'][] = 'Ilagay ang tamang amount.';
}

// ---- Proof of payment (REQUIRED) ----
$file = $_FILES['proof_of_payment'] ?? null;
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
$maxBytes = 5 * 1024 * 1024; // 5 MB
$proofExt = null;

if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
    $response['errors'][] = 'Mag-upload muna ng screenshot ng payment (proof of payment).';
} elseif ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    $response['errors'][] = 'Masyadong malaki ang file. Subukan ang mas maliit na screenshot.';
} elseif ($file['error'] !== UPLOAD_ERR_OK) {
    $response['errors'][] = 'Hindi na-upload ang file. Subukan ulit.';
} else {
    // Check the actual file content, not just the filename extension.
    $mimeType = mime_content_type($file['tmp_name']);
    if (!isset($allowedTypes[$mimeType])) {
        $response['errors'][] = 'PNG o JPG lang ang pwedeng i-upload.';
    } elseif ($file['size'] > $maxBytes) {
        $response['errors'][] = 'Hanggang 5MB lang ang pwedeng i-upload.';
    } else {
        $proofExt = $allowedTypes[$mimeType];
    }
}

if (empty($response['errors'])) {
    // Kunin ang member_id ng kasalukuyang naka-login na user
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $memberId = $stmt->fetch(PDO::FETCH_ASSOC)['member_id'] ?? null;

    if (empty($memberId)) {
        $response['errors'][] = 'Walang naka-link na member profile sa account mo.';
    } else {
        // I-save muna ang file bago i-INSERT ang payment.
        $uploadDir = __DIR__ . '/../../../assets/uploads/payment-proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = 'proof_' . bin2hex(random_bytes(8)) . '.' . $proofExt;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $response['errors'][] = 'Hindi ma-save ang na-upload na file. Subukan ulit.';
        } else {
            $proofPath = 'assets/uploads/payment-proofs/' . $filename;
            submitPendingPayment($pdo, $memberId, $paymentType, $amount, $userId, $proofPath);
            $response['success'] = true;
        }
    }
}

echo json_encode($response);