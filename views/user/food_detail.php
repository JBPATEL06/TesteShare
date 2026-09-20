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

$dishId = intval($_GET['id'] ?? 0);
if (!$dishId) {
    $dishId = $db->query("SELECT id FROM menu_items LIMIT 1")->fetchColumn();
}

$dish = null;
if ($dishId) {
    $stmt = $db->prepare("
        SELECT m.*, s.store_name, s.category as store_category
        FROM menu_items m
        JOIN stores s ON m.store_id = s.id
        WHERE m.id = ?
    ");
    $stmt->execute([$dishId]);
    $dish = $stmt->fetch();
}

if (!$dish) {
    echo "<div style='color:red; padding:50px; text-align:center;'>Dish not found.</div>";
    exit;
}

// Load store reviews for the same store
$reviewsStmt = $db->prepare("
    SELECT r.*, u.fullname as reviewer_name 
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    WHERE r.store_id = ? AND r.status = 'Public storefront'
    ORDER BY r.created_at DESC
");
$reviewsStmt->execute([$dish['store_id']]);
$allReviews = $reviewsStmt->fetchAll();
$reviewsCount = count($allReviews);
$reviews = array_slice($allReviews, 0, 3);

$totalRating = 0;
foreach ($allReviews as $rev) {
    $totalRating += $rev['rating_stars'];
}
$rating = $reviewsCount > 0 ? number_format($totalRating / $reviewsCount, 1) : '5.0';
$prepTime = (!empty($dish['prep_time']) ? $dish['prep_time'] : 20) . ' min';
$ingredientsList = !empty($dish['ingredients']) ? array_map('trim', explode(',', $dish['ingredients'])) : ['Hand-selected Premium Ingredients', 'Fresh Organic Dairy & Proteins', 'House-made Seasonings & Glazes'];

$pageTitle = $dish['name'];
$activeNav = 'explore';
view('partials/user_header', get_defined_vars());
?>

<style>
    .border-tight { border: 1px solid #2D2D2D; }
    .hero-gradient {
        background: linear-gradient(to top, rgba(19,19,19,1) 0%, rgba(19,19,19,0.4) 50%, rgba(19,19,19,0) 100%);
    }
</style>

<main class="flex-grow">
    <!-- Hero Section -->
    <section class="relative h-[60vh] md:h-[75vh] w-full overflow-hidden border-b border-tight">
        <div class="absolute inset-0 w-full h-full">
            <img class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($dish['name']); ?> presentation" src="<?php echo $dish['image_url'] ? asset($dish['image_url']) : asset('images/wagyu_gold_tartare.png'); ?>">
            <div class="absolute inset-0 hero-gradient"></div>
        </div>
        <div class="absolute bottom-0 left-0 w-full px-container-margin pb-section-gap max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-stack-lg">
                <div class="space-y-stack-sm max-w-2xl">
                    <div class="flex items-center gap-base mb-stack-sm">
                        <span class="bg-primary-container text-on-primary-container px-3 py-1 font-label-sm text-label-sm tracking-wider uppercase rounded-1 fw-bold"><?php echo htmlspecialchars($dish['category']); ?></span>
                        <span class="text-on-surface-variant font-label-md text-label-md ml-3"><?php echo htmlspecialchars($dish['store_name']); ?></span>
                    </div>
                    <h1 class="font-headline-xl text-headline-xl text-on-surface leading-tight font-bold"><?php echo htmlspecialchars($dish['name']); ?></h1>
                    <p class="font-body-lg text-body-lg text-on-surface-variant max-w-lg mb-0"><?php echo htmlspecialchars($dish['description']); ?></p>
                </div>
                <div class="flex flex-col items-start md:items-end gap-stack-sm">
                    <span class="font-headline-lg text-headline-lg text-primary-container font-bold">₹<?php echo number_format($dish['price'], 2); ?></span>
                    <div class="flex items-center gap-stack-md text-on-surface-variant font-label-md">
                        <div class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-primary-container text-[18px]" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span><?php echo $rating; ?></span>
                        </div>
                        <span class="w-1 h-1 bg-outline-variant rounded-full"></span>
                        <span class="ml-3"><?php echo $prepTime; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Details Grid -->
    <section class="px-container-margin py-section-gap max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-gutter my-5">
        <!-- Left Column: Content -->
        <div class="lg:col-span-7 space-y-section-gap">
            <!-- Chef's Notes -->
            <div class="bg-surface-container border-tight p-stack-lg relative overflow-hidden group rounded-3 mb-5">
                <div class="absolute top-0 right-0 p-stack-lg opacity-10 pointer-events-none">
                    <span class="material-symbols-outlined text-[120px]">restaurant_menu</span>
                </div>
                <div class="relative z-10 p-4">
                    <h3 class="font-headline-md text-headline-md mb-stack-md text-primary font-bold">Chef's Notes</h3>
                    <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed mb-0">
                        Crafted at <?php echo htmlspecialchars($dish['store_name']); ?>, this dish showcases the finest selection of ingredients prepared using expert culinary techniques to ensure maximum texture, flavor, and freshness.
                    </p>
                </div>
            </div>

            <!-- Ingredients & Allergens -->
            <div class="space-y-stack-lg mb-5">
                <h3 class="font-headline-md text-headline-md text-on-surface border-l-4 border-primary pl-stack-md font-bold mb-4">Ingredients & Quality</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-stack-md mb-4">
                    <?php foreach ($ingredientsList as $idx => $ingredient): ?>
                    <div class="flex items-center gap-stack-md p-stack-md bg-surface-container border-tight rounded-2">
                        <span class="material-symbols-outlined text-primary-container"><?php echo ['eco', 'egg', 'grass', 'diamond'][$idx % 4]; ?></span>
                        <span class="font-body-md text-body-md"><?php echo htmlspecialchars($ingredient); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-stack-md border-tight border-dashed border-outline-variant bg-surface-container-lowest rounded-3 p-4">
                    <div class="flex items-start gap-stack-md">
                        <span class="material-symbols-outlined text-error mt-0.5 mr-2">warning</span>
                        <p class="font-label-md text-label-md text-on-surface-variant mb-0 ml-2">Allergen Warning: Prepared in a commercial kitchen facility that handles gluten, soy, nuts, and dairy products. Please notify support for specific request notes.</p>
                    </div>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="space-y-stack-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-headline-md text-headline-md text-on-surface font-bold">Verified Reviews</h3>
                    <div class="flex items-center gap-base font-label-md text-label-md text-on-surface-variant">
                        <span><?php echo $reviewsCount; ?> reviews</span>
                    </div>
                </div>
                <div class="space-y-gutter">
                    <?php if (empty($reviews)): ?>
                        <p class="text-on-surface-variant font-body-md mb-0">No public reviews for this store yet.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div class="py-stack-lg border-b border-tight last:border-0 pb-4 mb-4">
                                <div class="flex justify-between items-start mb-stack-md">
                                    <div class="flex items-center gap-stack-md">
                                        <div class="w-12 h-12 bg-surface-container-highest border-tight overflow-hidden rounded-circle mr-3" style="width: 48px; height: 48px;">
                                            <img class="w-full h-full object-cover" alt="Reviewer avatar" src="<?php echo asset('images/reviewer_avatar_1.png'); ?>">
                                        </div>
                                        <div>
                                            <p class="font-label-md text-label-md text-on-surface mb-0 font-bold"><?php echo htmlspecialchars($rev['reviewer_name']); ?></p>
                                            <p class="font-label-sm text-label-sm text-on-surface-variant mb-0"><?php echo date('F d, Y', strtotime($rev['created_at'])); ?></p>
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

        <!-- Right Column: Order Box -->
        <div class="lg:col-span-5">
            <div class="bg-surface-container border-tight p-stack-lg rounded-3 p-4 sticky top-28">
                <h3 class="font-headline-md text-headline-md text-on-surface mb-3 font-bold">Order Customization</h3>
                <div class="border-b border-surface-variant pb-4 mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-on-surface font-label-md text-label-md"><?php echo htmlspecialchars($dish['name']); ?></span>
                        <span class="text-primary-container font-label-md text-label-md font-bold">₹<?php echo number_format($dish['price'], 2); ?></span>
                    </div>
                    <p class="text-on-surface-variant font-label-sm text-label-sm mb-0">Preparation time: <?php echo $prepTime; ?></p>
                </div>

                <!-- Quantity Selector -->
                <div class="flex items-center justify-between mb-4">
                    <span class="text-on-surface font-label-md text-label-md uppercase tracking-wider">Quantity</span>
                    <div class="flex items-center border border-surface-variant bg-surface rounded-2 overflow-hidden">
                        <button class="w-10 h-10 flex items-center justify-center hover:bg-surface-container-high transition-colors active:scale-95 bg-transparent border-0 text-on-surface" id="btn-minus" style="width: 40px; height: 40px;">
                            <span class="material-symbols-outlined text-sm">remove</span>
                        </button>
                        <span class="w-12 h-10 flex items-center justify-center font-label-md text-label-md text-on-surface font-bold" id="item-quantity" style="width: 48px; height: 40px;">1</span>
                        <button class="w-10 h-10 flex items-center justify-center hover:bg-surface-container-high transition-colors active:scale-95 bg-transparent border-0 text-on-surface" id="btn-plus" style="width: 40px; height: 40px;">
                            <span class="material-symbols-outlined text-sm">add</span>
                        </button>
                    </div>
                </div>

                <!-- Total Calculation -->
                <div class="border-t border-surface-variant pt-4 mb-4 flex justify-between items-end">
                    <span class="text-on-surface font-label-md text-label-md uppercase tracking-wider">Total Amount</span>
                    <span class="font-headline-md text-headline-md text-primary font-bold" id="total-price">₹<?php echo number_format($dish['price'], 2); ?></span>
                </div>

                <!-- Action Button -->
                <button id="add-to-cart-btn" class="w-full bg-primary-container text-on-primary-container py-3 rounded-2 font-label-md text-label-md font-bold uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-2 border-0">
                    <span class="material-symbols-outlined">shopping_cart</span>
                    Add to Cart
                </button>

                <div class="flex justify-center gap-4 mt-4 pt-3 border-t border-surface-variant text-on-surface-variant">
                    <div class="flex items-center gap-1 font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[16px]">security</span>
                        <span>Secured Checkout</span>
                    </div>
                    <div class="flex items-center gap-1 font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[16px]">verified</span>
                        <span>Premium Guarantee</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    // Quantity adjustment script
    const btnMinus = document.getElementById('btn-minus');
    const btnPlus = document.getElementById('btn-plus');
    const quantityEl = document.getElementById('item-quantity');
    const totalEl = document.getElementById('total-price');
    const unitPrice = <?php echo floatval($dish['price']); ?>;

    if(btnMinus && btnPlus && quantityEl && totalEl) {
        btnMinus.addEventListener('click', () => {
            let qty = parseInt(quantityEl.innerText);
            if(qty > 1) {
                qty--;
                quantityEl.innerText = qty;
                totalEl.innerText = '₹' + (qty * unitPrice).toFixed(2);
            }
        });
        btnPlus.addEventListener('click', () => {
            let qty = parseInt(quantityEl.innerText);
            qty++;
            quantityEl.innerText = qty;
            totalEl.innerText = '₹' + (qty * unitPrice).toFixed(2);
        });
    }

    const btnAddToCart = document.getElementById('add-to-cart-btn');
    if (btnAddToCart) {
        btnAddToCart.addEventListener('click', () => {
            let qty = parseInt(quantityEl.innerText);
            window.location.href = '<?php echo url('user/cart&add_id=' . $dish['id']); ?>&qty=' + qty;
        });
    }
</script>

<?php view('partials/user_footer'); ?>
