<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

function borrarPartida(): void
{
    unset(
        $_SESSION['punt_partida'],
        $_SESSION['partida_acabada'],
        $_SESSION['partida_token'],
        $_SESSION['partida_user'],
        $_SESSION['ultima_tirada']
    );
}

function abandonarPartida(): void
{
    if (empty($_SESSION['partida_acabada'])) {
        borrarPartida();
    }
}

function entrarPartida(string $user, bool $continuar): void
{
    if (($_SESSION['partida_user'] ?? null) !== $user
        || (!$continuar && empty($_SESSION['partida_acabada']))) {
        borrarPartida();
    }
    $_SESSION['partida_user'] = $user;
}

function tokenPartidaValido($token): bool
{
    return is_string($token)
        && isset($_SESSION['partida_token'])
        && hash_equals($_SESSION['partida_token'], $token);
}

// El cierre no necesita volver a enviar la contraseña del jugador.
if (isset($_POST['abandonar'])) {
    if (tokenPartidaValido($_POST['partida_token'] ?? null)) {
        abandonarPartida();
    }
    http_response_code(204);
    exit;
}

$continuar = isset($_POST['tirada']) || isset($_POST['nova_partida']);
if ($continuar && !tokenPartidaValido($_POST['partida_token'] ?? null)) {
    header('Location: index.html', true, 303);
    exit;
}

$user = $_POST['usuari'] ?? null;
$password = $_POST['contrassenya'] ?? null;

if (!is_string($user) || !is_string($password)) {
    header('Location: index.html');
    exit;
}

try {
    $conexion = require __DIR__ . '/config.php';
    $resultado = $conexion->prepare(
        'SELECT `user` FROM users WHERE `user` = :user AND `password` = :password LIMIT 2'
    );
    $resultado->execute(['user' => $user, 'password' => $password]);
    $usuarios = count($resultado->fetchAll());
} catch (PDOException | RuntimeException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit("No s'ha pogut accedir a la DB. Revisa el registre PHP.");
}

if ($usuarios != 1) {
    echo "El teu usuari o contrassenya no és correcte.";
    exit;
}

// Preparar el contador del usuario si todavía no tiene una fila.
try {
    $resultado = $conexion->prepare('SELECT 1 FROM record WHERE `user` = :user LIMIT 1');
    $resultado->execute(['user' => $user]);
    if ($resultado->fetchColumn() === false) {
        $resultado = $conexion->prepare(
            'INSERT INTO record (`user`, victorias, derrotas) VALUES (:user, 0, 0)'
        );
        $resultado->execute(['user' => $user]);
    }
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('No es pot preparar el registre del jugador.');
}

// Al entrar solo se descarta la partida pendiente, no un resultado final.
entrarPartida($user, $continuar);
// Renovarlo evita que el cierre tardío de una página antigua borre la nueva.
$_SESSION['partida_token'] = bin2hex(random_bytes(32));
$partida_token = $_SESSION['partida_token'];

echo "Benvingut, ";
echo htmlspecialchars($user, ENT_QUOTES, 'UTF-8');

$dau1 = 0;
$dau2 = 0;
$score = 0;

function tirada() {
global $dau1;
global $dau2;
global $score;

$dau1 = rand(1,6);
$dau2 = rand(1,6);
$_SESSION['ultima_tirada'] = [$dau1, $dau2];
$score = $dau1 + $dau2;
mostrarDados($dau1, $dau2);
}

function mostrarDados(int $dau1, int $dau2): void {

echo "<br>";
if ($dau1 == 1)
{
echo "<img src=img/dice1.png>";
}
if ($dau1 == 2)
{
echo "<img src=img/dice2.png>";
}
if ($dau1 == 3)
{
echo "<img src=img/dice3.png>";
}
if ($dau1 == 4)
{
echo "<img src=img/dice4.png>";
}
if ($dau1 == 5)
{
echo "<img src=img/dice5.png>";
}
if ($dau1 == 6)
{
echo "<img src=img/dice6.png>";
}

if ($dau2 == 1)
{
echo "<img src=img/dice1.png>";
}
if ($dau2 == 2)
{
echo "<img src=img/dice2.png>";
}
if ($dau2 == 3)
{
echo "<img src=img/dice3.png>";
}
if ($dau2 == 4)
{
echo "<img src=img/dice4.png>";
}
if ($dau2 == 5)
{
echo "<img src=img/dice5.png>";
}
if ($dau2 == 6)
{
echo "<img src=img/dice6.png>";
}

$score = $dau1 + $dau2;
echo "<br>";
echo "Has tret un <span style='font-weight: bold;'> ".$dau1." </span> i un <span style='font-weight: bold;'> ".$dau2."</span>. Sumes <span style='font-weight: bold';> ".$score."</span><br>";
}

