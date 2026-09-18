<?php

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

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/index.php');
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        die("Access denied. Required role: $role");
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
 * All equipment items, for the browse grid and the booking form's dropdown.
 */
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
 * All bookings still relevant to the manager's "Currently rented" table
 * (pending approval, ongoing, or overdue), across ALL members — unlike
 * getMyEquipmentBookings(), which is scoped to a single user.
 */
function getCurrentEquipmentBookings($pdo) {
    $stmt = $pdo->query("
        SELECT b.*, e.name AS equipment_name
        FROM equipment_bookings b
        INNER JOIN equipment e ON e.id = b.equipment_id
        WHERE b.status IN ('pending', 'ongoing', 'overdue')
        ORDER BY b.start_date ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

function getAllEquipment($pdo) {
    $stmt = $pdo->query("SELECT * FROM equipment ORDER BY name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * All bookings whose date range overlaps the given month, for the
 * manager's Booking Schedule calendar (get-month-booking.php).
 * Uses an overlap check (start <= last day of month AND end >= first
 * day of month) rather than matching start_date alone, so multi-day
 * bookings that started in an earlier month but run into this one
 * still show up on the calendar.
 */
function getBookingsForMonth($pdo, $year, $month) {
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $stmt = $pdo->prepare("
        SELECT b.*, e.name AS equipment_name
        FROM equipment_bookings b
        INNER JOIN equipment e ON e.id = b.equipment_id
        WHERE b.start_date <= LAST_DAY(:month_start)
          AND b.end_date >= :month_start
        ORDER BY b.start_date ASC
    ");
    $stmt->execute([':month_start' => $monthStart]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * The current user's own active bookings (pending approval, ongoing, or
 * overdue) with the equipment name joined in. Returned/rejected/cancelled
 * bookings are left out since this feeds the "active bookings" summary.
 */
function getMyEquipmentBookings($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT b.*, e.name AS equipment_name
        FROM equipment_bookings b
        INNER JOIN equipment e ON e.id = b.equipment_id
        WHERE b.recorded_by = :user_id
          AND b.status IN ('pending', 'ongoing', 'overdue')
        ORDER BY b.start_date ASC
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

function renderHeader($title) {
    $currentRole = $_SESSION['role'] ?? 'guest';
    $currentEmail = $_SESSION['email'] ?? '';
    $isLoggedIn = isset($_SESSION['user_id']);
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
        <link rel="stylesheet" href="http://localhost/coop-system/assets/css/style.css">

        <!-- Drawer/Sidebar toggle styles -->
        <style>
            :root {
                --drawer-width: 240px;
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
                justify-content: flex-end;
                height: var(--header-height);
                padding: 0 8px;
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
                    </div>
                </div>

                <div class="drawer" id="drawer">
                    <div class="drawer-header">
                        <button class="icon-button" id="closeBtn" aria-label="close drawer">&#8592;</button>
                    </div>

                    <hr class="divider">
                    <div class="list">
                        <?php if ($currentRole === 'admin'): ?>
                            <a class="<?php echo navActive('/app/admin/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/admin/dashboard.php">Dashboard</a>
                            <a class="<?php echo navActive('/app/admin/user-management.php'); ?>" href="<?php echo BASE_URL; ?>/app/admin/user-management.php">User Management</a>
                            <a class="<?php echo navActive('/app/admin/user-create.php'); ?>" href="<?php echo BASE_URL; ?>/app/admin/user-create.php">Create Account</a>
                        <?php elseif ($currentRole === 'manager'): ?>
                            <a class="<?php echo navActive('/app/manager/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/dashboard.php">Dashboard</a>
                            <a class="<?php echo navActive('/app/manager/member-management/member-management.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/member-management/member-management.php">Member Management</a>
                            <a class="<?php echo navActive('/app/manager/user-management.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/user-management.php">User Management</a>
                            <a class="<?php echo navActive('/app/manager/payments/payments.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/payments/payments.php">Transactions</a>
                            <a class="<?php echo navActive('/app/manager/meetings/meeting.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/meetings/meeting.php">Meetings</a>
                            <a class="<?php echo navActive('/app/manager/equipment/equipment.php'); ?>" href="<?php echo BASE_URL; ?>/app/manager/equipment/equipment.php">Equipment</a>
                        <?php elseif ($currentRole === 'user'): ?>
                            <a class="<?php echo navActive('/app/user/dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/dashboard.php">Dashboard</a>
                            <a class="<?php echo navActive('/app/user/profile.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/profile.php">My Account</a>
                            <a class="<?php echo navActive('/app/user/payments.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/payments.php">Transactions</a>
                            <a class="<?php echo navActive('/app/user/checkins.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/checkins.php">Meetings</a>
                            <a class="<?php echo navActive('/app/user/equipment.php'); ?>" href="<?php echo BASE_URL; ?>/app/user/equipment.php">Equipment</a>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: auto;">
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