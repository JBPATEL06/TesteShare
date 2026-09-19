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

// Fetch active subscription for store
$activeSubStmt = $db->prepare("
    SELECT * FROM billing_subscriptions 
    WHERE store_id = ? AND status = 'Active' AND end_date >= CURDATE()
    ORDER BY id DESC LIMIT 1
");
$activeSubStmt->execute([$storeId]);
$activeSub = $activeSubStmt->fetch();

$currentTier = getStoreTier($storeId);
$tierRankMap = ['Starter' => 1, 'Premium' => 2, 'Ultra Premium' => 3];
$currentRank = $tierRankMap[$currentTier] ?? 1;

$hasActivePaidSub = ($activeSub && floatval($activeSub['price_per_month']) > 0 && strtotime($activeSub['end_date']) >= time());
$remainingDays = 0;
$unusedCredit = 0.0;

if ($hasActivePaidSub) {
    $endDateTs = strtotime($activeSub['end_date']);
    $nowTs = time();
    if ($endDateTs > $nowTs) {
        $remainingDays = (int)ceil(($endDateTs - $nowTs) / (60 * 60 * 24));
        $paidPrice = floatval($activeSub['price_per_month']);
        $dailyRate = $paidPrice / 30.0;
        $unusedCredit = round($remainingDays * $dailyRate, 2);
    }
}

$subError = '';

// Handle upgrade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upgrade' && $storeId) {
    $newTier = trim($_POST['tier'] ?? '');
    
    // Map legacy names if passed
    if ($newTier === 'Growth Pro') $newTier = 'Premium';
    if ($newTier === 'Enterprise Elite') $newTier = 'Ultra Premium';
    
    $targetRank = $tierRankMap[$newTier] ?? 1;
    
    // Downgrade lock check
    if ($hasActivePaidSub && $targetRank < $currentRank) {
        $subError = "Plan downgrades are disabled while a paid subscription cycle is actively running ($remainingDays days remaining).";
    } elseif (in_array($newTier, ['Starter', 'Premium', 'Ultra Premium'])) {
        $basePrice = 0.00;
        $commRate = 5.00;
        if ($newTier === 'Premium') {
            $basePrice = 49.00;
            $commRate = 2.00;
        } elseif ($newTier === 'Ultra Premium') {
            $basePrice = 149.00;
            $commRate = 0.00;
        }
        
        $finalPriceToPay = max(0.00, $basePrice - $unusedCredit);
        
        // Invalidate old active subscriptions
        $db->prepare("UPDATE billing_subscriptions SET status = 'Cancelled' WHERE store_id = ? AND status = 'Active'")->execute([$storeId]);
        
        // Insert new active subscription
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+1 month'));
        $ins = $db->prepare("INSERT INTO billing_subscriptions (store_id, tier, price_per_month, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'Active')");
        $ins->execute([$storeId, $newTier, $finalPriceToPay, $startDate, $endDate]);
        
        // Update store cached tier and commission rate
        $updStore = $db->prepare("UPDATE stores SET subscription_tier = ?, commission_rate = ? WHERE id = ?");
        $updStore->execute([$newTier, $commRate, $storeId]);
        
        header("Location: " . url('seller/subscription'));
        exit;
    }
}

