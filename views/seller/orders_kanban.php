<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';

$db = getDB();
$sellerId = $_SESSION['user_id'] ?? null;

// Resolve store associated with logged-in owner
$storeId = 0;
if ($sellerId) {
    $stmtStore = $db->prepare("SELECT id FROM stores WHERE owner_id = ?");
    $stmtStore->execute([$sellerId]);
    $myStore = $stmtStore->fetch();
    if ($myStore) {
        $storeId = $myStore['id'];
    }
}

// Handle Promo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_promo' && $storeId) {
    $discount = floatval($_POST['discount_percentage']);
    $category = trim($_POST['target_category']);
    if (empty($category)) $category = null;
    $durationHours = intval($_POST['duration']);
    $expiresAt = date('Y-m-d H:i:s', strtotime("+$durationHours hours"));
    
    $title = "Flash " . $discount . "% Off";
    if ($category) $title .= " on " . $category;
    
    $ins = $db->prepare("INSERT INTO promotional_offers (store_id, title, discount_percentage, target_category, expires_at, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $ins->execute([$storeId, $title, $discount, $category, $expiresAt]);
    
    header("Location: " . url('seller/orders_kanban'));
    exit;
}

// Fetch menu items
$menuItems = [];
$totalItems = 0;
$soldOut = 0;
$activeDiscounts = 0;
if ($storeId) {
    $stmtPromo = $db->prepare("SELECT COUNT(*) FROM promotional_offers WHERE store_id = ? AND is_active = 1 AND expires_at > NOW()");
    $stmtPromo->execute([$storeId]);
    $activeDiscounts = $stmtPromo->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM menu_items WHERE store_id = ? ORDER BY created_at DESC");
    $stmt->execute([$storeId]);
    $menuItems = $stmt->fetchAll();
    
    $totalItems = count($menuItems);
    foreach ($menuItems as $item) {
        if (!$item['is_available']) {
            $soldOut++;
        }
    }
}

