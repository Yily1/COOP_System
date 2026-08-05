<?php
require_once '../config/config.php';
require_once '../config/functions.php';
// SECURITY: this page was previously public and unauthenticated, so
// anyone who found the URL could repeatedly trigger real emails
// (spam/abuse vector), and it hardcoded a personal email address.
// Restrict to admins, and delete this whole tests/ folder before
// going to production.
requireRole('admin');

$to = $_SESSION['email']; // send to the logged-in admin instead of a hardcoded address
$subject = 'Test Email';
$message = 'This is a test email from Coop System.';
$headers = 'From: noreply@ics-dev.io';

if (@mail($to, $subject, $message, $headers)) {
    echo 'Email sent successfully!';
} else {
    echo 'Email sending failed!';
}
?>