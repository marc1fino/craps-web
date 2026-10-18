<?php
$user = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
$passwordRepeat = $_POST['password_repeat'] ?? null;

if (!is_string($user) || !is_string($password) || !is_string($passwordRepeat)) {
    header('Location: signup.html?error=invalid_data', true, 303);
    exit;
}

$user = trim($user);

if ($user === '' || $password === '' || $passwordRepeat === '') {
    header('Location: signup.html?error=empty_fields', true, 303);
    exit;
}

if (mb_strlen($user, 'UTF-8') > 30) {
    header('Location: signup.html?error=username_too_long', true, 303);
    exit;
}

if ($password !== $passwordRepeat) {
    header('Location: signup.html?error=password_mismatch', true, 303);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $connection = require __DIR__ . '/config.php';
    $statement = $connection->prepare(
    'SELECT 1 FROM users WHERE `user` = :user LIMIT 1');
    $statement->execute(['user' => $user]);

    if ($statement->fetchColumn() !== false) {
        header('Location: login.html?error=user_exists', true, 303);
        exit;
    }
} catch (PDOException | RuntimeException $error) {
    error_log($error->getMessage());
    header('Location: signup.html?error=server_error', true, 303);
    exit;
}

$insertingUser = true;
try {
    // Save the account and its counters together, or undo both on failure.
    $connection->beginTransaction();

    $statement = $connection->prepare(
        'INSERT INTO users (`user`, `password`) VALUES (:user, :password)'
    );
    $statement->execute(['user' => $user, 'password' => $hash]);

    $insertingUser = false;
    $statement = $connection->prepare(
        'INSERT INTO record (`user`, wins, losses) VALUES (:user, 0, 0)'
    );
    $statement->execute(['user' => $user]);

    $connection->commit();
} catch (PDOException $error) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    error_log($error->getMessage());
    // UNIQUE also prevents duplicates when two registrations arrive at the same time.
    if ($insertingUser && ($error->errorInfo[1] ?? null) === 1062) {
        header('Location: login.html?error=user_exists', true, 303);
    } else {
        header('Location: signup.html?error=server_error', true, 303);
    }
    exit;
}

header('Location: login.html');
exit;
?>