// Empezar una partida nueva.
if (isset($_POST['nova_partida'])) {
    unset($_SESSION['punt_partida']);
    unset($_SESSION['partida_acabada']);
    unset($_SESSION['ultima_tirada']);
}

// Solo tirar si la partida sigue activa.
if (empty($_SESSION['partida_acabada'])) {
    $estado_anterior = $_SESSION;
    tirada();

    if (!isset($_SESSION['punt_partida'])) {
        // Primera tirada.
        if ($score == 7 OR $score == 11) {
            $_SESSION['partida_acabada'] = 'guanyat';
        } else if ($score == 2 OR $score == 3 OR $score == 12) {
            $_SESSION['partida_acabada'] = 'perdut';
        } else {
            // Conservar el punto para las siguientes peticiones.
            $_SESSION['punt_partida'] = $score;
        }
    } else {
        // Tiradas siguientes: el punto permanece fijo.
        if ($score == $_SESSION['punt_partida']) {
            $_SESSION['partida_acabada'] = 'guanyat';
        } else if ($score == 7) {
            $_SESSION['partida_acabada'] = 'perdut';
        }
    }

    // Guardar cada tirada realizada.
    try {
        $conexion->beginTransaction();
        $resultado = $conexion->prepare(
            'INSERT INTO resultats (dau1, dau2, `user`) VALUES (:dau1, :dau2, :user)'
        );
        $resultado->execute(['dau1' => $dau1, 'dau2' => $dau2, 'user' => $user]);
        // Contar únicamente la tirada que termina la partida, no al mostrarla.
        if (!empty($_SESSION['partida_acabada'])) {
            $victoria = $_SESSION['partida_acabada'] === 'guanyat' ? 1 : 0;
            $resultado = $conexion->prepare(
                'UPDATE record SET victorias = victorias + :victoria,
                 derrotas = derrotas + :derrota WHERE `user` = :user'
            );
            $resultado->execute([
                'victoria' => $victoria,
                'derrota' => 1 - $victoria,
                'user' => $user,
            ]);
        }
        $conexion->commit();
    } catch (PDOException $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $_SESSION = $estado_anterior;
        error_log($error->getMessage());
        http_response_code(500);
        exit('No es pot guardar la tirada. Revisa el registre PHP.');
    }
}

// Mostrar el estado y elegir el botón.
if (!empty($_SESSION['partida_acabada'])) {
    // Una visita posterior muestra la última tirada sin lanzar ni insertar otra.
    if ($dau1 === 0 && isset($_SESSION['ultima_tirada'])) {
        mostrarDados(...$_SESSION['ultima_tirada']);
    }
    if ($_SESSION['partida_acabada'] == 'guanyat') {
        echo "<br>Has guanyat!";
    } else {
        echo "<br>Has perdut!";
    }

    $boto = 'nova_partida';
    $text_boto = 'Torna a jugar';
} else {
    echo "<br>El teu punt de partida és "
         . $_SESSION['punt_partida'];

    $boto = 'tirada';
    $text_boto = 'Torna a tirar';
}

// Escapar los valores para incluirlos en HTML.
// Se usan los valores originales, no los escapados para SQL.
$user_html = htmlspecialchars($_POST['usuari'], ENT_QUOTES, 'UTF-8');
$password_html = htmlspecialchars(
    $_POST['contrassenya'],
    ENT_QUOTES,
    'UTF-8'
);

$partida_activa = empty($_SESSION['partida_acabada']) ? '1' : '0';

echo <<<EOD
<form id="joc" data-activa="$partida_activa" method="POST" action="juga.php">
    <input type="hidden" name="partida_token" value="$partida_token">
    <input type="hidden" name="usuari" value="$user_html">
    <input type="hidden" name="contrassenya" value="$password_html">
    <button type="submit" name="$boto" value="1">$text_boto</button>
</form>
<script src="partida.js"></script>
EOD;
