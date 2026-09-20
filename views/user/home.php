<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../../db.php';
}
if (!function_exists('view')) {
    require_once __DIR__ . '/../../config.php';
}
$db = getDB();

// Mark all user notifications as read
if (isset($_GET['mark_notifs_read']) && isset($_SESSION['user_id'])) {
    $db->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?")
       ->execute([$_SESSION['user_id']]);
    header("Location: " . url('user/home'));
    exit;
}

// Delete single user notification
if (isset($_GET['delete_user_notif']) && isset($_SESSION['user_id'])) {
    $notifId = intval($_GET['delete_user_notif']);
    $db->prepare("DELETE FROM user_notifications WHERE id = ? AND user_id = ?")
       ->execute([$notifId, $_SESSION['user_id']]);
    header("Location: " . url('user/home'));
    exit;
}

// Clear all user notifications box
if (isset($_GET['clear_user_notifs']) && isset($_SESSION['user_id'])) {
    $db->prepare("DELETE FROM user_notifications WHERE user_id = ?")
       ->execute([$_SESSION['user_id']]);
    header("Location: " . url('user/home'));
    exit;
}

// 1. Load active dishes from active, approved stores
$rawDishes = $db->query("
    SELECT m.*, s.store_name, s.opening_time, s.closing_time
    FROM menu_items m
    JOIN stores s ON m.store_id = s.id
    WHERE m.is_available = 1
      AND s.store_status = 'Active' 
      AND s.onboarding_status = 'Approved'
    ORDER BY m.created_at DESC
")->fetchAll();

// Filter out dishes if store is currently closed
$currentTime = date('H:i:s');
$dishes = [];
foreach ($rawDishes as $d) {
    $isOpen = true;
    if (!empty($d['opening_time']) && !empty($d['closing_time'])) {
        $open = $d['opening_time'];
        $close = $d['closing_time'];
        if ($open <= $close) {
            $isOpen = ($currentTime >= $open && $currentTime <= $close);
        } else {
            $isOpen = ($currentTime >= $open || $currentTime <= $close);
        }
    }
    if ($isOpen) {
        $dishes[] = $d;
    }
}

// 2. Load active stores/restaurants sorted by subscription tier & rating
try {
    $restaurants = $db->query("
        SELECT s.*, 
               IFNULL(AVG(r.rating_stars), 5.0) as avg_rating, 
               COUNT(DISTINCT r.id) as reviews_count,
               (SELECT COUNT(*) FROM promotional_offers po WHERE po.store_id = s.id AND po.status = 'Active' AND po.end_date >= NOW()) as active_offers_count,
               IFNULL(bs.tier, s.subscription_tier) as subscription_tier,
               stc.accent_color, stc.secondary_color
        FROM stores s
        LEFT JOIN reviews r ON s.id = r.store_id
        LEFT JOIN billing_subscriptions bs ON s.id = bs.store_id AND bs.status = 'Active' AND bs.end_date >= CURDATE()
        LEFT JOIN store_theme_config stc ON s.id = stc.store_id
        WHERE s.store_status = 'Active' AND s.onboarding_status = 'Approved'
        GROUP BY s.id
        ORDER BY 
            CASE 
                WHEN (IFNULL(bs.tier, s.subscription_tier) = 'Ultra Premium' OR IFNULL(bs.tier, s.subscription_tier) = 'Enterprise Elite') THEN 1
                WHEN (IFNULL(bs.tier, s.subscription_tier) = 'Premium' OR IFNULL(bs.tier, s.subscription_tier) = 'Growth Pro') AND IFNULL(AVG(r.rating_stars), 5.0) >= 3.5 THEN 2
                ELSE 3
            END ASC,
            avg_rating DESC
    ")->fetchAll();
} catch (Exception $e) {
    // Graceful fallback if store_theme_config or subscription_tier column not yet migrated
    $restaurants = $db->query("
        SELECT s.*, 
               IFNULL(AVG(r.rating_stars), 5.0) as avg_rating, 
               COUNT(DISTINCT r.id) as reviews_count,
               (SELECT COUNT(*) FROM promotional_offers po WHERE po.store_id = s.id AND po.status = 'Active' AND po.end_date >= NOW()) as active_offers_count,
               bs.tier as subscription_tier,
               '#ff9f0d' as accent_color, '#2d2d2d' as secondary_color
        FROM stores s
        LEFT JOIN reviews r ON s.id = r.store_id
        LEFT JOIN billing_subscriptions bs ON s.id = bs.store_id AND bs.status = 'Active'
        WHERE s.store_status = 'Active' AND s.onboarding_status = 'Approved'
        GROUP BY s.id
        ORDER BY (bs.tier = 'Enterprise Elite') DESC, avg_rating DESC
    ")->fetchAll();
}

$currentTime = date('H:i:s');
foreach ($restaurants as &$res) {
    $res['is_open'] = true;
    if (!empty($res['opening_time']) && !empty($res['closing_time'])) {
        $open = $res['opening_time'];
        $close = $res['closing_time'];
        if ($open <= $close) {
            $res['is_open'] = ($currentTime >= $open && $currentTime <= $close);
        } else { // wraps past midnight
            $res['is_open'] = ($currentTime >= $open || $currentTime <= $close);
        }
    }
}
unset($res);

// Index dishes by store for signature selections
$storeDishes = [];
foreach ($dishes as $d) {
    $storeDishes[$d['store_id']][] = $d;
}

// Load dynamic categories
$categories = $db->query("
    SELECT DISTINCT category 
    FROM menu_items 
    WHERE is_available = 1 AND category != ''
    ORDER BY category ASC
")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Explore';
$activeNav = 'explore';
view('partials/user_header', get_defined_vars());
?>

<main class="container-xl px-4 pb-5 mb-5">
    <!-- Hero Section -->
    <section class="position-relative overflow-hidden rounded-3 my-4" style="height: 350px;">
        <div class="position-absolute inset-0 w-100 h-100 z-0">
            <div class="w-100 h-100 bg-cover bg-center opacity-40 parallax-bg" style="background-image: url('<?php echo asset('images/hero_section_bg.png'); ?>'); background-size: cover; background-position: center;"></div>
            <div class="position-absolute inset-0 bg-gradient-to-t from-surface via-surface/60 to-transparent" style="background: linear-gradient(to top, #131313, transparent);"></div>
        </div>
        <div class="position-relative z-1 h-100 d-flex flex-column justify-content-end p-4 p-md-5 max-w-4xl">
            <h1 class="display-5 fw-bold text-white mb-2">The city's finest dishes,<br><span class="text-primary-container">delivered to your door.</span></h1>
            <p class="fs-5 text-on-surface-variant max-w-2xl mb-0">Discover individual masterpieces from local kitchens. From artisan sushi to wood-fired pizzas.</p>
        </div>
    </section>

    <!-- Discovery Toggle & Filters -->
    <div class="bg-surface py-3 border-bottom border-outline-variant mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <!-- Segmented Control -->
            <div class="d-flex p-1 bg-surface-container rounded-3 border border-outline-variant w-fit">
                <a href="#" data-tab-target="restaurants" class="btn btn-link text-on-surface-variant text-decoration-none px-4 py-2 fw-semibold fs-7">Explore Restaurants</a>
                <a href="#" data-tab-target="dishes" class="btn btn-primary-custom px-4 py-2 fw-semibold fs-7 text-decoration-none active-pill rounded-2">Explore Dishes</a>
            </div>
            <!-- Search Bar -->
            <div class="position-relative w-100" style="max-width: 400px;">
                <span class="material-symbols-outlined position-absolute top-50 start-0 translate-middle-y ms-3 text-on-surface-variant">search</span>
                <input id="main-search-box" class="form-control bg-surface-container-low border-outline-variant text-white ps-5 pe-3 py-2 input-focus-amber" placeholder="Search for a specific dish..." type="text" style="color: #ffffff !important;">
            </div>
        </div>
        <!-- Category Chips -->
        <div class="d-flex gap-2 mt-3 overflow-x-auto no-scrollbar py-1" id="category-filters">
            <button class="category-btn btn bg-primary-custom rounded-pill px-4 py-1 fs-7 flex-shrink-0" data-category="all">All Dishes</button>
            <?php foreach ($categories as $cat): ?>
                <button class="category-btn btn bg-surface-variant text-on-surface-variant border-outline-variant rounded-pill px-4 py-1 fs-7 flex-shrink-0 hover-primary" data-category="<?php echo htmlspecialchars(strtolower($cat)); ?>"><?php echo htmlspecialchars($cat); ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- TAB 1: DISHES FEED -->
    <div id="tab-dishes">
        <div id="dishes-feed" class="row gy-4 mb-5">
            <?php foreach ($dishes as $dish): ?>
                <?php
                // Generate a rating based on id
                $rating = number_format(4.5 + (($dish['id'] * 7) % 6) * 0.1, 1);
                $imageUrl = $dish['image_url'] ? asset($dish['image_url']) : asset('images/spicy_tuna_crunch.png');
                ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="dish-card">
                        <div class="dish-card-img-wrapper">
                            <img src="<?php echo $imageUrl; ?>" class="dish-card-img" alt="<?php echo htmlspecialchars($dish['name']); ?>">
                            <div class="badge-rating">
                                <span class="material-symbols-outlined text-primary-container fs-8 filled">star</span>
                                <span><?php echo $rating; ?></span>
                            </div>
                        </div>
                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h5 class="fw-bold mb-0"><a href="<?php echo url('user/food_detail&id=' . $dish['id']); ?>" class="text-white text-decoration-none"><?php echo htmlspecialchars($dish['name']); ?></a></h5>
                                <span class="text-primary-container fw-bold">₹<?php echo number_format($dish['price'], 2); ?></span>
                            </div>
                            <p class="fs-8 text-on-surface-variant d-flex align-items-center gap-1 mb-2">
                                <span class="material-symbols-outlined fs-8">restaurant</span> <?php echo htmlspecialchars($dish['store_name']); ?>
                            </p>
                            <div class="d-flex gap-2 mt-auto mb-3">
                                <span class="dish-category badge bg-surface-variant text-on-surface-variant border border-outline-variant text-uppercase"><?php echo htmlspecialchars($dish['category']); ?></span>
                            </div>
                            <a href="<?php echo url('user/cart&add_id=' . $dish['id']); ?>" class="btn bg-primary-custom w-100 py-2 fs-7 fw-bold text-decoration-none text-center">Add to Cart</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- TAB 2: RESTAURANTS FEED -->
    <div id="tab-restaurants" class="d-none">
        <section class="space-y-section-gap my-4">
            <?php foreach ($restaurants as $rObj): ?>
                <?php
                $store_id = $rObj['id'];
                $rDishes = $storeDishes[$store_id] ?? [];
                // Icon representation based on category
                $icon = 'restaurant';
                if (stripos($rObj['category'], 'pizza') !== false) {
                    $icon = 'local_pizza';
                } elseif (stripos($rObj['category'], 'bakery') !== false) {
                    $icon = 'bakery_dining';
                }

                $isUltra = ($rObj['subscription_tier'] === 'Ultra Premium' || $rObj['subscription_tier'] === 'Enterprise Elite');
                $isPremium = ($rObj['subscription_tier'] === 'Premium' || $rObj['subscription_tier'] === 'Growth Pro') && $rObj['avg_rating'] >= 3.5;
                $cardStyle = '';
                if ($isUltra) {
                    $cardStyle = 'border: 2px solid #ffb703; box-shadow: 0 0 16px rgba(255, 183, 3, 0.25);';
                } elseif ($isPremium) {
                    $cardStyle = 'border: 1px solid #ff9f0d;';
                }
                ?>
                <article class="bg-surface-container solid-border p-0 overflow-hidden group rounded-3 mb-5" style="<?php echo $cardStyle; ?>">
                    <div class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 solid-border bg-surface flex items-center justify-center rounded-2 overflow-hidden" style="width:48px;height:48px;">
                                <?php if (!empty($rObj['store_logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($rObj['store_logo']); ?>" alt="<?php echo htmlspecialchars($rObj['store_name']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;"><?php echo $icon; ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h2 class="font-headline-md text-headline-md text-on-surface mb-1 font-bold flex items-center gap-2">
                                    <?php echo htmlspecialchars($rObj['store_name']); ?>
                                </h2>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="flex items-center text-primary-container font-label-md text-label-md">
                                        <span class="material-symbols-outlined text-[16px] mr-1" style="font-variation-settings: 'FILL' 1;">star</span> <?php echo number_format($rObj['avg_rating'], 1); ?>
                                    </span>
                                    <span class="text-on-surface-variant font-label-md text-label-md">• <?php echo htmlspecialchars($rObj['category']); ?></span>
                                    <span class="text-on-surface-variant font-label-md text-label-md">• 20-30 min</span>
                                    <?php if ($rObj['active_offers_count'] > 0): ?>
                                        <span class="bg-primary-container text-on-primary-container font-label-sm px-2 py-0.5 rounded-1 flex items-center gap-1 font-bold">
                                            <span class="material-symbols-outlined text-[14px]">local_offer</span> Offers
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isUltra): ?>
                                        <span class="bg-amber-500/20 text-amber-400 border border-amber-500/40 font-label-sm px-2 py-0.5 rounded-1 font-bold flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">stars</span> 👑 Ultra VIP
                                        </span>
                                    <?php elseif ($isPremium): ?>
                                        <span class="bg-primary/20 text-primary border border-primary/40 font-label-sm px-2 py-0.5 rounded-1 font-bold flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">verified</span> ⭐ Featured
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$rObj['is_open']): ?>
                                        <span class="bg-error/20 text-error border border-error/30 font-label-sm px-2 py-0.5 rounded-1 font-bold uppercase">Closed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <?php if ($rObj['is_open']): ?>
                                <a href="<?php echo url('user/custom_order&store_id=' . $store_id); ?>" class="px-4 py-2 bg-primary-container text-on-primary-container font-label-md text-label-md hover:brightness-110 active:scale-95 transition-all text-decoration-none rounded-2 fw-bold">Custom Order</a>
                                <a href="<?php echo url('user/restaurant_storefront&id=' . $store_id); ?>" class="px-4 py-2 solid-border text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors text-decoration-none rounded-2">View All</a>
                            <?php else: ?>
                                <button disabled class="px-4 py-2 bg-surface-variant text-on-surface-variant font-label-md text-label-md rounded-2 fw-bold cursor-not-allowed border-0">Currently Closed</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($rDishes)): ?>
                        <!-- Main Dishes - Horizontal Scroll -->
                        <div class="px-4 pb-4">
                            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest mb-3">Signature Selections</p>
                            <div class="flex gap-6 overflow-x-auto hide-scrollbar pb-2">
                                <?php foreach ($rDishes as $dObj): ?>
                                    <?php
                                    $img = $dObj['image_url'] ? asset($dObj['image_url']) : asset('images/spicy_tuna_crunch.png');
                                    ?>
                                    <div onclick="window.location.href='<?php echo url('user/food_detail&id=' . $dObj['id']); ?>'" class="flex-none w-[280px] solid-border bg-surface p-2 group/dish cursor-pointer rounded-2">
                                        <div class="aspect-video mb-2 relative overflow-hidden rounded-1">
                                            <img class="w-full h-full object-cover transition-transform duration-500 group-hover/dish:scale-110" alt="<?php echo htmlspecialchars($dObj['name']); ?>" src="<?php echo $img; ?>">
                                            <div class="absolute inset-0 bg-black/20 group-hover/dish:bg-transparent transition-colors"></div>
                                        </div>
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="font-label-md text-label-md text-on-surface mb-0"><?php echo htmlspecialchars($dObj['name']); ?></h3>
                                                <p class="text-on-surface-variant font-label-sm text-label-sm mb-0"><?php echo htmlspecialchars(substr($dObj['description'], 0, 30)) . (strlen($dObj['description']) > 30 ? '...' : ''); ?></p>
                                            </div>
                                            <span class="font-label-md text-label-md text-primary-container fw-bold">₹<?php echo number_format($dObj['price'], 2); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </div>

    <!-- TAB 3: SEARCH RESULTS -->
    <div id="tab-search-results" class="d-none">
        <section class="mb-4 my-4">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div>
                    <h1 class="font-headline-xl text-headline-xl text-on-surface mb-2 hidden md:block font-bold">Showing results for "Pizza"</h1>
                    <h1 class="font-headline-lg-mobile text-headline-lg-mobile text-on-surface mb-2 md:hidden font-bold">Results for "Pizza"</h1>
                    <p class="text-on-surface-variant font-body-md mb-0">3 top-rated pizza artisans found near you.</p>
                </div>
            </div>
        </section>

        <!-- Filter Chips Area -->
        <section class="mb-4 overflow-x-auto no-scrollbar py-2 my-4">
            <div class="flex gap-4 items-center min-w-max">
                <button class="bg-primary-container text-on-primary-container px-4 py-2 rounded-lg font-label-md flex items-center gap-2 border border-primary-container fw-bold">
                    <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">local_pizza</span>
                    Pizza
                </button>
                <button class="bg-surface-container text-on-surface-variant px-4 py-2 rounded-lg font-label-md border border-surface-variant hover:text-on-surface transition-colors">
                    Fast Delivery
                </button>
                <button class="bg-surface-container text-on-surface-variant px-4 py-2 rounded-lg font-label-md border border-surface-variant hover:text-on-surface transition-colors">
                    Rating 4.5+
                </button>
                <button class="bg-surface-container text-on-surface-variant px-4 py-2 rounded-lg font-label-md border border-surface-variant hover:text-on-surface transition-colors">
                    Offers
                </button>
                <button class="bg-surface-container text-on-surface-variant px-4 py-2 rounded-lg font-label-md border border-surface-variant hover:text-on-surface transition-colors">
                    Vegan
                </button>
            </div>
        </section>

        <!-- Restaurant List -->
        <section class="space-y-6 my-4">
            <!-- Restaurant 1: Artisan Crust Lab -->
            <div class="bg-surface-container border border-surface-variant group hover:border-primary transition-all duration-300 rounded-3 overflow-hidden mb-4">
                <div class="flex flex-col md:flex-row">
                    <div class="md:w-64 h-48 md:h-auto overflow-hidden relative">
                        <div class="absolute inset-0 bg-black/20 z-10 transition-opacity group-hover:opacity-0"></div>
                        <div class="w-full h-full bg-cover bg-center transition-transform duration-500 group-hover:scale-105 min-h-[200px]" style="background-image: url('<?php echo asset('images/home_banner_1.png'); ?>')"></div>
                        <div class="absolute top-2 left-2 z-20 bg-primary-container text-on-primary-container px-2 py-1 text-label-sm font-bold rounded-1">
                            PROMOTED
                        </div>
                    </div>
                    <div class="flex-1 flex flex-col justify-between p-4">
                        <div>
                            <div class="flex justify-between items-start">
                                <h2 class="font-headline-md text-headline-md text-on-surface font-bold">Artisan Crust Lab</h2>
                                <div class="flex items-center gap-1 bg-surface-container-high px-2 py-1 rounded">
                                    <span class="material-symbols-outlined text-primary text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                    <span class="font-label-md text-on-surface">4.9</span>
                                </div>
                            </div>
                            <p class="text-on-surface-variant font-body-md mt-1 italic mb-2">Sourdough fermentation • Neapolitan Style • Premium Ingredients</p>
                            <div class="flex gap-4 mt-2 mb-3">
                                <span class="bg-surface-variant text-on-surface text-label-sm px-2 py-1 rounded">25-35 min</span>
                                <span class="bg-surface-variant text-on-surface text-label-sm px-2 py-1 rounded">Free delivery</span>
                                <span class="bg-surface-variant text-on-surface text-label-sm px-2 py-1 rounded">₹₹</span>
                            </div>
                        </div>
                        <div class="mt-4 border-t border-surface-variant pt-3 flex items-center justify-between">
                            <span class="text-label-sm text-on-surface-variant flex items-center gap-1 mb-0">
                                <span class="material-symbols-outlined text-[16px]">receipt_long</span>
                                Use code PIZZA20 for 20% off
                            </span>
                            <button onclick="window.location.href='<?php echo url('seller/store_menu'); ?>'" class="text-primary font-label-md flex items-center gap-1 hover:underline transition-all active:scale-95 bg-transparent border-0 fw-bold">
                                View Menu
                                <span class="material-symbols-outlined">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Restaurant 2: The Pizza Artisan -->
            <div class="bg-surface-container border border-surface-variant group hover:border-primary transition-all duration-300 rounded-3 overflow-hidden mb-4">
                <div class="flex flex-col md:flex-row">
                    <div class="md:w-64 h-48 md:h-auto overflow-hidden relative">
                        <div class="absolute inset-0 bg-black/20 z-10 transition-opacity group-hover:opacity-0"></div>
                        <div class="w-full h-full bg-cover bg-center transition-transform duration-500 group-hover:scale-105 min-h-[200px]" style="background-image: url('<?php echo asset('images/category_banner_2.png'); ?>')"></div>
                    </div>
                    <div class="flex-1 flex flex-col justify-between p-4">
                        <div>
                            <div class="flex justify-between items-start">
                                <h2 class="font-headline-md text-headline-md text-on-surface font-bold">The Pizza Artisan</h2>
                                <div class="flex items-center gap-1 bg-surface-container-high px-2 py-1 rounded">
                                    <span class="material-symbols-outlined text-primary text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                    <span class="font-label-md text-on-surface">4.7</span>
                                </div>
                            </div>
                            <p class="text-on-surface-variant font-body-md mt-1 italic mb-2">Detroit Style • Craft Pepperoni • Urban Kitchen</p>
                            <div class="flex gap-4 mt-2 mb-3">
                                <span class="bg-surface-variant text-on-surface text-label-sm px-2 py-1 rounded">15-20 min</span>
                                <span class="bg-surface-variant text-on-surface text-label-sm px-2 py-1 rounded">₹49 delivery</span>
                                <span class="bg-surface-variant text-on-surface text-label-sm px-2 py-1 rounded">₹₹</span>
                            </div>
                        </div>
                        <div class="mt-4 border-t border-surface-variant pt-3 flex items-center justify-between">
                            <span class="text-label-sm text-on-surface-variant flex items-center gap-1 mb-0">
                                <span class="material-symbols-outlined text-[16px]">timer</span>
                                Super fast delivery area
                            </span>
                            <button onclick="window.location.href='<?php echo url('seller/store_menu'); ?>'" class="text-primary font-label-md flex items-center gap-1 hover:underline transition-all active:scale-95 bg-transparent border-0 fw-bold">
                                View Menu
                                <span class="material-symbols-outlined">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Restaurant 3: Midnight Slices -->
            <div class="bg-surface-container border border-surface-variant group hover:border-primary transition-all duration-300 rounded-3 overflow-hidden mb-4">
                <div class="flex flex-col md:flex-row">
                    <div class="md:w-64 h-48 md:h-auto overflow-hidden relative">
                        <div class="absolute inset-0 bg-black/20 z-10 transition-opacity group-hover:opacity-0"></div>
                        <div class="w-full h-full bg-cover bg-center transition-transform duration-500 group-hover:scale-105 min-h-[200px]" style="background-image: url('<?php echo asset('images/category_banner_1.png'); ?>')"></div>
                    </div>
                    <div class="flex-1 flex flex-col justify-between p-4">
                        <div>
                            <div class="flex justify-between items-start">
                                <h2 class="font-headline-md text-headline-md text-on-surface font-bold">Midnight Slices</h2>
                                <div class="flex items-center gap-1 bg-surface-container-high px-2 py-1 rounded">
                                    <span class="material-symbols-outlined text-primary text-[16px]" style="font-variation-settings: 'FILL' 1;">star</span>
                                    <span class="font-label-md text-on-surface">4.5</span>
                                </div>
                            </div>
                            <p class="text-on-surface-variant font-body-md mt-1 italic mb-2">NY Style • Open Late • Truffle Specials</p>
                            <div class="flex gap-4 mt-2 mb-3">
                                <span class="bg-surface-variant text-on-surface text-label-sm px-2 py-1 rounded">30-45 min</span>
                                <span class="bg-surface-variant text-on-surface text-label-sm px-2 py-1 rounded">Free over ₹20</span>
                                <span class="bg-surface-variant text-on-surface text-label-sm px-2 py-1 rounded">$</span>
                            </div>
                        </div>
                        <div class="mt-4 border-t border-surface-variant pt-3 flex items-center justify-between">
                            <span class="text-label-sm text-on-surface-variant flex items-center gap-1 mb-0">
                                <span class="material-symbols-outlined text-[16px]">nights_stay</span>
                                Open until 3 AM
                            </span>
                            <button onclick="window.location.href='<?php echo url('seller/store_menu'); ?>'" class="text-primary font-label-md flex items-center gap-1 hover:underline transition-all active:scale-95 bg-transparent border-0 fw-bold">
                                View Menu
                                <span class="material-symbols-outlined">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- TAB 4: NO RESULTS STATE -->
    <div id="tab-no-results" class="d-none">
        <section class="max-w-screen-xl mx-auto flex flex-col items-center justify-center py-5 my-5">
            <div class="relative w-48 h-48 mb-4 my-4">
                <div class="absolute inset-0 bg-primary opacity-5 blur-3xl rounded-full"></div>
                <div class="relative flex items-center justify-center w-full h-full border border-surface-variant bg-surface-container-low rounded-circle">
                    <span class="material-symbols-outlined text-[80px] text-outline-variant select-none" style="font-variation-settings: 'wght' 200;">search_off</span>
                </div>
            </div>
            <div class="text-center max-w-lg">
                <h1 class="font-headline-xl text-headline-xl mb-3 text-on-background font-bold">No results found</h1>
                <p class="font-body-lg text-body-lg text-on-surface-variant mb-4">
                    We couldn't find anything matching your search. Try adjusting your filters or checking for typos.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 my-4">
                    <button onclick="window.location.href='<?php echo url('user/home'); ?>'" class="bg-primary-container text-on-primary-fixed px-8 py-3 font-label-md text-label-md uppercase tracking-wider transition-transform active:scale-95 duration-100 w-full sm:w-auto rounded-2 fw-bold border-0">
                        Clear all filters
                    </button>
                    <button onclick="window.location.href='<?php echo url('user/home'); ?>'" class="border border-on-background text-on-background px-8 py-3 font-label-md text-label-md uppercase tracking-wider hover:bg-surface-container-high transition-colors w-full sm:w-auto rounded-2 bg-transparent fw-bold">
                        Back to home
                    </button>
                </div>
            </div>
        </section>

        <!-- Divider -->
        <div class="max-w-screen-xl mx-auto border-t border-surface-variant my-5"></div>

        <!-- Recommendations Section -->
        <section class="max-w-screen-xl mx-auto my-5">
            <div class="flex items-center justify-between mb-4 my-4">
                <h2 class="font-headline-md text-headline-md text-on-background font-bold">Popular near you</h2>
                <a class="text-primary font-label-md text-label-md hover:underline text-decoration-none" href="<?php echo url('user/home'); ?>">View all</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Card 1 -->
                <div onclick="window.location.href='<?php echo url('user/food_detail'); ?>'" class="glass-card group cursor-pointer overflow-hidden relative rounded-3">
                    <div class="relative aspect-[4/3] overflow-hidden">
                        <img alt="Gourmet Burger" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" src="<?php echo asset('images/gourmet_burger.png'); ?>">
                        <div class="absolute inset-0 image-overlay"></div>
                        <div class="absolute top-3 left-3">
                            <span class="bg-primary-container text-on-primary-fixed px-2 py-1 font-label-sm text-label-sm uppercase tracking-tighter rounded-1 fw-bold">Fast Delivery</span>
                        </div>
                        <div class="absolute bottom-3 left-3 right-3 flex justify-between items-end">
                            <div>
                                <h3 class="font-headline-md text-headline-md text-white mb-1">The Butcher's Son</h3>
                                <div class="flex items-center gap-2 text-on-primary text-label-sm font-label-sm">
                                    <span class="material-symbols-outlined text-[16px] text-primary-container" style="font-variation-settings: 'FILL' 1;">star</span>
                                    <span class="text-white">4.9 (500+)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 flex justify-between items-center bg-surface-container">
                        <span class="text-on-surface-variant font-label-md text-label-md">Burger • American • $$</span>
                        <span class="text-primary font-label-md text-label-md">20-30 min</span>
                    </div>
                </div>
                <!-- Card 2 -->
                <div onclick="window.location.href='<?php echo url('user/food_detail'); ?>'" class="glass-card group cursor-pointer overflow-hidden relative rounded-3">
                    <div class="relative aspect-[4/3] overflow-hidden">
                        <img alt="Artisan Pizza" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" src="<?php echo asset('images/artisan_pizza.png'); ?>">
                        <div class="absolute inset-0 image-overlay"></div>
                        <div class="absolute top-3 left-3">
                            <span class="bg-surface-container-highest text-white px-2 py-1 font-label-sm text-label-sm border border-outline-variant rounded-1">Free Delivery</span>
                        </div>
                        <div class="absolute bottom-3 left-3 right-3 flex justify-between items-end">
                            <div>
                                <h3 class="font-headline-md text-headline-md text-white mb-1">Napoli Artisan</h3>
                                <div class="flex items-center gap-2 text-on-primary text-label-sm font-label-sm">
                                    <span class="material-symbols-outlined text-[16px] text-primary-container" style="font-variation-settings: 'FILL' 1;">star</span>
                                    <span class="text-white">4.7 (1.2k)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 flex justify-between items-center bg-surface-container">
                        <span class="text-on-surface-variant font-label-md text-label-md">Pizza • Italian • $$</span>
                        <span class="text-primary font-label-md text-label-md">15-25 min</span>
                    </div>
                </div>
                <!-- Card 3 -->
                <div onclick="window.location.href='<?php echo url('user/food_detail'); ?>'" class="glass-card group cursor-pointer overflow-hidden relative rounded-3">
                    <div class="relative aspect-[4/3] overflow-hidden">
                        <img alt="Healthy Bowl" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" src="<?php echo asset('images/healthy_bowl.png'); ?>">
                        <div class="absolute inset-0 image-overlay"></div>
                        <div class="absolute top-3 left-3 flex gap-2">
                            <span class="bg-secondary-container text-white px-2 py-1 font-label-sm text-label-sm border border-outline-variant rounded-1">Healthy</span>
                        </div>
                        <div class="absolute bottom-3 left-3 right-3 flex justify-between items-end">
                            <div>
                                <h3 class="font-headline-md text-headline-md text-white mb-1">Green Garden</h3>
                                <div class="flex items-center gap-2 text-on-primary text-label-sm font-label-sm">
                                    <span class="material-symbols-outlined text-[16px] text-primary-container" style="font-variation-settings: 'FILL' 1;">star</span>
                                    <span class="text-white">4.8 (850)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 flex justify-between items-center bg-surface-container">
                        <span class="text-on-surface-variant font-label-md text-label-md">Salads • Vegan • $</span>
                        <span class="text-primary font-label-md text-label-md">10-20 min</span>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- TAB 5: LOADING SKELETON STATE -->
    <div id="tab-loading" class="d-none">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 my-5">
            <!-- Left: Restaurant List Skeleton (Column Span 8) -->
            <section class="lg:col-span-8 space-y-6">
                <div class="h-8 w-48 skeleton-shimmer opacity-60 rounded mb-4"></div>
                <!-- Restaurant Cards -->
                <div class="divide-y divide-surface-variant border-t border-b border-surface-variant">
                    <div class="py-6 flex gap-6">
                        <div class="w-24 h-24 md:w-32 md:h-32 skeleton-shimmer border border-surface-variant rounded flex-shrink-0"></div>
                        <div class="flex-grow space-y-4">
                            <div class="h-6 w-1/3 skeleton-shimmer opacity-50 rounded"></div>
                            <div class="h-4 w-1/4 skeleton-shimmer opacity-30 rounded"></div>
                            <div class="flex gap-4">
                                <div class="h-5 w-16 skeleton-shimmer opacity-20 rounded"></div>
                                <div class="h-5 w-16 skeleton-shimmer opacity-20 rounded"></div>
                            </div>
                        </div>
                    </div>
                    <div class="py-6 flex gap-6">
                        <div class="w-24 h-24 md:w-32 md:h-32 skeleton-shimmer border border-surface-variant rounded flex-shrink-0"></div>
                        <div class="flex-grow space-y-4">
                            <div class="h-6 w-2/5 skeleton-shimmer opacity-50 rounded"></div>
                            <div class="h-4 w-1/5 skeleton-shimmer opacity-30 rounded"></div>
                            <div class="flex gap-4">
                                <div class="h-5 w-16 skeleton-shimmer opacity-20 rounded"></div>
                                <div class="h-5 w-16 skeleton-shimmer opacity-20 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Right: Signature Dish Previews Skeleton (Column Span 4) -->
            <aside class="lg:col-span-4 space-y-6">
                <div class="h-8 w-32 skeleton-shimmer opacity-60 rounded mb-4"></div>
                <!-- Dish Preview Grid -->
                <div class="grid grid-cols-1 gap-6">
                    <div class="bg-surface-container border border-surface-variant p-4 rounded-xl space-y-4">
                        <div class="aspect-video w-full skeleton-shimmer border border-surface-variant rounded"></div>
                        <div class="h-5 w-1/2 skeleton-shimmer opacity-40 rounded"></div>
                        <div class="h-4 w-1/4 skeleton-shimmer opacity-20 rounded"></div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Tab Switching (Restaurants vs Dishes)
    const tabBtns = document.querySelectorAll('[data-tab-target]');
    const tabDishes = document.getElementById('tab-dishes');
    const tabRestaurants = document.getElementById('tab-restaurants');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const target = btn.getAttribute('data-tab-target');
            
            tabBtns.forEach(b => {
                b.classList.remove('btn-primary-custom', 'active-pill', 'text-white', 'rounded-2');
                b.classList.add('btn-link', 'text-on-surface-variant');
            });
            btn.classList.remove('btn-link', 'text-on-surface-variant');
            btn.classList.add('btn-primary-custom', 'active-pill', 'text-white', 'rounded-2');
            
            if (target === 'restaurants') {
                if (tabDishes) tabDishes.classList.add('d-none');
                if (tabRestaurants) tabRestaurants.classList.remove('d-none');
            } else {
                if (tabRestaurants) tabRestaurants.classList.add('d-none');
                if (tabDishes) tabDishes.classList.remove('d-none');
            }
        });
    });

    // 2. Category Chips Filtering
    const categoryBtns = document.querySelectorAll('.category-btn');
    const dishCards = document.querySelectorAll('#dishes-feed > div');

    categoryBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            categoryBtns.forEach(b => {
                b.classList.remove('bg-primary-custom', 'text-white');
                b.classList.add('bg-surface-variant', 'text-on-surface-variant');
            });
            btn.classList.remove('bg-surface-variant', 'text-on-surface-variant');
            btn.classList.add('bg-primary-custom', 'text-white');

            const category = btn.getAttribute('data-category');

            dishCards.forEach(card => {
                if (category === 'all') {
                    card.style.display = 'block';
                } else {
                    const badge = card.querySelector('.dish-category');
                    if (badge && badge.textContent.trim().toLowerCase() === category) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        });
    });

    // 3. Dynamic Search Filter
    const searchInput = document.getElementById('main-search-box');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            
            dishCards.forEach(card => {
                const title = card.querySelector('h5')?.textContent.toLowerCase() || '';
                const store = card.querySelector('.text-on-surface-variant')?.textContent.toLowerCase() || '';
                if (query === '' || title.includes(query) || store.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            const restaurantCards = document.querySelectorAll('#tab-restaurants article');
            restaurantCards.forEach(card => {
                const name = card.querySelector('h2')?.textContent.toLowerCase() || '';
                if (query === '' || name.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php
view('partials/user_footer', get_defined_vars());
?>
