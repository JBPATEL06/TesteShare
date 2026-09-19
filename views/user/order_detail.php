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

$orderId = intval($_GET['id'] ?? 0);
if (!$orderId) {
    $recentStmt = $db->prepare("SELECT id FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
    $recentStmt->execute([$customerId]);
    $orderId = intval($recentStmt->fetchColumn() ?: 0);
}

$order = null;
if ($orderId) {
    $stmt = $db->prepare("
        SELECT o.*, s.store_name, s.address as store_address, s.contact_phone as store_phone
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        WHERE o.id = ? AND o.customer_id = ?
    ");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch();
}

if (!$order) {
    $pageTitle = 'Order Not Found';
    $activeNav = 'orders';
    view('partials/user_header', get_defined_vars());
    ?>
    <main class="flex-grow max-w-4xl mx-auto px-container-margin py-12 w-full my-8 text-center">
        <div class="bg-surface-container border border-outline-variant rounded-2xl p-12 shadow-xl space-y-6">
            <span class="material-symbols-outlined text-[64px] text-primary">lock_reset</span>
            <h2 class="text-headline-lg font-bold text-on-surface">Order Access Denied or Not Found</h2>
            <p class="text-body-lg text-on-surface-variant max-w-md mx-auto mb-4">
                You do not have authorization to view this order, or the order number does not exist in your purchase history.
            </p>
            <a href="<?php echo url('user/orders'); ?>" class="inline-block px-8 py-3 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 transition-all text-decoration-none shadow-lg">
                Return to My Orders
            </a>
        </div>
    </main>
    <?php
    view('partials/user_footer');
    exit;
}

// Load order items
$itemsStmt = $db->prepare("
    SELECT oi.*, m.name as dish_name, m.description as dish_description, m.image_url
    FROM order_items oi
    JOIN menu_items m ON oi.menu_item_id = m.id
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

// Calculations
$subtotal = 0.0;
foreach ($orderItems as $item) {
    $subtotal += ($item['price'] * $item['quantity']);
}
$deliveryFee = 2.99;
$taxes = 1.80;
$discount = $subtotal + $deliveryFee + $taxes - $order['total_amount'];
if ($discount < 0) $discount = 0.0;

$pageTitle = 'Order Details #UA-' . $order['id'];
$activeNav = 'orders';
view('partials/user_header', get_defined_vars());
?>

<main class="flex-grow max-w-7xl mx-auto px-container-margin py-stack-lg w-full my-4">
    <!-- Header with Back Link -->
    <div class="mb-4">
        <a href="<?php echo url('user/orders'); ?>" class="text-primary hover:text-primary-container text-decoration-none d-flex align-items-center gap-1 font-bold mb-3">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Order History
        </a>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left Column: Tracking & Details (Span 7) -->
        <div class="lg:col-span-7 space-y-6">
            
            <!-- Order Header Card -->
            <div class="bg-surface-container border border-outline-variant rounded-3 p-6 mb-4">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 mb-4 pb-4 border-b border-surface-variant">
                    <div>
                        <h1 class="text-headline-md text-on-surface font-bold mb-1">Order #UA-<?php echo $order['id']; ?></h1>
                        <p class="text-label-sm text-on-surface-variant mb-0">Placed on <?php echo date('M d, Y \a\t g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <?php
                    $status = $order['order_status'];
                    $badgeClass = 'bg-yellow-900/30 text-yellow-400 border border-yellow-800';
                    if ($status === 'Completed') {
                        $badgeClass = 'bg-green-900/30 text-green-400 border border-green-800';
                    } elseif ($status === 'Cancelled') {
                        $badgeClass = 'bg-red-900/30 text-red-400 border border-red-800';
                    }
                    ?>
                    <span class="<?php echo $badgeClass; ?> text-label-sm px-4 py-1.5 rounded-full font-bold self-start sm:self-auto flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-circle bg-green-400 animate-pulse"></span>
                        <?php echo $status; ?>
                    </span>
                </div>

                <?php if ($status === 'Cancelled'): ?>
                <div class="bg-error/10 border border-error/40 p-5 rounded-2xl mb-6">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="material-symbols-outlined text-error text-2xl">published_with_changes</span>
                        <h4 class="text-on-surface font-bold text-base mb-0">Order Cancelled & Refund Information</h4>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-3 pt-3 border-t border-error/20 text-xs">
                        <div>
                            <span class="text-on-surface-variant block mb-0.5">Cancelled By</span>
                            <strong class="text-on-surface font-bold"><?php echo htmlspecialchars($order['cancelled_by'] ?? 'Merchant'); ?></strong>
                        </div>
                        <div>
                            <span class="text-on-surface-variant block mb-0.5">Refund Status</span>
                            <strong class="text-green-400 font-bold"><?php echo htmlspecialchars($order['refund_status'] ?? ($order['payment_status'] === 'Paid' ? 'Processed & Refunded' : 'Cancelled Without Charge')); ?></strong>
                        </div>
                        <div>
                            <span class="text-on-surface-variant block mb-0.5">Refund Amount</span>
                            <strong class="text-green-400 font-bold">₹<?php echo number_format(!empty($order['refund_amount']) && floatval($order['refund_amount']) > 0 ? $order['refund_amount'] : $order['total_amount'], 2); ?></strong>
                        </div>
                        <div>
                            <span class="text-on-surface-variant block mb-0.5">Refund Method</span>
                            <strong class="text-on-surface font-bold"><?php echo htmlspecialchars(!empty($order['refund_method']) ? $order['refund_method'] : 'Original Payment Method'); ?></strong>
                        </div>
                    </div>
                    <?php if (!empty($order['cancellation_reason'])): ?>
                        <div class="mt-3 pt-2 text-xs text-on-surface-variant border-t border-error/10">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($order['cancellation_reason']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Progress Tracker -->
                <div class="py-4">
                    <h3 class="text-label-md uppercase tracking-wider text-on-surface-variant font-bold mb-4 font-semibold">Delivery Status</h3>
                    <div class="relative flex flex-col md:flex-row justify-between items-start md:items-center gap-6 md:gap-0">
                        
                        <!-- Timeline connecting bar (Desktop only) -->
                        <div class="hidden md:block absolute top-5 left-[12%] right-[12%] h-1 bg-surface-variant z-0">
                            <div class="h-full bg-primary-container" style="width: 100%;"></div>
                        </div>

                        <!-- Step 1: Placed -->
                        <div class="relative flex md:flex-col items-center gap-4 md:gap-2 z-10 md:w-1/4 text-left md:text-center">
                            <div class="w-10 h-10 rounded-circle bg-primary-container text-on-primary-container flex items-center justify-center font-bold shadow-lg">
                                <span class="material-symbols-outlined text-[20px] filled">check_circle</span>
                            </div>
                            <div>
                                <h4 class="text-label-md text-on-surface font-bold mb-0.5">Order Placed</h4>
                                <p class="text-label-sm text-on-surface-variant mb-0"><?php echo date('g:i A', strtotime($order['created_at'])); ?></p>
                            </div>
                        </div>

                        <!-- Step 2: Preparing -->
                        <?php
                        $isPre = in_array($status, ['Accepted', 'Preparing', 'Out For Delivery', 'Completed']);
                        $bgPre = $isPre ? 'bg-primary-container text-on-primary-container' : 'bg-surface-variant text-on-surface-variant';
                        ?>
                        <div class="relative flex md:flex-col items-center gap-4 md:gap-2 z-10 md:w-1/4 text-left md:text-center">
                            <div class="w-10 h-10 rounded-circle <?php echo $bgPre; ?> flex items-center justify-center font-bold shadow-lg">
                                <span class="material-symbols-outlined text-[20px] filled">restaurant</span>
                            </div>
                            <div>
                                <h4 class="text-label-md text-on-surface font-bold mb-0.5">Prepared</h4>
                                <p class="text-label-sm text-on-surface-variant mb-0"><?php echo $isPre ? 'Completed' : 'Pending'; ?></p>
                            </div>
                        </div>

                        <!-- Step 3: Courier Dispatched -->
                        <?php
                        $isDisp = in_array($status, ['Out For Delivery', 'Completed']);
                        $bgDisp = $isDisp ? 'bg-primary-container text-on-primary-container' : 'bg-surface-variant text-on-surface-variant';
                        ?>
                        <div class="relative flex md:flex-col items-center gap-4 md:gap-2 z-10 md:w-1/4 text-left md:text-center">
                            <div class="w-10 h-10 rounded-circle <?php echo $bgDisp; ?> flex items-center justify-center font-bold shadow-lg">
                                <span class="material-symbols-outlined text-[20px] filled">pedal_bike</span>
                            </div>
                            <div>
                                <h4 class="text-label-md text-on-surface font-bold mb-0.5">Dispatched</h4>
                                <p class="text-label-sm text-on-surface-variant mb-0"><?php echo $isDisp ? 'Completed' : 'Pending'; ?></p>
                            </div>
                        </div>

                        <!-- Step 4: Delivered -->
                        <?php
                        $isDeliv = $status === 'Completed';
                        $bgDeliv = $isDeliv ? 'bg-primary-container text-on-primary-container' : 'bg-surface-variant text-on-surface-variant';
                        ?>
                        <div class="relative flex md:flex-col items-center gap-4 md:gap-2 z-10 md:w-1/4 text-left md:text-center">
                            <div class="w-10 h-10 rounded-circle <?php echo $bgDeliv; ?> flex items-center justify-center font-bold shadow-lg">
                                <span class="material-symbols-outlined text-[20px] filled">sports_motorsports</span>
                            </div>
                            <div>
                                <h4 class="text-label-md text-on-surface font-bold mb-0.5">Delivered</h4>
                                <p class="text-label-sm text-on-surface-variant mb-0"><?php echo $isDeliv ? 'Completed' : 'Pending'; ?></p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Restaurant Summary Card -->
            <div class="bg-surface-container border border-outline-variant rounded-3 p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4 p-4">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2 overflow-hidden bg-surface-container-high shrink-0 border border-outline-variant flex items-center justify-center text-primary" style="width: 64px; height: 64px;">
                        <span class="material-symbols-outlined text-[32px]">restaurant</span>
                    </div>
                    <div>
                        <h2 class="text-headline-md text-on-surface font-bold mb-1"><?php echo htmlspecialchars($order['store_name']); ?></h2>
                        <p class="text-body-md text-on-surface-variant mb-0 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">location_on</span>
                            <?php echo htmlspecialchars($order['store_address']); ?>
                        </p>
                    </div>
                </div>
                <button onclick="window.location.href='tel:<?php echo $order['store_phone']; ?>'" class="px-5 py-2.5 bg-surface-container-high hover:bg-surface-container-highest text-on-surface text-label-md font-bold rounded-2 transition-colors border border-outline-variant flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">phone</span>
                    Contact Restaurant
                </button>
            </div>
        </div>

        <!-- Right Column: Order Items Summary & Receipts (Span 5) -->
        <aside class="lg:col-span-5 space-y-6">
            <div class="bg-surface-container border border-outline-variant rounded-3 p-6 p-4">
                <h2 class="text-label-md uppercase tracking-wider text-on-surface border-b border-surface-variant pb-3 mb-4 font-bold">Order Items</h2>
                
                <!-- Items list -->
                <div class="space-y-4 mb-4">
                    <?php foreach ($orderItems as $item): ?>
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-body-md text-on-surface font-semibold mb-0.5"><?php echo htmlspecialchars($item['dish_name']); ?></h3>
                                <p class="text-label-sm text-on-surface-variant mb-0"><?php echo $item['quantity']; ?>x @ ₹<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                            <span class="text-body-md text-on-surface font-bold">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Invoice Receipts Details -->
                <div class="border-t border-surface-variant pt-4 space-y-2.5">
                    <div class="flex justify-between items-center text-body-md">
                        <span class="text-on-surface-variant">Subtotal</span>
                        <span class="text-on-surface font-bold">₹<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-body-md">
                        <span class="text-on-surface-variant">Delivery Fee</span>
                        <span class="text-on-surface font-bold">₹<?php echo number_format($deliveryFee, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-body-md">
                        <span class="text-on-surface-variant">Taxes & Charges</span>
                        <span class="text-on-surface font-bold">₹<?php echo number_format($taxes, 2); ?></span>
                    </div>
                    <?php if ($discount > 0): ?>
                        <div class="flex justify-between items-center text-body-md text-primary">
                            <span>Coupon Discount</span>
                            <span class="font-bold">-₹<?php echo number_format($discount, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="border-t border-surface-variant pt-3 flex justify-between items-end">
                        <span class="text-label-md uppercase tracking-wider text-on-surface font-bold">Total Paid</span>
                        <span class="text-headline-md text-primary font-bold">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
        </aside>
        
    </div>
</main>

<?php view('partials/user_footer'); ?>
