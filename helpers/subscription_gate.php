<?php

if (!function_exists('getDB')) {
    require_once __DIR__ . '/../db.php';
}

/**
 * Get active subscription tier for a store.
 * Returns 'Starter', 'Premium', or 'Ultra Premium'.
 */
function getStoreTier($storeId) {
    if (!$storeId) return 'Starter';
    
    $db = getDB();
    
    // Check stores table cache or billing_subscriptions
    $stmt = $db->prepare("
        SELECT bs.tier 
        FROM billing_subscriptions bs 
        WHERE bs.store_id = ? AND bs.status = 'Active' AND bs.end_date >= CURDATE()
        ORDER BY bs.id DESC LIMIT 1
    ");
    $stmt->execute([$storeId]);
    $tier = $stmt->fetchColumn();
    
    if (!$tier) {
        return 'Starter';
    }
    
    // Map legacy names if present
    if ($tier === 'Growth Pro') return 'Premium';
    if ($tier === 'Enterprise Elite') return 'Ultra Premium';
    
    return $tier;
}

/**
 * Check if a store has access to a specific subscription feature.
 * Features:
 * - 'custom_orders': Premium, Ultra Premium
 * - 'bulk_orders': Premium, Ultra Premium
 * - 'raw_materials': Ultra Premium
 * - 'theme_customizer': Premium, Ultra Premium
 * - 'visibility_boost': Premium, Ultra Premium (requires rating >= 3.5)
 * - 'zero_commission': Ultra Premium
 * - 'analytics_advanced': Premium, Ultra Premium
 */
function hasFeature($storeId, $feature) {
    $tier = getStoreTier($storeId);
    
    switch ($feature) {
        case 'custom_orders':
        case 'bulk_orders':
        case 'theme_customizer':
        case 'analytics_advanced':
            return in_array($tier, ['Premium', 'Ultra Premium']);
            
        case 'raw_materials':
        case 'zero_commission':
            return $tier === 'Ultra Premium';
            
        case 'visibility_boost':
            if (!in_array($tier, ['Premium', 'Ultra Premium'])) {
                return false;
            }
            // Rating check (requires avg rating >= 3.5)
            $db = getDB();
            $stmt = $db->prepare("
                SELECT IFNULL(AVG(rating_stars), 5.0) as avg_rating 
                FROM reviews 
                WHERE store_id = ?
            ");
            $stmt->execute([$storeId]);
            $rating = floatval($stmt->fetchColumn() ?: 5.0);
            return $rating >= 3.5;
            
        default:
            return false;
    }
}

/**
 * Fetch store custom theme configuration.
 */
function getStoreThemeConfig($storeId) {
    if (!$storeId || !hasFeature($storeId, 'theme_customizer')) {
        return [
            'accent_color' => '#ff9f0d',
            'secondary_color' => '#2d2d2d',
            'banner_style' => 'default',
            'custom_css' => ''
        ];
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM store_theme_config WHERE store_id = ?");
        $stmt->execute([$storeId]);
        $config = $stmt->fetch();
        if ($config) {
            return $config;
        }
    } catch (Exception $e) {
        // Fallback if table doesn't exist
    }
    
    return [
        'accent_color' => '#ff9f0d',
        'secondary_color' => '#2d2d2d',
        'banner_style' => 'default',
        'custom_css' => ''
    ];
}
