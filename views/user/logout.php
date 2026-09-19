<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables explicitly
$_SESSION = array();

// If session cookie exists, clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to unified portal login
header("Location: " . url('user/login'));
exit;
