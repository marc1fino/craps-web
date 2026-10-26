<?php
require_once __DIR__ . '/session.php';

$user = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
$rememberMe = ($_POST['remember_me'] ?? '') === '1';

if (!is_string($user) || !is_string($password)) {
    header('Location: login.html?error=invalid_credentials', true, 303);
    exit;
}

$user = trim($user);
if ($user === '' || $password === '') {
    header('Location: login.html?error=invalid_credentials', true, 303);
    exit;
}

try { 
    $connection = require __DIR__ . '/config.php';
    $statement = $connection->prepare(
        'SELECT `user`, `password` FROM users WHERE `user` = :user LIMIT 1'
    );
    $statement->execute(['user' => $user]);
    $account = $statement->fetch();
} catch (PDOException | RuntimeException $error) {
    error_log($error->getMessage());
    header('Location: login.html?error=login_unavailable', true, 303);
    exit;
}

if (!$account || !password_verify($password, $account['password'])) {
    header('Location: login.html?error=invalid_credentials', true, 303);
    exit;
}

// Store only the authenticated username, never the password.
session_regenerate_id(true);
// A different account must not inherit the previous player's game.
if (($_SESSION['username'] ?? null) !== $account['user']) {
    $_SESSION = [];
}
$_SESSION['username'] = $account['user'];
$_SESSION['remember_me'] = $rememberMe;
$_SESSION['login_expires_at'] = time() + LOGIN_LIFETIME;
$_SESSION['logout_token'] = bin2hex(random_bytes(32));
sendLoginCookie();
header('Location: juga.php', true, 303);
exit;
