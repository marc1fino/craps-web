<?php
session_start();
$user = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;

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
$_SESSION['username'] = $account['user'];
header('Location: juga.php', true, 303);
exit;
