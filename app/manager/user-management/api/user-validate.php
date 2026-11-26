<?php
// Shared validation para sa account-create.php, user-update.php at user-delete.php.
// um_validate_account() returns ['errors' => [...], 'data' => [...]].

function um_valid_id($v): bool {
    return ctype_digit((string)$v) && (int)$v > 0;
}

function um_validate_account(array $in, bool $isUpdate = false): array {
    $errors = [];
    $t = fn($k) => trim((string)($in[$k] ?? ''));

    $usernameRe = '/^[a-zA-Z0-9_.]{3,50}$/';

    // ---- Member (create lang) ----
    $memberId = $t('member_id');
    if (!$isUpdate) {
        if ($memberId === '') $errors[] = 'Member is required.';
        elseif (!um_valid_id($memberId)) $errors[] = 'Member is not valid.';
    }

    // ---- Username ----
    $username = $t('username');
    if ($username === '') $errors[] = 'Username is required.';
    elseif (!preg_match($usernameRe, $username)) $errors[] = 'Username must be 3-50 characters (letters, numbers, underscore, or period only).';

    // ---- Email ----
    $email = $t('email');
    if ($email === '') $errors[] = 'Email is required.';
    elseif (mb_strlen($email) > 100) $errors[] = 'Email is too long (max 100).';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email must be a valid address (e.g. name@example.com).';

    // ---- Password (sa update, optional; kapag may tinype, dapat tugma ang confirm) ----
    $password = (string)($in['password'] ?? '');
    $confirm  = (string)($in['confirm_password'] ?? '');
    if (!$isUpdate && $password === '') $errors[] = 'Password is required.';
    elseif ($password !== $confirm) $errors[] = 'Passwords do not match.';

    // ---- Account status ----
    $status = $t('account_status');
    if ($status === '') $errors[] = 'Account Status is required.';
    elseif (!in_array($status, ['Active', 'Inactive'], true)) $errors[] = 'Account Status is not valid.';

    // ---- Role (update lang, at kapag naipasa lang ang field) ----
    $role = null;
    if ($isUpdate && isset($in['role'])) {
        $role = $t('role');
        if (!in_array($role, ['user', 'manager', 'admin'], true)) $errors[] = 'Role is not valid.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'member_id'      => $memberId !== '' && um_valid_id($memberId) ? (int)$memberId : 0,
            'username'       => $username,
            'email'          => $email,
            'password'       => $password,
            'account_status' => $status,
            'role'           => $role,
        ],
    ];
}