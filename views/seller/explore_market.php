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
$userId = $_SESSION['user_id'] ?? null;

// Get current store
$myStore = null;
if ($userId) {
    $stmt = $db->prepare("SELECT * FROM stores WHERE owner_id = ?");
    $stmt->execute([$userId]);
    $myStore = $stmt->fetch();
}

$storeId = $myStore['id'] ?? 0;

// Feature gate check: Ultra Premium required
if (!hasFeature($storeId, 'raw_materials')) {
    $pageTitle = 'Explore Market — Ultra Premium Feature';
    $activeNav = 'explore_market';
    view('partials/seller_header', get_defined_vars());
    view('partials/seller_sidebar', get_defined_vars());
    ?>
    <div class="flex-grow p-8 overflow-y-auto">
        <div class="max-w-4xl mx-auto py-12 text-center bg-surface-container border border-outline-variant rounded-2xl shadow-xl p-8 space-y-6">
            <span class="material-symbols-outlined text-[64px] text-amber-400">storefront</span>
            <h2 class="font-headline-lg text-on-surface font-bold">B2B Raw Material Market</h2>
            <p class="text-body-lg text-on-surface-variant max-w-lg mx-auto">
                Browsing and buying wholesale raw ingredients from other merchants is exclusively available to <strong class="text-amber-400">Ultra Premium VIP</strong> subscribers.
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

// Handle Buy POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buy') {
    $saleId = intval($_POST['sale_id']);
    
    // Get the listing
    $saleStmt = $db->prepare("SELECT * FROM raw_material_sales WHERE id = ?");
    $saleStmt->execute([$saleId]);
    $sale = $saleStmt->fetch();
    
    if ($sale && $sale['store_id'] !== $storeId) {
        // Create order
        $orderStmt = $db->prepare("INSERT INTO store_material_orders (buyer_store_id, seller_store_id, material_name, price, quantity, order_status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $orderStmt->execute([
            $storeId,
            $sale['store_id'],
            $sale['name'],
            $sale['price'],
            $sale['quantity']
        ]);
        
        // Soft update status to Sold Out instead of hard deleting listing
        $upStmt = $db->prepare("UPDATE raw_material_sales SET status = 'Sold Out' WHERE id = ?");
        $upStmt->execute([$saleId]);
        
        header("Location: " . url('seller/explore_market'));
        exit;
    }
}

