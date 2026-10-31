<?php
/**
 * ============================================================
 * PAYMENT & ATTENDANCE HELPER FUNCTIONS (v4 - rental split)
 * Ito ang laman ng config/payment-functions.php
 * ============================================================
 */

require_once __DIR__ . '/rental-split.php';

const REGISTRATION_FEE_REQUIRED = 150.00;
const INVESTMENT_TARGET_FOR_REGULAR = 1500.00;
const MEETINGS_REQUIRED_FOR_ASSOCIATE = 3;

// ------------------------------------------------------------
// GCash account kung saan magbabayad ang mga miyembro.
// SAMPLE LANG ITO - palitan ng totoong pangalan at number ng president.
// (Ito lang ang lugar na kailangan mong i-edit; lalabas ito sa
// "Pay via GCash to" box sa user payments page.)
// ------------------------------------------------------------
const COOP_GCASH_ACCOUNT_NAME = 'Lielane Marie Dagdagan';
const COOP_GCASH_NUMBER = '0917 123 4567';

/**
 * Kabuuang na-bayad na registration fee ng isang member.
 * CONFIRMED lang ang binibilang, hindi pending.
 */
function getTotalRegistrationPaid($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM payments
        WHERE member_id = ? AND payment_type = 'registration' AND status = 'confirmed'
    ");
    $stmt->execute([$memberId]);
    return (float) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

/**
 * Kabuuang na-bayad na investment ng isang member.
 * CONFIRMED lang ang binibilang, hindi pending.
 */
function getTotalInvestmentPaid($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM payments
        WHERE member_id = ? AND payment_type = 'investment' AND status = 'confirmed'
    ");
    $stmt->execute([$memberId]);
    return (float) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

/**
 * Ilang assembly meetings ang na-attend ng isang member.
 */
function getMeetingsAttendedCount($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM meeting_attendance
        WHERE member_id = ?
    ");
    $stmt->execute([$memberId]);
    return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

/**
 * Buong payment history ng isang member (pending AT confirmed),
 * pinaka-bago munang lalabas.
 */
function getPaymentHistory($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.email as recorded_by_email
        FROM payments p
        LEFT JOIN users u ON p.recorded_by = u.id
        WHERE p.member_id = ?
        ORDER BY p.payment_date DESC, p.created_at DESC
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Kompletong eligibility summary ng isang member.
 */
function getMemberEligibility($pdo, $memberId) {
    $registrationPaid = getTotalRegistrationPaid($pdo, $memberId);
    $meetingsAttended = getMeetingsAttendedCount($pdo, $memberId);
    $totalInvestment = getTotalInvestmentPaid($pdo, $memberId);

    $isRegistrationComplete = $registrationPaid >= REGISTRATION_FEE_REQUIRED;
    $isMeetingsComplete = $meetingsAttended >= MEETINGS_REQUIRED_FOR_ASSOCIATE;

    return [
        'registration_paid' => $isRegistrationComplete,
        'registration_amount' => $registrationPaid,
        'meetings_attended' => $meetingsAttended,
        'meetings_required' => MEETINGS_REQUIRED_FOR_ASSOCIATE,
        'eligible_for_associate' => $isRegistrationComplete && $isMeetingsComplete,
        'total_investment' => $totalInvestment,
        'investment_target' => INVESTMENT_TARGET_FOR_REGULAR,
        'eligible_for_regular' => $totalInvestment >= INVESTMENT_TARGET_FOR_REGULAR,
    ];
}

/**
 * Para sa Rental: ibalik ang equipment, quantity, operator share at repair share
 * na ilalagay sa INSERT. Para sa ibang type, puro NULL.
 * Returns [equipment, quantity, operator, repair].
 */
function rentalColumnsForInsert($paymentType, $amount, $equipment, $quantity) {
    if ($paymentType !== 'rental' || !$equipment) {
        return [null, null, null, null];
    }
    $split = calculateRentalSplit($equipment, $amount, $quantity);
    $qty = ($quantity !== null && $quantity !== '') ? (float) $quantity : null;
    return [$equipment, $qty, $split['operator'], $split['repair']];
}

/**
 * I-record ang bagong payment bilang CONFIRMED - ginagamit ng MANAGER.
 * $productName ay para sa product payments, $payerName ay para sa non-member.
 * $equipment at $quantity ay para sa rental; ang hati (operator / repair) ay
 * kinukuwenta at sine-save sa iisang INSERT, kaya hindi nagkakahiwalay.
 * Returns ang bagong payment ID, o false kapag nabigo.
 */
function recordPayment($pdo, $memberId, $paymentType, $amount, $paymentDate, $recordedBy, $notes = null, $productName = null, $payerName = null, $equipment = null, $quantity = null) {
    list($equip, $qty, $operator, $repair) = rentalColumnsForInsert($paymentType, $amount, $equipment, $quantity);

    $stmt = $pdo->prepare("
        INSERT INTO payments
            (member_id, payer_name, payment_type, product_name, equipment, quantity, operator_share, repair_share,
             amount, payment_date, recorded_by, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
    ");
    $ok = $stmt->execute([$memberId, $payerName, $paymentType, $productName, $equip, $qty, $operator, $repair, $amount, $paymentDate, $recordedBy, $notes]);
    return $ok ? (int) $pdo->lastInsertId() : false;
}

/**
 * I-submit ang bagong payment bilang PENDING - ginagamit ng MEMBER/USER mismo.
 * $submittedBy ay ang user_id ng member (para malaman kung sino nag-submit).
 * $proofOfPayment ay ang file path (relative sa project root) ng na-upload
 * na screenshot ng GCash payment.
 * Para sa Rental, kasama ang equipment, quantity at hati sa INSERT.
 * Returns ang bagong payment ID, o false kapag nabigo.
 */
function submitPendingPayment($pdo, $memberId, $paymentType, $amount, $submittedBy, $proofOfPayment = null, $productName = null, $equipment = null, $quantity = null) {
    list($equip, $qty, $operator, $repair) = rentalColumnsForInsert($paymentType, $amount, $equipment, $quantity);

    $stmt = $pdo->prepare("
        INSERT INTO payments
            (member_id, payment_type, product_name, equipment, quantity, operator_share, repair_share,
             amount, payment_date, recorded_by, notes, proof_of_payment, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'Submitted via GCash by member', ?, 'pending')
    ");
    $ok = $stmt->execute([$memberId, $paymentType, $productName, $equip, $qty, $operator, $repair, $amount, $submittedBy, $proofOfPayment]);
    return $ok ? (int) $pdo->lastInsertId() : false;
}

/**
 * I-confirm ang isang pending payment - MANAGER lang ang pwede.
 * True lang kapag may talagang na-update na row.
 */
function confirmPayment($pdo, $paymentId) {
    $stmt = $pdo->prepare("UPDATE payments SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
    $stmt->execute([$paymentId]);
    return $stmt->rowCount() > 0;
}

/**
 * I-reject/tanggalin ang isang pending payment - MANAGER lang ang pwede.
 * True lang kapag may talagang natanggal na row.
 */
function rejectPayment($pdo, $paymentId) {
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ? AND status = 'pending'");
    $stmt->execute([$paymentId]);
    return $stmt->rowCount() > 0;
}

/**
 * I-mark ang attendance ng isang member sa isang meeting.
 */
function markAttendance($pdo, $meetingId, $memberId, $recordedBy) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO meeting_attendance (meeting_id, member_id, recorded_by)
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$meetingId, $memberId, $recordedBy]);
}

/**
 * Tanggalin ang attendance mark.
 */
function unmarkAttendance($pdo, $meetingId, $memberId) {
    $stmt = $pdo->prepare("
        DELETE FROM meeting_attendance WHERE meeting_id = ? AND member_id = ?
    ");
    return $stmt->execute([$meetingId, $memberId]);
}