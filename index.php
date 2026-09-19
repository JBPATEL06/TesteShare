<?php

// Start global session management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Master Front Controller for TestShare PHP XAMPP Web Application.
 * Handles incoming requests and dispatches them via routes.php.
 */

// Load core configuration and helper functions
require_once __DIR__ . '/config.php';

// Load database helper configuration
require_once __DIR__ . '/db.php';

// Load route definitions and dispatcher
require_once __DIR__ . '/routes.php';

// Dispatch the current route
dispatchRoute($routes);

