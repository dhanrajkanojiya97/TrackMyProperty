<?php
declare(strict_types=1);

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';

require_login();

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentUserName = (string) ($_SESSION['user_name'] ?? 'User');
$currentUserEmail = (string) ($_SESSION['user_email'] ?? '');

$profileMessage = '';
$profileError = '';
$passwordMessage = '';
$passwordError = '';

$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$currentUserId]);
$user = $stmt->fetch();

if (!$user) {
    sign_out_user();
    header('Location: login.php');
    exit;
}

$profileForm = [
    'name' => (string) ($user['name'] ?? $currentUserName),
    'email' => (string) ($user['email'] ?? $currentUserEmail),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'update_profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        if ($name === '') {
            $profileError = 'Name is required.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'A valid email is required.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $currentUserId]);
            if ($stmt->fetch()) {
                $profileError = 'That email is already in use.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                $stmt->execute([$name, $email, $currentUserId]);

                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;

                $profileMessage = 'Profile updated successfully.';
                $currentUserName = $name;
                $profileForm['name'] = $name;
                $profileForm['email'] = $email;
            }
        }
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$currentUserId]);
        $row = $stmt->fetch();

        if (!$row || !isset($row['password_hash']) || !password_verify($currentPassword, (string) $row['password_hash'])) {
            $passwordError = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $passwordError = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'New passwords do not match.';
        } elseif (password_verify($newPassword, (string) $row['password_hash'])) {
            $passwordError = 'New password must be different from the current one.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $currentUserId]);
            $passwordMessage = 'Password updated successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | TrackMyProperty</title>
<link rel="stylesheet" href="style.css">
<style>
.profile-shell {
    max-width: 980px;
    margin: 60px auto 80px;
    padding: 0 24px;
}
.profile-header {
    text-align: center;
    margin-bottom: 32px;
}
.profile-header h1 {
    color: #be185d;
    font-size: 38px;
}
.profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 22px;
}
.profile-card {
    background: rgba(255,255,255,0.85);
    border-radius: 22px;
    padding: 26px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
}
.profile-card h2 {
    color: #be185d;
    margin-bottom: 12px;
    font-size: 22px;
}
.profile-card p {
    color: #6b7280;
    margin-bottom: 16px;
}
.profile-card input {
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid #f3f4f6;
    margin-top: 10px;
}
.profile-card button {
    width: 100%;
    margin-top: 16px;
}
.user-chip {
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(190,24,93,0.08);
    color: #be185d;
    font-size: 14px;
    font-weight: 600;
}
.alert {
    background: #fff1f2;
    color: #9f1239;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 14px;
}
.notice {
    background: #f0fdf4;
    color: #166534;
    padding: 10px 12px;
    border-radius: 10px;
    margin-bottom: 12px;
    font-size: 14px;
}
@media (max-width: 768px) {
    .profile-shell {
        margin: 30px auto 60px;
        padding: 0 18px;
    }
    .profile-header h1 {
        font-size: 30px;
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
        <a href="enquiries.php">Enquiries</a>
        <a class="user-chip" href="profile.php"><?php echo h($currentUserName); ?></a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<section class="profile-shell">
    <div class="profile-header">
        <h1>My Profile</h1>
        <p>Update your account details and keep your login secure.</p>
    </div>

    <div class="profile-grid">
        <div class="profile-card">
            <h2>Account Details</h2>
            <p>Keep your name and email up to date.</p>

            <?php if ($profileError): ?>
                <div class="alert"><?php echo h($profileError); ?></div>
            <?php endif; ?>

            <?php if ($profileMessage): ?>
                <div class="notice"><?php echo h($profileMessage); ?></div>
            <?php endif; ?>

            <form method="POST" action="profile.php">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="update_profile">
                <input type="text" name="name" placeholder="Full Name" value="<?php echo h($profileForm['name']); ?>" required>
                <input type="email" name="email" placeholder="Email" value="<?php echo h($profileForm['email']); ?>" required>
                <button type="submit">Save Changes</button>
            </form>
        </div>

        <div class="profile-card">
            <h2>Change Password</h2>
            <p>Use a strong password you do not reuse elsewhere.</p>

            <?php if ($passwordError): ?>
                <div class="alert"><?php echo h($passwordError); ?></div>
            <?php endif; ?>

            <?php if ($passwordMessage): ?>
                <div class="notice"><?php echo h($passwordMessage); ?></div>
            <?php endif; ?>

            <form method="POST" action="profile.php">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="change_password">
                <input type="password" name="current_password" placeholder="Current Password" required>
                <input type="password" name="new_password" placeholder="New Password (min 6 chars)" required>
                <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                <button type="submit">Update Password</button>
            </form>
        </div>
    </div>
</section>
</body>
</html>
