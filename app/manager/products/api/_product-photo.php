<?php
/**
 * Product photo helpers, included by add-product.php and update-product.php.
 *
 * Photos are saved on disk under <project root>/assets/img/products/ and the
 * database stores the path relative to the site root, for example
 * "assets/img/products/prod_ab12cd34.jpg". That matches how products.php
 * builds the image URL: BASE_URL . '/' . $p['photo_path'].
 *
 * This file only defines functions. It must not output anything.
 */

if (!defined('PRODUCT_PHOTO_DIR')) {
    define('PRODUCT_PHOTO_DIR', 'assets/img/products');
    define('PRODUCT_PHOTO_MAX_BYTES', 5 * 1024 * 1024); // same 5MB limit as the JS check
}

/**
 * Project root folder (app/manager/products/api -> 4 levels up).
 */
function productPhotoRoot() {
    return realpath(__DIR__ . '/../../../../');
}

/**
 * Validates and stores an uploaded product photo.
 *
 * Returns the relative path to save in products.photo_path, or null.
 * - No file chosen: returns null and leaves $error empty (keep current photo).
 * - Problem with the file: returns null and puts a message in $error.
 */
function saveProductPhoto($file, &$error) {
    $error = '';

    if (!is_array($file) || !isset($file['error'])) {
        return null;
    }

    // The form always sends the file field, even when nothing was chosen.
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            $error = 'Photo is too large (max 5MB).';
        } else {
            $error = 'Photo upload failed. Please try again.';
        }
        return null;
    }

    if ($file['size'] > PRODUCT_PHOTO_MAX_BYTES) {
        $error = 'Photo must be 5MB or smaller.';
        return null;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'Invalid upload.';
        return null;
    }

    // Check the real file contents, not the name or the browser-sent type.
    $info = @getimagesize($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if ($info === false || !isset($allowed[$info['mime']])) {
        $error = 'Photo must be a PNG, JPG or WEBP image.';
        return null;
    }

    $root = productPhotoRoot();
    if ($root === false) {
        $error = 'Could not find the project folder.';
        return null;
    }

    $dir = $root . '/' . PRODUCT_PHOTO_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $error = 'Could not create the photo folder (' . PRODUCT_PHOTO_DIR . ').';
        return null;
    }
    if (!is_writable($dir)) {
        $error = 'The photo folder is not writable (' . PRODUCT_PHOTO_DIR . ').';
        return null;
    }

    $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $allowed[$info['mime']];
    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        $error = 'Could not save the photo. Please try again.';
        return null;
    }

    return PRODUCT_PHOTO_DIR . '/' . $filename;
}

/**
 * Deletes a stored product photo file. Safe to call with null or an empty
 * path, and it only ever deletes files inside assets/img/products/.
 */
function deleteProductPhotoFile($path) {
    if (!$path || !is_string($path)) {
        return;
    }

    $prefix = PRODUCT_PHOTO_DIR . '/';
    if (strpos($path, $prefix) !== 0) {
        return; // not one of ours, leave it alone
    }

    $root = productPhotoRoot();
    if ($root === false) {
        return;
    }

    $full = $root . '/' . $prefix . basename($path);
    if (is_file($full)) {
        @unlink($full);
    }
}