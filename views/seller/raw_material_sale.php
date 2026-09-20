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

// Feature gate check: Ultra Premium required
if (!hasFeature($storeId, 'raw_materials')) {
    $pageTitle = 'Raw Material Sales — Ultra Premium Feature';
    $activeNav = 'raw_material_sale';
    view('partials/seller_header', get_defined_vars());
    view('partials/seller_sidebar', get_defined_vars());
    ?>
    <div class="flex-grow p-8 overflow-y-auto">
        <div class="max-w-4xl mx-auto py-12 text-center bg-surface-container border border-outline-variant rounded-2xl shadow-xl p-8 space-y-6">
            <span class="material-symbols-outlined text-[64px] text-amber-400">inventory</span>
            <h2 class="font-headline-lg text-on-surface font-bold">Raw Materials Marketplace</h2>
            <p class="text-body-lg text-on-surface-variant max-w-lg mx-auto">
                Listing surplus ingredients and raw materials for sale to other merchants is exclusively available to <strong class="text-amber-400">Ultra Premium VIP</strong> subscribers.
            </p>
            <a href="<?php echo url('seller/subscription'); ?>" class="inline-block px-8 py-3 bg-amber-500 text-black font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 transition-all text-decoration-none shadow-lg">
                Upgrade to Ultra Premium (₹149/mo)
            </a>
        </div>
    </div>
    <?php
    view('partials/seller_footer');
    exit;
}

