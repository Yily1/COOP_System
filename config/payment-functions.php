<?php
/**
 * ============================================================
 * PAYMENT & ATTENDANCE HELPER FUNCTIONS (v2 - may pending/confirmed status)
 * Palitan ang laman ng config/payment-functions.php mo ng file na ito.
 * ============================================================
 */

const REGISTRATION_FEE_REQUIRED = 150.00;
const INVESTMENT_TARGET_FOR_REGULAR = 1500.00;
const MEETINGS_REQUIRED_FOR_ASSOCIATE = 3;

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
 * I-record ang bagong payment bilang CONFIRMED - ginagamit ng MANAGER.
 */
function recordPayment($pdo, $memberId, $paymentType, $amount, $paymentDate, $recordedBy, $notes = null) {
    $stmt = $pdo->prepare("
        INSERT INTO payments (member_id, payment_type, amount, payment_date, recorded_by, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, 'confirmed')
    ");
    return $stmt->execute([$memberId, $paymentType, $amount, $paymentDate, $recordedBy, $notes]);
}

/**
 * I-submit ang bagong payment bilang PENDING - ginagamit ng MEMBER/USER mismo.
 * $submittedBy ay ang user_id ng member (para malaman kung sino nag-submit).
 */
function submitPendingPayment($pdo, $memberId, $paymentType, $amount, $submittedBy) {
    $stmt = $pdo->prepare("
        INSERT INTO payments (member_id, payment_type, amount, payment_date, recorded_by, notes, status)
        VALUES (?, ?, ?, CURDATE(), ?, 'Submitted via GCash by member', 'pending')
    ");
    return $stmt->execute([$memberId, $paymentType, $amount, $submittedBy]);
}

/**
 * I-confirm ang isang pending payment - MANAGER lang ang pwede.
 */
function confirmPayment($pdo, $paymentId) {
    $stmt = $pdo->prepare("UPDATE payments SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
    return $stmt->execute([$paymentId]);
}

/**
 * I-reject/tanggalin ang isang pending payment - MANAGER lang ang pwede.
 */
function rejectPayment($pdo, $paymentId) {
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ? AND status = 'pending'");
    return $stmt->execute([$paymentId]);
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