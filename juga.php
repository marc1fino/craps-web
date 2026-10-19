<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

function clearGame(): void
{
    unset(
        $_SESSION['game_point'],
        $_SESSION['game_result'],
        $_SESSION['game_token'],
        $_SESSION['game_user'],
        $_SESSION['last_roll']
    );
}

function abandonGame(): void
{
    if (empty($_SESSION['game_result'])) {
        clearGame();
    }
}

function enterGame(string $user, bool $continueGame): void
{
    if (($_SESSION['game_user'] ?? null) !== $user
        || (!$continueGame && empty($_SESSION['game_result']))) {
        clearGame();
    }
    $_SESSION['game_user'] = $user;
}

function isGameTokenValid($token): bool
{
    return is_string($token)
        && isset($_SESSION['game_token'])
        && hash_equals($_SESSION['game_token'], $token);
}

// Leaving the page does not require resending the player password.
if (isset($_POST['abandon'])) {
    if (isGameTokenValid($_POST['game_token'] ?? null)) {
        abandonGame();
    }
    http_response_code(204);
    exit;
}

$continueGame = isset($_POST['roll']) || isset($_POST['new_game']);
if ($continueGame && !isGameTokenValid($_POST['game_token'] ?? null)) {
    header('Location: index.html', true, 303);
    exit;
}

$user = $_SESSION['username'] ?? null;

if (!is_string($user) || $user === '') {
    header('Location: login.html', true, 303);
    exit;
}

try {
    $connection = require __DIR__ . '/config.php';
} catch (PDOException | RuntimeException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit("Could not access the database. Check the PHP log.");
}

// Initialize the player counters if their row does not exist yet.
try {
    $statement = $connection->prepare('SELECT 1 FROM record WHERE `user` = :user LIMIT 1');
    $statement->execute(['user' => $user]);
    if ($statement->fetchColumn() === false) {
        $statement = $connection->prepare(
            'INSERT INTO record (`user`, wins, losses) VALUES (:user, 0, 0)'
        );
        $statement->execute(['user' => $user]);
    }
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('Could not initialize the player record.');
}

// On login, discard unfinished games and keep completed results.
enterGame($user, $continueGame);
// Rotate the token so a delayed close request cannot delete a newer game.
$_SESSION['game_token'] = bin2hex(random_bytes(32));
$game_token = $_SESSION['game_token'];

echo "Welcome, ";
echo htmlspecialchars($user, ENT_QUOTES, 'UTF-8');

$die1 = 0;
$die2 = 0;
$score = 0;

function roll() {
global $die1;
global $die2;
global $score;

$die1 = rand(1,6);
$die2 = rand(1,6);
$_SESSION['last_roll'] = [$die1, $die2];
$score = $die1 + $die2;
showDice($die1, $die2);
}

function showDice(int $die1, int $die2): void {

echo "<br>";
if ($die1 == 1)
{
echo "<img src=img/dice1.png>";
}
if ($die1 == 2)
{
echo "<img src=img/dice2.png>";
}
if ($die1 == 3)
{
echo "<img src=img/dice3.png>";
}
if ($die1 == 4)
{
echo "<img src=img/dice4.png>";
}
if ($die1 == 5)
{
echo "<img src=img/dice5.png>";
}
if ($die1 == 6)
{
echo "<img src=img/dice6.png>";
}

if ($die2 == 1)
{
echo "<img src=img/dice1.png>";
}
if ($die2 == 2)
{
echo "<img src=img/dice2.png>";
}
if ($die2 == 3)
{
echo "<img src=img/dice3.png>";
}
if ($die2 == 4)
{
echo "<img src=img/dice4.png>";
}
if ($die2 == 5)
{
echo "<img src=img/dice5.png>";
}
if ($die2 == 6)
{
echo "<img src=img/dice6.png>";
}

$score = $die1 + $die2;
echo "<br>";
echo "You rolled <span style='font-weight: bold;'> ".$die1." </span> and <span style='font-weight: bold;'> ".$die2."</span>. Total: <span style='font-weight: bold';> ".$score."</span><br>";
}

// Start a new game.
if (isset($_POST['new_game'])) {
    unset($_SESSION['game_point']);
    unset($_SESSION['game_result']);
    unset($_SESSION['last_roll']);
}

// Roll only while the game is active.
if (empty($_SESSION['game_result'])) {
    $previousState = $_SESSION;
    roll();

    if (!isset($_SESSION['game_point'])) {
        // First roll.
        if ($score == 7 OR $score == 11) {
            $_SESSION['game_result'] = 'won';
        } else if ($score == 2 OR $score == 3 OR $score == 12) {
            $_SESSION['game_result'] = 'lost';
        } else {
            // Keep the point for subsequent requests.
            $_SESSION['game_point'] = $score;
        }
    } else {
        // Subsequent rolls: the point stays the same.
        if ($score == $_SESSION['game_point']) {
            $_SESSION['game_result'] = 'won';
        } else if ($score == 7) {
            $_SESSION['game_result'] = 'lost';
        }
    }

    // Save each roll.
    try {
        $connection->beginTransaction();
        $statement = $connection->prepare(
            'INSERT INTO results (die1, die2, `user`) VALUES (:die1, :die2, :user)'
        );
        $statement->execute(['die1' => $die1, 'die2' => $die2, 'user' => $user]);
        // Count the result only on the final roll, not when displaying it.
        if (!empty($_SESSION['game_result'])) {
            $win = $_SESSION['game_result'] === 'won' ? 1 : 0;
            $statement = $connection->prepare(
                'UPDATE record SET wins = wins + :win,
                 losses = losses + :loss WHERE `user` = :user'
            );
            $statement->execute([
                'win' => $win,
                'loss' => 1 - $win,
                'user' => $user,
            ]);
        }
        $connection->commit();
    } catch (PDOException $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        $_SESSION = $previousState;
        error_log($error->getMessage());
        http_response_code(500);
        exit('Could not save the roll. Check the PHP log.');
    }
}

// Display the game status and choose the button.
if (!empty($_SESSION['game_result'])) {
    // On a later visit, show the last roll without rolling or inserting again.
    if ($die1 === 0 && isset($_SESSION['last_roll'])) {
        showDice(...$_SESSION['last_roll']);
    }
    if ($_SESSION['game_result'] == 'won') {
        echo "<br>You won!";
    } else {
        echo "<br>You lost!";
    }

    $buttonName = 'new_game';
    $buttonText = 'Play again';
} else {
    echo "<br>Your point is "
         . $_SESSION['game_point'];

    $buttonName = 'roll';
    $buttonText = 'Roll again';
}

$game_active = empty($_SESSION['game_result']) ? '1' : '0';

echo <<<EOD
<form id="game" data-active="$game_active" method="POST" action="juga.php">
    <input type="hidden" name="game_token" value="$game_token">
    <button type="submit" name="$buttonName" value="1">$buttonText</button>
</form>
<script src="partida.js"></script>
EOD;
