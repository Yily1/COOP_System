<?php
require_once '../../../config/config.php';
require_once '../../../config/functions.php';
require_once '../../../config/payment-functions.php';

requireRole('user');

header('Content-Type: application/json');

$response = ['success' => false, 'errors' => []];

try {
    $userId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $memberId = $stmt->fetchColumn();

    if (!$memberId) {
        $response['errors'][] = 'Walang member profile na naka-link sa account mo.';
        echo json_encode($response);
        exit;
    }

    $paymentType = $_POST['payment_type'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $productName = trim($_POST['product_name'] ?? '');

    $allowedTypes = ['registration', 'investment', 'rental', 'loan_repayment', 'product'];
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

    if (!is_numeric($amount) || $amount <= 0) {
        $response['errors'][] = 'Ilagay ang tamang amount.';
    }

    $proofPath = null;
    $file = $_FILES['proof_of_payment'] ?? null;
    $ext = null;

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $response['errors'][] = 'Mag-upload ng screenshot ng payment.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $response['errors'][] = 'Hindi na-upload ang file. Subukan ulit.';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $response['errors'][] = 'Hanggang 5MB lang ang pwedeng i-upload.';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($allowedMimes[$mime])) {
            $response['errors'][] = 'PNG o JPG lang ang pwedeng i-upload.';
        } else {
            $ext = $allowedMimes[$mime];
        }
    }

    if (empty($response['errors'])) {
        $uploadDir = __DIR__ . '/../../../uploads/payment-proofs';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            throw new RuntimeException('Hindi magawa ang upload folder.');
        }

        $fileName = 'proof_' . $memberId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $fileName)) {
            throw new RuntimeException('Hindi ma-save ang na-upload na file.');
        }
        $proofPath = 'uploads/payment-proofs/' . $fileName;

        submitPendingPayment($pdo, $memberId, $paymentType, $amount, $userId, $proofPath, $productName);

        $response['success'] = true;
    }
} catch (Throwable $e) {
    $response['success'] = false;
    $response['errors'][] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);