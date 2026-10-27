<?php
require_once 'config/config.php';
require_once 'config/functions.php';
require_once 'includes/activity-logger.php'; 

// uncomment on deployment
/*
require_once $_SERVER['DOCUMENT_ROOT'] . '/test/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/test/config/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/test/includes/activity-logger.php';
*/

if (isLoggedIn()) {
    switch($_SESSION['role']) {
        case 'admin':
            redirect('/app/admin/dashboard.php');
            break;
        case 'manager':
            redirect('/app/manager/dashboard.php');
            break;
        case 'user':
            redirect('/app/user/dashboard.php');
            break;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accepts either the account's email or its username in the
    // same field. Kept as $email for logActivity()/error messages
    // below, but it may hold a username instead.
    $login = trim($_POST['login'] ?? '');
    $email = $login;
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND is_verified = 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        // Log successful login
        logActivity($pdo, $user['id'], $user['email'], 'login', 'success');
        
        switch($user['role']) {
            case 'admin':
                redirect('/app/admin/dashboard.php');
                break;
            case 'manager':
                redirect('/app/manager/dashboard.php');
                break;
            case 'user':
                redirect('/app/user/dashboard.php');
                break;
        }
    } else {
        // Failed login
        $error = "Invalid credentials or email not verified";
        
        // Log failed login attempt
        logActivity($pdo, null, $email, 'login', 'failed');
    }
}

renderHeader('Login');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
body { background: #f5f3ec; }

/* The global style.css wraps this page in .auth-wrapper > .auth-box
   (the outer white box with the green border). Its look is kept; only
   its size is tightened here and the whole thing is centered on screen.
   Only affects this page. */
.auth-wrapper {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 100vh !important;
    box-sizing: border-box !important;
    padding: 24px 16px 6vh !important;
}
.auth-box {
    box-sizing: border-box !important;
    width: 100% !important;
    max-width: 380px !important;
    margin: 0 auto !important;
    padding: 24px 22px 26px !important;
}

.login-shell {
    width: 100%;
    margin: 0;
    padding: 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    text-transform: none;
    letter-spacing: normal;
}

.login-logo {
    text-align: center;
    margin-bottom: 12px;
}
.login-logo img {
    max-width: 90px;
    height: auto;
}

.login-shell h1 {
    text-align: center;
    font-family: inherit;
    font-size: 20px;
    font-weight: 600;
    color: #1b3a24;
    text-transform: none;
    letter-spacing: normal;
    margin: 0 0 4px;
}

.login-sub {
    text-align: center;
    font-size: 14px;
    color: #3f4a3f;
    margin: 0 0 18px;
}

/* No border of its own: the outer .auth-box already frames the form */
.login-card {
    background: transparent;
    border: none;
    border-radius: 0;
    padding: 0;
}

.login-card .form-group { margin: 0 !important; }

.login-card label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    text-transform: none !important;
    letter-spacing: normal;
    color: #2f3a2f;
    margin: 0 0 6px;
}

.login-card input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid #E4DCC8;
    border-radius: 8px;
    background: #FAF7EF;
    font-family: inherit;
    font-size: 15px;
    color: #1b3a24;
    margin: 0 0 14px !important;
}

.login-card input::placeholder { color: #7a8276; }

.login-card input:focus {
    outline: none;
    border-color: #3B6D11;
    background: #fff;
}

/* Password field with show/hide eye button */
.pw-wrap {
    position: relative;
    margin: 0 0 14px;
}
.login-card .pw-wrap input {
    margin: 0 !important;
    padding-right: 44px;
}
.pw-toggle {
    position: absolute;
    top: 0;
    bottom: 0;
    right: 4px;
    width: 38px;
    display: flex !important;
    align-items: center;
    justify-content: center;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
    cursor: pointer;
}
.pw-toggle:hover { background: transparent !important; }
.pw-toggle svg {
    width: 20px;
    height: 20px;
    stroke: #3B6D11;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.pw-toggle .eye-off { display: none; }
.pw-toggle.is-visible .eye { display: none; }
.pw-toggle.is-visible .eye-off { display: block; }

/* Only the Login (submit) button gets the big green style */
.login-card button[type="submit"] {
    display: block;
    width: 100% !important;
    padding: 11px;
    margin: 4px 0 0 !important;
    border: none;
    border-radius: 10px;
    background: #3B6D11 !important;
    color: #fff;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    text-transform: none !important;
    letter-spacing: normal;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    cursor: pointer;
}

.login-card button[type="submit"]:hover { background: #2e5a0d !important; }

/* Mobile: 16px inputs stop iPhone from zooming in when tapping a field */
@media (max-width: 480px) {
    .auth-wrapper { padding: 16px 14px 4vh !important; }
    .auth-box { padding: 18px 16px 20px !important; max-width: 340px !important; }
    .login-logo { margin-bottom: 8px; }
    .login-logo img { max-width: 68px; }
    .login-shell h1 { font-size: 18px; }
    .login-sub { font-size: 13px; margin-bottom: 14px; }
    .login-card label { font-size: 13px; margin-bottom: 5px; }
    .login-card input { font-size: 16px; padding: 9px 11px; margin-bottom: 12px !important; }
    .login-card .pw-wrap { margin-bottom: 12px; }
    .login-card .pw-wrap input { margin-bottom: 0 !important; }
    .login-card button[type="submit"] { padding: 10px; }
}

.login-shell .error {
    border: none;
    background: #fbe6e6;
    color: #a6322f;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    margin-bottom: 14px;
}
</style>

<div class="login-shell">

<div class="login-logo">
    <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="SJFMC Coop Logo">
</div>

<h1>Welcome back</h1>
<p class="login-sub">Log in to continue to your account.</p>

<div class="login-card">

<?php if ($error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <div class="form-group">
        <label for="login">Email or username</label>
        <input type="text" id="login" name="login" placeholder="" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="password">Password</label>
        <div class="pw-wrap">
            <input type="password" id="password" name="password" placeholder="" required autocomplete="current-password">
            <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">
                <svg class="eye" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            </button>
        </div>
    </div>

    <button type="submit">Login</button>
</form>

</div>
</div>

<script>
(function () {
    var btn = document.getElementById('pwToggle');
    var input = document.getElementById('password');
    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.classList.toggle('is-visible', show);
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
})();
</script>

<?php renderFooter(); ?>