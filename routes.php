<?php

/**
 * Route definitions for TestShare PHP XAMPP application.
 * Matches route strings to view files in views/.
 */

$routes = [
    // User Panel Routes
    'user/home' => 'user/home',
    'user/login' => 'user/login',
    'user/logout' => 'user/logout',
    'user/register' => 'user/register',
    'user/food_detail' => 'user/food_detail',
    'user/cart' => 'user/cart',
    'user/profile' => 'user/profile',
    'user/orders' => 'user/orders',
    'user/order_detail' => 'user/order_detail',
    'user/custom_order' => 'user/custom_order',
    'user/location' => 'user/location',
    'user/restaurant_storefront' => 'user/restaurant_storefront',
    'user/forgot_password' => 'user/forgot_password',
    'user/reset_password' => 'user/reset_password',
    'user/help' => 'user/help',

    // Seller Panel Routes
    'seller/login' => 'seller/login',
    'seller/register' => 'seller/register',
    'seller/forgot_password' => 'user/forgot_password',
    'seller/pending_approval' => 'seller/pending_approval',
    'seller/dashboard1' => 'seller/dashboard1',
    'seller/orders_kanban' => 'seller/orders_kanban',
    'seller/menu' => 'seller/orders_kanban',
    'seller/order_detail' => 'seller/order_detail',
    'seller/orders_redesigned' => 'seller/orders_redesigned',
    'seller/menu_offers' => 'seller/menu_offers',
    'seller/reviews' => 'seller/reviews',
    'seller/settings' => 'seller/settings',
    'seller/notifications' => 'seller/notifications',
    'seller/subscription' => 'seller/subscription',
    'seller/custom_orders' => 'seller/custom_orders',
    'seller/raw_material_sale' => 'seller/raw_material_sale',
    'seller/explore_market' => 'seller/explore_market',
    'seller/location' => 'seller/location',
    'seller/create_offer' => 'seller/create_offer',
    'seller/theme_customizer' => 'seller/theme_customizer',
    'seller/profile' => 'seller/profile',

    // Super Admin Panel Routes
    'admin/dashboard' => 'admin/dashboard',
    'admin/sales_analytics' => 'admin/sales_analytics',
    'admin/users' => 'admin/users',
    'admin/sellers' => 'admin/sellers',
    'admin/offers' => 'admin/offers',
    'admin/subscriptions' => 'admin/subscriptions',
    'admin/reviews' => 'admin/reviews',
];

/**
 * Route Dispatcher
 */
function dispatchRoute($routes) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $route = isset($_GET['route']) ? trim($_GET['route'], '/') : 'user/home';

    // Global Authentication Middleware
    $role = $_SESSION['role'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if (strpos($route, 'admin/') === 0) {
        if (!$userId || $role !== 'super_admin') {
            header("Location: " . url('user/login'));
            exit;
        }
    } elseif (strpos($route, 'seller/') === 0) {
        $publicSellerRoutes = ['seller/login', 'seller/register'];
        if (!in_array($route, $publicSellerRoutes)) {
            if (!$userId || $role !== 'seller_manager') {
                header("Location: " . url('user/login'));
                exit;
            }
            // Require approved onboarding status for protected seller panel pages
            if ($route !== 'seller/pending_approval') {
                if (function_exists('getDB')) {
                    $db = getDB();
                    $stmtCheckOb = $db->prepare("SELECT onboarding_status FROM stores WHERE owner_id = ?");
                    $stmtCheckOb->execute([$userId]);
                    $obStatus = $stmtCheckOb->fetchColumn();
                    if ($obStatus !== 'Approved') {
                        header("Location: " . url('seller/pending_approval'));
                        exit;
                    }
                }
            }
        }
    } elseif (strpos($route, 'user/') === 0) {
        $protectedUserRoutes = ['user/profile', 'user/orders', 'user/cart', 'user/custom_order', 'user/order_detail'];
        if (in_array($route, $protectedUserRoutes)) {
            if (!$userId || $role !== 'customer') {
                header("Location: " . url('user/login'));
                exit;
            }
        }
    }

    if (array_key_exists($route, $routes)) {
        view($routes[$route]);
    } else {
        // Fallback 404 behavior - redirect or show 404 view
        echo "<div style='font-family: sans-serif; text-align: center; padding: 100px; background: #131313; color: #e5e2e1; height: 100vh;'>";
        echo "<h1 style='color: #ff9f0d;'>404 - Page Not Found</h1>";
        echo "<p>The requested route (" . htmlspecialchars($route) . ") does not exist.</p>";
        echo "<a href='" . url('user/home') . "' style='color: #ffc688; text-decoration: none; border: 1px solid #ffc688; padding: 10px 20px; border-radius: 5px; display: inline-block; margin-top: 20px;'>Return Home</a>";
        echo "</div>";
    }
}
