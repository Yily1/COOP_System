<?php



if (!defined('RENTAL_HARVESTER_OPERATOR_PER_HECTARE')) define('RENTAL_HARVESTER_OPERATOR_PER_HECTARE', 1500);
if (!defined('RENTAL_HARVESTER_REPAIR_RATE'))          define('RENTAL_HARVESTER_REPAIR_RATE', 0.10);
if (!defined('RENTAL_SHELLER_OPERATOR_RATE'))          define('RENTAL_SHELLER_OPERATOR_RATE', 0.60);
if (!defined('RENTAL_SHELLER_REPAIR_RATE'))            define('RENTAL_SHELLER_REPAIR_RATE', 0.10);

function rentalEquipmentOptions() {
    return [
        'corn_harvester' => 'Corn Harvester',
        'corn_sheller'   => 'Corn Sheller',
        'dryer'          => 'Dryer',
    ];
}

function rentalEquipmentLabel($key) {
    $all = rentalEquipmentOptions();
    return $all[$key] ?? (string) $key;
}

// Unit shown next to the quantity: hectares for the harvester, sacks for the rest
function rentalEquipmentUnit($key) {
    return $key === 'corn_harvester' ? 'ha' : 'sack';
}

/**
 * Kinukuha ang rate mula sa isang equipment row. Sinusubukan muna ang mga
 * kilalang pangalan ng column, tapos hinahanap ang kahit anong column na
 * may "member" / "non" sa pangalan. Kapag wala, 0 (manual ang Amount).
 */
function rentalPickRate(array $row, $forNonMember) {
    $known = $forNonMember
        ? ['rate_nonmember', 'rate_non_member', 'nonmember_rate', 'non_member_rate', 'non_member_price', 'nonmember_price']
        : ['rate_member', 'member_rate', 'rate_members', 'member_price'];

    foreach ($known as $col) {
        if (isset($row[$col]) && is_numeric($row[$col])) {
            return (float) $row[$col];
        }
    }

    foreach ($row as $col => $val) {
        $c = strtolower((string) $col);
        if (!is_numeric($val)) continue;
        $isNon = (strpos($c, 'non') !== false);
        $isMember = (strpos($c, 'member') !== false);
        if ($forNonMember && $isNon && $isMember) return (float) $val;
        if (!$forNonMember && $isMember && !$isNon) return (float) $val;
    }

    // Iisang rate lang ang meron (walang hiwalay para sa member / non-member)
    foreach (['rate', 'price', 'rental_rate', 'rate_per_unit'] as $col) {
        if (isset($row[$col]) && is_numeric($row[$col])) {
            return (float) $row[$col];
        }
    }

    return 0.0;
}

/**
 * Rates per hectare / per sack taken from the Equipment page, keyed by our
 * equipment keys. Returns e.g.
 *   ['corn_harvester' => ['member' => 2000.0, 'non_member' => 2500.0], ...]
 * Equipment without a rate simply has 0, so the Amount stays manual.
 */
function rentalEquipmentRates($pdo) {
    $rates = [];
    if (!function_exists('getAllEquipment')) {
        error_log('rentalEquipmentRates: getAllEquipment() not found');
        return $rates;
    }
    foreach (getAllEquipment($pdo) as $eq) {
        $n = strtolower((string) ($eq['name'] ?? ''));
        $key = null;
        if (strpos($n, 'harvester') !== false)   $key = 'corn_harvester';
        elseif (strpos($n, 'sheller') !== false) $key = 'corn_sheller';
        elseif (strpos($n, 'dryer') !== false)   $key = 'dryer';
        if ($key) {
            $rates[$key] = [
                'member'     => rentalPickRate($eq, false),
                'non_member' => rentalPickRate($eq, true),
            ];
            if ($rates[$key]['member'] <= 0 && $rates[$key]['non_member'] <= 0) {
                error_log('rentalEquipmentRates: no rate found for ' . $key . '. Columns: ' . implode(', ', array_keys($eq)));
            }
        }
    }
    return $rates;
}

/**
 * Checks the equipment and quantity sent by a form (manager or member).
 * Returns ['errors' => [...], 'quantity' => float|null].
 */
function validateRentalInput($equipment, $quantity) {
    $errors = [];
    $qty = null;

    if (!array_key_exists((string) $equipment, rentalEquipmentOptions())) {
        $errors[] = 'Please select the equipment being rented.';
    } elseif ($equipment === 'corn_harvester') {
        // The operator share depends on the number of hectares
        if (!is_numeric($quantity) || (float) $quantity <= 0) {
            $errors[] = 'Please enter how many hectares are being rented.';
        } else {
            $qty = (float) $quantity;
        }
    } elseif ($quantity !== '' && $quantity !== null) {
        if (!is_numeric($quantity) || (float) $quantity < 0) {
            $errors[] = 'Invalid number of sacks.';
        } else {
            $qty = (float) $quantity;
        }
    }

    return ['errors' => $errors, 'quantity' => $qty];
}

/**
 * Returns ['operator' => ..., 'repair' => ..., 'income' => ...] for one payment.
 */
function calculateRentalSplit($equipment, $amount, $quantity) {
    $amount = (float) $amount;
    $quantity = (float) $quantity;
    $operator = 0.0;
    $repair = 0.0;

    if ($equipment === 'corn_harvester') {
        $operator = min($amount, RENTAL_HARVESTER_OPERATOR_PER_HECTARE * $quantity);
        $repair = max(0.0, ($amount - $operator) * RENTAL_HARVESTER_REPAIR_RATE);
    } elseif ($equipment === 'corn_sheller') {
        $operator = $amount * RENTAL_SHELLER_OPERATOR_RATE;
        $repair = $amount * RENTAL_SHELLER_REPAIR_RATE;
    }

    $operator = round($operator, 2);
    $repair = round($repair, 2);

    return [
        'operator' => $operator,
        'repair'   => $repair,
        'income'   => round($amount - $operator - $repair, 2),
    ];
}

/**
 * SQL expression for the coop's own income of a payment row:
 * amount minus operator share minus repair share.
 * Old rows and non-rental rows have NULL shares, so the full amount counts.
 */
function coopIncomeSql($alias = '') {
    $p = $alias !== '' ? $alias . '.' : '';
    return "({$p}amount - COALESCE({$p}operator_share, 0) - COALESCE({$p}repair_share, 0))";
}

/**
 * Saves the equipment, quantity and the computed shares on a payment row
 * that already exists. Kept for older code; new payments save these
 * in the same INSERT (see recordPayment / submitPendingPayment).
 */
function savePaymentRentalDetails(PDO $pdo, $paymentId, $equipment, $quantity, $amount, $memberId = null) {
    if (!$paymentId) return;

    $split = calculateRentalSplit($equipment, $amount, $quantity);
    $qty = ($quantity !== null && $quantity !== '') ? (float) $quantity : null;

    $sql = "UPDATE payments
            SET equipment = ?, quantity = ?, operator_share = ?, repair_share = ?
            WHERE id = ? AND payment_type = 'rental'";
    $params = [$equipment ?: null, $qty, $split['operator'], $split['repair'], $paymentId];

    if ($memberId !== null) {
        $sql .= " AND member_id = ?";
        $params[] = $memberId;
    }

    $pdo->prepare($sql)->execute($params);
}