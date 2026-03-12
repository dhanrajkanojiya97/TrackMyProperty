<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

if (is_logged_in()) {
    header('Location: sell.php');
    exit;
}

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !isset($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            sign_in_user(
                (string) ($user['id'] ?? ''),
                (string) ($user['name'] ?? ''),
                (string) ($user['email'] ?? '')
            );
            header('Location: sell.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | TrackMyProperty</title>
<link rel="stylesheet" href="style.css">
<style>
.auth-wrap {
    max-width: 420px;
    margin: 70px auto;
    background: rgba(255,255,255,0.85);
    border-radius: 22px;
    padding: 30px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
}
.auth-wrap h2 {
    color: #be185d;
    margin-bottom: 15px;
    text-align: center;
}
.auth-wrap p {
    text-align: center;
    margin-top: 15px;
}
.auth-wrap input {
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid #f3f4f6;
    margin-top: 10px;
}
.auth-wrap button {
    width: 100%;
    margin-top: 16px;
}
.alert {
    background: #fff1f2;
    color: #9f1239;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 14px;
}
@media (max-width: 768px) {
    .auth-wrap {
        margin: 28px auto 40px;
        padding: 24px 18px;
    }
}
</style>
</head>
<body>
<nav>
    <div class="logo">TrackMy<span>Property</span></div>
    <div class="nav-links">
        <a href="buy.php">Buy</a>
        <a href="rent.php">Rent</a>
        <a href="sell.php">Sell</a>
        <a href="agent.php">Agents</a>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    </div>
</nav>

<div class="auth-wrap">
    <h2>Welcome Back</h2>
    <?php if ($error): ?>
        <div class="alert"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <?php echo csrf_input(); ?>
        <input type="email" name="email" placeholder="Email" value="<?php echo h($email); ?>" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
    <p><a href="forgot-password.php">Forgot your password?</a></p>
    <p>New here? <a href="register.php">Create an account</a></p>
</div>
</body>
</html>
