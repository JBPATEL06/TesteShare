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


// Handle Order Confirmation
if (isset($_GET['action']) && $_GET['action'] === 'confirm_receipt') {
    $orderIdToConfirm = intval($_GET['id'] ?? 0);

    // Fetch order details before updating so we can notify the seller
    $fetchStmt = $db->prepare("
        SELECT o.store_id, o.total_amount, s.owner_id, s.store_name
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        WHERE o.id = ? AND o.customer_id = ? AND o.order_status = 'Delivered'
    ");
    $fetchStmt->execute([$orderIdToConfirm, $customerId]);
    $orderToConfirm = $fetchStmt->fetch();

    if ($orderToConfirm) {
        // Mark order as Completed
        $db->prepare("UPDATE orders SET order_status = 'Completed' WHERE id = ?")
           ->execute([$orderIdToConfirm]);

        // Notify the seller
        $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
        $notifStmt->execute([
            $orderToConfirm['owner_id'],
            '✅ Order Completed — #UA-' . $orderIdToConfirm,
            'Customer confirmed receipt for order #UA-' . $orderIdToConfirm . '. Total: $' . number_format($orderToConfirm['total_amount'], 2) . '. Order is now marked as Completed.'
        ]);
    }

    header("Location: " . url('user/orders'));
    exit;
}

// Load customer orders
$orders = [];
$orderItemsSummary = [];
if ($customerId) {
    $stmt = $db->prepare("
        SELECT o.*, s.store_name, s.category as store_category
        FROM orders o
        JOIN stores s ON o.store_id = s.id
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll();

    $orderIds = array_column($orders, 'id');
    if (!empty($orderIds)) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemsStmt = $db->prepare("
            SELECT oi.*, m.name as dish_name
            FROM order_items oi
            JOIN menu_items m ON oi.menu_item_id = m.id
            WHERE oi.order_id IN ($placeholders)
        ");
        $itemsStmt->execute($orderIds);
        $allOrderItems = $itemsStmt->fetchAll();
        
        foreach ($allOrderItems as $item) {
            $orderItemsSummary[$item['order_id']][] = $item['quantity'] . 'x ' . $item['dish_name'];
        }
    }
}

$pageTitle = 'Order History';
$activeNav = 'orders';
view('partials/user_header', get_defined_vars());
?>

<main class="flex-grow max-w-7xl mx-auto px-container-margin py-stack-lg w-full my-4">
    <div class="flex border-b border-outline-variant mb-stack-lg mb-4">
        <button onclick="window.location.href='<?php echo url('user/orders'); ?>'" class="px-6 py-3 text-label-md font-bold text-primary border-b-2 border-primary transition-colors bg-transparent border-0 pb-3 border-bottom border-2 border-warning mr-3">
            Order History
        </button>
        <button onclick="window.location.href='<?php echo url('user/cart'); ?>'" class="px-6 py-3 text-label-md font-semibold text-on-surface-variant hover:text-on-surface transition-colors bg-transparent border-0 pb-3">
            Cart
        </button>
    </div>
    <header class="mb-stack-lg my-4">
        <h1 class="text-headline-xl text-on-surface font-bold mb-2">Order History</h1>
        <p class="text-on-surface-variant text-body-md mb-0">Manage your past orders and share your feedback.</p>
    </header>
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-gutter my-4">
        <!-- Sidebar / Filters -->
        <aside class="lg:col-span-1 space-y-stack-lg mb-4">
            <div class="bg-surface-container rounded-3 p-6 border border-outline-variant p-4">
                <h2 class="text-label-md uppercase tracking-wider mb-stack-md text-on-surface font-bold mb-3">Filter By</h2>
                <div class="space-y-4">
                    <div class="mb-3">
                        <label class="text-label-sm text-on-surface-variant block mb-2 font-bold">Order Status</label>
                        <select class="w-full bg-surface-container-highest border-outline-variant rounded-2 text-body-md p-2 text-on-surface">
                            <option>All Orders</option>
                            <option>Delivered</option>
                            <option>Cancelled</option>
                            <option>In Progress</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="text-label-sm text-on-surface-variant block mb-2 font-bold">Time Period</label>
                        <select class="w-full bg-surface-container-highest border-outline-variant rounded-2 text-body-md p-2 text-on-surface">
                            <option>Last 3 months</option>
                            <option>Last 6 months</option>
                            <option>2023</option>
                            <option>Earlier</option>
                        </select>
                    </div>
                    <button class="w-full py-2 bg-surface-container-high hover:bg-surface-container-highest text-primary-fixed-dim text-label-md rounded-2 transition-colors border border-outline-variant font-bold uppercase tracking-wider">
                        Clear All Filters
                    </button>
                </div>
            </div>
        </aside>
        <!-- Order List -->
        <section class="lg:col-span-3 space-y-gutter">
            <?php if (empty($orders)): ?>
                <div class="p-5 text-center bg-surface-container border border-outline-variant rounded-3 text-on-surface-variant">You have not placed any orders yet.</div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $status = $order['order_status'];
                    $badgeClass = 'bg-yellow-900/30 text-yellow-400 border border-yellow-800';
                    if ($status === 'Completed') {
                        $badgeClass = 'bg-green-900/30 text-green-400 border border-green-800';
                    } elseif ($status === 'Delivered') {
                        $badgeClass = 'bg-blue-900/30 text-blue-400 border border-blue-800';
                    } elseif ($status === 'Cancelled') {
                        $badgeClass = 'bg-red-900/30 text-red-400 border border-red-800';
                    }
                    $itemsDesc = isset($orderItemsSummary[$order['id']]) ? implode(', ', $orderItemsSummary[$order['id']]) : 'No items';
                    $orderDate = date('M d, Y', strtotime($order['created_at']));
                    ?>
                    <!-- Order Card -->
                    <article class="bg-surface-container rounded-3 border border-outline-variant overflow-hidden mb-4">
                        <div class="p-6 flex flex-col md:flex-row gap-gutter p-4">
                            <div class="w-24 h-24 rounded-2 overflow-hidden bg-surface-container-high shrink-0 mr-4 mb-3 md:mb-0 flex items-center justify-center text-primary" style="width: 96px; height: 96px; border: 1px solid #2d2d2d;">
                                <span class="material-symbols-outlined text-[48px]">restaurant</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="text-headline-md text-on-surface font-bold mb-1"><?php echo htmlspecialchars($order['store_name']); ?></h3>
                                        <p class="text-label-sm text-on-surface-variant mb-0">Order #UA-<?php echo $order['id']; ?> • <?php echo $orderDate; ?></p>
                                    </div>
                                    <span class="<?php echo $badgeClass; ?> text-label-sm px-3 py-1 rounded-full font-bold"><?php echo $status; ?></span>
                                </div>
                                <div class="text-body-md text-on-surface-variant mb-3">
                                    <?php echo htmlspecialchars($itemsDesc); ?>
                                </div>
                                <div class="flex items-center justify-between pt-3 border-t border-surface-variant">
                                    <span class="text-headline-md text-primary font-bold">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                                    <div class="flex gap-2">
                                        <button onclick="window.location.href='<?php echo url('user/order_detail&id=' . $order['id']); ?>'" class="px-4 py-2 border border-outline-variant bg-transparent text-on-surface text-label-md rounded-2 hover:bg-surface-container-high transition-colors font-bold cursor-pointer">
                                            View Details
                                        </button>
                                        <?php if ($status === 'Delivered'): ?>
                                            <button onclick="window.location.href='<?php echo url('user/orders&action=confirm_receipt&id=' . $order['id']); ?>'" class="px-4 py-2 bg-green-600 text-on-primary text-label-md font-bold rounded-2 hover:bg-green-500 transition-colors border-0 font-bold cursor-pointer shadow-md">
                                                Confirm Receipt
                                            </button>
                                        <?php elseif ($status === 'Completed'): ?>
                                            <button onclick="window.location.href='<?php echo url('user/restaurant_storefront&id=' . $order['store_id']); ?>'" class="px-4 py-2 bg-primary text-on-primary text-label-md font-bold rounded-2 hover:bg-primary-fixed transition-colors border-0 font-bold cursor-pointer">
                                                Write a Review
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php view('partials/user_footer'); ?>
