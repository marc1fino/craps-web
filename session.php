<?php

const LOGIN_LIFETIME = 30 * 24 * 60 * 60;

// Keep this app's sessions separate from XAMPP's shorter-lived sessions.
// The session files stay outside the website, in the PHP temporary directory.
$sessionPathParts = explode(';', ini_get('session.save_path'));
$sessionBasePath = end($sessionPathParts) ?: sys_get_temp_dir();
$sessionDirectory = rtrim($sessionBasePath, '/\\')
    . '/craps-sessions-' . substr(hash('sha256', __DIR__), 0, 16);

if (!is_dir($sessionDirectory)
    && !mkdir($sessionDirectory, 0700, true)
    && !is_dir($sessionDirectory)) {
    throw new RuntimeException('Could not create the session directory.');
}

session_save_path($sessionDirectory);
session_name('CRAPSSESSID');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) LOGIN_LIFETIME);

$cookiePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
session_set_cookie_params([
    // Only a successful login with Remember me creates a persistent cookie.
    'lifetime' => 0,
    'path' => $cookiePath,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
header('Cache-Control: no-store');

// A cookie expiry alone is not enough: also reject expired sessions on the server.
if (isset($_SESSION['username']) && ($_SESSION['login_expires_at'] ?? 0) <= time()) {
    $_SESSION = [];
    session_regenerate_id(true);
}

// Set the chosen duration after login; ordinary visits do not extend it.
function sendLoginCookie(): void
{
    $options = session_get_cookie_params();
    unset($options['lifetime']);
    $options['expires'] = ($_SESSION['remember_me'] ?? false) === true
        ? $_SESSION['login_expires_at']
        : 0;
    setcookie(session_name(), session_id(), $options);
}