// Fetch active listings
$salesStmt = $db->prepare("
    SELECT r.*, s.store_name 
    FROM raw_material_sales r 
    JOIN stores s ON r.store_id = s.id 
    WHERE r.store_id != ? AND (r.status IS NULL OR r.status = 'Available')
    ORDER BY r.created_at DESC
");
$salesStmt->execute([$storeId]);
$sales = $salesStmt->fetchAll();

$pageTitle = 'Explore Market';
$activeNav = 'explore_market';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto w-full flex flex-col justify-between" style="min-height: 80vh;">
        <div class="space-y-8">
            <!-- Header Section -->
            <div class="border-b border-outline-variant pb-4">
                <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Explore Raw Materials Market</h2>
                <p class="text-body-md text-on-surface-variant mb-0">Browse and buy high-quality raw ingredients, bulk supplies, or surplus stocks listed by other sellers.</p>
            </div>

            <!-- Market Search/Filter Bar -->
            <div class="flex gap-4">
                <div class="relative flex-grow">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant material-symbols-outlined text-sm">search</span>
                    <input type="text" placeholder="Search raw food materials..." class="bg-surface-container border border-outline-variant focus:border-primary focus:ring-0 text-on-surface pl-10 pr-4 py-2.5 rounded-lg text-sm w-full outline-none">
                </div>
                <button class="px-6 py-2.5 bg-surface-container border border-outline-variant hover:bg-surface-container-high text-on-surface font-bold text-xs uppercase tracking-wider rounded-xl transition-all bg-transparent">
                    Filters
                </button>
            </div>

            <!-- Market Listings Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php if (empty($sales)): ?>
                    <div class="col-span-3 text-center py-12 bg-surface-container border border-outline-variant rounded-xl text-on-surface-variant">
                        No raw materials available in the market right now.
                    </div>
                <?php else: ?>
                    <?php foreach ($sales as $sale): ?>
                        <div class="bg-surface-container border border-outline-variant p-6 rounded-2xl shadow-md flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start">
                                    <span class="bg-primary/20 text-primary text-[10px] font-bold px-2 py-0.5 rounded border border-primary/20 uppercase tracking-widest">In Stock</span>
                                    <span class="text-xs text-on-surface-variant">Seller: <?php echo htmlspecialchars($sale['store_name']); ?></span>
                                </div>
                                <h4 class="font-bold text-lg text-on-surface mt-3 mb-1"><?php echo htmlspecialchars($sale['name']); ?></h4>
                                <p class="text-xs text-on-surface-variant mb-3">Quantity Available: <?php echo htmlspecialchars($sale['quantity']); ?></p>
                                <p class="text-xs text-on-surface-variant mb-0"><?php echo htmlspecialchars($sale['description']); ?></p>
                            </div>
                            <div class="mt-6 border-t border-outline-variant pt-4 flex justify-between items-center">
                                <span class="text-xl font-bold text-primary">₹<?php echo number_format($sale['price'], 2); ?></span>
                                <form method="POST" style="margin:0;" onsubmit="event.preventDefault(); openMarketPayment('<?php echo htmlspecialchars($sale['name']); ?>', <?php echo floatval($sale['price']); ?>, this);">
                                    <input type="hidden" name="action" value="buy">
                                    <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                                    <button type="submit" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 active:scale-95 transition-all border-0 shadow-md cursor-pointer">
                                        Buy Stock
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

<?php view('partials/seller_footer'); ?>

<!-- Simulated Payment Modal -->
<div id="market-payment-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px);" class="flex items-center justify-center">
    <div style="background:#1c1c1e; border:1px solid #2d2d2d; border-radius:16px; width:100%; max-width:400px; margin:20px; overflow:hidden;">
        <div style="background:#ff9f0d; padding:20px 24px;" class="flex items-center gap-3">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
            <div>
                <div style="color:white; font-weight:800; font-size:16px;">TestShare Merchant Pay</div>
                <div style="color:rgba(255,255,255,0.8); font-size:12px;">Raw Material Purchase · Test Mode</div>
            </div>
        </div>
        <div style="padding:20px 24px; border-bottom:1px solid #2d2d2d;">
            <div style="color:#888; font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">Amount to Pay</div>
            <div style="color:#ff9f0d; font-size:28px; font-weight:800;" id="market-modal-amount">₹0</div>
            <div style="color:#aaa; font-size:12px; margin-top:2px;" id="market-modal-label"></div>
        </div>
        <div style="padding:20px 24px;" id="market-methods-panel">
            <div style="color:#aaa; font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Select Payment Method</div>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <label style="display:flex; align-items:center; gap:12px; padding:14px 16px; border:1px solid #2d2d2d; border-radius:10px; cursor:pointer;">
                    <input type="radio" name="market_pay_method" value="upi" checked style="accent-color:#ff9f0d;">
                    <span class="material-symbols-outlined" style="color:#ff9f0d;">smartphone</span>
                    <div>
                        <div style="color:#e5e2e1; font-weight:700; font-size:14px;">UPI</div>
                        <div style="color:#888; font-size:12px;">PhonePe, GPay, Paytm, BHIM</div>
                    </div>
                </label>
                <label style="display:flex; align-items:center; gap:12px; padding:14px 16px; border:1px solid #2d2d2d; border-radius:10px; cursor:pointer;">
                    <input type="radio" name="market_pay_method" value="card" style="accent-color:#ff9f0d;">
                    <span class="material-symbols-outlined" style="color:#ff9f0d;">credit_card</span>
                    <div>
                        <div style="color:#e5e2e1; font-weight:700; font-size:14px;">Credit / Debit Card</div>
                        <div style="color:#888; font-size:12px;">Visa, Mastercard, RuPay</div>
                    </div>
                </label>
                <label style="display:flex; align-items:center; gap:12px; padding:14px 16px; border:1px solid #2d2d2d; border-radius:10px; cursor:pointer;">
                    <input type="radio" name="market_pay_method" value="netbanking" style="accent-color:#ff9f0d;">
                    <span class="material-symbols-outlined" style="color:#ff9f0d;">account_balance</span>
                    <div>
                        <div style="color:#e5e2e1; font-weight:700; font-size:14px;">Net Banking</div>
                        <div style="color:#888; font-size:12px;">SBI, HDFC, ICICI, Axis & more</div>
                    </div>
                </label>
            </div>
            <div style="display:flex; gap:12px; margin-top:20px;">
                <button onclick="closeMarketModal()" style="flex:1; padding:12px; background:transparent; border:1px solid #2d2d2d; color:#aaa; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px;">Cancel</button>
                <button onclick="simulateMarketPayment()" style="flex:2; padding:12px; background:#ff9f0d; border:0; color:#000; border-radius:10px; font-weight:800; cursor:pointer; font-size:14px;">Confirm Purchase</button>
            </div>
        </div>
        <div style="padding:40px 24px; text-align:center; display:none;" id="market-processing-panel">
            <div style="width:56px; height:56px; border:4px solid #2d2d2d; border-top-color:#ff9f0d; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto 16px;"></div>
            <div style="color:#e5e2e1; font-weight:700; font-size:16px; margin-bottom:6px;">Processing Payment...</div>
            <div style="color:#888; font-size:13px;">Please do not close this window</div>
        </div>
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
var _marketForm = null;

function openMarketPayment(itemName, priceINR, formElement) {
    _marketForm = formElement;
    var numAmount = parseFloat(priceINR) || 0;
    var formattedINR = numAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('market-modal-amount').textContent = '₹' + formattedINR;
    document.getElementById('market-modal-label').textContent = 'Purchasing: ' + itemName;
    document.getElementById('market-methods-panel').style.display = 'block';
    document.getElementById('market-processing-panel').style.display = 'none';
    document.getElementById('market-payment-modal').style.display = 'flex';
}

function closeMarketModal() {
    document.getElementById('market-payment-modal').style.display = 'none';
    _marketForm = null;
}

function simulateMarketPayment() {
    document.getElementById('market-methods-panel').style.display = 'none';
    document.getElementById('market-processing-panel').style.display = 'block';
    setTimeout(function() {
        var method = document.querySelector('input[name="market_pay_method"]:checked').value;
        var mockPaymentId = 'pay_test_' + method.toUpperCase() + '_' + Date.now();
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'razorpay_payment_id';
        input.value = mockPaymentId;
        _marketForm.appendChild(input);
        _marketForm.submit();
    }, 1800);
}

document.getElementById('market-payment-modal').addEventListener('click', function(e) {
    if (e.target === this) closeMarketModal();
});
</script>
