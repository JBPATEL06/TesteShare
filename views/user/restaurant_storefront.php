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

$storeId = intval($_GET['id'] ?? 0);
if (!$storeId) {
    header("Location: " . url('user/home'));
    exit;
}

$store = null;
if ($storeId) {
    $stmt = $db->prepare("
        SELECT s.*, IFNULL(AVG(r.rating_stars), 5.0) as avg_rating, COUNT(r.id) as reviews_count
        FROM stores s
        LEFT JOIN reviews r ON s.id = r.store_id
        WHERE s.id = ? AND s.store_status = 'Active' AND s.onboarding_status = 'Approved'
        GROUP BY s.id
    ");
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();
}

if (!$store) {
    $pageTitle = 'Store Not Found';
    $activeNav = 'home';
    view('partials/user_header', get_defined_vars());
    ?>
    <main class="flex-grow max-w-4xl mx-auto px-container-margin py-12 w-full my-8 text-center">
        <div class="bg-surface-container border border-outline-variant rounded-2xl p-12 shadow-xl space-y-6">
            <span class="material-symbols-outlined text-[64px] text-primary">storefront</span>
            <h2 class="text-headline-lg font-bold text-on-surface">Store Unavailable or Not Found</h2>
            <p class="text-body-lg text-on-surface-variant max-w-md mx-auto mb-4">
                This store is currently inactive, undergoing maintenance, or does not exist on TestShare.
            </p>
            <a href="<?php echo url('user/home'); ?>" class="inline-block px-8 py-3 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 transition-all text-decoration-none shadow-lg">
                Explore Active Restaurants
            </a>
        </div>
    </main>
    <?php
    view('partials/user_footer');
    exit;
}

$storeTheme = getStoreThemeConfig($storeId);
$storeTier = getStoreTier($storeId);

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $rating = intval($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');
    $customerId = $_SESSION['user_id'] ?? null;
    
    if ($customerId && $storeId && $comment) {
        // Check if customer is a verified buyer who ordered from this store
        $checkOrder = $db->prepare("SELECT id FROM orders WHERE customer_id = ? AND store_id = ? AND order_status IN ('Completed', 'Delivered') LIMIT 1");
        $checkOrder->execute([$customerId, $storeId]);
        $isVerified = $checkOrder->fetch() ? 1 : 0;
        
        $statusStr = $isVerified ? 'Verified Purchase' : 'Public storefront';
        
        $stmt = $db->prepare("INSERT INTO reviews (customer_id, store_id, rating_stars, comment_text, helpful_upvotes, is_popular, status) VALUES (?, ?, ?, ?, 0, 0, ?)");
        $stmt->execute([$customerId, $storeId, $rating, $comment, $statusStr]);
    }
    header("Location: " . url('user/restaurant_storefront&id=' . $storeId . '#reviews'));
    exit;
}

// Load menu items categorized
$menuItems = $db->prepare("SELECT * FROM menu_items WHERE store_id = ? AND is_available = 1");
$menuItems->execute([$storeId]);
$dishes = $menuItems->fetchAll();

$categories = [];
foreach ($dishes as $dish) {
    $categories[$dish['category']][] = $dish;
}

// Load reviews
$reviewsStmt = $db->prepare("
    SELECT r.*, u.fullname as reviewer_name 
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    WHERE r.store_id = ? AND r.status = 'Public storefront'
    ORDER BY r.created_at DESC
");
$reviewsStmt->execute([$storeId]);
$reviews = $reviewsStmt->fetchAll();

// Load promotional offers
$offersStmt = $db->prepare("
    SELECT * FROM promotional_offers 
    WHERE store_id = ? AND status = 'Active' AND end_date >= NOW()
");
$offersStmt->execute([$storeId]);
$promotionalOffers = $offersStmt->fetchAll();

$pageTitle = $store['store_name'];
$activeNav = 'explore';
view('partials/user_header', get_defined_vars());
?>

<style>
    /* Custom Theme Colors */
    :root {
        --primary-accent: <?php echo htmlspecialchars($storeTheme['accent_color'] ?? '#ff9f0d'); ?>;
    }
    .text-primary, .tab-btn.active { color: var(--primary-accent) !important; }
    .bg-primary { background-color: var(--primary-accent) !important; }
    .border-primary { border-color: var(--primary-accent) !important; }
    
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #131313; }
    ::-webkit-scrollbar-thumb { background: #2D2D2D; border-radius: 4px; }
    /* Tab switching logic */
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .tab-btn.active { border-color: var(--primary-accent); color: var(--primary-accent); font-weight: bold; }
</style>

<main class="flex-grow pt-base">
    <!-- Store Banner for Premium / Ultra Premium Merchants -->
    <?php 
    $bannerUrl = !empty($store['store_banner']) ? $store['store_banner'] : ($storeTheme['store_banner'] ?? null);
    $showBanner = ($storeTier === 'Premium' || $storeTier === 'Ultra Premium') && !empty($bannerUrl);
    ?>
    <?php if ($showBanner): ?>
    <div class="w-full h-56 md:h-72 overflow-hidden relative border-b border-primary/30 shadow-2xl">
        <img src="<?php echo htmlspecialchars($bannerUrl); ?>" alt="<?php echo htmlspecialchars($store['store_name']); ?> banner" class="w-full h-full object-cover">
        <div class="absolute inset-0" style="background: linear-gradient(to top, rgba(19,19,19,0.95) 0%, rgba(19,19,19,0.2) 60%, transparent 100%);"></div>
    </div>
    <?php endif; ?>

    <!-- Restaurant Hero Section -->
    <section class="px-container-margin py-base border-b border-surface-variant bg-surface-container-low py-4 <?php echo $showBanner ? '-mt-20 relative z-10' : ''; ?>">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row gap-gutter items-start">
            <div class="relative w-full md:w-48 h-48 border border-surface-variant rounded-3 overflow-hidden mr-4" style="width: 192px; height: 192px;">
                <img class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($store['store_name']); ?>" src="<?php echo !empty($store['store_logo']) ? htmlspecialchars($store['store_logo']) : asset('images/ember_kitchen_logo.png'); ?>">
            </div>
            <div class="flex-grow space-y-base">
                <div class="flex flex-col md:flex-row md:justify-between md:items-start mb-3">
                    <div>
                        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1 flex items-center gap-3">
                            <?php echo htmlspecialchars($store['store_name']); ?>
                            <?php if ($storeTier === 'Ultra Premium'): ?>
                                <span class="bg-amber-500/20 text-amber-400 border border-amber-500/40 text-xs px-3 py-1 rounded-full font-bold">👑 Ultra VIP Merchant</span>
                            <?php elseif ($storeTier === 'Premium'): ?>
                                <span class="bg-primary/20 text-primary border border-primary/40 text-xs px-3 py-1 rounded-full font-bold">⭐ Verified Premium</span>
                            <?php endif; ?>
                        </h1>
                        <p class="font-body-md text-on-surface-variant mb-0"><?php echo htmlspecialchars($store['category']); ?> • <?php echo htmlspecialchars($store['address']); ?>, <?php echo htmlspecialchars($store['city']); ?></p>
                    </div>
                    <div class="bg-primary-container text-on-primary-container px-3 py-1 font-label-md rounded-2 flex items-center gap-stack-sm mt-3 md:mt-0 font-bold self-start">
                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                        <?php echo number_format($store['avg_rating'], 1); ?> (<?php echo $store['reviews_count']; ?> Reviews)
                    </div>
                </div>
                <div class="flex flex-wrap gap-stack-lg mt-base pt-2 border-t border-surface-variant">
                    <div class="flex items-center gap-stack-sm font-label-md text-on-surface mr-4">
                        <span class="material-symbols-outlined text-primary mr-1">schedule</span>
                        25-35 mins
                    </div>
                    <div class="flex items-center gap-stack-sm font-label-md text-on-surface mr-4">
                        <span class="material-symbols-outlined text-primary mr-1">delivery_dining</span>
                        Free delivery
                    </div>
                    <div class="flex items-center gap-stack-sm font-label-md text-on-surface">
                        <span class="material-symbols-outlined text-primary mr-1">payments</span>
                        Min. order ₹15.00
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Secondary Navigation (Tabs) -->
    <section class="sticky top-[65px] bg-surface z-30 border-b border-surface-variant">
        <div class="max-w-7xl mx-auto px-container-margin flex gap-stack-lg">
            <button class="tab-btn active py-4 font-label-md text-label-md border-b-2 border-transparent transition-all bg-transparent mr-4 border-0" onclick="switchTab('menu', this)">Menu</button>
            <button class="tab-btn py-4 font-label-md text-label-md border-b-2 border-transparent transition-all bg-transparent text-on-surface-variant border-0 mr-4" onclick="switchTab('offers', this)">Offers</button>
            <button class="tab-btn py-4 font-label-md text-label-md border-b-2 border-transparent transition-all bg-transparent text-on-surface-variant border-0" onclick="switchTab('reviews', this)">Reviews & Comments</button>
        </div>
    </section>

    <!-- Content Area -->
    <div class="max-w-7xl mx-auto px-container-margin py-stack-lg my-4">
        <!-- Menu Tab -->
        <div class="tab-content active" id="tab-menu">
            <div class="flex flex-col lg:flex-row gap-section-gap">
                <!-- Sticky Sidebar -->
                <aside class="hidden lg:block w-64 shrink-0 mr-4" style="width: 256px;">
                    <div class="sticky top-[140px] space-y-stack-md">
                        <h3 class="font-label-md text-label-md text-on-surface-variant uppercase tracking-widest mb-base font-bold mb-3">Categories</h3>
                        <nav class="space-y-1" id="storefrontCatNav">
                            <a onclick="filterStorefrontCat('all', this)" class="store-cat-link block py-2 px-3 border-l-2 border-primary text-primary bg-surface-container font-bold font-label-md transition-colors text-decoration-none mb-1 cursor-pointer">All Categories</a>
                            <?php foreach ($categories as $catName => $catDishes): ?>
                                <a onclick="filterStorefrontCat('cat-<?php echo md5($catName); ?>', this)" class="store-cat-link block py-2 px-3 border-l-2 border-transparent text-on-surface-variant hover:text-on-surface font-label-md transition-colors text-decoration-none mb-1 cursor-pointer"><?php echo htmlspecialchars($catName); ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                </aside>
                <!-- Dishes List -->
                <div class="flex-grow space-y-section-gap">
                    <?php if (empty($categories)): ?>
                        <p class="text-on-surface-variant font-body-md mb-0">No active menu items available for this store.</p>
                    <?php else: ?>
                        <?php foreach ($categories as $catName => $catDishes): ?>
                            <section id="cat-<?php echo md5($catName); ?>" class="mb-5 storefront-cat-section">
                                <h2 class="font-headline-md text-headline-md text-on-surface mb-stack-lg border-b border-surface-variant pb-base font-bold pb-2 mb-4"><?php echo htmlspecialchars($catName); ?></h2>
                                <div class="space-y-0 divide-y divide-surface-variant border-t border-surface-variant">
                                    <?php foreach ($catDishes as $dish): ?>
                                        <?php
                                        $imgUrl = $dish['image_url'] ? asset($dish['image_url']) : asset('images/smoked_bone_marrow.png');
                                        ?>
                                        <div class="py-stack-lg flex gap-gutter py-4">
                                            <div class="flex-grow pr-gutter pr-4">
                                                <h3 class="font-body-lg text-body-lg font-semibold text-on-surface font-bold"><?php echo htmlspecialchars($dish['name']); ?></h3>
                                                <p class="font-body-md text-on-surface-variant mt-stack-sm mb-2"><?php echo htmlspecialchars($dish['description']); ?></p>
                                                <div class="mt-base flex items-center gap-stack-lg">
                                                    <span class="font-label-md text-primary font-bold mr-3">₹<?php echo number_format($dish['price'], 2); ?></span>
                                                </div>
                                            </div>
                                            <div class="relative w-24 h-24 border border-surface-variant shrink-0 group overflow-hidden rounded-2" style="width: 96px; height: 96px;">
                                                <img class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?php echo htmlspecialchars($dish['name']); ?>" src="<?php echo $imgUrl; ?>">
                                                <button onclick="window.location.href='<?php echo url('user/cart&add_id=' . $dish['id']); ?>'" class="absolute bottom-1 right-1 bg-primary text-on-primary w-8 h-8 flex items-center justify-center rounded-sm shadow-xl active:scale-95 transition-all border-0" style="width: 32px; height: 32px;">
                                                    <span class="material-symbols-outlined text-[20px]">add</span>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reviews Tab -->
        <div class="tab-content" id="tab-reviews">
            <div class="max-w-3xl space-y-section-gap">
                <!-- Submit Review Box -->
                <div class="bg-surface-container border border-surface-variant p-4 rounded-3 mb-5">
                    <h3 class="font-headline-md text-on-surface font-bold mb-3">Leave a Review</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="submit_review">
                        <div class="mb-3">
                            <label class="font-label-sm text-on-surface-variant block mb-2 font-bold">Your Rating (1-5)</label>
                            <select name="rating" class="bg-surface border border-surface-variant p-2 text-on-surface rounded-2">
                                <option value="5">5 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="2">2 Stars</option>
                                <option value="1">1 Star</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="font-label-sm text-on-surface-variant block mb-2 font-bold">Your Experience</label>
                            <textarea name="comment" required class="w-full bg-surface border border-surface-variant p-3 text-on-surface focus:border-primary-container focus:ring-0 rounded-2 min-h-[100px] resize-none" placeholder="Share details of your dining experience..."></textarea>
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button type="submit" class="bg-primary-container text-on-primary-container font-bold py-2 px-6 rounded-2 hover:brightness-110 active:scale-95 transition-all border-0 uppercase tracking-wider">Submit Review</button>
                        <?php else: ?>
                            <a href="<?php echo url('user/login'); ?>" class="bg-surface-variant text-on-surface-variant font-bold py-2 px-6 rounded-2 hover:brightness-110 transition-all text-decoration-none inline-block">Login to Leave a Review</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Individual Reviews List -->
                <div class="space-y-4">
                    <?php if (empty($reviews)): ?>
                        <p class="text-on-surface-variant font-body-md mb-0">No public reviews for this store yet.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $rev): ?>
                            <?php
                            $revDate = date('Y-m-d', strtotime($rev['created_at']));
                            $avatar = asset('images/user_profile_avatar.png');
                            ?>
                            <div class="border-b border-surface-variant pb-4 mb-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 bg-surface-container-high rounded-circle overflow-hidden" style="width: 48px; height: 48px;">
                                            <img class="w-full h-full object-cover" alt="Reviewer" src="<?php echo $avatar; ?>">
                                        </div>
                                        <div>
                                            <p class="font-label-md text-on-surface font-bold mb-0"><?php echo htmlspecialchars($rev['reviewer_name']); ?></p>
                                            <p class="font-label-sm text-on-surface-variant mb-0"><?php echo $revDate; ?></p>
                                        </div>
                                    </div>
                                    <div class="flex text-primary-container">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' <?php echo $i <= $rev['rating_stars'] ? '1' : '0'; ?>;">star</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="text-on-surface-variant font-body-md mb-0">"<?php echo htmlspecialchars($rev['comment_text']); ?>"</p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Offers Tab -->
        <div class="tab-content" id="tab-offers">
            <div class="max-w-7xl space-y-section-gap">
                <?php if (empty($promotionalOffers)): ?>
                    <p class="text-on-surface-variant font-body-md mb-0">No active offers for this store right now.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($promotionalOffers as $offer): ?>
                            <div class="bg-surface-container border border-outline-variant rounded-2xl p-6 relative overflow-hidden group hover:border-primary transition-all shadow-xl">
                                <div class="absolute top-0 right-0 bg-primary-container text-on-primary-container font-bold px-4 py-1 rounded-bl-xl text-xs uppercase tracking-widest">Active</div>
                                <h3 class="font-bold text-2xl text-on-surface mb-2"><?php echo htmlspecialchars($offer['offer_title']); ?></h3>
                                <p class="text-on-surface-variant text-sm mb-6"><?php echo htmlspecialchars($offer['offer_description'] ?? ''); ?></p>
                                <div class="flex items-center justify-between bg-surface-container-low p-3 rounded-xl border border-outline-variant">
                                    <span class="font-mono font-bold text-primary tracking-wider text-lg" id="code-<?php echo htmlspecialchars($offer['coupon_code']); ?>"><?php echo htmlspecialchars($offer['coupon_code']); ?></span>
                                    <button onclick="copyCode('code-<?php echo htmlspecialchars($offer['coupon_code']); ?>', this)" class="bg-surface-container-high hover:bg-primary-container hover:text-on-primary-container text-on-surface font-bold px-4 py-2 rounded-lg text-sm transition-all border border-outline-variant font-bold">Copy Code</button>
                                </div>
                                <div class="mt-4 text-label-sm text-on-surface-variant">
                                    Min. Order: ₹<?php echo number_format($offer['min_order_value'], 2); ?> | Discount: <?php echo number_format($offer['discount_percentage'], 0); ?>%
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
    // Tab switching logic
    function switchTab(tabId, btn) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active', 'text-primary');
            b.classList.add('text-on-surface-variant');
        });
        document.getElementById('tab-' + tabId).classList.add('active');
        btn.classList.add('active', 'text-primary');
        btn.classList.remove('text-on-surface-variant');
    }

    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (window.location.hash === '#reviews' || urlParams.get('tab') === 'reviews') {
            const reviewBtn = document.querySelector('button[onclick*="switchTab(\'reviews\'"]');
            if (reviewBtn) {
                switchTab('reviews', reviewBtn);
            }
        }
    });

    function copyCode(elementId, btn) {
        const code = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(code);
        const origText = btn.innerText;
        btn.innerText = 'Copied!';
        btn.className = 'bg-green-600 text-white font-bold px-4 py-2 rounded-lg text-sm transition-all border border-green-600 font-bold';
        setTimeout(() => {
            btn.innerText = origText;
            btn.className = 'bg-surface-container-high hover:bg-primary-container hover:text-on-primary-container text-on-surface font-bold px-4 py-2 rounded-lg text-sm transition-all border border-outline-variant font-bold';
        }, 2000);
    }

    function filterStorefrontCat(catId, link) {
        document.querySelectorAll('.store-cat-link').forEach(l => {
            l.classList.remove('border-primary', 'text-primary', 'bg-surface-container', 'font-bold');
            l.classList.add('border-transparent', 'text-on-surface-variant');
        });
        if (link) {
            link.classList.remove('border-transparent', 'text-on-surface-variant');
            link.classList.add('border-primary', 'text-primary', 'bg-surface-container', 'font-bold');
        }
        if (catId === 'all') {
            document.querySelectorAll('.storefront-cat-section').forEach(sec => sec.style.display = 'block');
        } else {
            document.querySelectorAll('.storefront-cat-section').forEach(sec => {
                if (sec.id === catId) {
                    sec.style.display = 'block';
                    sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    sec.style.display = 'none';
                }
            });
        }
    }
</script>

<?php view('partials/user_footer'); ?>
