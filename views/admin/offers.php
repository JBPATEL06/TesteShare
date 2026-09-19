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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_promo') {
        $coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $discount = floatval($_POST['discount'] ?? 0.0);
        $subsidy = floatval($_POST['subsidy'] ?? 0.0);
        $absorb = 100.0 - $subsidy;
        if ($coupon_code && $discount) {
            $stmt = $db->prepare("INSERT INTO promotional_offers (store_id, coupon_code, offer_title, offer_description, discount_percentage, min_order_value, admin_subsidy_percentage, merchant_absorb_percentage, start_date, end_date, status) VALUES (NULL, ?, ?, ?, ?, 0.00, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'Active')");
            $stmt->execute([$coupon_code, "Global " . $discount . "% Off", "Platform subsidized discount", $discount, $subsidy, $absorb]);
        }
        exit;
    } elseif ($action === 'toggle_offer_status') {
        $offerId = intval($_POST['offer_id'] ?? 0);
        if ($offerId) {
            $stmt = $db->prepare("UPDATE promotional_offers SET status = IF(status = 'Active', 'Expired', 'Active') WHERE id = ? AND store_id IS NULL");
            $stmt->execute([$offerId]);
        }
        exit;
    } elseif ($action === 'delete_offer') {
        $offerId = intval($_POST['offer_id'] ?? 0);
        if ($offerId) {
            $stmt = $db->prepare("DELETE FROM promotional_offers WHERE id = ? AND store_id IS NULL");
            $stmt->execute([$offerId]);
        }
        exit;
    } elseif ($action === 'disburse_payout') {
        $payoutId = intval($_POST['payout_id'] ?? 0);
        if ($payoutId) {
            $stmt = $db->prepare("UPDATE payouts_ledger SET settlement_status = 'Settled', transfer_reference = ? WHERE id = ?");
            $stmt->execute(['TXN-' . strtoupper(uniqid()), $payoutId]);
        }
        exit;
    } elseif ($action === 'update_first_order_config') {
        $discountPct = floatval($_POST['discount_percentage'] ?? 15.0);
        $maxDiscount = floatval($_POST['max_discount_amount'] ?? 10.0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $db->exec("DELETE FROM first_order_config");
        $stmt = $db->prepare("INSERT INTO first_order_config (discount_percentage, max_discount_amount, is_active) VALUES (?, ?, ?)");
        $stmt->execute([$discountPct, $maxDiscount, $isActive]);
        exit;
    }
}

