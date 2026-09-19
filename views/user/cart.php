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

$customerId = $_SESSION['user_id'] ?? null;
if (!$customerId) {
    header("Location: " . url('user/login'));
    exit;
}

// Handle add_id
if (isset($_GET['add_id'])) {
    $addId = intval($_GET['add_id']);
    $qty = intval($_GET['qty'] ?? 1);
    if ($addId > 0) {
        $stmtStoreCheck = $db->prepare("SELECT store_id FROM menu_items WHERE id = ?");
        $stmtStoreCheck->execute([$addId]);
        $newItemStoreId = $stmtStoreCheck->fetchColumn();

        if ($newItemStoreId) {
            $currentCartStoreId = $_SESSION['cart_store_id'] ?? null;
            if (!empty($_SESSION['cart']) && $currentCartStoreId && $currentCartStoreId != $newItemStoreId) {
                // Clear cart if switching stores
                $_SESSION['cart'] = [];
            }
            $_SESSION['cart_store_id'] = $newItemStoreId;

            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            if (isset($_SESSION['cart'][$addId])) {
                $_SESSION['cart'][$addId] += $qty;
            } else {
                $_SESSION['cart'][$addId] = $qty;
            }
        }
    }
    header("Location: " . url('user/cart'));
    exit;
}

// Handle actions (increase, decrease, remove)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $itemId = intval($_GET['item_id'] ?? 0);
    
    if ($itemId > 0 && isset($_SESSION['cart'][$itemId])) {
        if ($action === 'increase') {
            $_SESSION['cart'][$itemId]++;
        } elseif ($action === 'decrease') {
            $_SESSION['cart'][$itemId]--;
            if ($_SESSION['cart'][$itemId] <= 0) {
                unset($_SESSION['cart'][$itemId]);
            }
        } elseif ($action === 'remove') {
            unset($_SESSION['cart'][$itemId]);
        }
    }
    header("Location: " . url('user/cart'));
    exit;
}

// Handle apply coupon
$couponError = '';
$couponMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_coupon') {
    $coupon = strtoupper(trim($_POST['coupon_code'] ?? ''));
    if ($coupon) {
        $couponStmt = $db->prepare("SELECT * FROM promotional_offers WHERE coupon_code = ? AND status = 'Active'");
        $couponStmt->execute([$coupon]);
        $promo = $couponStmt->fetch();
        if ($promo) {
            $_SESSION['applied_coupon'] = $promo['coupon_code'];
            $couponMsg = "Coupon applied successfully!";
        } else {
            $couponError = "Invalid or expired coupon code.";
        }
    }
}

// Handle clear coupon
if (isset($_GET['clear_coupon'])) {
    unset($_SESSION['applied_coupon']);
    header("Location: " . url('user/cart'));
    exit;
}

// Handle cart delivery address selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_cart_address') {
    $selectedAddrId = intval($_POST['address_id'] ?? 0);
    if ($customerId && $selectedAddrId) {
        $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$customerId]);
        $db->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$selectedAddrId, $customerId]);
    }
    header("Location: " . url('user/cart'));
    exit;
}

// Handle use current GPS location directly on cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'use_current_gps_location') {
    $address_line1 = trim($_POST['address_line1'] ?? 'GPS Current Location');
    $city = trim($_POST['city'] ?? 'Current Location');
    $lat = floatval($_POST['lat'] ?? 0);
    $lng = floatval($_POST['lng'] ?? 0);

    if ($customerId && $lat && $lng) {
        $db->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$customerId]);
        $ins = $db->prepare("INSERT INTO user_addresses (user_id, label, address_line1, city, state, zip_code, lat, lng, is_default) VALUES (?, 'Current Location', ?, ?, 'NY', '10001', ?, ?, 1)");
        $ins->execute([$customerId, $address_line1, $city, $lat, $lng]);
    }
    header("Location: " . url('user/cart'));
    exit;
}

// Load all customer saved addresses
$userAddresses = [];
if ($customerId) {
    $stmtUserAddrs = $db->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $stmtUserAddrs->execute([$customerId]);
    $userAddresses = $stmtUserAddrs->fetchAll();
}

// Check User Location & Geofence Radius
$cartStoreId = $_SESSION['cart_store_id'] ?? null;
$locationWarning = '';
$isOutOfRadius = false;
$distanceKm = null;
$userAddr = null;
$storeData = null;

