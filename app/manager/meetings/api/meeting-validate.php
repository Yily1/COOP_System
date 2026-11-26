<?php
// Validation para sa Create meeting (meeting.php).
// Returns ['errors' => [...], 'data' => [...]].

function mt_validate_meeting(array $in): array {
    $errors = [];
    $t = fn($k) => trim((string)($in[$k] ?? ''));

    $titleRe = '/^[\p{L} .,\'’\-&\/()]+$/u';           // letters lang (+ space at karaniwang bantas)
    $textRe  = '/^[\p{L}\p{N} .,\'’\-&\/():#]+$/u';   // letters, numbers, space at karaniwang bantas (Location)

    $isDate = function ($v) {
        $d = DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v;
    };

    // ---- Title ----
    $title = preg_replace('/\s+/', ' ', $t('title'));
    if ($title === '') $errors[] = 'Title is required.';
    elseif (mb_strlen($title) > 100) $errors[] = 'Title is too long (max 100).';
    elseif (!preg_match($titleRe, $title)) $errors[] = 'Title must contain letters only.';

    // ---- Date ----
    $date = $t('meeting_date');
    if ($date === '') $errors[] = 'Date is required.';
    elseif (!$isDate($date)) $errors[] = 'Date is not a valid date.';

    // ---- Time ----
    $time = $t('time');
    if ($time === '') $errors[] = 'Time is required.';
    elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) $errors[] = 'Time is not valid.';

    // ---- Location (optional) ----
    $location = preg_replace('/\s+/', ' ', $t('location'));
    if ($location !== '') {
        if (mb_strlen($location) > 100) $errors[] = 'Location is too long (max 100).';
        elseif (!preg_match($textRe, $location)) $errors[] = 'Location may only contain letters, numbers, spaces and . , \' - & / ( ) : #';
    }

    // ---- Description (optional) ----
    $description = $t('description');
    if (mb_strlen($description) > 1000) $errors[] = 'Description is too long (max 1000).';

    return [
        'errors' => $errors,
        'data' => [
            'title'        => $title,
            'meeting_date' => $date,
            'time'         => $time,
            'location'     => $location !== '' ? $location : null,
            'description'  => $description !== '' ? $description : null,
        ],
    ];
}

// ============================================================
// Para sa api/members.php at api/checkins.php
// ============================================================

function mt_valid_id($v): bool {
    return (is_int($v) || is_string($v)) && ctype_digit((string)$v) && (int)$v > 0;
}

// Paghahanap ng member: pangalan o membership ID (hal. "Juan", "SJFMC-0001")
function mt_validate_member_search($q): array {
    $errors = [];
    if (!is_string($q)) {
        return ['errors' => ['Search is not valid.'], 'data' => ['q' => '']];
    }
    $q = trim(preg_replace('/\s+/', ' ', $q));
    if (mb_strlen($q) > 100) $errors[] = 'Search is too long (max 100).';
    elseif ($q !== '' && !preg_match('/^[\p{L}\p{N} .\'’\-]+$/u', $q)) $errors[] = 'Search may only contain letters, numbers, spaces and . \' -';

    return ['errors' => $errors, 'data' => ['q' => $errors ? '' : $q]];
}

// Check-in: {meeting_id, member_id}
function mt_validate_checkin(array $in): array {
    $errors = [];
    $meetingId = $in['meeting_id'] ?? '';
    $memberId  = $in['member_id'] ?? '';

    if ($meetingId === '' || $meetingId === null) $errors[] = 'Meeting is required.';
    elseif (!mt_valid_id($meetingId)) $errors[] = 'Meeting is not valid.';

    if ($memberId === '' || $memberId === null) $errors[] = 'Member is required.';
    elseif (!mt_valid_id($memberId)) $errors[] = 'Member is not valid.';

    return [
        'errors' => $errors,
        'data' => [
            'meeting_id' => mt_valid_id($meetingId) ? (int)$meetingId : 0,
            'member_id'  => mt_valid_id($memberId) ? (int)$memberId : 0,
        ],
    ];
}