$pageTitle = 'Subscription Plans';
$activeNav = 'subscription';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto w-full flex flex-col justify-between" style="min-height: 80vh;">
        <div class="space-y-8">
            <?php if ($subError): ?>
                <div class="bg-error-container/20 border border-error text-error p-4 rounded-xl mb-6 font-bold text-sm">
                    <?php echo htmlspecialchars($subError); ?>
                </div>
            <?php endif; ?>

            <?php if ($hasActivePaidSub): ?>
                <div class="bg-primary-container/10 border border-primary/30 p-5 rounded-2xl mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-xl">
                    <div>
                        <span class="text-xs uppercase tracking-widest text-primary font-bold">Active Subscription Proration Active</span>
                        <h4 class="text-headline-sm font-bold text-on-surface mb-1 mt-1">Current Plan: <?php echo htmlspecialchars($currentTier); ?></h4>
                        <p class="text-body-md text-on-surface-variant mb-0">
                            You have <strong class="text-primary"><?php echo $remainingDays; ?> days remaining</strong> in your billing cycle. 
                            A prorated credit of <strong class="text-green-400">-₹<?php echo number_format($unusedCredit, 2); ?></strong> is automatically applied to tier upgrades!
                        </p>
                    </div>
                    <div class="bg-surface-container border border-primary/30 px-4 py-2 rounded-xl text-right">
                        <span class="text-label-sm text-on-surface-variant block">Unused Prorated Credit</span>
                        <span class="text-headline-md text-green-400 font-bold">-₹<?php echo number_format($unusedCredit, 2); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Title Header -->
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <span class="text-xs uppercase tracking-widest text-primary font-bold">Merchant Scaling Plans</span>
                <h1 class="text-headline-xl text-on-surface font-bold">Transparent Pricing. Maximum Growth.</h1>
                <p class="text-body-md text-on-surface-variant">Choose the plan that fits your business needs. Upgrade anytime with prorated billing credit!</p>
            </div>

            <!-- Pricing Grid (Bento Style 3 Columns) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
                
                <!-- Package 1: Starter -->
                <div class="bg-surface-container border <?php echo $currentTier === 'Starter' ? 'border-primary shadow-2xl' : 'border-outline-variant'; ?> rounded-2xl p-8 flex flex-col justify-between shadow-xl relative overflow-hidden group transition-all">
                    <div>
                        <div class="mb-6">
                            <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Starter Tier</span>
                            <div class="text-[40px] font-bold text-on-surface mt-2 mb-1">Free</div>
                            <span class="text-xs text-on-surface-variant">5.0% transaction commission fee</span>
                        </div>
                        <div class="border-t border-outline-variant py-6 space-y-4">
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-primary text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>Standard Storefront Listing</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface-variant opacity-50">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>Highlighted Placement</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface-variant opacity-50">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>Receive Custom &amp; Bulk Orders</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface-variant opacity-50">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>Panel Theme Customizer</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface-variant opacity-50">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>Raw Material Marketplace</span>
                            </div>
                        </div>
                    </div>
                    <?php if ($currentTier === 'Starter'): ?>
                        <button disabled class="w-full py-3 bg-surface-container-high border border-outline-variant text-on-surface font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed mt-6">
                            Active Tier
                        </button>
                    <?php elseif ($hasActivePaidSub): ?>
                        <button disabled class="w-full py-3 bg-surface-container-high border border-outline-variant text-on-surface-variant font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed mt-6 opacity-60" title="Downgrades are locked while a paid subscription cycle is active">
                            Downgrade Locked (<?php echo $remainingDays; ?> Days Active)
                        </button>
                    <?php else: ?>
                        <form action="<?php echo url('seller/subscription'); ?>" method="POST" class="m-0 mt-6">
                            <input type="hidden" name="action" value="upgrade">
                            <input type="hidden" name="tier" value="Starter">
                            <button type="submit" class="w-full py-3 bg-surface-container-high border border-outline-variant text-on-surface font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-surface-container-highest transition-all cursor-pointer">
                                Switch to Starter
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Package 2: Premium -->
                <?php $premiumPay = max(0.00, 49.00 - $unusedCredit); ?>
                <div class="bg-surface-container border <?php echo $currentTier === 'Premium' ? 'border-primary shadow-2xl' : 'border-primary/50'; ?> p-8 flex flex-col justify-between shadow-xl relative overflow-hidden rounded-2xl group transition-all">
                    <div class="absolute top-0 right-0 bg-primary text-on-primary font-bold px-4 py-1 rounded-bl-xl text-xs uppercase tracking-widest">Popular</div>
                    <div>
                        <div class="mb-6">
                            <span class="text-xs uppercase tracking-widest text-primary font-bold">Premium Tier</span>
                            <div class="text-[40px] font-bold text-primary mt-2 mb-1">₹49<span class="text-sm font-normal text-on-surface-variant">/mo</span></div>
                            <span class="text-xs text-on-surface-variant">2.0% transaction commission fee</span>
                        </div>
                        <div class="border-t border-outline-variant py-6 space-y-4">
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-primary text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>⭐ Highlighted Placement (Rating ≥ 3.5)</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-primary text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>Receive Custom &amp; Bulk Orders</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-primary text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>Custom Accent Colors &amp; Branding</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-primary text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>Advanced Sales Analytics</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface-variant opacity-50">
                                <span class="material-symbols-outlined text-sm">cancel</span>
                                <span>Raw Material Marketplace</span>
                            </div>
                        </div>
                    </div>
                    <?php if ($currentTier === 'Premium'): ?>
                        <button disabled class="w-full py-3 bg-surface-container-high border border-outline-variant text-on-surface font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed mt-6">
                            Active Tier
                        </button>
                    <?php elseif ($currentRank > 2 && $hasActivePaidSub): ?>
                        <button disabled class="w-full py-3 bg-surface-container-high border border-outline-variant text-on-surface-variant font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed mt-6 opacity-60" title="Downgrades are locked while a paid subscription cycle is active">
                            Downgrade Locked (<?php echo $remainingDays; ?> Days Active)
                        </button>
                    <?php else: ?>
                        <form action="<?php echo url('seller/subscription'); ?>" method="POST" class="m-0 mt-6" onsubmit="event.preventDefault(); payWithRazorpay('Premium', <?php echo $premiumPay; ?>, this);">
                            <input type="hidden" name="action" value="upgrade">
                            <input type="hidden" name="tier" value="Premium">
                            <button type="submit" class="w-full py-3 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 active:scale-95 transition-all shadow-lg cursor-pointer border-0">
                                Upgrade to Premium (₹<?php echo number_format($premiumPay, 2); ?>)
                            </button>
                            <?php if ($unusedCredit > 0): ?>
                                <span class="text-[11px] text-green-400 font-bold block text-center mt-2">Prorated -₹<?php echo number_format($unusedCredit, 2); ?> credit applied!</span>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Package 3: Ultra Premium -->
                <?php $ultraPay = max(0.00, 149.00 - $unusedCredit); ?>
                <div class="bg-surface-container border <?php echo $currentTier === 'Ultra Premium' ? 'border-amber-400 shadow-2xl' : 'border-outline-variant'; ?> rounded-2xl p-8 flex flex-col justify-between shadow-xl relative overflow-hidden group transition-all">
                    <div class="absolute top-0 right-0 bg-amber-500 text-black font-bold px-4 py-1 rounded-bl-xl text-xs uppercase tracking-widest">VIP Tier</div>
                    <div>
                        <div class="mb-6">
                            <span class="text-xs uppercase tracking-widest text-amber-400 font-bold">Ultra Premium</span>
                            <div class="text-[40px] font-bold text-on-surface mt-2 mb-1">₹149<span class="text-sm font-normal text-on-surface-variant">/mo</span></div>
                            <span class="text-xs text-amber-400 font-bold">0% zero commission fee</span>
                        </div>
                        <div class="border-t border-outline-variant py-6 space-y-4">
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-amber-400 text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>👑 #1 Top Priority Storefront Placement</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-amber-400 text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>Buy &amp; Sell Raw Material Stock</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-amber-400 text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>Receive Custom &amp; Bulk Orders</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-amber-400 text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>Full Theme Customizer &amp; User View Styling</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm text-on-surface">
                                <span class="material-symbols-outlined text-amber-400 text-sm filled-icon" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                <span>Dedicated Merchant Account Support</span>
                            </div>
                        </div>
                    </div>
                    <?php if ($currentTier === 'Ultra Premium'): ?>
                        <button disabled class="w-full py-3 bg-surface-container-high border border-outline-variant text-on-surface font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed mt-6">
                            Active Tier
                        </button>
                    <?php else: ?>
                        <form action="<?php echo url('seller/subscription'); ?>" method="POST" class="m-0 mt-6" onsubmit="event.preventDefault(); payWithRazorpay('Ultra Premium', <?php echo $ultraPay; ?>, this);">
                            <input type="hidden" name="action" value="upgrade">
                            <input type="hidden" name="tier" value="Ultra Premium">
                            <button type="submit" class="w-full py-3 bg-amber-500 text-black font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 active:scale-95 transition-all shadow-lg cursor-pointer border-0">
                                Upgrade to Ultra Premium (₹<?php echo number_format($ultraPay, 2); ?>)
                            </button>
                            <?php if ($unusedCredit > 0): ?>
                                <span class="text-[11px] text-amber-400 font-bold block text-center mt-2">Prorated -₹<?php echo number_format($unusedCredit, 2); ?> credit applied!</span>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

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

