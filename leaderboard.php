<!DOCTYPE html>
<html lang="en">

<head>
    <meta content="text/html; charset=UTF-8" http-equiv="content-type" />
    <title>Craps</title>
</head>


<?php
require_once __DIR__ . '/session.php';

try {
    $connection = require __DIR__ . '/config.php';
} catch (PDOException | RuntimeException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit("Could not access the database. Check the PHP log.");
}

try {
    $statement = $connection->prepare(
        'SELECT `user`, wins, losses
        FROM record
        ORDER BY wins DESC, losses ASC, `user` ASC'
    );

    $statement->execute();
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('Could not initialize the player record.');
}
?>

<body>
    <table>
        <tr>
            <th> Player </th>
            <th> Wins </th>
            <th> Losses </th>
        </tr>

        <?php while ($row = $statement->fetch(PDO::FETCH_ASSOC)): ?>
            <tr>
                <td> <?= htmlspecialchars($row['user'], ENT_QUOTES, 'UTF-8') ?> </td>
                <td> <?= (int) $row['wins'] ?> </td>
                <td> <?= (int) $row['losses'] ?> </td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>