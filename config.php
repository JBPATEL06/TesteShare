<?php

define('APP_NAME', 'TestShare');
define('BASE_PATH', __DIR__);
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/helpers/subscription_gate.php';


if (!function_exists('url')) {
    /**
     * Generate a route URL for the application.
     * Example: url('user/home') -> /sutu/web/index.php?route=user/home
     */
    function url($path = 'user/home') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $pos = strpos($script, '/web/');
        if ($pos !== false) {
            $base = substr($script, 0, $pos + 5);
        } else {
            $base = '/';
        }
        return $base . 'index.php?route=' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL.
     * Example: asset('css/custom.css') -> /sutu/web/css/custom.css
     */
    function asset($path) {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $pos = strpos($script, '/web/');
        if ($pos !== false) {
            $base = substr($script, 0, $pos + 5);
        } else {
            $base = '/';
        }
        return $base . ltrim($path, '/');
    }
}

if (!function_exists('view')) {
    /**
     * Load a view file and extract data into its scope.
     */
    function view($viewPath, $data = []) {
        $fullPath = BASE_PATH . '/views/' . $viewPath . '.php';
        if (file_exists($fullPath)) {
            if (is_array($data)) {
                unset($data['viewPath'], $data['fullPath'], $data['data']);
                extract($data);
            }
            require $fullPath;
        } else {
            echo "<div style='color:red; padding:20px;'>View file not found: " . htmlspecialchars($viewPath) . "</div>";
        }
    }
}