// Handle Add Item (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = htmlspecialchars(trim($_POST['item_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $quantity = htmlspecialchars(trim($_POST['quantity'] ?? ''), ENT_QUOTES, 'UTF-8');
    $price = floatval($_POST['price']);
    $desc = htmlspecialchars(trim($_POST['desc'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    if ($storeId && !empty($name) && !empty($quantity)) {
        $ins = $db->prepare("INSERT INTO raw_material_sales (store_id, name, quantity, price, description) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$storeId, $name, $quantity, $price, $desc]);
        header("Location: " . url('seller/raw_material_sale'));
        exit;
    }
}

// Handle Delete Item (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delId = intval($_GET['id']);
    if ($storeId) {
        $del = $db->prepare("DELETE FROM raw_material_sales WHERE id = ? AND store_id = ?");
        $del->execute([$delId, $storeId]);
        header("Location: " . url('seller/raw_material_sale'));
        exit;
    }
}

// Handle Update Sale Status (GET)
if (isset($_GET['action']) && $_GET['action'] === 'update_sale' && isset($_GET['id']) && isset($_GET['status'])) {
    $orderId = intval($_GET['id']);
    $status = $_GET['status'];
    if ($storeId && in_array($status, ['Accepted', 'Out For Delivery', 'Delivered', 'Cancelled'])) {
        $up = $db->prepare("UPDATE store_material_orders SET order_status = ? WHERE id = ? AND seller_store_id = ?");
        $up->execute([$status, $orderId, $storeId]);
        header("Location: " . url('seller/raw_material_sale&tab=sales'));
        exit;
    }
}

// Handle Confirm Purchase (GET)
if (isset($_GET['action']) && $_GET['action'] === 'confirm_purchase' && isset($_GET['id'])) {
    $orderId = intval($_GET['id']);
    if ($storeId) {
        $up = $db->prepare("UPDATE store_material_orders SET order_status = 'Completed' WHERE id = ? AND buyer_store_id = ? AND order_status = 'Delivered'");
        $up->execute([$orderId, $storeId]);
        header("Location: " . url('seller/raw_material_sale&tab=purchases'));
        exit;
    }
}

// Fetch Listings
$listings = [];
$salesOrders = [];
$purchaseOrders = [];

if ($storeId) {
    // Listings
    $stmt = $db->prepare("SELECT * FROM raw_material_sales WHERE store_id = ? ORDER BY created_at DESC");
    $stmt->execute([$storeId]);
    $listings = $stmt->fetchAll();
    
    // Sales Orders
    $soStmt = $db->prepare("SELECT sm.*, s.store_name as buyer_name FROM store_material_orders sm JOIN stores s ON sm.buyer_store_id = s.id WHERE sm.seller_store_id = ? ORDER BY sm.created_at DESC");
    $soStmt->execute([$storeId]);
    $salesOrders = $soStmt->fetchAll();
    
    // Purchase Orders
    $poStmt = $db->prepare("SELECT sm.*, s.store_name as seller_name FROM store_material_orders sm JOIN stores s ON sm.seller_store_id = s.id WHERE sm.buyer_store_id = ? ORDER BY sm.created_at DESC");
    $poStmt->execute([$storeId]);
    $purchaseOrders = $poStmt->fetchAll();
}

$activeTab = $_GET['tab'] ?? 'listings';

$pageTitle = 'Raw Material Hub';
$activeNav = 'raw_material_sale';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto w-full flex flex-col justify-between" style="min-height: 80vh;">
        <div class="space-y-8">
            <!-- Header Section -->
            <div class="border-b border-outline-variant pb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Raw Material Hub</h2>
                    <p class="text-body-md text-on-surface-variant mb-0">Manage your raw material listings, outgoing sales, and incoming stock purchases.</p>
                </div>
            </div>

            <!-- Custom Tabs -->
            <div class="flex gap-4 border-b border-outline-variant">
                <button onclick="switchTab('listings')" id="tabBtn-listings" class="px-6 py-3 font-bold text-sm tracking-wider uppercase bg-transparent border-0 border-b-2 <?php echo $activeTab === 'listings' ? 'text-primary border-primary' : 'text-on-surface-variant border-transparent hover:text-on-surface'; ?> transition-all cursor-pointer">
                    My Listings
                </button>
                <button onclick="switchTab('sales')" id="tabBtn-sales" class="px-6 py-3 font-bold text-sm tracking-wider uppercase bg-transparent border-0 border-b-2 <?php echo $activeTab === 'sales' ? 'text-primary border-primary' : 'text-on-surface-variant border-transparent hover:text-on-surface'; ?> transition-all cursor-pointer">
                    My Sales Orders
                </button>
                <button onclick="switchTab('purchases')" id="tabBtn-purchases" class="px-6 py-3 font-bold text-sm tracking-wider uppercase bg-transparent border-0 border-b-2 <?php echo $activeTab === 'purchases' ? 'text-primary border-primary' : 'text-on-surface-variant border-transparent hover:text-on-surface'; ?> transition-all cursor-pointer">
                    My Purchases
                </button>
            </div>

            <!-- TAB 1: My Listings -->
            <div id="tab-listings" class="space-y-6" style="display: <?php echo $activeTab === 'listings' ? 'block' : 'none'; ?>">
                <div class="flex justify-end">
                    <button onclick="document.getElementById('list-form').style.display='block'; this.style.display='none';" class="px-6 py-3 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 active:scale-95 transition-all border-0 shadow-lg font-bold cursor-pointer">
                        Add New Listing
                    </button>
                </div>
                <!-- Add Listing Form (Initially hidden) -->
                <div id="list-form" style="display: none;" class="bg-surface-container border border-outline-variant p-6 rounded-2xl shadow-xl space-y-4">
                    <h3 class="font-bold text-lg text-on-surface mb-2">Create Raw Material Sale Listing</h3>
                    <form action="<?php echo url('seller/raw_material_sale'); ?>" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <label for="item_name" class="block text-xs font-bold text-on-surface-variant uppercase">Material Name</label>
                                <input type="text" name="item_name" id="item_name" required placeholder="e.g., Dry Aged Ribeye Trimmings" class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                            </div>
                            <div class="space-y-2">
                                <label for="quantity" class="block text-xs font-bold text-on-surface-variant uppercase">Quantity / Weight</label>
                                <input type="text" name="quantity" id="quantity" required placeholder="e.g., 15 kg / 20 blocks" class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                            </div>
                            <div class="space-y-2">
                                <label for="price" class="block text-xs font-bold text-on-surface-variant uppercase">Sale Price ($)</label>
                                <input type="number" step="0.01" name="price" id="price" required placeholder="120.00" class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-2.5 rounded-lg text-sm w-full outline-none">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label for="desc" class="block text-xs font-bold text-on-surface-variant uppercase">Details / Description</label>
                            <textarea name="desc" id="desc" placeholder="Specify grade, expiration date, freshness status, packaging type, etc..." class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-2.5 rounded-lg text-sm w-full outline-none"></textarea>
                        </div>
                        <div class="flex gap-3 justify-end">
                            <button type="submit" class="px-6 py-2.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 cursor-pointer">List for Sale</button>
                            <button type="button" onclick="document.getElementById('list-form').style.display='none';" class="px-6 py-2.5 border border-outline-variant text-on-surface font-bold text-xs uppercase tracking-wider rounded-lg bg-transparent cursor-pointer">Cancel</button>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if (empty($listings)): ?>
                        <p class="text-on-surface-variant font-body-md col-span-2">No active raw material listings found.</p>
                    <?php else: ?>
                        <?php foreach ($listings as $item): ?>
                            <div class="bg-surface-container border border-outline-variant p-6 rounded-2xl shadow-md flex justify-between items-center">
                                <div>
                                    <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-0.5 rounded border border-primary/20 uppercase tracking-widest">Active Listing</span>
                                    <h4 class="font-bold text-lg text-on-surface mt-2 mb-1"><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p class="text-xs text-on-surface-variant mb-2">Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                    <p class="text-xs text-on-surface-variant mb-0">Listed on: <?php echo date('M j, Y', strtotime($item['created_at'])); ?></p>
                                </div>
                                <div class="text-right space-y-3">
                                    <span class="text-2xl font-bold text-primary block">₹<?php echo number_format($item['price'], 2); ?></span>
                                    <button onclick="removeListing(<?php echo $item['id']; ?>)" class="px-3 py-1.5 border border-error text-error hover:bg-error/10 font-bold text-xs uppercase tracking-wider rounded-lg transition-all bg-transparent cursor-pointer">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 2: My Sales Orders -->
            <div id="tab-sales" class="space-y-6" style="display: <?php echo $activeTab === 'sales' ? 'block' : 'none'; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if (empty($salesOrders)): ?>
                        <p class="text-on-surface-variant font-body-md col-span-2">No incoming B2B sales orders found.</p>
                    <?php else: ?>
                        <?php foreach ($salesOrders as $ord): ?>
                            <div class="bg-surface-container border border-outline-variant p-6 rounded-2xl shadow-md">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h4 class="font-bold text-lg text-on-surface mb-1">Order #B2B-<?php echo $ord['id']; ?></h4>
                                        <p class="text-xs text-on-surface-variant mb-0">Buyer: <span class="font-bold"><?php echo htmlspecialchars($ord['buyer_name']); ?></span></p>
                                    </div>
                                    <span class="bg-surface-container-highest text-on-surface text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider"><?php echo $ord['order_status']; ?></span>
                                </div>
                                <div class="mb-4">
                                    <p class="text-body-md font-bold text-on-surface mb-1"><?php echo htmlspecialchars($ord['material_name']); ?></p>
                                    <p class="text-sm text-on-surface-variant mb-0">Quantity: <?php echo htmlspecialchars($ord['quantity']); ?> | Price: ₹<?php echo number_format($ord['price'], 2); ?></p>
                                </div>
                                <div class="pt-4 border-t border-outline-variant flex justify-end gap-2">
                                    <?php if ($ord['order_status'] === 'Pending'): ?>
                                        <button onclick="window.location.href='<?php echo url('seller/raw_material_sale&action=update_sale&status=Accepted&id='.$ord['id']); ?>'" class="px-4 py-2 bg-primary-container text-on-primary-container font-bold text-xs uppercase tracking-wider rounded-lg border-0 cursor-pointer">Accept</button>
                                    <?php elseif ($ord['order_status'] === 'Accepted'): ?>
                                        <button onclick="window.location.href='<?php echo url('seller/raw_material_sale&action=update_sale&status=Out For Delivery&id='.$ord['id']); ?>'" class="px-4 py-2 bg-tertiary text-on-tertiary font-bold text-xs uppercase tracking-wider rounded-lg border-0 cursor-pointer">Dispatch</button>
                                    <?php elseif ($ord['order_status'] === 'Out For Delivery'): ?>
                                        <button onclick="window.location.href='<?php echo url('seller/raw_material_sale&action=update_sale&status=Delivered&id='.$ord['id']); ?>'" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer">Mark Delivered</button>
                                    <?php elseif ($ord['order_status'] === 'Delivered'): ?>
                                        <span class="text-xs font-bold text-on-surface-variant">Waiting for Buyer Confirmation</span>
                                    <?php elseif ($ord['order_status'] === 'Completed'): ?>
                                        <span class="text-xs font-bold text-green-400">Order Completed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 3: My Purchases -->
            <div id="tab-purchases" class="space-y-6" style="display: <?php echo $activeTab === 'purchases' ? 'block' : 'none'; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if (empty($purchaseOrders)): ?>
                        <p class="text-on-surface-variant font-body-md col-span-2">No B2B purchases found.</p>
                    <?php else: ?>
                        <?php foreach ($purchaseOrders as $ord): ?>
                            <div class="bg-surface-container border border-outline-variant p-6 rounded-2xl shadow-md">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h4 class="font-bold text-lg text-on-surface mb-1">Order #B2B-<?php echo $ord['id']; ?></h4>
                                        <p class="text-xs text-on-surface-variant mb-0">Supplier: <span class="font-bold"><?php echo htmlspecialchars($ord['seller_name']); ?></span></p>
                                    </div>
                                    <span class="bg-surface-container-highest text-on-surface text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider"><?php echo $ord['order_status']; ?></span>
                                </div>
                                <div class="mb-4">
                                    <p class="text-body-md font-bold text-on-surface mb-1"><?php echo htmlspecialchars($ord['material_name']); ?></p>
                                    <p class="text-sm text-on-surface-variant mb-0">Quantity: <?php echo htmlspecialchars($ord['quantity']); ?> | Price: ₹<?php echo number_format($ord['price'], 2); ?></p>
                                </div>
                                <div class="pt-4 border-t border-outline-variant flex justify-end gap-2">
                                    <?php if ($ord['order_status'] === 'Delivered'): ?>
                                        <button onclick="window.location.href='<?php echo url('seller/raw_material_sale&action=confirm_purchase&id='.$ord['id']); ?>'" class="px-4 py-2 bg-green-600 text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer">Confirm Receipt</button>
                                    <?php elseif ($ord['order_status'] === 'Completed'): ?>
                                        <span class="text-xs font-bold text-green-400">Order Completed</span>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-on-surface-variant">In Progress...</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

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
    function removeListing(id) {
        if (confirm('Are you sure you want to remove this raw material listing?')) {
            window.location.href = '<?php echo url("seller/raw_material_sale&action=delete&id="); ?>' + id;
        }
    }

    function switchTab(tabId) {
        // Hide all tabs
        document.getElementById('tab-listings').style.display = 'none';
        document.getElementById('tab-sales').style.display = 'none';
        document.getElementById('tab-purchases').style.display = 'none';

        // Reset button styles
        const btns = ['tabBtn-listings', 'tabBtn-sales', 'tabBtn-purchases'];
        btns.forEach(id => {
            const el = document.getElementById(id);
            el.classList.remove('text-primary', 'border-primary');
            el.classList.add('text-on-surface-variant', 'border-transparent');
        });

        // Show target tab and active button
        document.getElementById('tab-' + tabId).style.display = 'block';
        const activeBtn = document.getElementById('tabBtn-' + tabId);
        activeBtn.classList.remove('text-on-surface-variant', 'border-transparent');
        activeBtn.classList.add('text-primary', 'border-primary');
    }
</script>

<?php view('partials/seller_footer'); ?>
