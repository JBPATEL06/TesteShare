<?php

define('APP_NAME', 'TestShare');
define('BASE_PATH', __DIR__);
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/helpers/subscription_gate.php';


if (!function_exists('base_url')) {
    /**
     * Dynamically determine the base web URL path regardless of directory or server configuration.
     * Works seamlessly in XAMPP subdirectories, Apache VirtualHosts, or PHP built-in server.
     */
    function base_url() {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '.' || $dir === '/' || $dir === '') {
            return '/';
        }
        return rtrim($dir, '/') . '/';
    }
}

if (!function_exists('url')) {
    /**
     * Generate an application route URL.
     * Example: url('user/home') -> /TesteShare-main/TesteShare-main/index.php?route=user/home
     */
    function url($path = 'user/home') {
        return base_url() . 'index.php?route=' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL.
     * Example: asset('css/bootstrap.min.css') -> /TesteShare-main/TesteShare-main/css/bootstrap.min.css
     */
    function asset($path) {
        return base_url() . ltrim($path, '/');
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