$pageTitle = 'Menu Inventory';
$activeNav = 'orders_kanban';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>
    <div class="flex-1 overflow-y-auto px-8 py-8 bg-background">
        <div class="max-w-container-max-width mx-auto">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
                <div>
                    <h1 class="font-headline-lg text-headline-lg text-primary mb-2 font-bold">Menu Inventory</h1>
                    <p class="text-on-surface-variant font-body-md mb-0">Manage your offerings, adjust pricing, and create seasonal discounts.</p>
                </div>
                <div class="flex gap-4">
                    <button class="flex items-center gap-2 px-6 py-2.5 border border-outline-variant rounded-lg font-label-md text-on-surface hover:bg-surface-container transition-colors bg-transparent font-bold">
                        <span class="material-symbols-outlined text-[20px]">filter_list</span> Filter
                    </button>
                    <button onclick="window.location.href='<?php echo url('seller/menu_offers'); ?>'" class="flex items-center gap-2 px-6 py-2.5 bg-primary-container text-on-primary-container rounded-lg font-label-md hover:opacity-90 transition-opacity border-0 font-bold">
                        <span class="material-symbols-outlined text-[20px]">add</span> Add Dish
                    </button>
                </div>
            </div>
            <!-- Metrics Bento Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bento-card bg-surface-container-low border border-outline-variant p-6 rounded-xl">
                    <p class="text-on-surface-variant font-label-sm uppercase mb-2 font-bold tracking-widest">Total Items</p>
                    <h3 class="text-headline-lg font-bold text-on-surface mb-0"><?php echo $totalItems; ?></h3>
                    <p class="text-[12px] text-primary mt-2 mb-0 flex items-center gap-1 font-bold">
                        <span class="material-symbols-outlined text-[16px]">trending_up</span> Live
                    </p>
                </div>
                <div class="bento-card bg-surface-container-low border border-outline-variant p-6 rounded-xl">
                    <p class="text-on-surface-variant font-label-sm uppercase mb-2 font-bold tracking-widest">Active Discounts</p>
                    <h3 class="text-headline-lg font-bold text-primary mb-0"><?php echo $activeDiscounts; ?></h3>
                    <p class="text-[12px] text-on-surface-variant mt-2 mb-0">Driving sales</p>
                </div>
                <div class="bento-card bg-surface-container-low border border-outline-variant p-6 rounded-xl">
                    <p class="text-on-surface-variant font-label-sm uppercase mb-2 font-bold tracking-widest">Sold Out Items</p>
                    <h3 class="text-headline-lg font-bold text-error mb-0"><?php echo $soldOut; ?></h3>
                    <p class="text-[12px] text-error mt-2 mb-0 flex items-center gap-1 font-bold">
                        <span class="material-symbols-outlined text-[16px]">warning</span> Action required
                    </p>
                </div>
                <div class="bento-card bg-surface-container-low border border-outline-variant p-6 rounded-xl">
                    <p class="text-on-surface-variant font-label-sm uppercase mb-2 font-bold tracking-widest">Avg. Dish Margin</p>
                    <h3 class="text-headline-lg font-bold text-on-surface mb-0">64%</h3>
                    <p class="text-[12px] text-primary mt-2 mb-0 font-bold">Optimal range</p>
                </div>
            </div>
            <!-- Menu Management Section -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-2xl mb-12">
                <div class="p-6 border-b border-outline-variant flex flex-col md:flex-row justify-between items-center bg-surface-container gap-4">
                    <div class="flex flex-wrap gap-4">
                        <button class="font-label-md px-4 py-2 border-b-2 border-primary text-primary font-bold bg-transparent">All Dishes</button>
                        <button class="font-label-md px-4 py-2 text-on-surface-variant hover:text-on-surface bg-transparent border-0 font-bold">Main Course</button>
                        <button class="font-label-md px-4 py-2 text-on-surface-variant hover:text-on-surface bg-transparent border-0 font-bold">Appetizers</button>
                        <button class="font-label-md px-4 py-2 text-on-surface-variant hover:text-on-surface bg-transparent border-0 font-bold">Beverages</button>
                    </div>
                    <div class="relative w-full md:w-auto">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                        <input class="bg-background border border-outline-variant pl-10 pr-4 py-2 rounded-lg text-sm focus:ring-1 focus:ring-primary focus:border-primary outline-none text-on-surface w-full md:w-72" placeholder="Search menu..." type="text">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface-container-low border-b border-outline-variant">
                            <tr>
                                <th class="p-6 font-label-sm text-on-surface-variant uppercase tracking-wider font-bold">Dish Detail</th>
                                <th class="p-6 font-label-sm text-on-surface-variant uppercase tracking-wider font-bold">Category</th>
                                <th class="p-6 font-label-sm text-on-surface-variant uppercase tracking-wider font-bold">Base Price</th>
                                <th class="p-6 font-label-sm text-on-surface-variant uppercase tracking-wider font-bold">Discount</th>
                                <th class="p-6 font-label-sm text-on-surface-variant uppercase tracking-wider font-bold">Status</th>
                                <th class="p-6 font-label-sm text-on-surface-variant uppercase tracking-wider text-right font-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <?php if (empty($menuItems)): ?>
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-on-surface-variant">No menu items found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($menuItems as $index => $item): ?>
                                    <tr class="hover:bg-surface-container transition-colors group">
                                        <td class="p-6">
                                            <div class="flex items-center gap-4">
                                                <div class="w-16 h-16 rounded-lg overflow-hidden flex-shrink-0 border border-outline-variant <?php echo !$item['is_available'] ? 'opacity-50 grayscale' : ''; ?>">
                                                    <?php $imgSrc = $item['image_url'] ? asset($item['image_url']) : 'https://placehold.co/100x100?text=No+Image'; ?>
                                                    <img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($imgSrc); ?>">
                                                </div>
                                                <div>
                                                    <p class="font-body-md text-body-md font-bold <?php echo !$item['is_available'] ? 'text-on-surface-variant' : 'text-on-surface'; ?> mb-1"><?php echo htmlspecialchars($item['name']); ?></p>
                                                    <?php if (!$item['is_available']): ?>
                                                        <p class="text-body-sm text-error mb-0 font-bold">Out of Stock</p>
                                                    <?php else: ?>
                                                        <p class="text-body-sm text-on-surface-variant mb-0"><?php echo htmlspecialchars(substr((string)$item['description'], 0, 40)); ?>...</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-6">
                                            <span class="px-3 py-1.5 rounded-full border border-outline-variant text-[12px] uppercase font-bold text-on-surface-variant"><?php echo htmlspecialchars($item['category']); ?></span>
                                        </td>
                                        <td class="p-6 font-label-md text-on-surface font-bold">₹<?php echo number_format($item['price'], 2); ?></td>
                                        <td class="p-6">
                                            <span class="text-label-sm text-on-surface-variant italic">-</span>
                                        </td>
                                        <td class="p-6">
                                            <div class="flex items-center gap-3">
                                                <div class="relative inline-block w-12 h-6 align-middle select-none transition duration-200 ease-in">
                                                    <?php if ($item['is_available']): ?>
                                                        <input checked class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-2 border-primary appearance-none cursor-pointer right-0" id="toggle<?php echo $item['id']; ?>" name="toggle" type="checkbox">
                                                        <label class="toggle-label block overflow-hidden h-6 rounded-full bg-primary-container cursor-pointer" for="toggle<?php echo $item['id']; ?>"></label>
                                                    <?php else: ?>
                                                        <input class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-outline border-2 border-outline-variant appearance-none cursor-pointer left-0" id="toggle<?php echo $item['id']; ?>" name="toggle" type="checkbox">
                                                        <label class="toggle-label block overflow-hidden h-6 rounded-full bg-surface-container-highest cursor-pointer" for="toggle<?php echo $item['id']; ?>"></label>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($item['is_available']): ?>
                                                    <span class="text-label-sm text-primary font-bold">Available</span>
                                                <?php else: ?>
                                                    <span class="text-label-sm text-on-surface-variant font-bold">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="p-6 text-right">
                                            <div class="flex justify-end gap-3 text-on-surface-variant">
                                                <button class="material-symbols-outlined hover:text-primary transition-colors bg-transparent border-0 p-1 cursor-pointer">edit</button>
                                                <button class="material-symbols-outlined hover:text-error transition-colors bg-transparent border-0 p-1 cursor-pointer">delete</button>
                                                <button class="material-symbols-outlined hover:text-on-surface transition-colors bg-transparent border-0 p-1 cursor-pointer">more_vert</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Table Footer / Pagination -->
                <div class="p-6 flex flex-col sm:flex-row justify-between items-center bg-surface-container-low border-t border-outline-variant gap-4">
                    <p class="text-label-sm text-on-surface-variant mb-0 font-bold">Showing <?php echo $totalItems; ?> items</p>
                    <div class="flex gap-2">
                        <button class="w-10 h-10 flex items-center justify-center border border-outline-variant rounded-lg hover:bg-surface-container transition-colors bg-transparent text-on-surface"><span class="material-symbols-outlined text-[20px]">chevron_left</span></button>
                        <button class="w-10 h-10 flex items-center justify-center bg-primary-container text-on-primary-container rounded-lg font-bold border-0">1</button>
                        <button class="w-10 h-10 flex items-center justify-center border border-outline-variant rounded-lg hover:bg-surface-container transition-colors bg-transparent text-on-surface font-bold">2</button>
                        <button class="w-10 h-10 flex items-center justify-center border border-outline-variant rounded-lg hover:bg-surface-container transition-colors bg-transparent text-on-surface font-bold">3</button>
                        <button class="w-10 h-10 flex items-center justify-center border border-outline-variant rounded-lg hover:bg-surface-container transition-colors bg-transparent text-on-surface"><span class="material-symbols-outlined text-[20px]">chevron_right</span></button>
                    </div>
                </div>
            </div>
            <!-- Recent Orders Preview (Asymmetric Layout) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
                <!-- Orders List (Col Span 2) -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="font-headline-md text-on-surface font-bold mb-0">Recent Live Orders</h2>
                        <a class="text-primary font-label-md hover:underline font-bold text-decoration-none" href="<?php echo url('seller/orders_redesigned'); ?>">View all orders</a>
                    </div>
                    <div class="space-y-4">
                        <div onclick="window.location.href='<?php echo url('seller/orders_redesigned'); ?>'" class="bg-surface-container-lowest border border-outline-variant p-6 rounded-xl flex justify-between items-center bento-card cursor-pointer mb-4">
                            <div class="flex gap-4 items-center">
                                <div class="p-3 bg-primary-container/10 rounded-full text-primary flex items-center justify-center">
                                    <span class="material-symbols-outlined">receipt_long</span>
                                </div>
                                <div>
                                    <p class="font-bold text-on-surface mb-1">Order #8821 — Table 04</p>
                                    <p class="text-body-sm text-on-surface-variant mb-0">2x Wagyu Fillet, 1x Truffle Fries</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-label-md text-primary font-bold mb-2">₹296.50</p>
                                <span class="text-[12px] px-3 py-1 bg-on-tertiary-container/30 text-tertiary rounded-full uppercase font-bold">Preparing</span>
                            </div>
                        </div>
                        <div onclick="window.location.href='<?php echo url('seller/orders_redesigned'); ?>'" class="bg-surface-container-lowest border border-outline-variant p-6 rounded-xl flex justify-between items-center bento-card cursor-pointer">
                            <div class="flex gap-4 items-center">
                                <div class="p-3 bg-primary-container/10 rounded-full text-primary flex items-center justify-center">
                                    <span class="material-symbols-outlined">local_shipping</span>
                                </div>
                                <div>
                                    <p class="font-bold text-on-surface mb-1">Order #8820 — Pickup</p>
                                    <p class="text-body-sm text-on-surface-variant mb-0">1x Black Lobster Ravioli</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-label-md text-primary font-bold mb-2">₹48.00</p>
                                <span class="text-[12px] px-3 py-1 bg-surface-container-highest text-on-surface-variant rounded-full uppercase font-bold">Ready</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Discount / Promo Creator Sidebar -->
                <div class="bg-surface-container-low border border-outline-variant rounded-xl p-6 h-fit">
                    <form action="<?php echo url('seller/orders_kanban'); ?>" method="POST">
                        <input type="hidden" name="action" value="create_promo">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined text-primary">bolt</span>
                            <h2 class="font-headline-md text-on-surface text-[22px] font-bold mb-0">Flash Discount</h2>
                        </div>
                        <p class="text-body-sm text-on-surface-variant mb-6">Create an instant site-wide or category-specific discount to boost evening sales.</p>
                        <div class="space-y-6">
                            <div class="mb-4">
                                <label class="font-label-sm text-on-surface-variant uppercase block mb-2 font-bold tracking-widest">Percentage (%)</label>
                                <input name="discount_percentage" class="w-full bg-background border border-outline-variant rounded-lg p-3 text-on-surface focus:border-primary outline-none font-bold" type="number" value="10" min="1" max="100" required>
                            </div>
                            <div class="mb-4">
                                <label class="font-label-sm text-on-surface-variant uppercase block mb-2 font-bold tracking-widest">Target Category</label>
                                <select name="target_category" class="w-full bg-background border border-outline-variant rounded-lg p-3 text-on-surface focus:border-primary outline-none font-bold">
                                    <option value="">All Items (Site-wide)</option>
                                    <option value="Main Course">Main Course</option>
                                    <option value="Desserts">Desserts</option>
                                </select>
                            </div>
                            <div class="mb-6">
                                <label class="font-label-sm text-on-surface-variant uppercase block mb-2 font-bold tracking-widest">Duration (Hours)</label>
                                <div class="flex gap-3">
                                    <label class="flex-1">
                                        <input type="radio" name="duration" value="2" class="peer hidden" checked>
                                        <div class="py-2 text-center border border-outline-variant text-on-surface-variant peer-checked:border-primary peer-checked:text-primary rounded-lg text-sm font-bold bg-transparent cursor-pointer transition-all">2h</div>
                                    </label>
                                    <label class="flex-1">
                                        <input type="radio" name="duration" value="4" class="peer hidden">
                                        <div class="py-2 text-center border border-outline-variant text-on-surface-variant peer-checked:border-primary peer-checked:text-primary rounded-lg text-sm font-bold bg-transparent cursor-pointer transition-all">4h</div>
                                    </label>
                                    <label class="flex-1">
                                        <input type="radio" name="duration" value="8" class="peer hidden">
                                        <div class="py-2 text-center border border-outline-variant text-on-surface-variant peer-checked:border-primary peer-checked:text-primary rounded-lg text-sm font-bold bg-transparent cursor-pointer transition-all">8h</div>
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="w-full py-3 bg-primary text-on-primary font-bold rounded-lg hover:opacity-90 transition-opacity border-0 font-bold text-lg cursor-pointer">Activate Promo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<!-- Footer -->
<footer class="bg-background text-on-surface w-full bottom-0 border-t border-outline-variant flat no shadows relative z-50 shrink-0">
    <div class="flex flex-col md:flex-row justify-between items-center px-8 py-6 w-full max-w-container-max-width mx-auto">
        <div class="mb-4 md:mb-0 text-center md:text-left">
            <span class="font-headline-sm text-headline-sm text-primary font-bold">TestShare Enterprise</span>
            <p class="text-body-sm text-on-surface-variant mt-1 mb-0">© 2024 TestShare Enterprise. All rights reserved.</p>
        </div>
        <div class="flex flex-wrap justify-center gap-8">
            <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Privacy Policy</a>
            <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Terms of Service</a>
            <a class="font-body-sm text-body-sm text-primary underline text-decoration-none font-bold" href="#">API Docs</a>
            <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Contact Support</a>
        </div>
    </div>
</footer>
<?php view('partials/seller_footer'); ?>
<script>
    // Micro-interactions for toggles
    document.querySelectorAll('.toggle-checkbox').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const label = this.nextElementSibling;
            const textSpan = this.parentElement.nextElementSibling;
            
            if(this.checked) {
                this.classList.replace('left-0', 'right-0');
                this.classList.replace('bg-outline', 'bg-white');
                this.classList.replace('border-outline-variant', 'border-primary');
                label.classList.replace('bg-surface-container-highest', 'bg-primary-container');
                textSpan.textContent = 'Available';
                textSpan.classList.replace('text-on-surface-variant', 'text-primary');
            } else {
                this.classList.replace('right-0', 'left-0');
                this.classList.replace('bg-white', 'bg-outline');
                this.classList.replace('border-primary', 'border-outline-variant');
                label.classList.replace('bg-primary-container', 'bg-surface-container-highest');
                textSpan.textContent = 'Inactive';
                textSpan.classList.replace('text-primary', 'text-on-surface-variant');
            }
        });
    });
</script>
</body>
</html>
