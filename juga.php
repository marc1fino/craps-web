<?php
require_once __DIR__ . '/session.php';
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

function clearGame(): void
{
    unset(
        $_SESSION['game_point'],
        $_SESSION['game_result'],
        $_SESSION['game_token'],
        $_SESSION['game_user'],
        $_SESSION['last_roll'],
        $_SESSION['bet_amount']
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

$isBetRequest = isset($_POST['place_bet']);
$isRollRequest = isset($_POST['roll']);
$isNewGameRequest = isset($_POST['new_game']);
$continueGame = $isBetRequest || $isRollRequest || $isNewGameRequest;
if ((int) $isBetRequest + (int) $isRollRequest + (int) $isNewGameRequest > 1) {
    http_response_code(400);
    exit('Choose only one game action.');
}
if ($continueGame && !isGameTokenValid($_POST['game_token'] ?? null)) {
    header('Location: index.php', true, 303);
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

// Returning without a game action abandons an unfinished bet without a refund.
enterGame($user, $continueGame);
// Rotate the token so a delayed close request cannot delete a newer game.
$_SESSION['game_token'] = bin2hex(random_bytes(32));
$game_token = $_SESSION['game_token'];

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
if ($isNewGameRequest && !empty($_SESSION['game_result'])) {
    unset($_SESSION['game_point']);
    unset($_SESSION['game_result']);
    unset($_SESSION['last_roll']);
    unset($_SESSION['bet_amount']);
}

$errorMessage = null;
$bet = null;
if ($isBetRequest) {
    $input = $_POST['bet_amount'] ?? null;
    $bet = is_string($input) ? filter_var($input, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => intdiv(PHP_INT_MAX, 2)],
    ]) : false;

    if (isset($_SESSION['bet_amount']) || !empty($_SESSION['game_result'])) {
        $errorMessage = 'Finish this game before placing another bet.';
    } elseif ($bet === false) {
        $errorMessage = 'Enter a whole-number bet greater than zero.';
    }
}
if ($isRollRequest && !isset($_SESSION['bet_amount'])) {
    $errorMessage = 'Place a bet before rolling.';
}
if ($isNewGameRequest && isset($_SESSION['bet_amount'])) {
    $errorMessage = 'Finish your current game before starting another one.';
}

// A visit never rolls automatically. Only a valid bet or roll request does.
if (($isBetRequest || $isRollRequest)
    && empty($_SESSION['game_result']) && $errorMessage === null) {
    $previousState = $_SESSION;
    try {
        $connection->beginTransaction();
        if ($isBetRequest) {
            // Check and deduct atomically, even if another session uses the same balance.
            $statement = $connection->prepare(
                'UPDATE users SET money = money - :amount
                 WHERE `user` = :user AND money >= :minimum'
            );
            $statement->execute(['amount' => $bet, 'minimum' => $bet, 'user' => $user]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('Insufficient balance for this bet.');
            }
            $_SESSION['bet_amount'] = $bet;
        }
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

        // Save the roll, result and payout in the same transaction as the bet.
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
            if ($win === 1) {
                $statement = $connection->prepare(
                    'UPDATE users SET money = money + :payout WHERE `user` = :user'
                );
                $statement->execute([
                    'payout' => $_SESSION['bet_amount'] * 2,
                    'user' => $user,
                ]);
            }
        }
        $connection->commit();
    } catch (PDOException | DomainException $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        $_SESSION = $previousState;
        if ($error instanceof DomainException) {
            $errorMessage = $error->getMessage();
        } else {
            error_log($error->getMessage());
            http_response_code(500);
            $errorMessage = 'Could not save the turn. No balance changes were saved. Please try again.';
        }
    }
}

// Read the balance after the transaction so the displayed amount is current.
try {
    $statement = $connection->prepare('SELECT money FROM users WHERE `user` = :user LIMIT 1');
    $statement->execute(['user' => $user]);
    $money = $statement->fetchColumn();
    if ($money === false) {
        http_response_code(403);
        exit('Your account is no longer available.');
    }
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('Could not load your balance. Please try again later.');
}

echo 'Welcome, ' . htmlspecialchars($user, ENT_QUOTES, 'UTF-8');
echo '<p>Balance: ' . htmlspecialchars((string) $money, ENT_QUOTES, 'UTF-8') . ' credits</p>';
if ($errorMessage !== null) {
    echo '<p role="alert">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</p>';
}
if (isset($_SESSION['bet_amount'])) {
    echo '<p>Bet: ' . (int) $_SESSION['bet_amount'] . ' credits</p>';
}
// Rendering never changes the balance or records another roll.
if (isset($_SESSION['last_roll'])) {
    showDice(...$_SESSION['last_roll']);
}

// Display the game status and choose the button.
if (!empty($_SESSION['game_result'])) {
    if ($_SESSION['game_result'] == 'won') {
        echo "<br>You won!";
    } else {
        echo "<br>You lost!";
    }

    $buttonName = 'new_game';
    $buttonText = 'Play again';
} elseif (isset($_SESSION['bet_amount'])) {
    echo "<br>Your point is "
         . $_SESSION['game_point'];

    $buttonName = 'roll';
    $buttonText = 'Roll again';
} else {
    $buttonName = 'place_bet';
    $buttonText = 'Bet and roll';
}

$game_active = isset($_SESSION['bet_amount']) && empty($_SESSION['game_result']) ? '1' : '0';

echo <<<EOD
<form id="game" data-active="$game_active" method="POST" action="juga.php">
    <input type="hidden" name="game_token" value="$game_token">
EOD;

if ($buttonName === 'place_bet') {
    echo <<<EOD
    <p>Winning returns twice your bet, including your stake. Leaving an unfinished game forfeits the bet.</p>
    <label for="bet_amount">Your bet (whole credits):</label>
    <input type="number" id="bet_amount" name="bet_amount" min="1" step="1" required>
EOD;
}

echo <<<EOD
    <button type="submit" name="$buttonName" value="1">$buttonText</button>
</form>
<script src="partida.js"></script>
EOD;
