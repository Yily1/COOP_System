<?php
// Shared validation para sa save-member.php at update-member.php.
// Returns ['errors' => [...], 'data' => [...]].

function mm_validate_member(array $in): array {
    $errors = [];
    $t = fn($k) => trim((string)($in[$k] ?? ''));

    $livestockOptions = ['Chicken', 'Duck', 'Pig', 'Goat', 'Cow', 'Carabao'];
    $cropOptions = ['Corn', 'Cassava', 'Cucumber', 'Eggplant', 'Squash', 'Chayote', 'Bitter Melon', 'String Beans'];

    $nameRe   = '/^\p{L}[\p{L} .\'’\-]*$/u';          // letters lang (+ space . ' -)
    $othersRe = '/^[\p{L} ,.\'’\-]+$/u';              // letters lang (+ comma)
    $purokRe  = '/^[\p{L}\p{N} .,#\/\-]+$/u';         // letters at numbers (Purok 3)
    $geoRe    = '/^[\p{L}\p{N} .,\'’()\/\-]+$/u';     // PSGC names
    $codeRe   = '/^\d{6,10}$/';

    $isDate = function ($v) {
        $d = DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v;
    };

    // ---- Names ----
    $last = $t('last_name'); $first = $t('first_name'); $middle = $t('middle_name');
    foreach ([['Last Name', $last], ['First Name', $first], ['Middle Name', $middle]] as [$label, $val]) {
        if ($val === '') $errors[] = "$label is required.";
        elseif (mb_strlen($val) > 50) $errors[] = "$label is too long (max 50).";
        elseif (!preg_match($nameRe, $val)) $errors[] = "$label must contain letters only.";
    }

    // ---- Gender / dates ----
    $gender = $t('gender');
    if (!in_array($gender, ['Male', 'Female'], true)) $errors[] = 'Gender is required.';

    $dob = $t('date_of_birth');
    if ($dob === '') $errors[] = 'Date of Birth is required.';
    elseif (!$isDate($dob)) $errors[] = 'Date of Birth is not a valid date.';
    elseif ($dob > date('Y-m-d')) $errors[] = 'Date of Birth cannot be in the future.';

    $joined = $t('date_joined');
    if ($joined === '') $errors[] = 'Date Joined is required.';
    elseif (!$isDate($joined)) $errors[] = 'Date Joined is not a valid date.';

    // ---- Occupation / contact ----
    $occupation = $t('occupation');
    if ($occupation === '') $errors[] = 'Occupation is required.';
    elseif (mb_strlen($occupation) > 60) $errors[] = 'Occupation is too long (max 60).';
    elseif (!preg_match($nameRe, $occupation)) $errors[] = 'Occupation must contain letters only.';

    $contact = $t('contact_number');
    if ($contact === '') $errors[] = 'Contact Number is required.';
    elseif (!preg_match('/^09\d{9}$/', $contact)) $errors[] = 'Contact Number must be 11 digits and start with 09 (e.g. 09123456789).';

    // ---- Address ----
    $regionCode = $t('region_code'); $provinceCode = $t('province_code');
    $cityCode = $t('city_code'); $brgyCode = $t('barangay_code');
    $regionName = $t('region_name'); $provinceName = $t('province_name');
    $cityName = $t('city_name'); $brgyName = $t('barangay_name');
    $purok = preg_replace('/\s+/', ' ', $t('purok'));

    if (!preg_match($codeRe, $regionCode)) $errors[] = 'Region is required.';
    if ($provinceCode !== '' && !preg_match($codeRe, $provinceCode)) $errors[] = 'Province is not valid.';
    if (!preg_match($codeRe, $cityCode)) $errors[] = 'Municipality / City is required.';
    if (!preg_match($codeRe, $brgyCode)) $errors[] = 'Barangay is required.';

    foreach ([['Region', $regionName, true], ['Province', $provinceName, $provinceCode !== ''],
              ['Municipality / City', $cityName, true], ['Barangay', $brgyName, true]] as [$label, $val, $req]) {
        if ($val === '') { if ($req && !in_array("$label is required.", $errors, true)) $errors[] = "$label is required."; }
        elseif (mb_strlen($val) > 100 || !preg_match($geoRe, $val)) $errors[] = "$label is not valid.";
    }

    if ($purok === '') $errors[] = 'Purok / Street is required.';
    elseif (mb_strlen($purok) > 100) $errors[] = 'Purok / Street is too long (max 100).';
    elseif (!preg_match($purokRe, $purok)) $errors[] = 'Purok / Street may only contain letters, numbers, spaces and . , # / -';

    $brgyLabel = $brgyName === '' ? '' : (preg_match('/^(brgy|barangay)\b/i', $brgyName) ? $brgyName : 'Brgy. ' . $brgyName);
    $address = implode(', ', array_filter([$purok, $brgyLabel, $cityName, $provinceName, $regionName], fn($v) => $v !== ''));

    // ---- Membership / farming ----
    $membershipType = $t('membership_type');
    if (!in_array($membershipType, ['Regular', 'Associate'], true)) $errors[] = 'Membership Type is required.';

    $hectares = $t('hectares_cultivated');
    if (!in_array($hectares, array_map('strval', range(1, 10)), true)) $errors[] = 'Number of Hectares Cultivated is required.';

    $farmerType = $t('farmer_type');
    if (!in_array($farmerType, ['Livestock', 'Crops', 'Both'], true)) $errors[] = 'Type of Farmer is required.';

    $livestockSelected = array_values(array_intersect((array)($in['livestock'] ?? []), $livestockOptions));
    $cropsSelected = array_values(array_intersect((array)($in['crops'] ?? []), $cropOptions));
    $livestockOthers = $t('livestock_others');
    $cropsOthers = $t('crops_others');

    if ($livestockOthers !== '' && !preg_match($othersRe, $livestockOthers)) $errors[] = 'Other livestock must contain letters only.';
    if ($cropsOthers !== '' && !preg_match($othersRe, $cropsOthers)) $errors[] = 'Other crops must contain letters only.';

    $livestockDetails = implode(', ', array_filter(array_merge($livestockSelected, $livestockOthers !== '' ? [$livestockOthers] : [])));
    $cropsDetails = implode(', ', array_filter(array_merge($cropsSelected, $cropsOthers !== '' ? [$cropsOthers] : [])));

    if (in_array($farmerType, ['Livestock', 'Both'], true) && $livestockDetails === '') $errors[] = 'Please select or specify the livestock raised.';
    if (in_array($farmerType, ['Crops', 'Both'], true) && $cropsDetails === '') $errors[] = 'Please select or specify the crops grown.';
    if ($farmerType === 'Livestock') $cropsDetails = '';
    if ($farmerType === 'Crops') $livestockDetails = '';

    return [
        'errors' => $errors,
        'data' => [
            'last_name' => $last, 'first_name' => $first, 'middle_name' => $middle,
            'gender' => $gender, 'date_of_birth' => $dob, 'occupation' => $occupation,
            'contact_number' => $contact,
            'region_code' => $regionCode, 'province_code' => $provinceCode !== '' ? $provinceCode : null,
            'city_code' => $cityCode, 'barangay_code' => $brgyCode,
            'purok' => $purok, 'address' => $address,
            'membership_type' => $membershipType, 'date_joined' => $joined,
            'hectares_cultivated' => $hectares, 'farmer_type' => $farmerType,
            'livestock_details' => $livestockDetails !== '' ? $livestockDetails : null,
            'crops_details' => $cropsDetails !== '' ? $cropsDetails : null,
        ],
    ];
}