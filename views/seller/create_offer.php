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
$sellerId = $_SESSION['user_id'] ?? null;

// Resolve store
$storeId = 0;
$myStore = null;
if ($sellerId) {
    $stmtStore = $db->prepare("SELECT * FROM stores WHERE owner_id = ?");
    $stmtStore->execute([$sellerId]);
    $myStore = $stmtStore->fetch();
    if ($myStore) $storeId = $myStore['id'];
}
if (!$sellerId || !$storeId) {
    header("Location: " . url('seller/login'));
    exit;
}

// Fetch all menu items for the store to populate checkboxes
$dishesStmt = $db->prepare("SELECT * FROM menu_items WHERE store_id = ? ORDER BY category, name");
$dishesStmt->execute([$storeId]);
$storeDishes = $dishesStmt->fetchAll();

// Fetch categories present in the store's menu items
$categoriesStmt = $db->prepare("SELECT DISTINCT category FROM menu_items WHERE store_id = ? ORDER BY category");
$categoriesStmt->execute([$storeId]);
$storeCategories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

// Handle offer creation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_promo') {
    $couponCode = strtoupper(trim($_POST['offer_title'] ?? ''));
    $offerTitle = trim($_POST['offer_title_display'] ?? '');
    $discount   = floatval($_POST['discount_range'] ?? 25.0);
    $minOrder   = floatval($_POST['price_limit'] ?? 0.0);

    if ($couponCode && $storeId) {
        // Check for duplicate coupon code
        $dupCheck = $db->prepare("SELECT id FROM promotional_offers WHERE coupon_code = ?");
        $dupCheck->execute([$couponCode]);
        if ($dupCheck->fetch()) {
            $createError = "Coupon code \"$couponCode\" already exists. Please choose a different code.";
        } else {
            $ins = $db->prepare("
                INSERT INTO promotional_offers
                    (store_id, coupon_code, offer_title, offer_description, discount_percentage,
                     min_order_value, admin_subsidy_percentage, merchant_absorb_percentage,
                     start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?, 0.00, 100.00, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'Active')
            ");
            $ins->execute([
                $storeId,
                $couponCode,
                $offerTitle ?: $couponCode,
                'Promotional discount coupon by ' . ($myStore['store_name'] ?? 'store'),
                $discount,
                $minOrder
            ]);
            $offerId = $db->lastInsertId();

            $selectedDishes = $_POST['dish_ids'] ?? [];
            if (!empty($selectedDishes) && is_array($selectedDishes) && $offerId) {
                // Ensure table exists safely
                try {
                    $insItem = $db->prepare("INSERT INTO promotional_offer_items (offer_id, menu_item_id) VALUES (?, ?)");
                    foreach ($selectedDishes as $dId) {
                        $insItem->execute([$offerId, intval($dId)]);
                    }
                } catch (Exception $e) {
                    // Fallback log if table mapping isn't used
                }
            }

            header("Location: " . url('seller/menu_offers'));
            exit;
        }
    } else {
        $createError = "Please fill in all required fields.";
    }
}

