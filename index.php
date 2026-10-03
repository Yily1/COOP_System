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

<style>
body { background: #f5f3ec; }

.login-shell {
    width: 100%;
    max-width: 380px;
    margin: 40px auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.login-card {
    background: #fff;
    border: 1px solid #eceae4;
    border-radius: 14px;
    padding: 24px 22px;
}

.login-shell h1 {
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    color: #1b3a24;
    margin: 0 0 20px;
}

.login-card label {
    display: block;
    font-size: 12.5px;
    font-weight: 600;
    color: #667066;
    margin-bottom: 6px;
}

.login-card input {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid #eceae4;
    border-radius: 8px;
    background: #f7f8f5;
    font-family: inherit;
    font-size: 14px;
    color: #1b3a24;
    margin-bottom: 14px;
}

.login-card input:focus {
    outline: none;
    border-color: #2e7d32;
    background: #fff;
}

.login-card button {
    width: 100%;
    padding: 11px;
    border: none;
    border-radius: 10px;
    background: #2e7d32;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.login-card button:hover { background: #256b28; }

.login-shell .error {
    background: #fbe6e6;
    color: #a6322f;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13px;
    margin-bottom: 14px;
}
</style>

<div class="login-shell">

<div style="text-align: center; margin-bottom: 20px;">
    <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="SJFMC Coop Logo" style="max-width: 120px; height: auto;">
</div>

<h1>Log in to your SJFMC account</h1>

<div class="login-card">

<?php if ($error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <div class="form-group">
        <label for="login">Email or Username:</label>
        <input type="text" id="login" name="login" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>">
    </div>
    
    <div class="form-group">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    
    <button type="submit">Login</button>
</form>

</div>
</div>

<?php renderFooter(); ?>