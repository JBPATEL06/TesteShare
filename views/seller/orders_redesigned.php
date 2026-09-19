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
$sellerId = $_SESSION['user_id'] ?? null;

// Resolve store associated with logged-in owner
$storeId = 0;
$myStore = null;
if ($sellerId) {
    $stmtStore = $db->prepare("SELECT * FROM stores WHERE owner_id = ?");
    $stmtStore->execute([$sellerId]);
    $myStore = $stmtStore->fetch();
    if ($myStore) {
        $storeId = $myStore['id'];
    }
}

// Handle status updates
if (isset($_GET['action']) && $_GET['action'] === 'update_status') {
    $orderId = intval($_GET['id'] ?? 0);
    $status = $_GET['status'] ?? '';
    $reason = trim($_GET['reason'] ?? $_POST['reason'] ?? 'Merchant unavailable / Item out of stock');
    
    // Ensure the order belongs to this store
    $stmt = $db->prepare("SELECT o.id, o.customer_id, o.total_amount, o.payment_status, s.store_name FROM orders o JOIN stores s ON o.store_id = s.id WHERE o.id = ? AND o.store_id = ?");
    $stmt->execute([$orderId, $storeId]);
    $orderRow = $stmt->fetch();

    if ($orderRow && in_array($status, ['Pending', 'Accepted', 'Preparing', 'Out For Delivery', 'Delivered', 'Cancelled'])) {
        if ($status === 'Cancelled') {
            $wasPaid = ($orderRow['payment_status'] === 'Paid');
            $refundStatus = $wasPaid ? 'Processed & Refunded' : 'Cancelled Without Charge';
            $refundMethod = $wasPaid ? 'Original Payment Method / Bank Transfer' : 'N/A';
            $paymentStatus = $wasPaid ? 'Refunded' : $orderRow['payment_status'];
            
            $upStmt = $db->prepare("
                UPDATE orders 
                SET order_status = 'Cancelled',
                    payment_status = ?,
                    refund_status = ?,
                    refund_amount = ?,
                    refund_method = ?,
                    cancelled_by = 'Seller',
                    cancellation_reason = ?
                WHERE id = ?
            ");
            $upStmt->execute([$paymentStatus, $refundStatus, floatval($orderRow['total_amount']), $refundMethod, $reason, $orderId]);

            $refundText = $wasPaid 
                ? 'Full refund of ₹' . number_format($orderRow['total_amount'], 2) . ' has been processed back to your original payment method.' 
                : 'No charges were incurred.';

            $notifStmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'Order Refund', 0)");
            $notifStmt->execute([
                $orderRow['customer_id'], 
                '❌ Order Cancelled & Refunded — #UA-' . $orderId, 
                'Your order #UA-' . $orderId . ' from ' . $orderRow['store_name'] . ' was cancelled by the merchant. Reason: ' . $reason . '. ' . $refundText
            ]);
        } else {
            $upStmt = $db->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
            $upStmt->execute([$status, $orderId]);

            $notifMap = [
                'Accepted'        => ['✅ Order Accepted — #UA-' . $orderId,         'Your order from ' . $orderRow['store_name'] . ' has been accepted and is being prepared.'],
                'Preparing'       => ['👨‍🍳 Order Being Prepared — #UA-' . $orderId,  $orderRow['store_name'] . ' is now preparing your order. Estimated time: 20-30 mins.'],
                'Out For Delivery'=> ['🛵 Order Out For Delivery — #UA-' . $orderId, 'Your order from ' . $orderRow['store_name'] . ' is on the way! Track your delivery.'],
                'Delivered'       => ['📦 Order Delivered — #UA-' . $orderId,        'Your order from ' . $orderRow['store_name'] . ' has been delivered. Please confirm receipt in your Order History to complete the order.'],
            ];

            if (isset($notifMap[$status])) {
                [$title, $message] = $notifMap[$status];
                $notifStmt = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'Order Update', 0)");
                $notifStmt->execute([$orderRow['customer_id'], $title, $message]);
            }
        }
    }
    
    header("Location: " . url('seller/orders_redesigned'));
    exit;
}

