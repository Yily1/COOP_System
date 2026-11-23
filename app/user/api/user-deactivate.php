<?php
// Hindi na puwedeng i-deactivate ng user ang sarili niyang account.
// Manager lang ang puwedeng mag-Active / Inactive ng account.
// Hindi na ginagamit ng profile.php ang file na ito, pero hinaharangan pa rin
// para walang makapag-deactivate sa pamamagitan ng direktang request.
// (Puwede mo na ring burahin ang file na ito.)
header('Content-Type: application/json');
http_response_code(403);
echo json_encode([
    'success' => false,
    'message' => 'Only the manager can change your account status.',
]);
exit;