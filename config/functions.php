<?php

// Loan ledger / interest helpers (single source for loan balances)
require_once __DIR__ . '/loan-interest.php';

function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * True kung Inactive na ang account ng naka-login (o wala na ang account
 * sa database). Tinitingnan sa database sa bawat page, kaya kahit may
 * bukas pang session, agad siyang haharangin kapag na-Inactive.
 * Kapag nag-error ang database mismo, hindi natin ila-lock out ang user.
 */
function isAccountInactive() {
    global $pdo;
    if (!isset($_SESSION['user_id']) || !isset($pdo)) {
        return false;
    }
    try {
        $stmt = $pdo->prepare('SELECT account_status FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('isAccountInactive failed: ' . $e->getMessage());
        return false;
    }
    // false = wala nang ganung user; 'Inactive' = naka-deactivate
    return $status === false || $status === 'Inactive';
}

/**
 * Buburahin ang buong session (data + cookie).
 */
function forceLogoutInactive() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/index.php');
    }
    if (isAccountInactive()) {
        forceLogoutInactive();
        redirect('/login.php?inactive=1');
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        die("Access denied. Required role: $role");
    }
}

/**
 * API versions of requireLogin()/requireRole().
 * Imbes na redirect o die(), JSON + tamang status code ang sagot.
 */
function apiRequireRole($role) {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized. Please log in.']);
        exit;
    }
    if (isAccountInactive()) {
        forceLogoutInactive();
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Your account is inactive.', 'inactive' => true]);
        exit;
    }
    if (!hasRole($role)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => "Forbidden. Required role: $role"]);
        exit;
    }
}

/**
 * Central permission rules for editing/deleting a target user.
 * Used by BOTH the UI (to show/hide links) and the actual
 * update/delete scripts (to actually enforce it). Previously
 * only the UI checked this, so anyone could bypass it by hitting
 * user-update.php / user-delete.php directly with a user_id.
 */
function canEditUser($currentRole, $currentUserId, $targetRole, $targetUserId) {
    // Everyone can edit their own account.
    if ($targetUserId == $currentUserId) {
        return true;
    }
    // Admin manages staff accounts (Admin/Manager).
    if ($currentRole === 'admin') {
        return in_array($targetRole, ['admin', 'manager']);
    }
    // Manager manages regular User (member) accounts.
    if ($currentRole === 'manager') {
        return $targetRole === 'user';
    }
    return false;
}

function canDeleteUser($currentRole, $currentUserId, $targetRole, $targetUserId) {
    // Nobody can delete their own account.
    if ($targetUserId == $currentUserId) {
        return false;
    }
    if ($currentRole === 'admin') {
        return in_array($targetRole, ['admin', 'manager']);
    }
    if ($currentRole === 'manager') {
        return $targetRole === 'user';
    }
    return false;
}

/**
 * Only admins can change a role, and never their own.
 */
function canChangeRole($currentRole, $currentUserId, $targetUserId) {
    if ($targetUserId == $currentUserId) {
        return false;
    }
    return $currentRole === 'admin';
}

/**
 * Only manager and user (member) roles may view the Equipment Rental page.
 */
function canAccessEquipment($role) {
    return in_array($role, ['manager', 'user'], true);
}

/**
 * Total number of equipment items (all statuses).
 */
function getTotalEquipmentCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM equipment");
    return (int) $stmt->fetchColumn();
}

/**
 * How many bookings started within the current calendar month,
 * across all equipment (used for the "Rented this month" summary card).
 */
function getRentedThisMonthCount($pdo) {
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM equipment_bookings
        WHERE start_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
          AND start_date < DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')
    ");
    return (int) $stmt->fetchColumn();
}

/**
 * Flips 'ongoing' bookings whose end_date has already passed to 'overdue'.
 * Runs whenever the bookings lists are loaded, so no cron job is needed.
 * ('approved' bookings that were never started are left alone.)
 */
function markOverdueBookings($pdo) {
    $pdo->exec("
        UPDATE equipment_bookings
        SET status = 'overdue'
        WHERE status = 'ongoing'
          AND end_date < CURDATE()
    ");
}

/**
 * All bookings still relevant to the manager's "Currently rented" table
 * (pending approval, approved, ongoing, overdue, or returned), across ALL
 * members — unlike getMyEquipmentBookings(), which is scoped to a single user.
 */
function getCurrentEquipmentBookings($pdo) {
    markOverdueBookings($pdo);
    $stmt = $pdo->query("
        SELECT b.*, e.name AS equipment_name
        FROM equipment_bookings b
        INNER JOIN equipment e ON e.id = b.equipment_id
        WHERE b.status IN ('pending', 'approved', 'ongoing', 'overdue', 'returned')
        ORDER BY (b.status = 'returned') ASC, b.start_date DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Inaayos ang equipment bookings para sa "Currently rented" table (PHP sort, hindi SQL):
 *   1) PENDING (Approve/Reject) sa pinakataas
 *   2) APPROVED (Use) sunod
 *   3) ONGOING / OVERDUE (Mark returned) sunod
 *   4) RETURNED sa pinakababa
 * Pending/Approved/Ongoing/Overdue: pataas ang start date (pinakaluma muna).
 * Returned: pababa ang start date (pinakabago muna).
 * Kapag pareho ang date, sinusunod ang id.
 */
function sortEquipmentBookingsForManager(array $bookings) {
    $rank = function ($status) {
        if ($status === 'pending')  return 0;
        if ($status === 'approved') return 1;
        if (in_array($status, ['ongoing', 'overdue'], true)) return 2;
        return 3; // returned
    };

    usort($bookings, function ($a, $b) use ($rank) {
        $ra = $rank($a['status']);
        $rb = $rank($b['status']);

        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        $dateCmp = strcmp($a['start_date'], $b['start_date']);

        if ($ra < 3) {
            // Hindi pa returned: pataas ang date (pinakaluma muna).
            if ($dateCmp !== 0) return $dateCmp;
            return ((int) $a['id']) <=> ((int) $b['id']);
        }

        // Returned: pababa ang date (pinakabago muna).
        if ($dateCmp !== 0) return -$dateCmp;
        return ((int) $b['id']) <=> ((int) $a['id']);
    });

    return $bookings;
}

/**
 * Bookings-this-month count per equipment, e.g. [3 => 5, 7 => 2],
 * for the per-equipment summary cards ($monthlyRentCounts[$eq['id']]).
 */
function getMonthlyRentCountByEquipment($pdo) {
    $stmt = $pdo->query("
        SELECT equipment_id, COUNT(*) AS cnt
        FROM equipment_bookings
        WHERE start_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
          AND start_date < DATE_FORMAT(NOW() + INTERVAL 1 MONTH, '%Y-%m-01')
        GROUP BY equipment_id
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['equipment_id']] = (int) $row['cnt'];
    }
    return $counts;
}

/**
 * All equipment items, for the browse grid and the booking form's dropdown.
 */