// Handle live poll — JS fetches this every 20s to detect new orders without a full page reload
if (isset($_GET['action']) && $_GET['action'] === 'poll_count') {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = ?");
    $countStmt->execute([$storeId]);
    $totalCount = (int) $countStmt->fetchColumn();

    $pendingStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE store_id = ? AND order_status = 'Pending'");
    $pendingStmt->execute([$storeId]);
    $pendingCount = (int) $pendingStmt->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode(['count' => $totalCount, 'pending' => $pendingCount]);
    exit;
}

// Handle Orders CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && $storeId) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="seller_orders_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Customer Name', 'Customer Email', 'Total Amount', 'Payment Status', 'Order Status', 'Date & Time']);
    
    $exportStmt = $db->prepare("
        SELECT o.id, u.fullname as customer_name, u.email as customer_email, o.total_amount, o.payment_status, o.order_status, o.created_at
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        WHERE o.store_id = ?
        ORDER BY o.created_at DESC
    ");
    $exportStmt->execute([$storeId]);
    $rows = $exportStmt->fetchAll();
    
    foreach ($rows as $row) {
        fputcsv($output, [
            '#UA-' . $row['id'],
            $row['customer_name'],
            $row['customer_email'],
            '₹' . number_format($row['total_amount'], 2),
            $row['payment_status'],
            $row['order_status'],
            date('M d, Y H:i', strtotime($row['created_at']))
        ]);
    }
    fclose($output);
    exit;
}

$ordersStmt = $db->prepare("
    SELECT o.*, u.fullname as customer_name, u.email as customer_email
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    WHERE o.store_id = ?
    ORDER BY o.created_at DESC
");
$ordersStmt->execute([$storeId]);
$orders = $ordersStmt->fetchAll();

// Fetch items summary for each order
$orderSummaries = [];
foreach ($orders as $o) {
    $itemsStmt = $db->prepare("
        SELECT oi.*, m.name as dish_name
        FROM order_items oi
        JOIN menu_items m ON oi.menu_item_id = m.id
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$o['id']]);
    $items = $itemsStmt->fetchAll();
    
    $summary = [];
    foreach ($items as $it) {
        $summary[] = $it['quantity'] . 'x ' . $it['dish_name'];
    }
    $orderSummaries[$o['id']] = implode(', ', $summary);
}