if ($cartStoreId && $customerId) {
    // Customer default address
    $stmtUserAddr = $db->prepare("SELECT id, label, address_line1, city, lat, lng FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1");
    $stmtUserAddr->execute([$customerId]);
    $userAddr = $stmtUserAddr->fetch();

    // Store location details
    $stmtStoreLoc = $db->prepare("SELECT id, store_name, address, city, lat, lng, delivery_radius FROM stores WHERE id = ?");
    $stmtStoreLoc->execute([$cartStoreId]);
    $storeData = $stmtStoreLoc->fetch();

    if (!$userAddr) {
        $isOutOfRadius = true;
        $locationWarning = "Delivery Address Required: Please set your delivery location before placing an order.";
    } elseif ($storeData) {
        $userLat = floatval($userAddr['lat'] ?? 0);
        $userLng = floatval($userAddr['lng'] ?? 0);
        $storeLat = floatval($storeData['lat'] ?? 40.7128);
        $storeLng = floatval($storeData['lng'] ?? -74.0060);
        $storeRadius = floatval($storeData['delivery_radius'] ?? 5.0);

        if ($userLat && $userLng && $storeLat && $storeLng) {
            $earthRadius = 6371; // km
            $dLat = deg2rad($storeLat - $userLat);
            $dLon = deg2rad($storeLng - $userLng);
            $a = sin($dLat / 2) * sin($dLat / 2) +
                 cos(deg2rad($userLat)) * cos(deg2rad($storeLat)) *
                 sin($dLon / 2) * sin($dLon / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distanceKm = round($earthRadius * $c, 1);

            if ($distanceKm > $storeRadius) {
                $isOutOfRadius = true;
                $locationWarning = "Location Out of Delivery Range: Your address (" . htmlspecialchars($userAddr['address_line1'] . ', ' . $userAddr['city']) . ", " . $distanceKm . " km away) is outside " . htmlspecialchars($storeData['store_name']) . "'s delivery radius (" . number_format($storeRadius, 1) . " km max).";
            }
        }
    }
}

// Handle checkout
$checkoutError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    if (!$customerId) {
        header("Location: " . url('user/login'));
        exit;
    }
    
    // STRICT LOCATION GEOFENCE ENFORCEMENT
    if ($isOutOfRadius) {
        $checkoutError = $locationWarning ?: "Order Cannot Be Placed: Your location is outside the restaurant's delivery radius.";
    } else {
        $cart = $_SESSION['cart'] ?? [];
        if (!empty($cart)) {
            // Get cart items details
            $placeholders = implode(',', array_fill(0, count($cart), '?'));
            $stmt = $db->prepare("SELECT * FROM menu_items WHERE id IN ($placeholders)");
            $stmt->execute(array_keys($cart));
            $items = $stmt->fetchAll();
            
            $storeId = 0;
            $totalSub = 0.0;
            foreach ($items as $item) {
                $qty = $cart[$item['id']];
                $totalSub += $item['price'] * $qty;
                $storeId = $item['store_id'];
            }
            
            // Compute discount
            $discount = 0.0;
            $appliedCoupon = $_SESSION['applied_coupon'] ?? '';
            if ($appliedCoupon) {
                $couponStmt = $db->prepare("SELECT * FROM promotional_offers WHERE coupon_code = ? AND status = 'Active'");
                $couponStmt->execute([$appliedCoupon]);
                $promo = $couponStmt->fetch();
                if ($promo && $totalSub >= $promo['min_order_value']) {
                    $discount = ($totalSub * $promo['discount_percentage']) / 100.0;
                }
            }
            
            // Auto-apply Admin First-Order Discount if no coupon applied
            if ($discount == 0.0) {
                $firstOrderConfig = $db->query("SELECT * FROM first_order_config WHERE is_active = 1 LIMIT 1")->fetch();
                if ($firstOrderConfig) {
                    $priorOrders = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND payment_status = 'Paid'");
                    $priorOrders->execute([$customerId]);
                    if ($priorOrders->fetchColumn() == 0) {
                        $calcDiscount = ($totalSub * floatval($firstOrderConfig['discount_percentage'])) / 100.0;
                        $maxDiscount = floatval($firstOrderConfig['max_discount_amount']);
                        if ($maxDiscount > 0 && $calcDiscount > $maxDiscount) {
                            $calcDiscount = $maxDiscount;
                        }
                        $discount = $calcDiscount;
                    }
                }
            }
            
            $totalAmount = $totalSub - $discount;
            if ($totalAmount < 0) $totalAmount = 0.0;
            
            // Fetch store commission
            $storeRate = $db->prepare("SELECT commission_rate FROM stores WHERE id = ?");
            $storeRate->execute([$storeId]);
            $commRate = floatval($storeRate->fetchColumn() ?? 5.0);
            
            $commission = ($totalAmount * $commRate) / 100.0;
            $netAmount = $totalAmount - $commission;
            $rzpId = $_POST['razorpay_payment_id'] ?? null;
            $orderNote = trim($_POST['order_note'] ?? '');
            
            $db->beginTransaction();
            try {
                $insOrder = $db->prepare("
                    INSERT INTO orders (customer_id, store_id, total_amount, platform_commission, store_net_amount, payment_status, order_status, razorpay_payment_id, notes)
                    VALUES (?, ?, ?, ?, ?, 'Paid', 'Pending', ?, ?)
                ");
                $insOrder->execute([$customerId, $storeId, $totalAmount, $commission, $netAmount, $rzpId, $orderNote ?: null]);
                $orderId = $db->lastInsertId();
                
                $insItem = $db->prepare("
                    INSERT INTO order_items (order_id, menu_item_id, quantity, price)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($items as $item) {
                    $qty = $cart[$item['id']];
                    $insItem->execute([$orderId, $item['id'], $qty, $item['price']]);
                }

                // Notify the seller — look up the store owner's user_id
                $ownerStmt = $db->prepare("SELECT owner_id FROM stores WHERE id = ?");
                $ownerStmt->execute([$storeId]);
                $ownerId = $ownerStmt->fetchColumn();
                if ($ownerId) {
                    $itemLabels = [];
                    foreach ($items as $item) {
                        $itemLabels[] = $cart[$item['id']] . 'x ' . $item['name'];
                    }
                    $notifMsg = 'New order #UA-' . $orderId . ': ' . implode(', ', $itemLabels) . '. Total: ₹' . number_format($totalAmount, 2) . ($orderNote ? ' [Note: ' . $orderNote . ']' : '') . '. Awaiting your acceptance.';
                    $insNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
                    $insNotif->execute([$ownerId, '🛎 New Order Received — #UA-' . $orderId, $notifMsg]);
                }
                
                $db->commit();
                
                // Clear cart
                $_SESSION['cart'] = [];
                unset($_SESSION['applied_coupon']);
                
                header("Location: " . url('user/orders'));
                exit;
            } catch (Exception $ex) {
                $db->rollBack();
                $checkoutError = "Checkout failed: " . $ex->getMessage();
            }
        } else {
            $checkoutError = "Cart is empty.";
        }
    }
}

// Load cart items from db
$cartItems = [];
$subtotal = 0.0;
$cart = $_SESSION['cart'] ?? [];

if (!empty($cart)) {
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $db->prepare("
        SELECT m.*, s.store_name
        FROM menu_items m
        JOIN stores s ON m.store_id = s.id
        WHERE m.id IN ($placeholders)
    ");
    $stmt->execute(array_keys($cart));
    $dbItems = $stmt->fetchAll();
    
    foreach ($dbItems as $dbi) {
        $qty = $cart[$dbi['id']];
        $dbi['quantity'] = $qty;
        $dbi['subtotal'] = $dbi['price'] * $qty;
        $subtotal += $dbi['subtotal'];
        $cartItems[] = $dbi;
    }
}

// Load coupon info if applied
$discount = 0.0;
$appliedCoupon = $_SESSION['applied_coupon'] ?? '';
$promo = null;
if ($appliedCoupon) {
    $couponStmt = $db->prepare("SELECT * FROM promotional_offers WHERE coupon_code = ? AND status = 'Active'");
    $couponStmt->execute([$appliedCoupon]);
    $promo = $couponStmt->fetch();
    if ($promo) {
        $minVal = floatval($promo['min_order_value']);
        if ($subtotal >= $minVal) {
            $discount = ($subtotal * floatval($promo['discount_percentage'])) / 100.0;
        } else {
            $couponError = "Minimum order value of ₹" . number_format($minVal, 2) . " required for coupon " . htmlspecialchars($promo['coupon_code']) . ".";
            $promo = null; // Invalidate active display if order total doesn't meet minimum requirement
        }
    } else {
        $couponError = "Invalid or expired coupon code.";
    }
}

$deliveryFee = 2.99;
if ($cartStoreId) {
    try {
        $stmtFee = $db->prepare("SELECT delivery_fee FROM stores WHERE id = ?");
        $stmtFee->execute([$cartStoreId]);
        $customFee = $stmtFee->fetchColumn();
        if ($customFee !== false && $customFee !== null) {
            $deliveryFee = floatval($customFee);
        }
    } catch (Exception $e) {}
}

$taxes = round($subtotal * 0.06, 2);
$toPay = $subtotal - $discount + $deliveryFee + $taxes;
if ($toPay < 0) $toPay = 0.0;

$pageTitle = 'My Cart';
$activeNav = 'explore';
view('partials/user_header', get_defined_vars());
?>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #1c1b1b;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #353534;
    }
</style>

<!-- Main Content Canvas -->
<main class="flex-grow w-full max-w-7xl mx-auto px-container-margin py-section-gap flex flex-col md:grid md:grid-cols-12 gap-gutter my-4">
    <!-- Left Column: Cart Items -->
    <div class="md:col-span-8 flex flex-col gap-stack-lg">
        <div class="flex border-b border-surface-variant mb-stack-lg">
            <a href="<?php echo url('user/orders'); ?>" class="px-stack-lg py-stack-md font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-colors border-b-2 border-transparent text-decoration-none mr-3">Order History</a>
            <a href="<?php echo url('user/cart'); ?>" class="px-stack-lg py-stack-md font-label-md text-label-md text-primary font-bold border-b-2 border-primary-container text-decoration-none">Cart</a>
        </div>
        <h1 class="font-headline-lg text-headline-lg mb-stack-md font-bold">My Cart</h1>
        
        <!-- Location Out of Radius Banner -->
        <?php if ($isOutOfRadius && !empty($cartItems)): ?>
            <div class="p-4 bg-red-500/10 border border-red-500/30 rounded-2xl text-red-400 text-sm font-semibold flex flex-col gap-2 mb-4 shadow-lg">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-400">location_off</span>
                    <span class="font-bold text-base">Delivery Location Out of Range</span>
                </div>
                <p class="mb-0 text-xs text-red-300"><?php echo htmlspecialchars($locationWarning); ?></p>
                <div class="mt-2 pt-2 border-t border-red-500/20 flex justify-between items-center">
                    <span class="text-[11px] text-red-400">Orders can only be placed within the merchant's delivery geofence.</span>
                    <a href="<?php echo url('user/location'); ?>" class="px-3 py-1 bg-red-500/20 text-red-300 hover:text-white rounded-lg text-xs font-bold text-decoration-none border border-red-500/30 transition-all">
                        Change Delivery Location &rarr;
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($checkoutError): ?>
            <div class="bg-error-container text-on-error-container p-3 rounded-2 mb-4 font-bold"><?php echo htmlspecialchars($checkoutError); ?></div>
        <?php endif; ?>

        <div class="border border-surface-variant bg-surface-container-low rounded-3 overflow-hidden">
            <?php if (empty($cartItems)): ?>
                <div class="p-5 text-center text-on-surface-variant">Your cart is currently empty.</div>
            <?php else: ?>
                <?php foreach ($cartItems as $cItem): ?>
                    <?php
                    $img = $cItem['image_url'] ? asset($cItem['image_url']) : asset('images/truffle_umami_burger.png');
                    ?>
                    <!-- Cart Item -->
                    <div class="flex items-center gap-gutter p-stack-lg border-b border-surface-variant p-4">
                        <div class="w-24 h-24 flex-shrink-0 bg-surface-container border border-surface-variant relative rounded-2 overflow-hidden mr-3" style="width: 96px; height: 96px;">
                            <img class="w-full h-full object-cover" alt="<?php echo htmlspecialchars($cItem['name']); ?>" src="<?php echo $img; ?>">
                        </div>
                        <div class="flex-grow flex flex-col pl-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-title-md text-title-md text-on-surface mb-0 font-bold"><?php echo htmlspecialchars($cItem['name']); ?></h3>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant mb-0"><?php echo htmlspecialchars($cItem['store_name']); ?></p>
                                </div>
                                <span class="font-title-md text-title-md text-primary font-bold">₹<?php echo number_format($cItem['subtotal'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center mt-3">
                                <div class="flex items-center bg-surface-container border border-surface-variant rounded-2 overflow-hidden">
                                    <button onclick="window.location.href='<?php echo url('user/cart&action=decrease&item_id=' . $cItem['id']); ?>'" class="w-10 h-10 flex items-center justify-center hover:bg-surface-container-high transition-colors active:scale-95 bg-transparent border-0 text-on-surface" style="width: 40px; height: 40px;">
                                        <span class="material-symbols-outlined text-sm">remove</span>
                                    </button>
                                    <span class="w-10 h-10 flex items-center justify-center font-label-md text-label-md text-on-surface font-bold" style="width: 40px; height: 40px;"><?php echo $cItem['quantity']; ?></span>
                                    <button onclick="window.location.href='<?php echo url('user/cart&action=increase&item_id=' . $cItem['id']); ?>'" class="w-10 h-10 flex items-center justify-center hover:bg-surface-container-high transition-colors active:scale-95 bg-transparent border-0 text-on-surface" style="width: 40px; height: 40px;">
                                        <span class="material-symbols-outlined text-sm">add</span>
                                    </button>
                                </div>
                                <button onclick="window.location.href='<?php echo url('user/cart&action=remove&item_id=' . $cItem['id']); ?>'" class="flex items-center gap-base text-on-surface-variant hover:text-error transition-colors bg-transparent border-0 cursor-pointer">
                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                    <span class="font-label-sm text-label-sm uppercase fw-bold">Remove</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Note to Restaurant -->
            <div class="p-stack-lg p-4">
                <label class="block font-label-md text-label-md text-on-surface mb-stack-sm font-bold mb-2">Add a note for the restaurant</label>
                <textarea id="cartOrderNote" name="order_note" class="w-full bg-surface border border-surface-variant p-stack-md text-body-md text-on-surface focus:border-primary-container focus:ring-0 transition-all min-h-[100px] resize-none rounded-2 p-3" placeholder="Eg: No onions please, or extra napkins."></textarea>
            </div>
        </div>
    </div>

    <!-- Right Column: Bill Details & Checkout -->
    <aside class="md:col-span-4 flex flex-col gap-stack-lg">
        <!-- Delivery Location Selection Card -->
        <div class="bg-surface-container border border-surface-variant p-stack-lg flex flex-col gap-stack-md rounded-3 p-4 mb-4 shadow-md">
            <div class="flex justify-between items-center border-b border-surface-variant pb-2 mb-2">
                <h3 class="font-label-md text-label-md text-on-surface uppercase tracking-widest font-bold mb-0 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-base">location_on</span>
                    <span>Delivery Address</span>
                </h3>
                <a href="<?php echo url('user/location'); ?>" class="text-[11px] text-primary font-bold text-decoration-none hover:underline">
                    Manage &rarr;
                </a>
            </div>

            <?php if (empty($userAddresses)): ?>
                <div class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-center space-y-2">
                    <p class="text-xs text-amber-300 font-bold mb-1">No Location Selected</p>
                    <p class="text-[11px] text-on-surface-variant mb-2">Please select or add your delivery location before placing an order.</p>
                    <a href="<?php echo url('user/location'); ?>" class="px-3 py-1.5 bg-primary text-on-primary font-bold text-xs rounded-lg text-decoration-none inline-flex items-center gap-1 shadow">
                        <span class="material-symbols-outlined text-xs">add_location_alt</span>
                        <span>Select / Add Location</span>
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="space-y-2">
                    <input type="hidden" name="action" value="select_cart_address">
                    <label class="text-[11px] text-on-surface-variant font-bold block mb-1">Deliver to:</label>
                    <select name="address_id" onchange="this.form.submit()" class="w-full bg-surface border border-surface-variant text-on-surface text-xs rounded-xl p-2.5 font-bold outline-none cursor-pointer focus:border-primary">
                        <?php foreach ($userAddresses as $ua): ?>
                            <option value="<?php echo $ua['id']; ?>" <?php echo ($userAddr && $ua['id'] == $userAddr['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ua['label']); ?>: <?php echo htmlspecialchars($ua['address_line1'] . ', ' . $ua['city']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <form id="gpsCartForm" method="POST" action="" class="mt-2">
                    <input type="hidden" name="action" value="use_current_gps_location">
                    <input type="hidden" name="lat" id="gpsCartLat" value="">
                    <input type="hidden" name="lng" id="gpsCartLng" value="">
                    <input type="hidden" name="address_line1" id="gpsCartAddr" value="">
                    <input type="hidden" name="city" id="gpsCartCity" value="">
                    <button type="button" onclick="detectCartGPSLocation()" class="w-full py-2 bg-primary/10 text-primary border border-primary/30 rounded-xl text-xs font-bold flex items-center justify-center gap-2 hover:bg-primary/20 cursor-pointer transition-all">
                        <span class="material-symbols-outlined text-sm">my_location</span>
                        <span>Use Current Location</span>
                    </button>
                </form>
                <?php if ($userAddr): ?>
                    <div class="text-[11px] text-on-surface-variant flex justify-between items-center pt-1 border-t border-surface-variant/40 mt-1">
                        <span>Selected Pin: <strong class="text-on-surface"><?php echo htmlspecialchars($userAddr['address_line1']); ?></strong></span>
                        <?php if ($distanceKm !== null): ?>
                            <span class="font-mono text-primary font-bold"><?php echo $distanceKm; ?> km away</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <!-- Promotions Section -->
        <div class="bg-surface-container border border-surface-variant p-stack-lg flex flex-col gap-stack-md rounded-3 p-4 mb-4">
            <h3 class="font-label-md text-label-md text-on-surface uppercase tracking-widest font-bold mb-3">Apply Coupon</h3>
            
            <?php if ($couponError): ?>
                <div class="text-error font-bold mb-2 text-xs"><?php echo htmlspecialchars($couponError); ?></div>
            <?php endif; ?>
            <?php if ($couponMsg): ?>
                <div class="text-primary font-bold mb-2 text-xs"><?php echo htmlspecialchars($couponMsg); ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="flex gap-base mb-3">
                <input type="hidden" name="action" value="apply_coupon">
                <input class="flex-grow bg-surface border border-surface-variant px-stack-md py-stack-sm text-body-md text-on-surface focus:border-primary-container focus:ring-0 rounded-2 p-2" placeholder="e.g. SUPER50" type="text" name="coupon_code" value="<?php echo htmlspecialchars($appliedCoupon); ?>">
                <button type="submit" class="px-stack-lg py-stack-sm bg-surface-variant text-on-surface font-label-md border border-surface-variant hover:bg-secondary-container transition-colors rounded-2 border-0 fw-bold px-4 cursor-pointer">Apply</button>
            </form>
            
            <?php if ($promo): ?>
                <!-- Active Promo -->
                <div onclick="window.location.href='<?php echo url('user/cart&clear_coupon=1'); ?>'" class="bg-primary-container/10 border border-primary/20 p-stack-md flex items-center justify-between group cursor-pointer hover:bg-primary-container/20 transition-all rounded-2 p-3">
                    <div class="flex items-center gap-stack-md">
                        <span class="material-symbols-outlined text-primary mr-2">local_offer</span>
                        <div>
                            <p class="font-label-md text-label-md text-primary mb-0 font-bold"><?php echo htmlspecialchars($promo['coupon_code']); ?> (Active)</p>
                            <p class="font-label-sm text-label-sm text-on-surface-variant mb-0"><?php echo htmlspecialchars($promo['offer_title']); ?> - Click to Clear</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-primary group-hover:translate-x-1 transition-transform">close</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bill Details Section -->
        <div class="bg-surface-container border border-surface-variant p-stack-lg flex flex-col gap-stack-md rounded-3 p-4">
            <h3 class="font-label-md text-label-md text-on-surface uppercase tracking-widest border-b border-surface-variant pb-stack-sm font-bold pb-3 mb-3">Bill Details</h3>
            <div class="flex justify-between items-center mt-stack-sm mb-2">
                <span class="font-body-md text-body-md text-on-surface-variant">Item Total</span>
                <span class="font-body-md text-body-md text-on-surface font-bold">₹<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="flex justify-between items-center mb-2">
                <span class="font-body-md text-body-md text-on-surface-variant">Delivery Fee</span>
                <span class="font-body-md text-body-md text-on-surface font-bold">₹<?php echo number_format($deliveryFee, 2); ?></span>
            </div>
            <div class="flex justify-between items-center mb-2">
                <div class="flex items-center gap-stack-sm">
                    <span class="font-body-md text-body-md text-on-surface-variant mr-1">Taxes &amp; Charges</span>
                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant">info</span>
                </div>
                <span class="font-body-md text-body-md text-on-surface font-bold">₹<?php echo number_format($taxes, 2); ?></span>
            </div>
            
            <?php if ($discount > 0): ?>
                <div class="flex justify-between items-center text-primary mb-3">
                    <span class="font-body-md text-body-md font-bold">Coupon Applied (<?php echo htmlspecialchars($appliedCoupon); ?>)</span>
                    <span class="font-body-md text-body-md font-bold">-₹<?php echo number_format($discount, 2); ?></span>
                </div>
            <?php endif; ?>

            <div class="mt-stack-md pt-stack-md border-t border-surface-variant flex justify-between items-end pt-3 mb-4">
                <div class="flex flex-col">
                    <span class="font-label-md text-label-md text-on-surface uppercase tracking-tighter font-bold">To Pay</span>
                    <span class="font-headline-lg text-headline-lg text-on-surface font-bold">₹<?php echo number_format($toPay, 2); ?></span>
                </div>
                <?php if ($discount > 0): ?>
                    <span class="font-label-sm text-label-sm text-primary font-bold">Saved ₹<?php echo number_format($discount, 2); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($isOutOfRadius): ?>
                <div class="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-center mb-3">
                    <p class="text-xs text-red-400 font-bold mb-1">Order Disabled — Out of Delivery Radius</p>
                    <a href="<?php echo url('user/location'); ?>" class="text-[11px] text-primary font-bold underline">Update Delivery Location</a>
                </div>
                <button type="button" disabled class="w-full bg-surface-container-high text-on-surface-variant/40 py-3 rounded-2 font-label-md text-label-md font-bold uppercase tracking-widest border border-outline-variant/30 cursor-not-allowed">
                    Out of Delivery Range
                </button>
            <?php else: ?>
                <form method="POST" action="" onsubmit="event.preventDefault(); payWithRazorpay(<?php echo $toPay; ?>, this);">
                    <input type="hidden" name="action" value="checkout">
                    <input type="hidden" name="coupon_code" value="<?php echo htmlspecialchars($appliedCoupon); ?>">
                    <input type="hidden" name="order_note" id="hiddenOrderNote" value="">
                    <button type="submit" <?php echo empty($cartItems) ? 'disabled' : ''; ?> class="w-full bg-primary-container text-on-primary-container py-3 rounded-2 font-label-md text-label-md font-bold uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center gap-stack-md border-0 cursor-pointer">
                        Place Order
                        <span class="material-symbols-outlined ml-2">arrow_forward</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Trust Badges -->
        <div class="flex justify-center gap-stack-lg py-stack-md border-t border-surface-variant mt-4 pt-4">
            <div class="flex flex-col items-center gap-stack-sm mr-4">
                <span class="material-symbols-outlined text-on-surface-variant mb-1">security</span>
                <span class="font-label-sm text-label-sm text-on-surface-variant">Secure Payment</span>
            </div>
            <div class="flex flex-col items-center gap-stack-sm">
                <span class="material-symbols-outlined text-on-surface-variant mb-1">timer</span>
                <span class="font-label-sm text-label-sm text-on-surface-variant">30 Min Delivery</span>
            </div>
        </div>
    </aside>
</main>

<!-- Mobile Navigation Shell -->
<div class="md:hidden fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 py-3 pb-safe bg-surface-container border-t border-surface-variant">
    <a href="<?php echo url('user/home'); ?>" class="flex flex-col items-center justify-center text-on-surface-variant hover:text-on-surface text-decoration-none">
        <span class="material-symbols-outlined">home</span>
        <span class="font-label-sm text-label-sm">Home</span>
    </a>
    <a href="<?php echo url('user/home'); ?>" class="flex flex-col items-center justify-center text-on-surface-variant hover:text-on-surface text-decoration-none">
        <span class="material-symbols-outlined">local_offer</span>
        <span class="font-label-sm text-label-sm">Offers</span>
    </a>
    <a href="<?php echo url('user/orders'); ?>" class="flex flex-col items-center justify-center text-on-surface-variant hover:text-on-surface text-decoration-none">
        <span class="material-symbols-outlined">receipt_long</span>
        <span class="font-label-sm text-label-sm">Orders</span>
    </a>
    <a href="<?php echo url('user/cart'); ?>" class="flex flex-col items-center justify-center text-primary scale-110 transition-transform duration-200 text-decoration-none">
        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">shopping_cart</span>
        <span class="font-label-sm text-label-sm">Cart</span>
    </a>
</div>
</div>

<!-- Simulated Payment Modal -->
<div id="payment-modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px);" class="flex items-center justify-center">
    <div style="background:#1c1c1e; border:1px solid #2d2d2d; border-radius:16px; width:100%; max-width:400px; margin:20px; overflow:hidden;">
        <!-- Modal Header -->
        <div style="background:#ff9f0d; padding:20px 24px;" class="flex items-center gap-3">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
            <div>
                <div style="color:white; font-weight:800; font-size:16px;">TestShare Checkout</div>
                <div style="color:rgba(255,255,255,0.8); font-size:12px;">Secured by Razorpay (Test Mode)</div>
            </div>
        </div>
        <!-- Amount -->
        <div style="padding:20px 24px; border-bottom:1px solid #2d2d2d;">
            <div style="color:#888; font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Amount to Pay</div>
            <div style="color:#ff9f0d; font-size:28px; font-weight:800;" id="modal-amount">₹0</div>
        </div>
        <!-- Payment Methods -->
        <div style="padding:20px 24px;" id="payment-methods-panel">
            <div style="color:#aaa; font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;">Select Payment Method</div>
            <div class="space-y-3">
                <!-- UPI -->
                <label style="display:flex; align-items:center; gap:12px; padding:14px 16px; border:1px solid #2d2d2d; border-radius:10px; cursor:pointer; transition:border-color 0.2s;" class="payment-method-option hover:border-primary">
                    <input type="radio" name="pay_method" value="upi" checked style="accent-color:#ff9f0d;">
                    <span class="material-symbols-outlined" style="color:#ff9f0d;">smartphone</span>
                    <div>
                        <div style="color:#e5e2e1; font-weight:700; font-size:14px;">UPI</div>
                        <div style="color:#888; font-size:12px;">PhonePe, GPay, Paytm, BHIM</div>
                    </div>
                </label>
                <!-- Card -->
                <label style="display:flex; align-items:center; gap:12px; padding:14px 16px; border:1px solid #2d2d2d; border-radius:10px; cursor:pointer; transition:border-color 0.2s;" class="payment-method-option hover:border-primary">
                    <input type="radio" name="pay_method" value="card" style="accent-color:#ff9f0d;">
                    <span class="material-symbols-outlined" style="color:#ff9f0d;">credit_card</span>
                    <div>
                        <div style="color:#e5e2e1; font-weight:700; font-size:14px;">Credit / Debit Card</div>
                        <div style="color:#888; font-size:12px;">Visa, Mastercard, RuPay</div>
                    </div>
                </label>
                <!-- Netbanking -->
                <label style="display:flex; align-items:center; gap:12px; padding:14px 16px; border:1px solid #2d2d2d; border-radius:10px; cursor:pointer; transition:border-color 0.2s;" class="payment-method-option hover:border-primary">
                    <input type="radio" name="pay_method" value="netbanking" style="accent-color:#ff9f0d;">
                    <span class="material-symbols-outlined" style="color:#ff9f0d;">account_balance</span>
                    <div>
                        <div style="color:#e5e2e1; font-weight:700; font-size:14px;">Net Banking</div>
                        <div style="color:#888; font-size:12px;">SBI, HDFC, ICICI, Axis &amp; more</div>
                    </div>
                </label>
            </div>
            <!-- Action Buttons -->
            <div style="display:flex; gap:12px; margin-top:20px;">
                <button onclick="closePaymentModal()" style="flex:1; padding:12px; background:transparent; border:1px solid #2d2d2d; color:#aaa; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px;">Cancel</button>
                <button onclick="simulatePayment()" style="flex:2; padding:12px; background:#ff9f0d; border:0; color:#000; border-radius:10px; font-weight:800; cursor:pointer; font-size:14px;">Pay Now</button>
            </div>
        </div>
        <!-- Processing State (hidden by default) -->
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

function payWithRazorpay(priceUSD, formElement) {
    var noteEl = document.getElementById('cartOrderNote');
    var hiddenNoteEl = document.getElementById('hiddenOrderNote');
    if (noteEl && hiddenNoteEl) {
        hiddenNoteEl.value = noteEl.value;
    }
    if (priceUSD <= 0) {
        formElement.submit();
        return;
    }
    _checkoutForm = formElement;
    var priceINR = Math.round(priceUSD * 83);
    document.getElementById('modal-amount').textContent = '₹' + priceINR.toLocaleString('en-IN');
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
        var method = document.querySelector('input[name="pay_method"]:checked').value;
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

function detectCartGPSLocation() {
    if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            document.getElementById('gpsCartLat').value = lat;
            document.getElementById('gpsCartLng').value = lng;

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => {
                    let street = 'GPS Current Location';
                    let city = 'Current City';
                    if (data && data.address) {
                        street = data.address.road || data.address.suburb || data.address.neighbourhood || data.address.amenity || street;
                        city = data.address.city || data.address.town || data.address.village || data.address.county || city;
                    }
                    document.getElementById('gpsCartAddr').value = street;
                    document.getElementById('gpsCartCity').value = city;
                    document.getElementById('gpsCartForm').submit();
                })
                .catch(() => {
                    document.getElementById('gpsCartAddr').value = 'GPS Location (' + lat.toFixed(4) + ')';
                    document.getElementById('gpsCartCity').value = 'Detected Location';
                    document.getElementById('gpsCartForm').submit();
                });
        }, function(err) {
            alert('GPS Detection Failed: ' + err.message);
        }, { enableHighAccuracy: true });
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}
</script>

<?php view('partials/user_footer'); ?>
