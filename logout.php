<?php
require_once __DIR__ . '/session.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: index.php', true, 303);
    exit;
}

$token = $_POST['logout_token'] ?? null;
$savedToken = $_SESSION['logout_token'] ?? null;

if (!is_string($token) || !is_string($savedToken) || !hash_equals($savedToken, $token)) {
    http_response_code(403);
    exit('Invalid logout request.');
}
$_SESSION = [];

$params = session_get_cookie_params();

setcookie(session_name(), "", [
    'expires' => time() - 3600,
    "path" => $params['path'],
    'domain' => $params['domain'],
    'secure' => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite']
]);

session_destroy();

header('Location: index.php', true, 303);
exit;
?>
