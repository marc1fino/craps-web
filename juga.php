<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

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
    exit('No se pudo acceder a la base de datos. Revisa la configuración y el registro de PHP.');
}

if ($usuarios != 1) {
    echo "El teu usuari o contrassenya no �s correcte.";
    exit;
}

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
}

// Solo tirar si la partida sigue activa.
if (empty($_SESSION['partida_acabada'])) {
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
        $resultado = $conexion->prepare(
            'INSERT INTO resultats (dau1, dau2, `user`) VALUES (:dau1, :dau2, :user)'
        );
        $resultado->execute(['dau1' => $dau1, 'dau2' => $dau2, 'user' => $user]);
    } catch (PDOException $error) {
        error_log($error->getMessage());
        http_response_code(500);
        exit('No se pudo guardar la tirada. Revisa el registro de PHP.');
    }
}

// Mostrar el estado y elegir el bot�n.
if (!empty($_SESSION['partida_acabada'])) {
    if ($_SESSION['partida_acabada'] == 'guanyat') {
        echo "<br>Has guanyat!";
    } else {
        echo "<br>Has perdut!";
    }

    $boto = 'nova_partida';
    $text_boto = 'Torna a jugar';
} else {
    echo "<br>El teu punt de partida �s "
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

echo <<<EOD
<form method="POST" action="">
    <input type="hidden" name="usuari" value="$user_html">
    <input type="hidden" name="contrassenya" value="$password_html">
    <button type="submit" name="$boto" value="1">$text_boto</button>
</form>
EOD;
?>