$pageTitle = 'Orders Panel';
$activeNav = 'orders_redesigned';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

    <!-- Main Content Area -->
    <div class="flex-grow p-8 overflow-y-auto flex flex-col justify-between">
        <!-- Order Management Content -->
        <div class="max-w-[1600px] mx-auto w-full">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h2 class="font-headline-lg text-on-surface font-bold mb-1">Order Management</h2>
                    <p class="text-body-md text-on-surface-variant mb-0">Live updates and history for your store's performance.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="exportOrdersCSV()" class="bg-surface-container border border-outline-variant px-4 py-2 rounded-lg text-label-md flex items-center gap-2 hover:bg-surface-container-high transition-colors text-on-surface font-bold">
                        <span class="material-symbols-outlined text-sm">download</span> Export CSV
                    </button>
                    <button onclick="window.location.reload()" class="bg-primary-container text-on-primary-container font-bold px-4 py-2 rounded-lg text-label-md flex items-center gap-2 hover:opacity-90 transition-opacity border-0 font-bold">
                        <span class="material-symbols-outlined text-sm">refresh</span> Refresh Live
                    </button>
                </div>
            </div>
            
            <!-- Filters Bar -->
            <div class="bg-surface-container p-4 rounded-xl border border-outline-variant mb-6 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2 bg-surface-container-low border border-outline-variant rounded-lg px-3 py-1.5">
                    <span class="material-symbols-outlined text-on-surface-variant text-[20px]">filter_list</span>
                    <span class="font-label-sm text-on-surface-variant font-bold">Filter by:</span>
                    <select onchange="filterOrdersStatus(this.value)" class="bg-transparent border-none focus:ring-0 text-label-md p-0 cursor-pointer text-on-surface font-bold">
                        <option value="ALL">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="Preparing">Preparing</option>
                        <option value="Out For Delivery">Out For Delivery</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 bg-surface-container-low border border-outline-variant rounded-lg px-3 py-1.5">
                    <span class="material-symbols-outlined text-on-surface-variant text-[20px]">calendar_today</span>
                    <select class="bg-transparent border-none focus:ring-0 text-label-md p-0 cursor-pointer text-on-surface font-bold">
                        <option>Today</option>
                        <option>Yesterday</option>
                        <option>Last 7 Days</option>
                        <option>Last 30 Days</option>
                    </select>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <div class="flex items-center gap-2 px-4 py-1.5 bg-primary-container/10 border border-primary-container/30 rounded-full">
                        <div class="w-2 h-2 bg-primary-container rounded-full animate-pulse"></div>
                        <span class="text-label-sm text-primary-container font-bold mb-0">Live Sync Active</span>
                    </div>
                </div>
            </div>

            <!-- Orders Table Container -->
            <div class="bg-surface-container rounded-xl border border-outline-variant overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="ordersTable">
                        <thead>
                            <tr class="bg-surface-container-high border-b border-outline-variant">
                                <th class="px-6 py-4 font-label-md text-on-surface-variant uppercase tracking-wider font-bold">Order ID</th>
                                <th class="px-6 py-4 font-label-md text-on-surface-variant uppercase tracking-wider font-bold">Customer</th>
                                <th class="px-6 py-4 font-label-md text-on-surface-variant uppercase tracking-wider font-bold">Items Summary</th>
                                <th class="px-6 py-4 font-label-md text-on-surface-variant uppercase tracking-wider font-bold">Total</th>
                                <th class="px-6 py-4 font-label-md text-on-surface-variant uppercase tracking-wider font-bold">Status</th>
                                <th class="px-6 py-4 font-label-md text-on-surface-variant uppercase tracking-wider font-bold">Time</th>
                                <th class="px-6 py-4 font-label-md text-on-surface-variant uppercase tracking-wider text-right font-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/30">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-on-surface-variant">No orders found for this store.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $ord): ?>
                                    <?php
                                    $summary = $orderSummaries[$ord['id']] ?? 'No items';
                                    $status = $ord['order_status'];
                                    
                                    // Set badge details
                                    $badgeClass = 'bg-primary-container/20 text-primary-container border border-primary-container/30';
                                    $dotColor = 'bg-primary-container';
                                    if ($status === 'Preparing') {
                                        $badgeClass = 'bg-tertiary-container/20 text-tertiary-container border border-tertiary-container/30';
                                        $dotColor = 'bg-tertiary';
                                    } elseif ($status === 'Out For Delivery') {
                                        $badgeClass = 'bg-blue-500/20 text-blue-400 border border-blue-500/30';
                                        $dotColor = 'bg-blue-400';
                                    } elseif ($status === 'Completed') {
                                        $badgeClass = 'bg-green-500/20 text-green-400 border border-green-500/30';
                                        $dotColor = 'bg-green-400';
                                    } elseif ($status === 'Cancelled') {
                                        $badgeClass = 'bg-error-container/20 text-error border border-error-container/30';
                                        $dotColor = 'bg-error';
                                    }
                                    
                                    // Determine action button
                                    $actionBtn = '';
                                    if ($status === 'Pending') {
                                        $actionBtn = '
                                            <button onclick="changeStatus(\'' . $ord['id'] . '\', \'Preparing\')" class="bg-primary-container text-on-primary-container px-3 py-1.5 rounded-lg text-label-sm font-bold hover:opacity-90 border-0 cursor-pointer">Accept</button>
                                            <button onclick="changeStatus(\'' . $ord['id'] . '\', \'Cancelled\')" class="bg-error/20 text-error px-3 py-1.5 rounded-lg text-label-sm font-bold hover:bg-error/30 border border-error/30 cursor-pointer">Decline</button>
                                        ';
                                    } elseif ($status === 'Accepted') {
                                        $actionBtn = '<button onclick="changeStatus(\'' . $ord['id'] . '\', \'Preparing\')" class="bg-primary-container text-on-primary-container px-3 py-1.5 rounded-lg text-label-sm font-bold hover:opacity-90 border-0 cursor-pointer">Start Prep</button>';
                                    } elseif ($status === 'Preparing') {
                                        $actionBtn = '<button onclick="changeStatus(\'' . $ord['id'] . '\', \'Out For Delivery\')" class="bg-tertiary text-on-tertiary px-3 py-1.5 rounded-lg text-label-sm font-bold hover:opacity-90 border-0 cursor-pointer">Dispatch</button>';
                                    } elseif ($status === 'Out For Delivery') {
                                        $actionBtn = '<button onclick="changeStatus(\'' . $ord['id'] . '\', \'Delivered\')" class="bg-surface-container-highest text-on-surface px-3 py-1.5 rounded-lg text-label-sm font-bold hover:bg-surface-variant border border-outline-variant cursor-pointer">Delivered</button>';
                                    } elseif ($status === 'Delivered') {
                                        $actionBtn = '<span class="text-label-sm text-on-surface-variant font-bold italic">Awaiting Customer</span>';
                                    }
                                    ?>
                                    <tr class="order-row transition-colors <?php echo $status === 'Pending' ? 'order-flash' : ''; ?>" data-status="<?php echo $status; ?>">
                                        <td class="px-6 py-4">
                                            <span class="font-bold text-primary">#UA-<?php echo $ord['id']; ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="h-8 w-8 rounded-full bg-surface-container-highest flex items-center justify-center font-bold text-primary" style="width: 32px; height: 32px; border: 1px solid #444;">
                                                    <?php echo substr($ord['customer_name'], 0, 2); ?>
                                                </div>
                                                <span class="font-label-md font-bold"><?php echo htmlspecialchars($ord['customer_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-body-md truncate max-w-[200px] mb-0"><?php echo htmlspecialchars($summary); ?></p>
                                            <?php if (!empty($ord['notes'])): ?>
                                                <span class="inline-flex items-center gap-1 mt-1 text-[11px] bg-amber-500/10 text-amber-400 border border-amber-500/20 px-2 py-0.5 rounded font-bold" title="<?php echo htmlspecialchars($ord['notes']); ?>">
                                                    <span class="material-symbols-outlined text-[13px]">sticky_note_2</span>
                                                    <span>Note: <?php echo htmlspecialchars(mb_strimwidth($ord['notes'], 0, 25, '...')); ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="font-bold">₹<?php echo number_format($ord['total_amount'], 2); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full <?php echo $badgeClass; ?> text-label-sm font-bold status-badge">
                                                <span class="w-1.5 h-1.5 rounded-full <?php echo $dotColor; ?>"></span> <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="text-label-md text-on-surface-variant font-bold"><?php echo date('H:i', strtotime($ord['created_at'])); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button onclick="openStatusModal('<?php echo $ord['id']; ?>', '<?php echo htmlspecialchars($ord['customer_name']); ?>', '<?php echo htmlspecialchars($summary); ?>', <?php echo $ord['total_amount']; ?>, '<?php echo date('H:i', strtotime($ord['created_at'])); ?>', '<?php echo $status; ?>', '<?php echo addslashes(htmlspecialchars($ord['notes'] ?? '')); ?>', this)" class="p-2 hover:bg-surface-container-highest rounded-lg transition-colors text-on-surface-variant bg-transparent border-0 cursor-pointer" title="View Details">
                                                    <span class="material-symbols-outlined">visibility</span>
                                                </button>
                                                <?php echo $actionBtn; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="w-full border-t border-outline-variant bg-background shrink-0 mt-12 pt-6">
            <div class="flex flex-col md:flex-row justify-between items-center px-8 py-4 w-full max-w-[1600px] mx-auto">
                <p class="font-body-sm text-body-sm text-on-surface-variant mb-0">© 2024 TestShare Enterprise. All rights reserved.</p>
                <div class="flex gap-6 mt-4 md:mt-0">
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Privacy Policy</a>
                    <a class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors text-decoration-none" href="#">Terms of Service</a>
                </div>
            </div>
        </footer>
    </div>

<!-- Interactive Status Management Modal -->
<div id="statusModal" class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-surface-container border border-outline-variant max-w-lg w-full rounded-2xl shadow-2xl p-6 space-y-6">
        <div class="flex justify-between items-center border-b border-outline-variant pb-3">
            <h3 class="font-bold text-lg text-primary mb-0" id="modalOrderTitle">Order Status Management</h3>
            <button onclick="closeStatusModal()" class="text-on-surface-variant hover:text-on-surface bg-transparent border-0 cursor-pointer">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4 text-sm text-on-surface">
                <div>
                    <span class="text-on-surface-variant font-bold block mb-1">Customer Name:</span>
                    <span class="font-semibold" id="modalCustomerName">John Doe</span>
                </div>
                <div>
                    <span class="text-on-surface-variant font-bold block mb-1">Placed Time:</span>
                    <span class="font-semibold" id="modalOrderTime">2 mins ago</span>
                </div>
            </div>
            <div class="text-sm text-on-surface">
                <span class="text-on-surface-variant font-bold block mb-1">Items Summary:</span>
                <span class="font-semibold" id="modalItemsSummary">2x Truffle Burger, 1x Cajun Fries</span>
            </div>

            <!-- Customer Note Box -->
            <div class="text-sm text-on-surface" id="modalNoteContainer" style="display:none;">
                <span class="text-amber-400 font-bold block mb-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">sticky_note_2</span> Customer Note / Instructions:
                </span>
                <div class="p-3 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-200 font-bold text-xs" id="modalCustomerNote"></div>
            </div>

            <!-- Refund / Cancellation Info Box -->
            <div class="text-sm text-on-surface" id="modalRefundContainer" style="display:none;">
                <span class="text-error font-bold block mb-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">published_with_changes</span> Cancellation &amp; Refund Details:
                </span>
                <div class="p-3 bg-error/10 border border-error/30 rounded-xl text-xs text-error font-bold space-y-1" id="modalRefundDetails"></div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm text-on-surface">
                <div>
                    <span class="text-on-surface-variant font-bold block mb-1">Total Bill:</span>
                    <span class="text-primary font-bold text-base" id="modalOrderTotal">₹42.50</span>
                </div>
                <div>
                    <span class="text-on-surface-variant font-bold block mb-1">Current Status:</span>
                    <span class="font-semibold uppercase tracking-wider" id="modalCurrentStatus">New</span>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3 border-t border-outline-variant pt-4" id="modalActions">
            <!-- Dynamic Actions populated by JS -->
        </div>
    </div>
</div>

<script>
    // ── Modal ──────────────────────────────────────────────────
    function openStatusModal(orderId, customer, items, total, time, status, note, btn) {
        document.getElementById('modalOrderTitle').innerText = 'Order #UA-' + orderId;
        document.getElementById('modalCustomerName').innerText = customer;
        document.getElementById('modalOrderTime').innerText = time;
        document.getElementById('modalItemsSummary').innerText = items;
        document.getElementById('modalOrderTotal').innerText = '₹' + parseFloat(total).toFixed(2);
        document.getElementById('modalCurrentStatus').innerText = status;
        
        if (note && note.trim() !== '') {
            document.getElementById('modalCustomerNote').innerText = note;
            document.getElementById('modalNoteContainer').style.display = 'block';
        } else {
            document.getElementById('modalNoteContainer').style.display = 'none';
        }

        updateActionsHtml(orderId, status);
        const modal = document.getElementById('statusModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeStatusModal() {
        const modal = document.getElementById('statusModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function updateActionsHtml(orderId, status) {
        let html = '';
        if (status === 'Pending') {
            html = `
                <button onclick="changeStatus('${orderId}', 'Preparing')"
                    class="flex-1 px-4 py-2.5 bg-primary-container text-on-primary-container font-bold text-xs uppercase tracking-wider rounded-lg border-0 cursor-pointer hover:opacity-90 transition-opacity">
                    ✓ Accept Order
                </button>
                <button onclick="changeStatus('${orderId}', 'Cancelled')"
                    class="flex-1 px-4 py-2.5 bg-error/20 text-error font-bold text-xs uppercase tracking-wider rounded-lg border border-error/40 cursor-pointer hover:bg-error/30 transition-colors">
                    ✕ Decline Order
                </button>`;
        } else if (status === 'Accepted') {
            html = `
                <button onclick="changeStatus('${orderId}', 'Preparing')"
                    class="flex-1 px-4 py-2.5 bg-primary-container text-on-primary-container font-bold text-xs uppercase tracking-wider rounded-lg border-0 cursor-pointer hover:opacity-90">
                    Start Preparing
                </button>`;
        } else if (status === 'Preparing') {
            html = `
                <button onclick="changeStatus('${orderId}', 'Out For Delivery')"
                    class="flex-1 px-4 py-2.5 bg-tertiary text-on-tertiary font-bold text-xs uppercase tracking-wider rounded-lg border-0 cursor-pointer hover:opacity-90">
                    Dispatch Order
                </button>`;
        } else if (status === 'Out For Delivery') {
            html = `
                <button onclick="changeStatus('${orderId}', 'Delivered')"
                    class="flex-1 px-4 py-2.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 cursor-pointer hover:opacity-90">
                    Mark as Delivered
                </button>`;
        } else if (status === 'Delivered') {
            html = `<div class="flex-1 text-center py-2.5 bg-surface-container border border-outline-variant text-on-surface-variant font-bold text-xs uppercase tracking-wider rounded-lg">Awaiting Customer Confirmation</div>`;
        } else if (status === 'Completed') {
            html = `<div class="flex-1 text-center py-2.5 bg-green-900/30 text-green-400 border border-green-800 font-bold text-xs uppercase tracking-wider rounded-lg">✓ Order Completed</div>`;
        } else if (status === 'Cancelled') {
            html = `<div class="flex-1 text-center py-2.5 bg-error/10 text-error border border-error/30 font-bold text-xs uppercase tracking-wider rounded-lg">Order Cancelled</div>`;
        }
        document.getElementById('modalActions').innerHTML = html;
    }

    function changeStatus(orderId, nextStatus) {
        if (nextStatus === 'Cancelled') {
            let reason = prompt('Please enter cancellation reason (sent to customer with refund details):', 'Merchant unavailable / Item out of stock');
            if (reason === null) return;
            closeStatusModal();
            window.location.href = '<?php echo url("seller/orders_redesigned&action=update_status&id="); ?>' + orderId + '&status=Cancelled&reason=' + encodeURIComponent(reason);
            return;
        }
        closeStatusModal();
        window.location.href = '<?php echo url("seller/orders_redesigned&action=update_status&id="); ?>' + orderId + '&status=' + encodeURIComponent(nextStatus);
    }

    function filterOrdersStatus(val) {
        document.querySelectorAll('#ordersTable tbody tr.order-row').forEach(row => {
            row.style.display = (val === 'ALL' || row.dataset.status === val) ? '' : 'none';
        });
    }

    function exportOrdersCSV() {
        window.location.href = '<?php echo url("seller/orders_redesigned&action=export_csv"); ?>';
    }

    // ── Live polling — check for new Pending orders every 20 seconds ──
    let _knownOrderCount = <?php echo count($orders); ?>;
    let _pollPaused = false;

    function pollNewOrders() {
        if (_pollPaused) return;
        fetch('<?php echo url("seller/orders_redesigned&action=poll_count"); ?>')
            .then(r => r.json())
            .then(data => {
                if (data.count > _knownOrderCount) {
                    _knownOrderCount = data.count;
                    showNewOrderBanner(data.pending);
                }
            })
            .catch(() => {}); // silent fail — no connection = no banner
    }

    function showNewOrderBanner(pendingCount) {
        const existing = document.getElementById('new-order-banner');
        if (existing) existing.remove();
        const banner = document.createElement('div');
        banner.id = 'new-order-banner';
        banner.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;background:#ff9f0d;color:#000;padding:12px 24px;border-radius:12px;font-weight:800;font-size:14px;box-shadow:0 8px 32px rgba(255,159,13,0.4);cursor:pointer;animation:slideDown 0.3s ease;display:flex;align-items:center;gap:10px;';
        banner.innerHTML = `<span style="font-size:20px;">🛎</span> ${pendingCount} new order${pendingCount > 1 ? 's' : ''} received! <span style="text-decoration:underline;margin-left:6px;">Refresh</span>`;
        banner.onclick = () => window.location.reload();
        document.body.appendChild(banner);
        setTimeout(() => { if (banner.parentNode) banner.remove(); }, 8000);
    }

    setInterval(pollNewOrders, 20000);
</script>
<style>
@keyframes slideDown { from { opacity:0; top:0; } to { opacity:1; top:16px; } }
</style>
<?php view('partials/seller_footer'); ?>