function getAllEquipment($pdo) {
    $stmt = $pdo->query("SELECT * FROM equipment ORDER BY name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * All ACTIVE bookings whose date range overlaps the given month, for the
 * Booking Schedule calendar (get-month-booking.php).
 * Overlap check: start <= last day of month AND end >= first day of month,
 * so multi-day bookings that started in an earlier month but run into this
 * one still show up on the calendar.
 * Returned / rejected / cancelled bookings are left out, since they no
 * longer occupy the equipment.
 */
function getBookingsForMonth($pdo, $year, $month) {
    // Make sure ongoing bookings that already passed their end date show as overdue
    markOverdueBookings($pdo);

    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $stmt = $pdo->prepare("
        SELECT b.*, e.name AS equipment_name
        FROM equipment_bookings b
        INNER JOIN equipment e ON e.id = b.equipment_id
        WHERE b.start_date <= LAST_DAY(:month_start_a)
          AND b.end_date >= :month_start_b
          AND b.status IN ('pending', 'approved', 'ongoing', 'overdue')
        ORDER BY b.start_date ASC
    ");
    // Named placeholders must be unique when PDO emulated prepares are off,
    // so the same date is bound twice under two different names.
    $stmt->execute([
        ':month_start_a' => $monthStart,
        ':month_start_b' => $monthStart,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * A meeting is open for check-in until the END of its date (Philippine time).
 * Once the date has passed, check-in is closed.
 */
function isMeetingCheckInOpen($meetingDate) {
    $today = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    return $meetingDate >= $today;
}

/* ============================================================
   COOPERATIVE SERVICES: LOANS
   ============================================================ */

// Members need at least this much in confirmed investment payments
// before they're allowed to request a loan at all.
define('LOAN_ELIGIBILITY_THRESHOLD', 1500.00);

// Simple interest per month. Guarded because loan-interest.php also defines it.
if (!defined('LOAN_INTEREST_RATE_MONTHLY')) {
    define('LOAN_INTEREST_RATE_MONTHLY', 10.00);
}

/**
 * Total confirmed 'investment'-type payments a member has made.
 * This is also, by the coop's current rule, their loanable amount
 * once they clear LOAN_ELIGIBILITY_THRESHOLD.
 */
function getMemberInvestment($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM payments
        WHERE member_id = :member_id
          AND payment_type = 'investment'
          AND status = 'confirmed'
    ");
    $stmt->execute([':member_id' => $memberId]);
    return (float) $stmt->fetchColumn();
}

/**
 * Loanable amount = the member's total investment, but only once
 * they've crossed LOAN_ELIGIBILITY_THRESHOLD. Below that, it's 0
 * (not eligible to request a loan yet).
 */
function getLoanableAmount($pdo, $memberId) {
    $investment = getMemberInvestment($pdo, $memberId);
    return $investment >= LOAN_ELIGIBILITY_THRESHOLD ? $investment : 0.0;
}

/**
 * Outstanding loan balance, from the loan ledger (loan-interest.php):
 * principal + monthly interest of every released loan, minus the confirmed
 * loan_repayment payments applied to each loan (oldest first).
 * Same source the manager's Loans page uses.
 */
function getMemberLoanBalance($pdo, $memberId) {
    return memberLoanBalanceLive($pdo, (int) $memberId);
}

/**
 * Amount already reserved by loans that are not released yet
 * (pending or approved), so it counts against the member's credit.
 * Without this, a member could request several loans one after the
 * other before the manager releases the first one.
 * Rejected loans are not counted, so their credit is given back.
 */
function getReservedLoanAmount($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) FROM loans
        WHERE member_id = :member_id AND status IN ('pending', 'approved')
    ");
    $stmt->execute([':member_id' => $memberId]);
    return (float) $stmt->fetchColumn();
}

/**
 * How much more a member can still borrow right now:
 * loanable amount - balance of released loans - pending/approved loans.
 */
function getAvailableCredit($pdo, $memberId) {
    return max(0.0,
        getLoanableAmount($pdo, $memberId)
        - getMemberLoanBalance($pdo, $memberId)
        - getReservedLoanAmount($pdo, $memberId)
    );
}

/**
 * A member's own loan request history, newest first.
 */
function getMemberLoans($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT * FROM loans WHERE member_id = :member_id ORDER BY created_at DESC
    ");
    $stmt->execute([':member_id' => $memberId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * All loan requests, for the manager's review queue (with member info joined in).
 */
function getAllLoans($pdo) {
    $stmt = $pdo->query("
        SELECT l.*, m.first_name, m.last_name, m.membership_id
        FROM loans l
        INNER JOIN members m ON m.id = l.member_id
        ORDER BY (l.status = 'pending') DESC, l.created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Inaayos ang loan rows para sa manager's table (PHP sort, hindi SQL):
 *   1) PENDING sa taas, pataas ang date (pinakaluma muna).
 *   2) APPROVED (hinihintay ang Release) sa ilalim noon, pataas din ang date.
 *   3) RELEASED / PAID OFF / REJECTED sa baba, pababa ang date (pinakabago muna).
 * Petsa ng request (created_at) ang ginagamit. Kapag pareho, sinusunod ang id.
 */
function sortLoansForManager(array $loans) {
    $rank = function ($status) {
        if ($status === 'pending')  return 0;
        if ($status === 'approved') return 1;
        return 2;
    };

    usort($loans, function ($a, $b) use ($rank) {
        $ra = $rank($a['status']);
        $rb = $rank($b['status']);
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        $dateCmp = strcmp($a['created_at'], $b['created_at']);

        if ($ra < 2) {
            // Pending / Approved: pataas ang date (pinakaluma muna).
            if ($dateCmp !== 0) return $dateCmp;
            return ((int) $a['id']) <=> ((int) $b['id']);
        }

        // Released / Paid off / Rejected: pababa ang date (pinakabago muna).
        if ($dateCmp !== 0) return -$dateCmp;
        return ((int) $b['id']) <=> ((int) $a['id']);
    });

    return $loans;
}

/**
 * Coop-wide loan portfolio numbers. Now delegates to the ledger-based
 * version so every page shows the same figures.
 */
function getLoanPortfolioStats($pdo) {
    return loanPortfolioStatsLive($pdo);
}

/**
 * A released loan is overdue once its due_date has passed and it still
 * has an outstanding balance. Pass the balance of THAT loan (from
 * memberLoanLedger()) for the most accurate result.
 */
function isLoanOverdue($loan, $memberBalance) {
    return $loan['status'] === 'released'
        && !empty($loan['due_date'])
        && strtotime($loan['due_date']) < strtotime('today')
        && $memberBalance > 0;
}

function loanStatusBadge($status) {
    $map = [
        'pending'  => ['label' => 'Pending',  'bg' => '#fff3cd', 'text' => '#856404'],
        'approved' => ['label' => 'Approved', 'bg' => '#cce5ff', 'text' => '#004085'],
        'released' => ['label' => 'Released', 'bg' => '#d4edda', 'text' => '#155724'],
        'rejected' => ['label' => 'Rejected', 'bg' => '#fdecea', 'text' => '#c62828'],
    ];
    $m = $map[$status] ?? ['label' => ucfirst($status), 'bg' => '#eee', 'text' => '#555'];
    return '<span style="padding:2px 10px;border-radius:4px;font-size:12px;background:' . $m['bg'] . ';color:' . $m['text'] . ';white-space:nowrap;">' . htmlspecialchars($m['label']) . '</span>';
}

/* ============================================================
   COOPERATIVE SERVICES: PRODUCTS
   ============================================================ */

function getAllProducts($pdo) {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY category, name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * A member's own product requests, newest first (joins the product
 * name when it's a listed product; falls back to the free-text
 * requested_product_name for "we don't carry this yet" requests).
 */
function getMemberProductRequests($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT r.*, p.name AS product_name, p.unit AS product_unit
        FROM product_requests r
        LEFT JOIN products p ON p.id = r.product_id
        WHERE r.member_id = :member_id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':member_id' => $memberId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Product + request counts for the manager's summary cards.
 */
function getProductPortfolioStats($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $total = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'available'");
    $available = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'out_of_stock'");
    $outOfStock = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM product_requests WHERE status IN ('pending', 'under_review')");
    $pendingRequests = (int) $stmt->fetchColumn();

    return [
        'total'            => $total,
        'available'        => $available,
        'out_of_stock'     => $outOfStock,
        'pending_requests' => $pendingRequests,
    ];
}


/**
 * All product requests, for the manager's queue, with member + product info joined in.
 */
function getAllProductRequests($pdo) {
    $stmt = $pdo->query("
        SELECT r.*, p.name AS product_name, p.unit AS product_unit,
               m.first_name, m.last_name, m.membership_id
        FROM product_requests r
        LEFT JOIN products p ON p.id = r.product_id
        INNER JOIN members m ON m.id = r.member_id
        ORDER BY (r.status = 'pending') DESC, r.created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Inaayos ang product requests para sa manager's table (PHP sort, hindi SQL):
 *   1) PENDING / UNDER REVIEW (Approve/Reject) sa TAAS.
 *   2) APPROVED / PROCESSING / READY FOR PICKUP (may Update) sunod.
 *   3) CLAIMED / REJECTED sa BABA.
 * Sa loob ng bawat grupo: pinakabagong request (pinakamalaking id) sa taas,
 * pinakaluma sa baba.
 */
function sortProductRequestsForManager(array $requests) {
    $rank = function ($status) {
        if (in_array($status, ['pending', 'under_review'], true)) return 0;
        if (in_array($status, ['approved', 'processing', 'ready_for_pickup'], true)) return 1;
        return 2;
    };

    usort($requests, function ($a, $b) use ($rank) {
        $ra = $rank($a['status']);
        $rb = $rank($b['status']);
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return ((int) $b['id']) <=> ((int) $a['id']);
    });

    return $requests;
}

function productRequestStatusBadge($status) {
    $map = [
        'pending'          => ['label' => 'Pending',          'bg' => '#fff3cd', 'text' => '#856404'],
        'under_review'     => ['label' => 'Under Review',     'bg' => '#eeedfe', 'text' => '#3c3489'],
        'approved'         => ['label' => 'Approved',         'bg' => '#cce5ff', 'text' => '#004085'],
        'processing'       => ['label' => 'Processing',       'bg' => '#faeeda', 'text' => '#633806'],
        'ready_for_pickup' => ['label' => 'Ready for Pickup', 'bg' => '#d4edda', 'text' => '#155724'],
        'claimed'          => ['label' => 'Claimed',          'bg' => '#e2e0d5', 'text' => '#444'],
        'rejected'         => ['label' => 'Rejected',         'bg' => '#fdecea', 'text' => '#c62828'],
    ];
    $m = $map[$status] ?? ['label' => ucfirst($status), 'bg' => '#eee', 'text' => '#555'];
    return '<span style="padding:2px 10px;border-radius:4px;font-size:12px;background:' . $m['bg'] . ';color:' . $m['text'] . ';white-space:nowrap;">' . htmlspecialchars($m['label']) . '</span>';
}

/* ============================================================
   COOPERATIVE SERVICES: CROPS MANAGEMENT
   ============================================================ */

/**
 * Only manager and user (member) roles may view the Crops Management page.
 */
function canAccessCrops($role) {
    return in_array($role, ['manager', 'user'], true);
}

/**
 * A single member's own crop plantings (used on the user's own view).
 */
function getCropsForMember($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT c.*, m.first_name, m.last_name, m.membership_id
        FROM crops c
        INNER JOIN members m ON m.id = c.member_id
        WHERE c.member_id = :member_id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([':member_id' => $memberId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * All crop plantings across every member, for the manager's table
 * (same join pattern as getAllLoans() / getAllProductRequests()).
 */
function getAllCrops($pdo) {
    $stmt = $pdo->query("
        SELECT c.*, m.first_name, m.last_name, m.membership_id
        FROM crops c
        INNER JOIN members m ON m.id = c.member_id
        ORDER BY (c.status = 'pending') DESC, c.created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Inaayos ang crop rows para sa manager's table (PHP sort, hindi SQL):
 *   1) PENDING (Approve/Reject) sa pinakataas
 *   2) GROWING sunod
 *   3) READY TO HARVEST sunod
 *   4) HARVESTED sa baba
 *   5) REJECTED sa pinakababa
 * Pending/Growing/Ready: pataas ang date (pinakaluma muna).
 * Harvested/Rejected: pababa ang date (pinakabago muna).
 * Petsa ng pagkakatala (created_at) ang ginagamit. Kapag pareho, sinusunod ang id.
 */
function sortCropsForManager(array $crops) {
    $rank = function ($status) {
        if ($status === 'pending')          return 0;
        if ($status === 'growing')          return 1;
        if ($status === 'ready_to_harvest') return 2;
        if ($status === 'harvested')        return 3;
        return 4; // rejected
    };

    usort($crops, function ($a, $b) use ($rank) {
        $ra = $rank($a['status']);
        $rb = $rank($b['status']);

        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        $dateCmp = strcmp($a['created_at'], $b['created_at']);

        if ($ra < 3) {
            // Pending / Growing / Ready: pataas ang date (pinakaluma muna).
            if ($dateCmp !== 0) return $dateCmp;
            return ((int) $a['id']) <=> ((int) $b['id']);
        }

        // Harvested / Rejected: pababa ang date (pinakabago muna).
        if ($dateCmp !== 0) return -$dateCmp;
        return ((int) $b['id']) <=> ((int) $a['id']);
    });

    return $crops;
}

/**
 * Summary counts for the top cards. Pass $memberId to scope the
 * stats to one member; omit for coop-wide stats.
 */
function getCropStats($pdo, $memberId = null) {
    $sql = "SELECT status, COUNT(*) AS cnt FROM crops";
    $params = [];
    if ($memberId !== null) {
        $sql .= " WHERE member_id = :member_id";
        $params[':member_id'] = $memberId;
    }
    $sql .= " GROUP BY status";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'total'            => 0,
        'pending'          => 0,
        'rejected'         => 0,
        'growing'          => 0,
        'ready_to_harvest' => 0,
        'harvested'        => 0,
    ];

    foreach ($rows as $row) {
        $stats[$row['status']] = (int) $row['cnt'];
        $stats['total'] += (int) $row['cnt'];
    }

    return $stats;
}

function getCropById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM crops WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Listahan ng crops sa dropdown, galing sa crop_types table
 * (i-edit sa phpMyAdmin: pangalan, min_value, max_value, unit = days o months).
 */
function getCropTypes($pdo) {
    $stmt = $pdo->query("SELECT id, name, min_value, max_value, unit FROM crop_types ORDER BY name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCropTypeById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT id, name, min_value, max_value, unit FROM crop_types WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Expected harvest = planting date + ang HULING araw ng range ng crop
 * (max_value, sa days o months). Returns Y-m-d, o null kung mali ang petsa.
 */
function calculateCropExpectedHarvest($plantingDate, $maxValue, $unit) {
    $d = DateTime::createFromFormat('Y-m-d', (string) $plantingDate);
    if (!$d || $d->format('Y-m-d') !== $plantingDate) {
        return null;
    }
    $d->modify('+' . (int) $maxValue . ($unit === 'months' ? ' months' : ' days'));
    return $d->format('Y-m-d');
}

/**
 * Petsa ngayon (Philippine time) bilang Y-m-d.
 */
function cropTodayManila() {
    return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
}

/**
 * Bagong planting: Growing agad, walang approval.
 */
function addCropPlanting($pdo, $memberId, $cropName, $location, $areaHectares, $plantingDate, $expectedHarvestDate, $cropTypeId = null) {
    $stmt = $pdo->prepare("
        INSERT INTO crops (member_id, crop_type_id, crop_name, location, area_hectares, planting_date, expected_harvest_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'growing')
    ");
    return $stmt->execute([$memberId, $cropTypeId, $cropName, $location, $areaHectares, $plantingDate, $expectedHarvestDate]);
}

/**
 * Owner can only edit their own entry while it is still pending.
 * Manager can edit any entry regardless of status.
 */
function updateCropDetails($pdo, $id, $cropName, $location, $areaHectares, $plantingDate, $expectedHarvestDate) {
    $stmt = $pdo->prepare("
        UPDATE crops SET crop_name = ?, location = ?, area_hectares = ?, planting_date = ?, expected_harvest_date = ?
        WHERE id = ?
    ");
    return $stmt->execute([$cropName, $location, $areaHectares, $plantingDate, $expectedHarvestDate, $id]);
}

function approveCropPlanting($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE crops SET status = 'growing' WHERE id = ? AND status = 'pending'");
    return $stmt->execute([$id]);
}

function rejectCropPlanting($pdo, $id, $reason = null) {
    $stmt = $pdo->prepare("UPDATE crops SET status = 'rejected', rejection_reason = ? WHERE id = ? AND status = 'pending'");
    return $stmt->execute([$reason, $id]);
}

/**
 * Moves a growing crop forward: growing -> ready_to_harvest -> harvested.
 * Sets actual_harvest_date automatically when marked harvested.
 */
function advanceCropStatus($pdo, $id, $newStatus) {
    $allowed = ['ready_to_harvest', 'harvested'];
    if (!in_array($newStatus, $allowed, true)) {
        return false;
    }

    if ($newStatus === 'harvested') {
        $stmt = $pdo->prepare("UPDATE crops SET status = ?, actual_harvest_date = CURDATE() WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("UPDATE crops SET status = ? WHERE id = ?");
    }

    return $stmt->execute([$newStatus, $id]);
}

/**
 * HTML status badge, same visual pattern as equipmentStatusBadge() / loanStatusBadge().
 */
function cropStatusBadge($status) {
    $map = [
        'pending'          => ['label' => 'Pending approval', 'class' => 'cm-pending'],
        'rejected'         => ['label' => 'Rejected',         'class' => 'cm-rejected'],
        'growing'          => ['label' => 'Growing',           'class' => 'cm-growing'],
        'ready_to_harvest' => ['label' => 'Ready to harvest',  'class' => 'cm-ready'],
        'harvested'        => ['label' => 'Harvested',         'class' => 'cm-harvested'],
    ];

    $m = $map[$status] ?? ['label' => ucfirst($status), 'class' => ''];

    return '<span class="cm-status-badge ' . $m['class'] . '">' . htmlspecialchars($m['label']) . '</span>';
}

/* ============================================================
   COOPERATIVE SERVICES: DA RESOURCE DISTRIBUTION
   ============================================================ */

/**
 * Only managers use this page (giving out DA resources to eligible members).
 */
function canAccessResourceDistribution($role) {
    return $role === 'manager';
}

/**
 * Fixed list ng resources sa Add distribution dropdown.
 * Dagdagan o bawasan dito at susunod na ang dropdown at ang validation.
 */
function getDistributionResourceOptions() {
    return ['Fertilizer', 'Seeds', 'Pesticide', 'Herbicide'];
}

/**
 * Mga unit sa Unit dropdown: key (na nase-save) => label.
 */
function getDistributionUnitOptions() {
    return [
        'sack'  => 'Sack',
        'pcs'   => 'Pcs',
        'kg'    => 'Kg',
        'liter' => 'Liter',
        'can'   => 'Can',
    ];
}

/**
 * Quantity na ipapakita, hal. "5 sacks". Kapag walang unit (lumang record
 * na text ang quantity, hal. "5 sacks"), ipinapakita ito nang buo.
 */
function formatDistributionQuantity($quantity, $unit) {
    $quantity = trim((string) $quantity);
    $unit = trim((string) $unit);

    if ($unit === '' || !ctype_digit($quantity)) {
        return $quantity;
    }

    $n = (int) $quantity;
    $labels = [
        'sack'  => ['sack', 'sacks'],
        'pcs'   => ['pcs', 'pcs'],
        'kg'    => ['kg', 'kg'],
        'liter' => ['liter', 'liters'],
        'can'   => ['can', 'cans'],
    ];

    if (!isset($labels[$unit])) {
        return $n . ' ' . $unit;
    }

    return $n . ' ' . ($n === 1 ? $labels[$unit][0] : $labels[$unit][1]);
}

/**
 * Members who have at least one crop planting on record, whatever its
 * status (pending, growing, ready_to_harvest, harvested, rejected), for
 * the "Add distribution" member dropdown. A member with more than one
 * planting still appears once, and can be given resources any number
 * of times.
 */
function getEligibleMembersForDistribution($pdo) {
    $stmt = $pdo->query("
        SELECT DISTINCT m.id, m.first_name, m.last_name, m.membership_id
        FROM members m
        INNER JOIN crops c ON c.member_id = m.id
        ORDER BY m.last_name, m.first_name
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * All distribution records, newest first, with member info joined in
 * (same join pattern as getAllLoans() / getAllCrops()).
 * Ang SQL ay hindi ginalaw; ang ayos para sa manager's table ay ginagawa
 * sa PHP gamit ang sortDistributionsForManager() sa ibaba.
 */
function getAllDistributions($pdo) {
    $stmt = $pdo->query("
        SELECT d.*, m.first_name, m.last_name, m.membership_id
        FROM resource_distributions d
        INNER JOIN members m ON m.id = d.member_id
        ORDER BY d.distribution_date DESC, d.created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Inaayos ang distribution rows para sa manager's table (PHP sort, hindi SQL):
 *   1) NOT RELEASED (may Confirm/Decline) sa TAAS.
 *   2) RELEASED sa BABA.
 * Sa loob ng bawat grupo: ang pinakabagong na-add (pinakamalaking id)
 * ang nasa taas, ang pinakaunang na-add ang nasa baba.
 * Ang id ang ginagamit, hindi ang distribution date, kaya ang bagong
 * Add distribution ay laging nasa taas ng Not released.
 */
function sortDistributionsForManager(array $distributions) {
    usort($distributions, function ($a, $b) {
        $aReleased = ($a['status'] === 'released') ? 1 : 0;
        $bReleased = ($b['status'] === 'released') ? 1 : 0;

        if ($aReleased !== $bReleased) {
            return $aReleased <=> $bReleased;
        }

        return ((int) $b['id']) <=> ((int) $a['id']);
    });

    return $distributions;
}

function getDistributionById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM resource_distributions WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * A single member's own distribution records, newest first
 * (used on the member's read-only "My resources" page).
 */
function getDistributionsForMember($pdo, $memberId) {
    $stmt = $pdo->prepare("
        SELECT * FROM resource_distributions
        WHERE member_id = :member_id
        ORDER BY distribution_date DESC, created_at DESC
    ");
    $stmt->execute([':member_id' => $memberId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Inaayos ang distribution list ng member (PHP sort, hindi SQL):
 *   1) NOT RELEASED sa TAAS
 *   2) RELEASED sa baba
 * Sa loob ng bawat grupo: pinakabagong date muna.
 * Kapag pareho ang date, sinusunod ang id (pinakabago muna).
 */
function sortDistributionsForMember(array $distributions) {
    usort($distributions, function ($a, $b) {
        $aReleased = ($a['status'] === 'released') ? 1 : 0;
        $bReleased = ($b['status'] === 'released') ? 1 : 0;

        if ($aReleased !== $bReleased) {
            return $aReleased <=> $bReleased;
        }

        $dateCmp = strcmp($b['distribution_date'], $a['distribution_date']);
        if ($dateCmp !== 0) return $dateCmp;
        return ((int) $b['id']) <=> ((int) $a['id']);
    });

    return $distributions;
}

/**
 * Records a new distribution. Always starts as 'not_released' —
 * the manager confirms it once the resource is actually handed over.
 * $distributedBy is the logged-in manager's user id (users.id).
 * $quantity ay whole number (string), $unit ay key mula sa getDistributionUnitOptions().
 */
function addDistribution($pdo, $memberId, $resourceName, $quantity, $unit, $distributionDate, $notes, $distributedBy) {
    $stmt = $pdo->prepare("
        INSERT INTO resource_distributions (member_id, resource_name, quantity, unit, distribution_date, notes, status, distributed_by)
        VALUES (?, ?, ?, ?, ?, ?, 'not_released', ?)
    ");
    return $stmt->execute([$memberId, $resourceName, $quantity, $unit, $distributionDate, $notes, $distributedBy]);
}

/**
 * Confirms a distribution — marks it as actually handed over.
 */
function confirmDistribution($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE resource_distributions SET status = 'released' WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Declines a distribution — puts it back to 'not_released' (can
 * still be confirmed later; this does not delete the record).
 */
function declineDistribution($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE resource_distributions SET status = 'not_released' WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * HTML status badge for a distribution record — same visual pattern
 * as cropStatusBadge().
 */
function distributionStatusBadge($status) {
    if ($status === 'released') {
        return '<span class="rd-status-badge rd-released">Released</span>';
    }
    return '<span class="rd-status-badge rd-not-released">Not released</span>';
}


/**
 * The current user's own bookings. Rejected/cancelled bookings are left out.
 * Ayos (PHP sort, hindi SQL), kapareho ng grupo sa manager:
 *   1) PENDING
 *   2) APPROVED
 *   3) ONGOING / OVERDUE
 *   4) RETURNED sa pinakababa
 * Sa loob ng bawat grupo: pinakabagong start date sa taas, pinakaluma sa baba.
 * Kapag pareho ang date, sinusunod ang id (pinakabago muna).
 */
function getMyEquipmentBookings($pdo, $userId) {
    markOverdueBookings($pdo);
    $stmt = $pdo->prepare("
        SELECT b.*, e.name AS equipment_name
        FROM equipment_bookings b
        INNER JOIN equipment e ON e.id = b.equipment_id
        WHERE b.recorded_by = :user_id
          AND b.status IN ('pending', 'approved', 'ongoing', 'overdue', 'returned')
    ");
    $stmt->execute([':user_id' => $userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rank = function ($status) {
        if ($status === 'pending')  return 0;
        if ($status === 'approved') return 1;
        if (in_array($status, ['ongoing', 'overdue'], true)) return 2;
        return 3; // returned
    };

    usort($bookings, function ($a, $b) use ($rank) {
        $ra = $rank($a['status']);
        $rb = $rank($b['status']);

        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        // Pinakabagong start date muna sa loob ng grupo.
        $dateCmp = strcmp($b['start_date'], $a['start_date']);
        if ($dateCmp !== 0) return $dateCmp;
        return ((int) $b['id']) <=> ((int) $a['id']);
    });

    return $bookings;
}

/**
 * Human-friendly label for an equipment's billing unit (matches the
 * 'day' | 'hectare' | 'sack' values enforced in add-equipment.php).
 */
function equipmentUnitLabel($unitType) {
    $map = [
        'day'     => 'day',
        'hectare' => 'hectare',
        'sack'    => 'sack',
    ];
    return $map[$unitType] ?? $unitType;
}

/**
 * Renders the colored status pill used in the bookings table.
 */
function equipmentStatusBadge($status) {
    $map = [
        'pending'   => ['label' => 'Pending Approval', 'class' => 'eq-rented'],
        'approved'  => ['label' => 'Approved', 'class' => 'eq-approved'],
        'ongoing'   => ['label' => 'Ongoing', 'class' => 'eq-rented'],
        'overdue'   => ['label' => 'Overdue', 'class' => 'eq-overdue'],
        'returned'  => ['label' => 'Returned', 'class' => 'eq-returned'],
        'rejected'  => ['label' => 'Rejected', 'class' => 'eq-overdue'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'eq-overdue'],
    ];
    $meta = $map[$status] ?? ['label' => ucfirst($status), 'class' => ''];
    return '<span class="eq-status-badge ' . htmlspecialchars($meta['class']) . '">' . htmlspecialchars($meta['label']) . '</span>';
}

/**
 * Generate the next Membership ID, e.g. SJFMC-0001, SJFMC-0002...
 * Looks at the highest existing numeric suffix (not just row count)
 * so deleted members don't cause duplicate IDs to be reused.
 */
function generateMembershipId($pdo, $prefix = 'SJFMC') {
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(membership_id, '-', -1) AS UNSIGNED)) as max_num
        FROM members
        WHERE membership_id LIKE ?
    ");
    $stmt->execute([$prefix . '-%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $nextNum = ($result && $result['max_num']) ? ((int)$result['max_num'] + 1) : 1;

    return $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Members who don't have a user account linked to them yet -
 * these are the only ones an Admin should be able to pick from
 * when creating a new account, to avoid duplicate accounts.
 */
function getAvailableMembers($pdo) {
    $stmt = $pdo->query("
        SELECT m.*
        FROM members m
        LEFT JOIN users u ON u.member_id = m.id
        WHERE u.id IS NULL
        ORDER BY m.last_name, m.first_name
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function navActive($path) {
    return (strpos($_SERVER['REQUEST_URI'], $path) !== false) ? 'active' : '';
}

/**
 * Mga category ng Expenses. Iisang listahan ito para sa Expenses page at
 * sa add-expense API. Ang value ay nase-save sa expenses.category (VARCHAR(30)).
 */
function getExpenseCategories() {
    return ['Operational', 'Repair and Maintenance'];
}

/**
 * Greeting depende sa oras (Philippine time).
 * (Hindi na ginagamit sa header, pero nandito pa rin kung kailanganin sa ibang page.)
 */
function getGreeting() {
    $hour = (int) (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('G');
    if ($hour < 12) return 'Good morning';
    if ($hour < 18) return 'Good afternoon';
    return 'Good evening';
}

/**
 * Pangalang ipapakita sa greeting. Una: $_SESSION['first_name'] (kung
 * na-set sa login). Fallback: unang bahagi ng email, o ang role.
 */
function getDisplayName() {
    if (!empty($_SESSION['first_name'])) {
        return $_SESSION['first_name'];
    }
    if (!empty($_SESSION['email'])) {
        return ucfirst(explode('@', $_SESSION['email'])[0]);
    }
    return ucfirst($_SESSION['role'] ?? 'User');
}

/**
 * Pangalan at ikalawang linya para sa header (sa tabi ng logout button).
 *  - admin / manager: pangalan + "Admin" / "Manager"
 *  - user: pangalan + membership ID (hal. SJFMC-0001)
 * Kinukuha sa database ang pangalan (users.member_id -> members).
 * Kapag walang naka-link na member (hal. admin), fallback sa getDisplayName().
 */
function getHeaderUserInfo() {
    global $pdo;
    $role = $_SESSION['role'] ?? '';
    $name = '';
    $membershipId = '';

    if (isset($pdo) && isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT m.first_name, m.last_name, m.membership_id,
                       u.first_name AS staff_first, u.last_name AS staff_last
                FROM users u
                LEFT JOIN members m ON m.id = u.member_id
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Member: pangalan galing sa members. Staff (admin/manager): galing sa users.
                $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if ($name === '') {
                    $name = trim(($row['staff_first'] ?? '') . ' ' . ($row['staff_last'] ?? ''));
                }
                $membershipId = $row['membership_id'] ?? '';
            }
        } catch (Throwable $e) {
            error_log('getHeaderUserInfo failed: ' . $e->getMessage());
        }
    }

    if ($name === '') {
        $name = getDisplayName();
    }

    if ($role === 'user') {
        $sub = $membershipId !== '' ? $membershipId : 'Member';
    } else {
        $sub = ucfirst($role);
    }

    return ['name' => $name, 'sub' => $sub];
}

function renderHeader($title) {
    $currentRole = $_SESSION['role'] ?? 'guest';
    $currentEmail = $_SESSION['email'] ?? '';
    $isLoggedIn = isset($_SESSION['user_id']);
    $headerUser = $isLoggedIn ? getHeaderUserInfo() : ['name' => '', 'sub' => ''];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <!-- Material Icons -->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">

        <!-- Drawer/Sidebar toggle styles -->
        <style>
            :root {
                --drawer-width: 180px;
                --header-height: 56px;
            }

            <?php if ($isLoggedIn): ?>
            /* ===== Box (mirrors MUI's <Box sx={{ display: 'flex' }}>) ===== */
            .box {
                display: flex;
                width: 100%;
                min-height: 100vh;
            }

            /* ===== AppBar (mirrors MUI's <AppBar>) ===== */
            .app-bar {
                position: fixed;
                top: 0; left: 0; right: 0;
                height: var(--header-height);
                display: flex;
                align-items: center;
                justify-content: flex-start;
                padding: 0 12px;
                background: #3B6D11;
                color: #fff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.15);
                z-index: 30;
                transition: margin-left .3s ease, width .3s ease;
            }
            .app-bar.app-bar-shift {
                margin-left: var(--drawer-width);
                width: calc(100% - var(--drawer-width));
            }
            /* Toolbar (mirrors MUI's <Toolbar>) */
            .toolbar {
                display: flex;
                align-items: center;
                width: 100%;
            }
            /* IconButton inside AppBar (mirrors MUI's <IconButton>) */
            .toolbar .icon-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: auto !important;
                max-width: 40px;
                background: none;
                border: none;
                color: #fff;
                font-size: 22px;
                line-height: 1;
                text-align: center;
                cursor: pointer;
                margin: 0 16px 0 0;
                padding: 8px;
            }
            .toolbar .icon-button.icon-button-hidden { display: none; }

            /* Typography h6 (mirrors MUI's <Typography variant="h6">) */
            .typography-h6 {
                font-size: 1.25rem;
                font-weight: 500;
                margin: 0;
                white-space: nowrap;
                color: #fff;
            }

            /* ===== Logo sa app bar (kita kapag naka-close ang drawer) ===== */
            .app-bar-brand {
                display: flex;
                align-items: center;
                flex: 1 1 auto;
                min-width: 0;
            }
            .app-bar-brand img {
                width: 40px;
                height: 40px;
                object-fit: contain;
                background: #fff;
                border-radius: 50%;
                padding: 2px;
                box-sizing: border-box;
                flex-shrink: 0;
            }
            /* Buong pangalan ng coop sa tabi ng logo (kita kapag naka-close ang drawer) */
            .app-bar-brand span {
                margin-left: 8px;
                font-weight: 700;
                font-size: 15px;
                color: #fff;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            /* Kapag bukas ang drawer, nasa drawer na ang logo at SJFMC, kaya itago dito */
            .app-bar.app-bar-shift .app-bar-brand { display: none; }

            /* ===== Pangalan + role/member ID at Logout icon (kanan ng app bar) ===== */
            .app-bar-right {
                margin-left: auto;
                display: flex;
                align-items: center;
                gap: 14px;
            }
            .header-user {
                text-align: right;
                line-height: 1.2;
                color: #fff;
            }
            .header-user .hu-name {
                display: block;
                font-size: 13px;
                font-weight: 500;
                white-space: nowrap;
            }
            .header-user .hu-sub {
                display: block;
                font-size: 11px;
                opacity: .85;
                white-space: nowrap;
            }
            /* Admin at Manager lang: mas malaki ang pangalan at role (desktop lang) */
            @media (min-width: 769px) {
                .header-user.hu-staff .hu-name { font-size: 15px; }
                .header-user.hu-staff .hu-sub  { font-size: 12px; }
            }
            .logout-icon-btn {
                width: 34px;
                height: 34px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(255,255,255,0.6);
                border-radius: 8px;
                color: #fff;
                text-decoration: none;
            }
            .logout-icon-btn .material-icons { font-size: 20px; }
            .logout-icon-btn:hover { background: rgba(255,255,255,0.15); }
            .app-bar-right { flex-shrink: 0; }

            /* ===== Drawer (mirrors MUI's <Drawer variant="persistent">) ===== */
            .drawer {
                position: fixed;
                top: 0; left: 0; bottom: 0;
                width: var(--drawer-width);
                background: #3B6D11;
                color: #fff;
                transform: translateX(-100%);
                transition: transform .3s ease;
                z-index: 40;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
            }
            .drawer.drawer-open { transform: translateX(0); }

            /* DrawerHeader (mirrors MUI's styled <DrawerHeader>) */
            .drawer-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                height: var(--header-height);
                padding: 0 8px 0 12px;
                background: #3B6D11;
            }
            .drawer-header .icon-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: auto !important;
                background: none;
                border: none;
                color: #fff;
                font-size: 18px;
                cursor: pointer;
                padding: 6px;
                border-radius: 50%;
                margin: 0;
            }
            .drawer-header .icon-button:hover { background: rgba(255,255,255,0.15); }

            /* Logo + pangalan sa drawer header */
            .drawer-brand {
                display: flex;
                align-items: center;
                gap: 8px;
                color: #fff;
                text-decoration: none;
            }
            .drawer-brand img {
                width: 44px;
                height: 44px;
                object-fit: contain;
                background: #fff;
                border-radius: 50%;
                padding: 2px;
                box-sizing: border-box;
            }
            .drawer-brand span {
                font-weight: 700;
                font-size: 15px;
            }

            /* Divider (mirrors MUI's <Divider />) */
            hr.divider {
                border: none;
                border-top: 1px solid rgba(255,255,255,0.2);
                margin: 0;
            }

            /* List (mirrors MUI's <List> / <ListItem> / <ListItemButton>) */
            .list {
                display: flex;
                flex-direction: column;
                padding: 8px 0;
            }
            .list a {
                display: block;
                width: 100%;
                box-sizing: border-box;
                color: #fff;
                text-decoration: none;
                padding: 10px 16px;
                font-size: .875rem;
            }
            .list a:hover {
                background: rgba(255,255,255,0.1);
            }
            .list a.active {
                background: rgba(255,255,255,0.2);
                font-weight: 600;
            }

            /* Logout shown right under the menu on phones only (hidden on desktop,
               where the Logout at the very bottom of the drawer is used) */
            .drawer .mobile-logout { display: none; }

            /* Dark overlay for mobile (temporary drawer variant) */
            .backdrop {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 35;
            }
            .backdrop.backdrop-show { display: block; }

            /* ===== Main (mirrors MUI's styled <Main>) ===== */
            .main {
                flex: 1 1 auto;
                min-width: 0;
                margin-top: 0;
                margin-left: 0;
                transition: margin-left .225s cubic-bezier(0.4, 0, 0.6, 1);
            }
            .main.main-shift {
                margin-left: var(--drawer-width);
                transition: margin-left .225s cubic-bezier(0, 0, 0.2, 1);
            }
            /* DrawerHeader spacer inside Main (mirrors bare <DrawerHeader /> for top offset) */
            .drawer-header-spacer {
                height: var(--header-height);
            }

            /* Center the page content within Main, with equal spacing on both sides */
            .main > .container {
                width: 100% !important;
                max-width: 1300px !important;
                margin-left: auto !important;
                margin-right: auto !important;
                padding: 24px !important;
                box-sizing: border-box !important;
                float: none !important;
            }

            .main > .container * {
                min-width: 0;
            }
            .main > .container img,
            .main > .container canvas,
            .main > .container table {
                max-width: 100%;
            }

            @media (max-width: 768px) {
                .app-bar.app-bar-shift { margin-left: 0; width: 100%; }
                .main.main-shift { margin-left: 0; }
                .drawer { width: 160px; max-width: 160px; }
                .drawer .list a {
                    padding: 10px 10px;
                    font-size: 12px;
                }

                /* Phone: mas maliit ang brand text at user info para magkasya */
                .app-bar { padding: 0 8px; }
                .toolbar .icon-button { margin-right: 6px; padding: 6px; }
                .app-bar-brand img { width: 30px; height: 30px; }
                .app-bar-brand span {
                    margin-left: 6px;
                    font-size: 11px;
                    line-height: 1.2;
                    white-space: normal;
                    overflow: visible;
                    text-overflow: clip;
                }
                .app-bar-right { gap: 6px; margin-left: 6px; }
                .header-user .hu-name { font-size: 9px; }
                .header-user .hu-sub { font-size: 8px; }
                .logout-icon-btn { width: 30px; height: 30px; }
                .logout-icon-btn .material-icons { font-size: 18px; }

                /* Phone: Logout sits right under the last menu item, so it never
                   ends up hidden under the browser's bottom bar */
                .drawer .mobile-logout {
                    display: block;
                    margin-top: 8px;
                    border-top: 1px solid rgba(255,255,255,0.2);
                }
                .drawer .drawer-footer { display: none; }

                /* Extra room at the bottom of every page so the browser bar
                   doesn't cover the last content */
                .main > .container { padding-bottom: 120px !important; }
            }
            <?php endif; ?>
        </style>
    </head>
    <body>
        <?php if ($isLoggedIn): ?>

            <div class="box">
                <div class="app-bar" id="appBar">
                    <div class="toolbar">
                        <button class="icon-button" id="openBtn" aria-label="open drawer">&#9776;</button>

                        <!-- Logo at buong pangalan ng coop (kita kapag naka-close ang drawer, lahat ng role) -->
                        <div class="app-bar-brand">
                            <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="SJFMC logo">
                            <span>San Jose Farmers Multipurpose Cooperative</span>
                        </div>

                        <!-- Pangalan + role/member ID at Logout icon (lahat ng role) -->
                        <div class="app-bar-right">
                            <div class="header-user<?php echo in_array($currentRole, ['admin', 'manager'], true) ? ' hu-staff' : ''; ?>">
                                <span class="hu-name"><?php echo htmlspecialchars($headerUser['name']); ?></span>
                                <span class="hu-sub"><?php echo htmlspecialchars($headerUser['sub']); ?></span>
                            </div>
                            <a class="logout-icon-btn" href="<?php echo BASE_URL; ?>/app/auth/signout.php" aria-label="Logout" title="Logout">
                                <span class="material-icons">logout</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="drawer" id="drawer">
                    <div class="drawer-header">
                        <div class="drawer-brand">
                            <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="SJFMC logo">
                            <span>SJFMC</span>
                        </div>
                        <button class="icon-button" id="closeBtn" aria-label="close drawer">&#8592;</button>
                    </div>

                    <hr class="divider">
                    <div class="list">
                        <?php if ($currentRole === 'admin'): ?>
                            <a class="<?php echo navActive('/app/admin/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/admin/dashboard.php">Dashboard</a>
                            <a class="<?php echo navActive('/app/admin/user-management/user-management.php'); ?>" href="<?php echo BASE_URL; ?>/app/admin/user-management/user-management.php">User Management</a>
                        <?php elseif ($currentRole === 'manager'): ?>
                            <a class="<?php echo navActive('/app/manager/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/dashboard.php">Dashboard</a>
                            <a class="<?php echo navActive('/app/manager/member-management/member-management.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/member-management/member-management.php">Member Registration</a>
                            <a class="<?php echo navActive('/app/manager/user-management/user-management.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/user-management/user-management.php">Member Management</a>
                            <a class="<?php echo navActive('/app/manager/meetings/meeting.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/meetings/meeting.php">Meetings</a>
                            <a class="<?php echo navActive('/app/manager/payments/payments.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/payments/payments.php">Transactions</a>
                            <a class="<?php echo navActive('/app/manager/products/products.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/products/products.php">Products</a>
                            <a class="<?php echo navActive('/app/manager/equipment/equipment.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/equipment/equipment.php">Equipment</a>
                            <a class="<?php echo navActive('/app/manager/loans/loans.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/loans/loans.php">Loans</a>
                            <a class="<?php echo navActive('/app/manager/crops/crops.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/crops/crops.php">Crops Information</a>
                            <a class="<?php echo navActive('/app/manager/resources/resources.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/resources/resources.php">Agriculture Resources</a>
                            <a class="<?php echo navActive('/app/manager/expenses/expenses.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/expenses/expenses.php">Expenses</a>
                        <?php elseif ($currentRole === 'user'): ?>
                            <a class="<?php echo navActive('/app/user/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/dashboard.php">Dashboard</a>
                            <a class="<?php echo navActive('/app/user/profile.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/profile.php">My Account</a>
                            <a class="<?php echo navActive('/app/user/checkins.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/checkins.php">Meetings</a>
                            <a class="<?php echo navActive('/app/user/payments.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/payments.php">Transactions</a>
                            <a class="<?php echo navActive('/app/user/products.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/products.php">Products</a>
                            <a class="<?php echo navActive('/app/user/equipment.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/equipment.php">Equipment</a>
                            <a class="<?php echo navActive('/app/user/loans.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/loans.php">Loans</a>
                            <a class="<?php echo navActive('/app/user/crops.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/crops.php">Crops Information</a>
                            <a class="<?php echo navActive('/app/user/resources.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/resources.php">Agri Resources</a>
                        <?php endif; ?>
                        <a class="mobile-logout" href="<?php echo BASE_URL; ?>/app/auth/signout.php">Logout</a>
                    </div>
                    <div class="drawer-footer" style="margin-top: auto;">
                        <hr class="divider">
                        <div class="list">
                            <a href="<?php echo BASE_URL; ?>/app/auth/signout.php">Logout</a>
                        </div>
                    </div>
                </div>

                <div class="backdrop" id="backdrop"></div>
                <div class="main" id="main">
                    <div class="drawer-header-spacer"></div>
                    <div class="container">
        <?php else: ?>
            <div class="auth-wrapper">
                <div class="auth-box">
        <?php endif; ?>
    <?php
}

function renderFooter() {
    $isLoggedIn = isset($_SESSION['user_id']);
    ?>
        <?php if ($isLoggedIn): ?>
                </div>
            </div>
            </div>

            <script>
                (function() {
                    const drawer   = document.getElementById('drawer');
                    const appBar   = document.getElementById('appBar');
                    const main     = document.getElementById('main');
                    const backdrop = document.getElementById('backdrop');
                    const openBtn  = document.getElementById('openBtn');
                    const closeBtn = document.getElementById('closeBtn');

                    function isMobile() {
                        return window.innerWidth <= 768;
                    }

                    const STORAGE_KEY = 'drawerOpen';

                    // mirrors handleDrawerOpen() / setOpen(true)
                    function handleDrawerOpen() {
                        drawer.classList.add('drawer-open');
                        appBar.classList.add('app-bar-shift');
                        openBtn.classList.add('icon-button-hidden');
                        if (isMobile()) {
                            backdrop.classList.add('backdrop-show');
                        } else {
                            main.classList.add('main-shift');
                        }
                        // Remember the state so it survives navigating to another page
                        localStorage.setItem(STORAGE_KEY, 'true');
                    }

                    // mirrors handleDrawerClose() / setOpen(false)
                    function handleDrawerClose() {
                        drawer.classList.remove('drawer-open');
                        appBar.classList.remove('app-bar-shift');
                        main.classList.remove('main-shift');
                        backdrop.classList.remove('backdrop-show');
                        openBtn.classList.remove('icon-button-hidden');
                        localStorage.setItem(STORAGE_KEY, 'false');
                    }

                    openBtn.addEventListener('click', handleDrawerOpen);
                    closeBtn.addEventListener('click', handleDrawerClose);
                    backdrop.addEventListener('click', handleDrawerClose);

                    window.addEventListener('resize', () => {
                        if (!drawer.classList.contains('drawer-open')) return;
                        if (isMobile()) {
                            main.classList.remove('main-shift');
                            backdrop.classList.add('backdrop-show');
                        } else {
                            backdrop.classList.remove('backdrop-show');
                            main.classList.add('main-shift');
                        }
                    });

                    const wasOpen = localStorage.getItem(STORAGE_KEY) === 'false';
                    if (wasOpen && !isMobile()) {
                        handleDrawerOpen();
                    }
                })();
            </script>
        <?php else: ?>
                </div>
            </div>
        <?php endif; ?>
    </body>
    </html>
    <?php
}

?>