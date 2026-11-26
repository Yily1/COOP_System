<?php
// Shared validation + insert para sa manager at user add-planting.php.
// Kailangan munang na-load ang config/functions.php (getCropTypeById, calculateCropExpectedHarvest).

/**
 * Returns ['errors' => [...], 'data' => [...]].
 */
function cm_validate_planting(PDO $pdo, array $in): array {
    $errors = [];
    $t = fn($k) => trim((string)($in[$k] ?? ''));

    $purokRe = '/^[\p{L}\p{N} .,#\/\-]+$/u';          // letters at numbers (Purok 3)
    $geoRe   = '/^[\p{L}\p{N} .,\'’()\/\-]+$/u';      // PSGC names
    $codeRe  = '/^\d{6,10}$/';

    // ---- Crop ----
    $cropTypeId = (int)($in['crop_type_id'] ?? 0);
    $cropType = $cropTypeId > 0 ? getCropTypeById($pdo, $cropTypeId) : null;
    if (!$cropType) $errors[] = 'Please choose a crop from the list.';

    // ---- Area (numbers lang, hanggang 2 decimals, higit sa 0) ----
    $area = $t('area_hectares');
    if ($area === '') {
        $errors[] = 'Area (hectares) is required.';
    } elseif (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $area)) {
        $errors[] = 'Area (hectares) must be a number with up to 2 decimals (e.g. 1.5).';
    } elseif ((float)$area <= 0) {
        $errors[] = 'Area (hectares) must be greater than 0.';
    }

    // ---- Planting date ----
    $planted = $t('planting_date');
    $d = DateTime::createFromFormat('Y-m-d', $planted);
    $plantedOk = $d && $d->format('Y-m-d') === $planted;
    if ($planted === '') $errors[] = 'Planting date is required.';
    elseif (!$plantedOk) $errors[] = 'Enter a valid planting date.';

    $expected = null;
    if ($cropType && $plantedOk) {
        $expected = calculateCropExpectedHarvest($planted, $cropType['max_value'], $cropType['unit']);
    }

    // ---- Address ----
    $regionCode = $t('region_code'); $provinceCode = $t('province_code');
    $cityCode = $t('city_code'); $brgyCode = $t('barangay_code');
    $regionName = $t('region_name'); $provinceName = $t('province_name');
    $cityName = $t('city_name'); $brgyName = $t('barangay_name');
    $purok = preg_replace('/\s+/', ' ', $t('purok'));

    $addrErrors = [];
    if (!preg_match($codeRe, $regionCode)) $addrErrors[] = 'Region is required.';
    if ($provinceCode !== '' && !preg_match($codeRe, $provinceCode)) $addrErrors[] = 'Province is not valid.';
    if (!preg_match($codeRe, $cityCode)) $addrErrors[] = 'Municipality / City is required.';
    if (!preg_match($codeRe, $brgyCode)) $addrErrors[] = 'Barangay is required.';

    foreach ([['Region', $regionName, true], ['Province', $provinceName, $provinceCode !== ''],
              ['Municipality / City', $cityName, true], ['Barangay', $brgyName, true]] as [$label, $val, $req]) {
        if ($val === '') {
            if ($req && !in_array("$label is required.", $addrErrors, true)) $addrErrors[] = "$label is required.";
        } elseif (mb_strlen($val) > 100 || !preg_match($geoRe, $val)) {
            $addrErrors[] = "$label is not valid.";
        }
    }

    if ($purok === '') $addrErrors[] = 'Purok / Street is required.';
    elseif (mb_strlen($purok) > 100) $addrErrors[] = 'Purok / Street is too long (max 100).';
    elseif (!preg_match($purokRe, $purok)) $addrErrors[] = 'Purok / Street may only contain letters, numbers, spaces and . , # / -';

    $errors = array_merge($errors, $addrErrors);

    $brgyLabel = $brgyName === '' ? '' : (preg_match('/^(brgy|barangay)\b/i', $brgyName) ? $brgyName : 'Brgy. ' . $brgyName);
    $location = implode(', ', array_filter([$purok, $brgyLabel, $cityName, $provinceName, $regionName], fn($v) => $v !== ''));

    return [
        'errors' => $errors,
        'data' => [
            'crop_type_id' => $cropType ? (int)$cropType['id'] : null,
            'crop_name' => $cropType['name'] ?? '',
            'area_hectares' => (float)$area,
            'planting_date' => $planted,
            'expected_harvest_date' => $expected,
            'location' => $location,
            'region_code' => $regionCode,
            'province_code' => $provinceCode !== '' ? $provinceCode : null,
            'city_code' => $cityCode,
            'barangay_code' => $brgyCode,
            'purok' => $purok,
        ],
    ];
}

/**
 * Bagong planting: Growing agad (kapareho ng addCropPlanting()), may address codes na.
 */
function cm_insert_planting(PDO $pdo, int $memberId, array $d): bool {
    $stmt = $pdo->prepare("
        INSERT INTO crops
            (member_id, crop_type_id, crop_name, location, region_code, province_code, city_code, barangay_code, purok,
             area_hectares, planting_date, expected_harvest_date, status)
        VALUES
            (:member_id, :crop_type_id, :crop_name, :location, :region_code, :province_code, :city_code, :barangay_code, :purok,
             :area_hectares, :planting_date, :expected_harvest_date, 'growing')
    ");
    return $stmt->execute([
        ':member_id' => $memberId,
        ':crop_type_id' => $d['crop_type_id'],
        ':crop_name' => $d['crop_name'],
        ':location' => $d['location'],
        ':region_code' => $d['region_code'],
        ':province_code' => $d['province_code'],
        ':city_code' => $d['city_code'],
        ':barangay_code' => $d['barangay_code'],
        ':purok' => $d['purok'],
        ':area_hectares' => $d['area_hectares'],
        ':planting_date' => $d['planting_date'],
        ':expected_harvest_date' => $d['expected_harvest_date'],
    ]);
}