// Fetch Global Offers (store_id IS NULL)
$globalOffers = $db->query("
    SELECT * FROM promotional_offers 
    WHERE store_id IS NULL 
    ORDER BY id DESC
")->fetchAll();

// Check if payouts_ledger is empty, and seed from paid orders
$payoutsCount = $db->query("SELECT COUNT(*) FROM payouts_ledger")->fetchColumn();
if ($payoutsCount == 0) {
    // Fetch paid orders
    $orders = $db->query("SELECT id, store_id, total_amount FROM orders WHERE payment_status = 'Paid'")->fetchAll();
    // Fetch the SUPER50 offer
    $offerId = $db->query("SELECT id FROM promotional_offers WHERE coupon_code = 'SUPER50'")->fetchColumn();
    foreach ($orders as $order) {
        $discountAmount = $order['total_amount'] * 0.5;
        $subsidyAmount = $discountAmount * 0.8;
        $ins = $db->prepare("INSERT INTO payouts_ledger (store_id, order_id, offer_id, discharged_reimbursement, settlement_status) VALUES (?, ?, ?, ?, 'Escrow Hold')");
        $ins->execute([$order['store_id'], $order['id'], $offerId ?: null, $subsidyAmount]);
    }
}

// Fetch payouts ledger logs
$ledger = $db->query("
    SELECT p.*, s.store_name, o.id as order_num, o.total_amount as order_val, 
           po.coupon_code, po.discount_percentage, po.admin_subsidy_percentage
    FROM payouts_ledger p
    JOIN stores s ON p.store_id = s.id
    JOIN orders o ON p.order_id = o.id
    LEFT JOIN promotional_offers po ON p.offer_id = po.id
    ORDER BY p.created_at DESC
")->fetchAll();

$pageTitle = 'Global Offers & Subsidy Engine';
$activeNav = 'offers';
view('partials/admin_header', get_defined_vars());
view('partials/admin_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <!-- Content Canvas -->
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Section Header -->
        <div class="border-b border-outline pb-6">
            <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Global Promotions &amp; Subsidy Split Engine</h2>
            <p class="text-body-md text-on-surface-variant mb-0">Create, activate, or deactivate global platform-level discount codes and configure subsidy splits to reimburse merchant losses.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left: Promo Creator & Subsidy Config -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-surface-container rounded-2xl border border-outline p-6 shadow-xl space-y-4">
                    <h3 class="font-bold text-on-surface text-base mb-4 pb-2 border-b border-outline/30 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">add_card</span>
                        <span>Create Global Offer</span>
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="text-xs text-on-surface-variant font-bold block mb-1">Promo Coupon Code</label>
                            <input type="text" id="promoCodeInput" class="w-full bg-surface border border-outline rounded-xl px-4 py-2.5 text-sm text-on-surface font-mono uppercase" placeholder="e.g. MEGA50" value="MEGA50">
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="text-xs text-on-surface-variant font-bold">Discount Percentage</label>
                                <span class="text-xs text-primary font-bold font-mono" id="discountPctDisplay">50% OFF</span>
                            </div>
                            <input id="discountPctRange" class="w-full h-2 bg-outline-variant rounded-lg appearance-none cursor-pointer accent-primary" max="75" min="10" step="5" type="range" value="50" oninput="document.getElementById('discountPctDisplay').textContent = this.value + '% OFF'">
                        </div>

                        <!-- Admin Subsidy Slider -->
                        <div class="p-4 bg-surface rounded-xl border border-outline/30 space-y-3">
                            <div class="flex justify-between items-center">
                                <label class="text-xs text-on-surface font-bold">Admin Subsidy Ratio</label>
                                <span class="text-xs text-primary font-bold font-mono" id="subsidyDisplay">80% Admin Paid</span>
                            </div>
                            <input id="subsidyRange" class="w-full h-2 bg-outline-variant rounded-lg appearance-none cursor-pointer accent-primary" max="100" min="50" step="5" type="range" value="80" oninput="updateSubsidySplit(this.value)">
                            <div class="flex justify-between text-[10px] text-on-surface-variant font-bold font-mono">
                                <span>50% (Equal)</span>
                                <span>100% (Full Admin)</span>
                            </div>
                            <p class="text-[10px] text-on-surface-variant mb-0 italic">How much of the customer discount is compensated/funded by the admin so the seller does not suffer losses.</p>
                        </div>

                        <div>
                            <button onclick="createGlobalPromo()" class="w-full py-3 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl border-0 shadow-lg cursor-pointer hover:brightness-110 active:scale-[0.98] transition-all">
                                Deploy Global Offer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Active Promotions Management Table & Payout Ledger -->
            <div class="lg:col-span-8 space-y-6">
                
                <!-- Global Offers Management Card -->
                <div class="bg-surface-container rounded-2xl border border-outline p-6 shadow-xl space-y-4">
                    <div class="flex justify-between items-center pb-4 border-b border-outline/30">
                        <h3 class="font-bold text-on-surface text-base mb-0 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">sell</span>
                            <span>Manage Global Offers (Active / Inactive Controls)</span>
                        </h3>
                        <span class="text-xs text-on-surface-variant font-bold"><?php echo count($globalOffers); ?> Offers Configured</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-outline bg-surface-container-low font-bold text-[10px] uppercase tracking-wider text-on-surface-variant">
                                    <th class="py-3 px-4">Coupon Code</th>
                                    <th class="py-3 px-4 text-center">Discount</th>
                                    <th class="py-3 px-4 text-center">Admin Subsidy</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($globalOffers)): ?>
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-on-surface-variant italic text-sm">No global offers deployed. Create one using the form on the left!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($globalOffers as $gOffer): ?>
                                        <?php $isActive = ($gOffer['status'] === 'Active'); ?>
                                        <tr class="border-b border-outline/30 align-middle text-xs hover:bg-surface-container-high">
                                            <td class="py-3.5 px-4 font-mono font-bold text-primary">
                                                <span class="text-sm"><?php echo htmlspecialchars($gOffer['coupon_code']); ?></span>
                                                <span class="block text-[10px] text-on-surface-variant font-sans font-normal"><?php echo htmlspecialchars($gOffer['offer_title']); ?></span>
                                            </td>
                                            <td class="py-3.5 px-4 text-center font-bold text-on-surface">
                                                <?php echo number_format($gOffer['discount_percentage'], 0); ?>% OFF
                                            </td>
                                            <td class="py-3.5 px-4 text-center font-bold text-primary">
                                                <?php echo number_format($gOffer['admin_subsidy_percentage'], 0); ?>% Admin
                                            </td>
                                            <td class="py-3.5 px-4 text-center">
                                                <?php if ($isActive): ?>
                                                    <span class="px-2.5 py-1 bg-green-900/40 text-green-400 border border-green-800 font-bold text-[10px] uppercase tracking-wider rounded-md">
                                                        ✓ Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 bg-error/20 text-error border border-error/40 font-bold text-[10px] uppercase tracking-wider rounded-md">
                                                        ✕ Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3.5 px-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button onclick="toggleGlobalOffer('<?php echo $gOffer['id']; ?>', '<?php echo $isActive ? 'deactivate' : 'activate'; ?>')" 
                                                        class="px-3 py-1.5 <?php echo $isActive ? 'bg-error/20 text-error border border-error/40 hover:bg-error/30' : 'bg-green-900/30 text-green-400 border border-green-800 hover:bg-green-900/50'; ?> font-bold text-[10px] uppercase tracking-wider rounded-lg border-0 cursor-pointer transition-all">
                                                        <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                    <button onclick="deleteGlobalOffer('<?php echo $gOffer['id']; ?>', '<?php echo htmlspecialchars($gOffer['coupon_code']); ?>')" 
                                                        class="p-1.5 text-on-surface-variant hover:text-error bg-transparent border-0 cursor-pointer rounded-lg hover:bg-surface-container-highest transition-colors" title="Delete Global Offer">
                                                        <span class="material-symbols-outlined text-sm">delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payout Settlement Ledger Card -->
                <div class="bg-surface-container rounded-2xl border border-outline p-6 shadow-xl">
                    <div class="flex justify-between items-center mb-6 pb-4 border-b border-outline/30">
                        <h3 class="font-bold text-on-surface text-base mb-0 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">receipt_long</span>
                            <span>Platform Promotion &amp; Payout Settlement Logs</span>
                        </h3>
                        <span class="text-xs text-on-surface-variant">Razorpay splits T+1 active</span>
                    </div>

                    <!-- Ledger Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-outline bg-surface-container-low font-bold text-[10px] uppercase tracking-wider text-on-surface-variant">
                                    <th class="py-3 px-4">Order ID &amp; Store</th>
                                    <th class="py-3 px-4 text-right">Order Value</th>
                                    <th class="py-3 px-4 text-right">Promo applied</th>
                                    <th class="py-3 px-4 text-right">Customer Paid</th>
                                    <th class="py-3 px-4 text-right text-primary">Admin Subsidy Paid</th>
                                    <th class="py-3 px-4 text-right">Net Payout</th>
                                    <th class="py-3 px-4 text-right">Reimburse Status</th>
                                </tr>
                            </thead>
                            <tbody id="ledgerTableBody">
                                <?php foreach ($ledger as $lRow): ?>
                                    <?php
                                    $orderVal = floatval($lRow['order_val']);
                                    $discountPct = floatval($lRow['discount_percentage'] ?? 50.00);
                                    $subsidyPct = floatval($lRow['admin_subsidy_percentage'] ?? 80.00);
                                    
                                    $discountAmt = $orderVal * ($discountPct / 100.00);
                                    $customerPaid = $orderVal - $discountAmt;
                                    $subsidyAmt = $discountAmt * ($subsidyPct / 100.00);
                                    $commission = $orderVal * 0.05;
                                    $netPayout = ($customerPaid + $subsidyAmt) - $commission;
                                    
                                    $status = $lRow['settlement_status'];
                                    ?>
                                    <tr class="border-b border-outline/30 align-middle text-xs hover:bg-surface-container-high" id="ledger-row-<?php echo $lRow['id']; ?>">
                                        <td class="py-4 px-4">
                                            <div class="font-bold text-on-surface">#<?php echo htmlspecialchars($lRow['order_num']); ?></div>
                                            <div class="text-[10px] text-on-surface-variant font-semibold"><?php echo htmlspecialchars($lRow['store_name']); ?></div>
                                        </td>
                                        <td class="py-4 px-4 text-right font-mono font-bold">₹<?php echo number_format($orderVal, 2); ?></td>
                                        <td class="py-4 px-4 text-right text-error font-mono font-bold">
                                            -₹<?php echo number_format($discountAmt, 2); ?><br>
                                            <span class="text-[8px] text-on-surface-variant uppercase font-bold font-sans"><?php echo htmlspecialchars($lRow['coupon_code'] ?? 'GLOBAL'); ?> (<?php echo number_format($discountPct, 0); ?>%)</span>
                                        </td>
                                        <td class="py-4 px-4 text-right font-mono font-bold">₹<?php echo number_format($customerPaid, 2); ?></td>
                                        <td class="py-4 px-4 text-right text-primary font-mono font-bold font-subsidy" data-subsidy-factor="<?php echo $subsidyPct / 100.00; ?>">
                                            +₹<?php echo number_format($subsidyAmt, 2); ?><br>
                                            <span class="text-[8px] text-on-surface-variant uppercase font-bold font-sans">(<?php echo number_format($subsidyPct, 0); ?>% Admin)</span>
                                        </td>
                                        <td class="py-4 px-4 text-right font-mono font-bold font-net" data-base="<?php echo $orderVal; ?>" data-discount="<?php echo $discountAmt; ?>" data-subsidy="<?php echo $subsidyAmt; ?>">
                                            ₹<?php echo number_format($netPayout, 2); ?><br>
                                            <span class="text-[8px] text-on-surface-variant uppercase font-bold font-sans font-mono">(incl. 5% commission)</span>
                                        </td>
                                        <td class="py-4 px-4 text-right">
                                            <?php if ($status === 'Settled'): ?>
                                                <span class="px-3 py-1 bg-surface-container-high border border-outline text-on-surface-variant font-bold text-[10px] uppercase tracking-wider rounded-lg select-none">Disbursed</span>
                                            <?php else: ?>
                                                <button onclick="reimburseStore('<?php echo $lRow['id']; ?>', '<?php echo addslashes($lRow['store_name']); ?>', <?php echo $subsidyAmt; ?>, this)" class="px-3 py-1 bg-primary text-on-primary font-bold text-[10px] uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                                                    Pay Store
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
    // Update live subsidy indicators in table based on range slider changes
    function updateSubsidySplit(val) {
        document.getElementById('subsidyDisplay').textContent = `${val}% Admin Paid`;
        
        const rows = document.querySelectorAll('#ledgerTableBody tr');
        rows.forEach(row => {
            const subsidyCell = row.querySelector('.font-subsidy');
            const netCell = row.querySelector('.font-net');
            if (subsidyCell && netCell) {
                const baseVal = parseFloat(netCell.getAttribute('data-base'));
                const discountVal = parseFloat(netCell.getAttribute('data-discount'));
                
                const subsidyAmount = discountVal * (parseFloat(val) / 100);
                subsidyCell.innerHTML = `+₹${subsidyAmount.toFixed(2)}<br><span class="text-[8px] text-on-surface-variant uppercase font-bold font-sans">(${val}% Admin)</span>`;
                
                const customerPaid = baseVal - discountVal;
                const netPayout = (customerPaid + subsidyAmount) - (baseVal * 0.05);
                netCell.innerHTML = `₹${netPayout.toFixed(2)}<br><span class="text-[8px] text-on-surface-variant uppercase font-bold font-sans font-mono">(incl. 5% commission)</span>`;
                
                netCell.setAttribute('data-subsidy', subsidyAmount);
                
                const btn = row.querySelector('button');
                if (btn) {
                    const store = row.querySelector('.text-[10px]').textContent;
                    const orderId = row.id.split('-').pop();
                    btn.setAttribute('onclick', `reimburseStore('${orderId}', '${store}', ${subsidyAmount}, this)`);
                }
            }
        });
    }

    // Deploy global offers via POST
    function createGlobalPromo() {
        const code = document.getElementById('promoCodeInput').value;
        const discount = document.getElementById('discountPctRange').value;
        const subsidy = document.getElementById('subsidyRange').value;
        
        if (!code) {
            alert('Please enter a valid coupon code.');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'create_promo');
        formData.append('coupon_code', code);
        formData.append('discount', discount);
        formData.append('subsidy', subsidy);
        
        fetch('', { method: 'POST', body: formData })
            .then(() => {
                alert(`Successfully deployed global offer: ${code} with ${discount}% off. Platform subsidy funding rate set to ${subsidy}%.`);
                window.location.reload();
            });
    }

    // Toggle Global Offer Status (Active / Deactivate)
    function toggleGlobalOffer(offerId, actionType) {
        if (confirm(`Are you sure you want to ${actionType} this global offer?`)) {
            const formData = new FormData();
            formData.append('action', 'toggle_offer_status');
            formData.append('offer_id', offerId);
            
            fetch('', { method: 'POST', body: formData })
                .then(() => window.location.reload());
        }
    }

    // Delete Global Offer
    function deleteGlobalOffer(offerId, code) {
        if (confirm(`Are you sure you want to delete global offer ${code}? This action cannot be undone.`)) {
            const formData = new FormData();
            formData.append('action', 'delete_offer');
            formData.append('offer_id', offerId);
            
            fetch('', { method: 'POST', body: formData })
                .then(() => window.location.reload());
        }
    }

    // Pay store reimbursement via Razorpay Route Split POST
    function reimburseStore(payoutId, store, amount, btn) {
        if (confirm(`Confirm Razorpay Route payout release of ₹${parseFloat(amount).toFixed(2)} to ${store} for promotional subsidy reimbursement?`)) {
            const formData = new FormData();
            formData.append('action', 'disburse_payout');
            formData.append('payout_id', payoutId);
            
            fetch('', { method: 'POST', body: formData })
                .then(() => {
                    alert(`Razorpay Route settlement split disbursed: ₹${parseFloat(amount).toFixed(2)} transfer completed for ${store}!`);
                    window.location.reload();
                });
        }
    }
</script>

<?php view('partials/admin_footer'); ?>
