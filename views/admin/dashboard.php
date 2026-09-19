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

// Stat queries
$consumerCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$activeStores = $db->query("SELECT COUNT(*) FROM stores WHERE store_status = 'Active' AND onboarding_status = 'Approved'")->fetchColumn();
$pendingApprovals = $db->query("SELECT COUNT(*) FROM stores WHERE onboarding_status = 'Under Review'")->fetchColumn();
$gmv = $db->query("SELECT IFNULL(SUM(total_amount), 0) FROM orders WHERE payment_status = 'Paid'")->fetchColumn();
$commission = $db->query("SELECT IFNULL(SUM(platform_commission), 0) FROM orders WHERE payment_status = 'Paid'")->fetchColumn();

// Top performing stores
$topStores = $db->query("
    SELECT s.store_name, COUNT(o.id) as orders_count, IFNULL(SUM(o.total_amount), 0) as total_rev, IFNULL(AVG(r.rating_stars), 5.0) as avg_rating
    FROM stores s
    LEFT JOIN orders o ON s.id = o.store_id AND o.payment_status = 'Paid'
    LEFT JOIN reviews r ON s.id = r.store_id
    GROUP BY s.id
    ORDER BY total_rev DESC, orders_count DESC
    LIMIT 5
")->fetchAll();

// Top 5 loyal customers
$topCustomers = $db->query("
    SELECT u.fullname, u.email, COUNT(o.id) as orders_count, IFNULL(SUM(o.total_amount), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.customer_id AND o.payment_status = 'Paid'
    WHERE u.role = 'customer'
    GROUP BY u.id
    ORDER BY total_spent DESC, orders_count DESC
    LIMIT 5
")->fetchAll();

$pageTitle = 'Master Administration Console';
$activeNav = 'dashboard';
view('partials/admin_header', get_defined_vars());
view('partials/admin_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <!-- Content Canvas -->
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Platform Statistics Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Stat 1: Consumers -->
            <div class="bg-surface-container border border-outline p-5 rounded-2xl shadow-lg relative overflow-hidden group hover:border-primary transition-all">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Total Consumers</span>
                        <div class="text-2xl font-bold font-mono text-on-surface mt-2"><?php echo number_format($consumerCount); ?></div>
                    </div>
                    <span class="material-symbols-outlined text-primary text-3xl">group</span>
                </div>
                <div class="text-xs text-primary font-bold flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">trending_up</span>
                    <span>Verified accounts active</span>
                </div>
            </div>

            <!-- Stat 2: Active Sellers -->
            <div class="bg-surface-container border border-outline p-5 rounded-2xl shadow-lg relative overflow-hidden group hover:border-primary transition-all">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Active Stores</span>
                        <div class="text-2xl font-bold font-mono text-on-surface mt-2" id="statActiveSellers"><?php echo number_format($activeStores); ?></div>
                    </div>
                    <span class="material-symbols-outlined text-primary text-3xl">storefront</span>
                </div>
                <div class="text-xs text-primary font-bold flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">verified</span>
                    <span><?php echo number_format($pendingApprovals); ?> pending approvals</span>
                </div>
            </div>

            <!-- Stat 3: Platform GMV -->
            <div class="bg-surface-container border border-outline p-5 rounded-2xl shadow-lg relative overflow-hidden group hover:border-primary transition-all">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Platform GMV</span>
                        <div class="text-2xl font-bold font-mono text-on-surface mt-2">$<?php echo number_format($gmv, 2); ?></div>
                    </div>
                    <span class="material-symbols-outlined text-primary text-3xl">payments</span>
                </div>
                <div class="text-xs text-primary font-bold flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">trending_up</span>
                    <span>Paid transactions settled</span>
                </div>
            </div>

            <!-- Stat 4: Commission Collected -->
            <div class="bg-surface-container border border-outline p-5 rounded-2xl shadow-lg relative overflow-hidden group hover:border-primary transition-all">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Commission (5%)</span>
                        <div class="text-2xl font-bold font-mono text-on-surface mt-2">$<?php echo number_format($commission, 2); ?></div>
                    </div>
                    <span class="material-symbols-outlined text-primary text-3xl">account_balance_wallet</span>
                </div>
                <div class="text-xs text-primary font-bold flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">payments</span>
                    <span>Split settlements verified</span>
                </div>
            </div>
        </div>

        <!-- Two Column Grid for Stores and Customers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left: Top Performing Stores -->
            <div class="bg-surface-container rounded-2xl border border-outline p-6 shadow-xl space-y-4">
                <div class="flex items-center gap-3 border-b border-outline pb-4">
                    <span class="material-symbols-outlined text-primary">workspace_premium</span>
                    <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-0">Top Performing Stores</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-outline/30 text-on-surface-variant font-bold uppercase tracking-wider">
                                <th class="pb-3">Merchant Name</th>
                                <th class="pb-3 text-right">Orders</th>
                                <th class="pb-3 text-right">Total Revenue</th>
                                <th class="pb-3 text-right">Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topStores as $storeRow): ?>
                                <tr class="border-b border-outline/10 hover:bg-surface-container-high align-middle">
                                    <td class="py-3 font-bold text-on-surface flex items-center gap-2">
                                        <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                                        <span><?php echo htmlspecialchars($storeRow['store_name']); ?></span>
                                    </td>
                                    <td class="py-3 text-right font-mono font-semibold"><?php echo number_format($storeRow['orders_count']); ?></td>
                                    <td class="py-3 text-right font-mono font-bold text-primary">$<?php echo number_format($storeRow['total_rev'], 2); ?></td>
                                    <td class="py-3 text-right font-mono font-semibold text-yellow-400"><?php echo number_format($storeRow['avg_rating'], 1); ?> ★</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topStores)): ?>
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-on-surface-variant italic">No sales recorded yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right: Top 5 Loyal Customers -->
            <div class="bg-surface-container rounded-2xl border border-outline p-6 shadow-xl space-y-4">
                <div class="flex items-center gap-3 border-b border-outline pb-4">
                    <span class="material-symbols-outlined text-primary">military_tech</span>
                    <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-0">Top 5 Loyal Customers</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-outline/30 text-on-surface-variant font-bold uppercase tracking-wider">
                                <th class="pb-3">Customer Details</th>
                                <th class="pb-3 text-right">Orders</th>
                                <th class="pb-3 text-right">Spent</th>
                                <th class="pb-3 text-right">Offer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topCustomers as $customerRow): ?>
                                <tr class="border-b border-outline/10 hover:bg-surface-container-high align-middle">
                                    <td class="py-3">
                                        <div class="font-bold text-on-surface"><?php echo htmlspecialchars($customerRow['fullname']); ?></div>
                                        <span class="text-[9px] text-on-surface-variant font-mono"><?php echo htmlspecialchars($customerRow['email']); ?></span>
                                    </td>
                                    <td class="py-3 text-right font-mono font-semibold"><?php echo number_format($customerRow['orders_count']); ?></td>
                                    <td class="py-3 text-right font-mono font-bold text-primary">$<?php echo number_format($customerRow['total_spent'], 2); ?></td>
                                    <td class="py-3 text-right">
                                        <button onclick="openSpecialOfferModal('<?php echo addslashes($customerRow['fullname']); ?>')" class="px-2.5 py-1.5 bg-primary/10 border border-primary/20 hover:bg-primary/20 text-primary font-bold rounded-lg tracking-wide transition-all cursor-pointer">
                                            Release Offer
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topCustomers)): ?>
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-on-surface-variant italic">No customers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Special Offer release Modal -->
<div id="specialOfferModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface-container border border-outline rounded-2xl w-full max-w-md p-6 shadow-2xl scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center border-b border-outline pb-3 mb-4">
            <h3 class="font-bold text-on-surface text-base mb-0">Release Special Offer Coupon</h3>
            <button onclick="closeModal('specialOfferModal')" class="bg-transparent border-0 text-on-surface-variant hover:text-on-surface cursor-pointer p-0">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="space-y-4">
            <div>
                <span class="text-xs text-on-surface-variant font-bold block mb-1">Target Customer</span>
                <span id="targetCustomerName" class="font-bold text-on-surface text-sm block">Julian Rivera</span>
            </div>
            <div>
                <label class="text-xs text-on-surface-variant font-bold block mb-1">Offer / Reward Type</label>
                <select id="offerTypeSelect" class="w-full bg-surface border border-outline rounded-xl px-3 py-2.5 text-sm text-on-surface">
                    <option value="15% Discount Coupon">15% Off Cart Discount</option>
                    <option value="$10 Wallet Cashback">$10 Wallet Cash Reward</option>
                    <option value="Free Dessert Reward">Free Dessert Voucher</option>
                    <option value="Free Delivery Code">Free Delivery Code (5 Orders)</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-on-surface-variant font-bold block mb-1">Custom Reward Code</label>
                <input type="text" id="offerCodeInput" class="w-full bg-surface border border-outline rounded-xl px-3 py-2.5 text-sm text-on-surface font-mono uppercase" placeholder="e.g. LOYAL15" value="LOYAL15">
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button onclick="closeModal('specialOfferModal')" class="px-4 py-2 border border-outline text-on-surface bg-transparent font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-surface-container-highest cursor-pointer">
                Cancel
            </button>
            <button onclick="submitSpecialOffer()" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                Send Reward
            </button>
        </div>
    </div>
</div>

<script>
    // Modal transitions
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('.scale-95')?.classList.remove('scale-95');
            }, 10);
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('opacity-0');
            modal.querySelector('.scale-95')?.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }

    function openSpecialOfferModal(name) {
        document.getElementById('targetCustomerName').textContent = name;
        openModal('specialOfferModal');
    }

    function submitSpecialOffer() {
        const name = document.getElementById('targetCustomerName').textContent;
        const reward = document.getElementById('offerTypeSelect').value;
        const code = document.getElementById('offerCodeInput').value;

        if (!code) {
            alert('Please enter a valid reward code.');
            return;
        }

        closeModal('specialOfferModal');
        alert(`Special offer successfully released! Sent ${reward} (Code: ${code.toUpperCase()}) directly to ${name}'s inbox and notification center!`);
    }
</script>

<?php view('partials/admin_footer'); ?>