<!-- Simulated Payment Modal -->
<div id="payment-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px);" class="flex items-center justify-center">
    <div style="background:#1c1c1e; border:1px solid #2d2d2d; border-radius:16px; width:100%; max-width:400px; margin:20px; overflow:hidden;">
        <div style="background:#ff9f0d; padding:20px 24px;" class="flex items-center gap-3">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
            <div>
                <div style="color:white; font-weight:800; font-size:16px;">TestShare Merchant Pay</div>
                <div style="color:rgba(255,255,255,0.8); font-size:12px;">Subscription Upgrade · Test Mode</div>
            </div>
        </div>
        <div style="padding:20px 24px; border-bottom:1px solid #2d2d2d;">
            <div style="color:#888; font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">Amount to Pay</div>
            <div style="color:#ff9f0d; font-size:28px; font-weight:800;" id="modal-amount">₹0</div>
            <div style="color:#aaa; font-size:12px; margin-top:2px;" id="modal-plan-label"></div>
        </div>
        <div style="padding:20px 24px;" id="payment-methods-panel">
            <div style="color:#aaa; font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Select Payment Method</div>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <label style="display:flex; align-items:center; gap:12px; padding:14px 16px; border:1px solid #2d2d2d; border-radius:10px; cursor:pointer;">
                    <input type="radio" name="sub_pay_method" value="upi" checked style="accent-color:#ff9f0d;">
                    <span class="material-symbols-outlined" style="color:#ff9f0d;">smartphone</span>
                    <div>
                        <div style="color:#e5e2e1; font-weight:700; font-size:14px;">UPI</div>
                        <div style="color:#888; font-size:12px;">PhonePe, GPay, Paytm, BHIM</div>
                    </div>
                </label>
                <label style="display:flex; align-items:center; gap:12px; padding:14px 16px; border:1px solid #2d2d2d; border-radius:10px; cursor:pointer;">
                    <input type="radio" name="sub_pay_method" value="card" style="accent-color:#ff9f0d;">
                    <span class="material-symbols-outlined" style="color:#ff9f0d;">credit_card</span>
                    <div>
                        <div style="color:#e5e2e1; font-weight:700; font-size:14px;">Credit / Debit Card</div>
                        <div style="color:#888; font-size:12px;">Visa, Mastercard, RuPay</div>
                    </div>
                </label>
                <label style="display:flex; align-items:center; gap:12px; padding:14px 16px; border:1px solid #2d2d2d; border-radius:10px; cursor:pointer;">
                    <input type="radio" name="sub_pay_method" value="netbanking" style="accent-color:#ff9f0d;">
                    <span class="material-symbols-outlined" style="color:#ff9f0d;">account_balance</span>
                    <div>
                        <div style="color:#e5e2e1; font-weight:700; font-size:14px;">Net Banking</div>
                        <div style="color:#888; font-size:12px;">SBI, HDFC, ICICI, Axis & more</div>
                    </div>
                </label>
            </div>
            <div style="display:flex; gap:12px; margin-top:20px;">
                <button onclick="closePaymentModal()" style="flex:1; padding:12px; background:transparent; border:1px solid #2d2d2d; color:#aaa; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px;">Cancel</button>
                <button onclick="simulatePayment()" style="flex:2; padding:12px; background:#ff9f0d; border:0; color:#000; border-radius:10px; font-weight:800; cursor:pointer; font-size:14px;">Pay Now</button>
            </div>
        </div>
        <div style="padding:40px 24px; text-align:center; display:none;" id="payment-processing-panel">
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
var _checkoutForm = null;

