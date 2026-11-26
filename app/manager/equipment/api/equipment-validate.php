<?php
// Location: app/manager/equipment/api/equipment-validate.php
// Shared validation helpers for add-equipment, update-equipment and book-equipment.
// Each check returns an error message (string), or null if the value is okay.

function eqFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Letters only (and space . ' -). Set $allowDigits = true to also allow numbers (e.g. "Thresher 2000").
function eqCheckName($value, string $label, bool $allowDigits = false): ?string {
    $value = trim((string) $value);
    if ($value === '') return "$label is required.";
    if (mb_strlen($value) > 100) return "$label is too long (max 100 characters).";

    $pattern = $allowDigits ? '/^[\p{L}0-9\s\.\'\-]+$/u' : '/^[\p{L}\s\.\'\-]+$/u';
    if (!preg_match($pattern, $value)) {
        return $allowDigits
            ? "$label may only contain letters, numbers, and spaces."
            : "$label may only contain letters and spaces.";
    }
    return null;
}

// Digits only (whole number), e.g. rates.
function eqCheckWholeNumber($value, string $label, int $max = 1000000): ?string {
    $value = trim((string) $value);
    if ($value === '') return "$label is required.";
    if (!ctype_digit($value)) return "$label must be a whole number (digits only).";
    if ((int) $value > $max) return "$label is too large.";
    return null;
}

// Digits with up to 2 decimal places, greater than 0.
function eqCheckQuantity($value): ?string {
    $value = trim((string) $value);
    if ($value === '') return 'Quantity is required.';
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) return 'Quantity must be a number (digits only).';
    if ((float) $value <= 0) return 'Quantity must be greater than 0.';
    if ((float) $value > 100000) return 'Quantity is too large.';
    return null;
}

function eqCheckDate($value, string $label): ?string {
    $value = (string) $value;
    if ($value === '') return "$label is required.";
    $d = DateTime::createFromFormat('Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) return "$label is not a valid date.";
    return null;
}

// Location is built by the dropdowns as "Barangay, City/Municipality, Province".
function eqCheckLocation($value): ?string {
    $value = trim((string) $value);
    if ($value === '') return 'Please select the farm location (province, city/municipality, and barangay).';
    if (mb_strlen($value) > 255) return 'Farm location is too long.';
    if (!preg_match('/^[\p{L}0-9\s,\.\-\(\)\']+$/u', $value)) return 'Farm location contains invalid characters.';
    return null;
}

function eqCheckSelectId($value, string $label): ?string {
    $value = trim((string) $value);
    if ($value === '' || !ctype_digit($value)) return "Please select $label.";
    return null;
}

function eqCheckChoice($value, array $allowed, string $label): ?string {
    if (!in_array($value, $allowed, true)) return "Please select a valid $label.";
    return null;
}