$pageTitle = 'Create Dynamic Promotion';
$activeNav = 'create_offer';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-4xl mx-auto w-full flex flex-col justify-between" style="min-height: 80vh;">
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="border-b border-outline-variant pb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Create Category &amp; Dish Offer</h2>
                    <p class="text-body-md text-on-surface-variant mb-0">List dynamic promo discounts on specific menu items, categories, or price ranges.</p>
                </div>
                <a href="<?php echo url('seller/menu_offers'); ?>" class="px-4 py-2 bg-surface-container-high border border-outline-variant text-on-surface hover:text-primary rounded-xl text-xs font-bold flex items-center gap-2 text-decoration-none transition-all shrink-0">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                    <span>Back to Offers List</span>
                </a>
            </div>

            <!-- Promotion Form -->
            <div class="bg-surface-container border border-outline-variant p-8 rounded-2xl shadow-xl space-y-6">
                <?php if (!empty($createError)): ?>
                    <div class="bg-error-container/20 border border-error-container text-error rounded-xl p-3 text-sm font-bold">
                        <?php echo htmlspecialchars($createError); ?>
                    </div>
                <?php endif; ?>
                <form action="" method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="create_promo">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Offer Title -->
                        <div class="space-y-2">
                            <label for="offer_title" class="block text-xs font-bold text-on-surface-variant uppercase">Offer Coupon Code</label>
                            <input type="text" name="offer_title" id="offer_title" required placeholder="e.g., RPG50" class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-sm w-full outline-none font-mono tracking-wider uppercase">
                        </div>
                        
                        <!-- Offer Display Name -->
                        <div class="space-y-2">
                            <label for="offer_title_display" class="block text-xs font-bold text-on-surface-variant uppercase">Offer Display Title</label>
                            <input type="text" name="offer_title_display" id="offer_title_display" required placeholder="e.g., Butter Chicken Festival" class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-sm w-full outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Select Category -->
                        <div class="space-y-2">
                            <label for="category_select" class="block text-xs font-bold text-on-surface-variant uppercase">Target Category</label>
                            <select id="category_select" onchange="filterDishesByCategory(this.value)" class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-sm w-full outline-none">
                                <option value="ALL">All Categories</option>
                                <?php foreach ($storeCategories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Target Dishes (Multiselect checkboxes with category filters) -->
                    <div class="space-y-3">
                        <span class="block text-xs font-bold text-on-surface-variant uppercase">Select Specific Dishes</span>
                        <div class="bg-surface-container-low border border-outline-variant p-4 rounded-lg max-h-48 overflow-y-auto custom-scrollbar grid grid-cols-1 sm:grid-cols-2 gap-3" id="dishes-list">
                            <?php if (empty($storeDishes)): ?>
                                <p class="text-on-surface-variant text-xs col-span-2">No menu items found. Add dishes in Dish Studio first!</p>
                            <?php else: ?>
                                <?php foreach ($storeDishes as $sd): ?>
                                    <label class="flex items-center gap-2 p-2 bg-surface-container rounded border border-outline-variant/30 cursor-pointer hover:border-primary" data-category="<?php echo htmlspecialchars($sd['category']); ?>">
                                        <input type="checkbox" name="dishes[]" value="<?php echo htmlspecialchars($sd['name']); ?>" class="accent-primary">
                                        <span class="text-xs text-on-surface font-semibold"><?php echo htmlspecialchars($sd['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Discount Value / Price Range -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label for="discount_percent" class="block text-xs font-bold text-on-surface-variant uppercase">Discount Percentage (%)</label>
                            <div class="flex gap-4 items-center bg-surface-container-low border border-outline-variant px-4 py-2.5 rounded-lg">
                                <input type="range" name="discount_range" min="5" max="75" value="25" class="flex-grow accent-primary" id="discount_range" oninput="updateRangeText(this.value)">
                                <span class="text-primary font-bold text-base w-12 text-right" id="rangeValue">25%</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label for="price_limit" class="block text-xs font-bold text-on-surface-variant uppercase">Minimum Order Value (₹)</label>
                            <input name="price_limit" type="number" id="price_limit" required placeholder="20.00" class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-sm w-full outline-none">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 border-t border-outline-variant">
                        <button type="submit" class="w-full py-3.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 active:scale-95 transition-all border-0 shadow-lg font-bold">
                            Generate Promotion Active Code
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer inside scrollable wrapper -->
        <footer class="w-full border-t border-outline-variant bg-background shrink-0 mt-12 pt-6">
            <div class="flex flex-col md:flex-row justify-between items-center w-full max-w-container-max-width mx-auto">
                <p class="font-body-sm text-body-sm text-on-surface-variant mb-0">© 2024 TestShare Enterprise. All rights reserved.</p>
                <div class="flex gap-6 mt-4 md:mt-0">
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Privacy Policy</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Terms of Service</a>
                </div>
            </div>
        </footer>
    </div>
</div>

<script>
    function updateRangeText(val) {
        document.getElementById('rangeValue').innerText = val + '%';
    }

    function filterDishesByCategory(category) {
        const list = document.getElementById('dishes-list');
        const labels = list.getElementsByTagName('label');
        for (let label of labels) {
            const labelCat = label.getAttribute('data-category');
            if (category === 'ALL' || labelCat === category) {
                label.style.display = 'flex';
            } else {
                label.style.display = 'none';
            }
        }
    }
</script>
<?php view('partials/seller_footer'); ?>