function payWithRazorpay(tier, priceUSD, formElement) {
    if (priceUSD <= 0) {
        formElement.submit();
        return;
    }
    _checkoutForm = formElement;
    var priceINR = Math.round(priceUSD * 83);
    document.getElementById('modal-amount').textContent = '₹' + priceINR.toLocaleString('en-IN');
    document.getElementById('modal-plan-label').textContent = tier + ' Plan — ₹' + priceINR + '/month';
    document.getElementById('payment-methods-panel').style.display = 'block';
    document.getElementById('payment-processing-panel').style.display = 'none';
    document.getElementById('payment-modal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('payment-modal').style.display = 'none';
    _checkoutForm = null;
}

function simulatePayment() {
    document.getElementById('payment-methods-panel').style.display = 'none';
    document.getElementById('payment-processing-panel').style.display = 'block';
    setTimeout(function() {
        var method = document.querySelector('input[name="sub_pay_method"]:checked').value;
        var mockPaymentId = 'pay_test_' + method.toUpperCase() + '_' + Date.now();
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'razorpay_payment_id';
        input.value = mockPaymentId;
        _checkoutForm.appendChild(input);
        _checkoutForm.submit();
    }, 1800);
}

document.getElementById('payment-modal').addEventListener('click', function(e) {
    if (e.target === this) closePaymentModal();
});
</script>

<?php view('partials/seller_footer'); ?>
