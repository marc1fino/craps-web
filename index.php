<?php
require_once __DIR__ . '/session.php';

if (!isset($_SESSION['logout_token'])) {
    $_SESSION['logout_token'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Craps</title>
</head>

<body>
    <h1>Craps Game</h1>
    <?php if (isset($_SESSION['username'])): ?>
        <p> Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
        <form method="post" action="juga.php">
            <input type="submit" value="Play">
        </form>
        
        <br>

        <form method="post" action="logout.php">
            <input type="hidden" name="logout_token" value="<?= htmlspecialchars($_SESSION['logout_token'], ENT_QUOTES, 'UTF-8') ?>" >
            <input type="submit" value="Log out">
        </form>
        
    <?php else: ?>
        <form action="login.html">
            <input type="submit" value="Log in">
        </form>
        
        <br>

        <form action="signup.html">
            <input type="submit" value="Sign up">
        </form>

    <?php endif; ?>
</body>
</html>
