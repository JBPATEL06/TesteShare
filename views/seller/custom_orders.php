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

if (!$sellerId || !$storeId) {
    header("Location: " . url('seller/login'));
    exit;
}

// Feature gate check: Premium or Ultra Premium required
if (!hasFeature($storeId, 'custom_orders')) {
    $pageTitle = 'Custom & Bulk Orders — Premium Feature';
    $activeNav = 'custom_orders';
    view('partials/seller_header', get_defined_vars());
    view('partials/seller_sidebar', get_defined_vars());
    ?>
    <div class="flex-grow p-8 overflow-y-auto">
        <div class="max-w-4xl mx-auto py-12 text-center bg-surface-container border border-outline-variant rounded-2xl shadow-xl p-8 space-y-6">
            <span class="material-symbols-outlined text-[64px] text-primary">contract</span>
            <h2 class="font-headline-lg text-on-surface font-bold">Custom &amp; Bulk Culinary Orders</h2>
            <p class="text-body-lg text-on-surface-variant max-w-lg mx-auto">
                Receiving custom dish requests, catering negotiations, and bulk culinary orders from customers is exclusively available to <strong class="text-primary">Premium</strong> and <strong class="text-amber-400">Ultra Premium</strong> merchants.
            </p>
            <a href="<?php echo url('seller/subscription'); ?>" class="inline-block px-8 py-3 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 transition-all text-decoration-none shadow-lg">
                Upgrade to Premium ($49/mo)
            </a>
        </div>
    </div>
    <?php
    view('partials/seller_footer');
    exit;
}

// 1. Handle Add Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $orderId = intval($_POST['order_id']);
    $noteText = trim($_POST['note_text'] ?? '');
    if ($noteText) {
        $stmt = $db->prepare("SELECT notes FROM custom_orders WHERE id = ? AND store_id = ?");
        $stmt->execute([$orderId, $storeId]);
        $order = $stmt->fetch();
        if ($order) {
            $notes = json_decode($order['notes'] ?: '[]', true);
            $notes[] = [
                'sender_role' => 'seller',
                'note_text' => $noteText,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $upd = $db->prepare("UPDATE custom_orders SET notes = ? WHERE id = ?");
            $upd->execute([json_encode($notes), $orderId]);
        }
    }
    header("Location: " . url('seller/custom_orders'));
    exit;
}

// 2. Handle Accept Budget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_budget') {
    $orderId = intval($_POST['order_id']);
    $stmt = $db->prepare("SELECT notes, offered_price, customer_id, dish_name FROM custom_orders WHERE id = ? AND store_id = ?");
    $stmt->execute([$orderId, $storeId]);
    $order = $stmt->fetch();
    if ($order) {
        $notes = json_decode($order['notes'] ?: '[]', true);
        $notes[] = [
            'sender_role' => 'seller',
            'note_text' => "Chef accepted your offered budget of $" . number_format($order['offered_price'], 2) . ". Preparing order!",
            'created_at' => date('Y-m-d H:i:s')
        ];
        $upd = $db->prepare("UPDATE custom_orders SET status = 'Accepted', demanded_price = ?, notes = ? WHERE id = ?");
        $upd->execute([$order['offered_price'], json_encode($notes), $orderId]);

        // Notify customer
        $notif = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type) VALUES (?, ?, ?, 'Custom Order')");
        $notif->execute([
            $order['customer_id'],
            '✅ Custom Order Accepted — #CO-' . $orderId,
            'Great news! The chef accepted your budget of $' . number_format($order['offered_price'], 2) . ' for "' . $order['dish_name'] . '". Your order is now being prepared.'
        ]);
    }
    header("Location: " . url('seller/custom_orders'));
    exit;
}

// 3. Handle Demand Price (Quote)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'demand_price') {
    $orderId = intval($_POST['order_id']);
    $quote = floatval($_POST['demanded_price'] ?? 0);
    if ($quote > 0) {
        $stmt = $db->prepare("SELECT notes, customer_id, dish_name, offered_price FROM custom_orders WHERE id = ? AND store_id = ?");
        $stmt->execute([$orderId, $storeId]);
        $order = $stmt->fetch();
        if ($order) {
            $notes = json_decode($order['notes'] ?: '[]', true);
            $notes[] = [
                'sender_role' => 'seller',
                'note_text' => "Chef requested a custom price quote: $" . number_format($quote, 2) . ".",
                'created_at' => date('Y-m-d H:i:s')
            ];
            $upd = $db->prepare("UPDATE custom_orders SET status = 'Quote Sent', demanded_price = ?, notes = ? WHERE id = ?");
            $upd->execute([$quote, json_encode($notes), $orderId]);

            // Notify customer
            $notif = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type) VALUES (?, ?, ?, 'Custom Order')");
            $notif->execute([
                $order['customer_id'],
                '💬 Chef Sent a Price Quote — #CO-' . $orderId,
                'The chef reviewed your custom order "' . $order['dish_name'] . '" and sent a new price of $' . number_format($quote, 2) . ' (your offer: $' . number_format($order['offered_price'], 2) . '). Open your Custom Orders tracker to accept or decline.'
            ]);
        }
    }
    header("Location: " . url('seller/custom_orders'));
    exit;
}

// 4. Handle Reject Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_request') {
    $orderId = intval($_POST['order_id']);
    $stmt = $db->prepare("SELECT notes, customer_id, dish_name FROM custom_orders WHERE id = ? AND store_id = ?");
    $stmt->execute([$orderId, $storeId]);
    $order = $stmt->fetch();
    if ($order) {
        $notes = json_decode($order['notes'] ?: '[]', true);
        $notes[] = [
            'sender_role' => 'seller',
            'note_text' => "Chef declined this custom order request.",
            'created_at' => date('Y-m-d H:i:s')
        ];
        $upd = $db->prepare("UPDATE custom_orders SET status = 'Rejected', notes = ? WHERE id = ?");
        $upd->execute([json_encode($notes), $orderId]);

        // Notify customer
        $notif = $db->prepare("INSERT INTO user_notifications (user_id, title, message, type) VALUES (?, ?, ?, 'Custom Order')");
        $notif->execute([
            $order['customer_id'],
            '❌ Custom Order Declined — #CO-' . $orderId,
            'Unfortunately the chef declined your custom order request for "' . $order['dish_name'] . '". You can submit a new request with adjusted specifications.'
        ]);
    }
    header("Location: " . url('seller/custom_orders'));
    exit;
}

// Fetch all store orders from database
$stmt = $db->prepare("SELECT * FROM custom_orders WHERE store_id = ? ORDER BY id DESC");
$stmt->execute([$storeId]);
$dbOrders = $stmt->fetchAll();

$pageTitle = 'Custom Orders Management';
$activeNav = 'custom_orders';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto w-full flex flex-col justify-between" style="min-height: 80vh;">
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="mb-8 border-b border-outline-variant pb-4">
                <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Manage Custom Orders</h2>
                <p class="text-body-md text-on-surface-variant mb-0">Review custom recipes, ingredient requests, and budget offers. Respond with your demanded price.</p>
            </div>

            <!-- Custom Orders Dynamic List -->
            <div id="seller-orders-list" class="space-y-6">
                <!-- Custom order cards will be rendered dynamically here -->
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

<script>
    // State management loading from Database
    const dbOrders = <?php echo json_encode($dbOrders); ?>;

    function getOrders() {
        return dbOrders.map(order => {
            let attachments = {};
            let notes = [];
            try {
                attachments = typeof order.attachments === 'string' ? JSON.parse(order.attachments) : (order.attachments || {});
            } catch(e) { attachments = {}; }
            try {
                notes = typeof order.notes === 'string' ? JSON.parse(order.notes) : (order.notes || []);
            } catch(e) { notes = {}; }
            
            return {
                id: order.id,
                dish_name: order.dish_name,
                ingredients: order.ingredients,
                offered_price: parseFloat(order.offered_price),
                demanded_price: order.demanded_price ? parseFloat(order.demanded_price) : null,
                image_source_type: attachments.image ? attachments.image.source_type : 'upload',
                image_url: attachments.image ? attachments.image.url : '',
                image_path: attachments.image ? attachments.image.path : '',
                doc_source_type: attachments.doc ? attachments.doc.source_type : 'upload',
                doc_url: attachments.doc ? attachments.doc.url : '',
                doc_path: attachments.doc ? attachments.doc.path : '',
                video_url: attachments.video_url || '',
                delivery_date: order.delivery_date,
                delivery_time: order.delivery_time ? order.delivery_time.substring(0, 5) : "00:00",
                status: order.status,
                created_at: order.created_at,
                notes: notes
            };
        });
    }

    // Render logic
    function renderOrders() {
        const orders = getOrders();
        const container = document.getElementById('seller-orders-list');
        
        if (orders.length === 0) {
            container.innerHTML = `
                <div class="bg-surface-container-low border border-outline-variant p-12 rounded-2xl text-center shadow-xl">
                    <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-2">description_alert</span>
                    <h3 class="font-bold text-on-surface">No Custom Requests</h3>
                    <p class="text-on-surface-variant text-xs mb-0">No customers have placed any custom requests for your store yet.</p>
                </div>`;
            return;
        }

        container.innerHTML = '';
        orders.forEach(order => {
            let statusBadge = '';
            let budgetPriceDisplay = '';
            let actionArea = '';

            // Status Badge & Price Display Setup
            if (order.status === 'Pending') {
                statusBadge = `<span class="bg-amber-600/20 text-amber-400 border border-amber-500/30 text-xs font-bold px-2.5 py-1 rounded uppercase tracking-wider">Pending Review</span>`;
                budgetPriceDisplay = `
                    <span class="text-xs text-on-surface-variant block">Offered Budget</span>
                    <span class="text-xl font-bold text-primary">$${parseFloat(order.offered_price).toFixed(2)}</span>`;
                
                actionArea = `
                    <div class="bg-surface-container border border-outline-variant p-4 rounded-xl flex flex-col md:flex-row justify-between items-center gap-4">
                        <form method="POST" action="" class="flex-grow w-full md:w-auto flex flex-col md:flex-row items-center gap-4">
                            <input type="hidden" name="action" value="demand_price">
                            <input type="hidden" name="order_id" value="${order.id}">
                            <div class="flex-grow w-full md:w-auto">
                                <label for="demand_price_${order.id}" class="block text-xs font-bold text-on-surface-variant uppercase mb-2">Demand Custom Price ($):</label>
                                <div class="relative w-full md:w-64">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm font-bold">$</span>
                                    <input type="number" step="0.01" id="demand_price_${order.id}" name="demanded_price" placeholder="e.g., 185.00" required class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface pl-8 pr-4 py-2 rounded-lg text-sm w-full outline-none">
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-3 w-full md:w-auto mt-4 md:mt-0 justify-end">
                                <button type="submit" class="px-5 py-2.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg hover:brightness-110 active:scale-95 transition-all border-0 shadow-md cursor-pointer">
                                    Demand More (Quote)
                                </button>
                            </div>
                        </form>
                        <div class="flex gap-3 justify-end shrink-0">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="accept_budget">
                                <input type="hidden" name="order_id" value="${order.id}">
                                <button type="submit" class="px-5 py-2.5 bg-green-700 hover:bg-green-600 text-white font-bold text-xs uppercase tracking-wider rounded-lg active:scale-95 transition-all border-0 shadow-md cursor-pointer">
                                    Accept Budget
                                </button>
                            </form>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="reject_request">
                                <input type="hidden" name="order_id" value="${order.id}">
                                <button type="submit" class="px-5 py-2.5 border border-outline-variant text-on-surface font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-surface-container-high transition-all bg-transparent cursor-pointer">
                                    Reject Request
                                </button>
                            </form>
                        </div>
                    </div>`;
            } else if (order.status === 'Quote Sent') {
                statusBadge = `<span class="bg-primary/20 text-primary border border-primary/30 text-xs font-bold px-2.5 py-1 rounded uppercase tracking-wider">Quote Sent</span>`;
                budgetPriceDisplay = `
                    <span class="text-xs text-on-surface-variant block">Demanded Price</span>
                    <span class="text-xl font-bold text-primary-container">$${parseFloat(order.demanded_price).toFixed(2)}</span>
                    <span class="text-[10px] text-on-surface-variant line-through block">Budget: $${parseFloat(order.offered_price).toFixed(2)}</span>`;
                
                actionArea = `
                    <div class="bg-surface-container/50 border border-outline-variant/60 p-4 rounded-xl text-center text-xs">
                        <span class="text-primary font-bold">Waiting for customer acceptance.</span> Custom price quote of <strong class="text-primary-container font-bold">$${parseFloat(order.demanded_price).toFixed(2)}</strong> was sent to the customer.
                    </div>`;
            } else if (order.status === 'Accepted') {
                statusBadge = `<span class="bg-green-600/20 text-green-400 border border-green-500/30 text-xs font-bold px-2.5 py-1 rounded uppercase tracking-wider">Accepted</span>`;
                const finalPrice = order.demanded_price ? order.demanded_price : order.offered_price;
                budgetPriceDisplay = `
                    <span class="text-xs text-on-surface-variant block">Agreed Price</span>
                    <span class="text-xl font-bold text-green-400">$${parseFloat(finalPrice).toFixed(2)}</span>`;
                
                actionArea = `
                    <div class="bg-green-950/20 border border-green-500/30 p-4 rounded-xl text-xs flex justify-between items-center text-green-400 font-bold">
                        <span>Order accepted and moved to prep pipeline.</span>
                        <span class="material-symbols-outlined">restaurant</span>
                    </div>`;
            } else if (order.status === 'Rejected') {
                statusBadge = `<span class="bg-error-container/20 text-error border border-error-container/30 text-xs font-bold px-2.5 py-1 rounded uppercase tracking-wider">Declined</span>`;
                budgetPriceDisplay = `
                    <span class="text-xs text-on-surface-variant block font-bold text-decoration-line-through">Offered Budget</span>
                    <span class="text-xl font-bold text-on-surface-variant line-through">$${parseFloat(order.offered_price).toFixed(2)}</span>`;
                
                actionArea = `
                    <div class="bg-surface-container/30 border border-outline-variant/40 p-4 rounded-xl text-center text-xs text-on-surface-variant italic">
                        This custom order request was declined.
                    </div>`;
            }

            // Image attachment rendering
            let imageMarkup = '';
            if (order.image_source_type === 'upload' && order.image_path) {
                imageMarkup = `
                    <div class="relative w-full max-w-[200px] h-32 rounded-lg border border-outline-variant overflow-hidden group mb-2">
                        <img src="${order.image_path}" class="w-full h-full object-cover group-hover:scale-105 transition-all" alt="Uploaded Reference">
                        <span class="absolute bottom-1 right-1 bg-surface-container-lowest/80 text-[8px] font-mono text-on-surface px-1 py-0.5 rounded">Uploaded File</span>
                    </div>`;
            } else if (order.image_source_type === 'url' && order.image_url && order.image_url !== '#') {
                imageMarkup = `
                    <a href="${order.image_url}" target="_blank" class="w-full max-w-[200px] h-32 rounded-lg border border-outline-variant overflow-hidden block mb-2 hover:border-primary transition-all relative group">
                        <img src="${order.image_url}" class="w-full h-full object-cover group-hover:scale-105 transition-all" onerror="this.src='https://placehold.co/150x100?text=Reference+Image';">
                        <div class="absolute inset-0 bg-surface-container-lowest/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-all">
                            <span class="material-symbols-outlined text-xl text-primary">open_in_new</span>
                        </div>
                    </a>`;
            } else {
                imageMarkup = `<p class="text-xs text-on-surface-variant italic mb-0">No reference image provided</p>`;
            }

            // Document attachment rendering
            let docMarkup = '';
            if (order.doc_source_type === 'upload' && order.doc_path) {
                docMarkup = `
                    <div class="flex items-center gap-2 p-2.5 bg-surface-container rounded-lg border border-outline-variant/60">
                        <span class="material-symbols-outlined text-primary text-xl">description</span>
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-bold text-on-surface block truncate">${order.doc_path}</span>
                            <span class="text-[9px] text-on-surface-variant block">Uploaded File Attachment</span>
                        </div>
                    </div>`;
            } else if (order.doc_source_type === 'url' && order.doc_url && order.doc_url !== '#') {
                docMarkup = `
                    <a href="${order.doc_url}" target="_blank" class="flex items-center gap-2 p-2.5 bg-surface-container rounded-lg border border-outline-variant/60 text-decoration-none hover:border-primary transition-all">
                        <span class="material-symbols-outlined text-primary text-xl">link</span>
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-bold text-primary block truncate">Reference Document Link</span>
                            <span class="text-[9px] text-on-surface-variant block truncate">${order.doc_url}</span>
                        </div>
                    </a>`;
            } else {
                docMarkup = `<p class="text-xs text-on-surface-variant italic mb-0">No document attached</p>`;
            }

            // Video attachment rendering
            let videoMarkup = '';
            if (order.video_url) {
                videoMarkup = `
                    <a href="${order.video_url}" target="_blank" class="flex items-center gap-2 p-2.5 bg-surface-container rounded-lg border border-outline-variant/60 text-decoration-none hover:border-primary transition-all">
                        <span class="material-symbols-outlined text-primary text-xl">play_circle</span>
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-bold text-primary block truncate">Reference Video</span>
                            <span class="text-[9px] text-on-surface-variant block truncate">${order.video_url}</span>
                        </div>
                    </a>`;
            } else {
                videoMarkup = `<p class="text-xs text-on-surface-variant italic mb-0">No video link provided</p>`;
            }

            // Notes thread rendering
            let notesThread = '';
            if (order.notes && order.notes.length > 0) {
                order.notes.forEach(note => {
                    const isSeller = note.sender_role === 'seller';
                    const timestamp = new Date(note.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    notesThread += `
                        <div class="flex flex-col ${isSeller ? 'items-end' : 'items-start'} mb-3">
                            <span class="text-[9px] text-on-surface-variant font-bold mb-1 uppercase tracking-wider">
                                ${isSeller ? 'You (Chef)' : 'Customer'}
                            </span>
                            <div class="max-w-[85%] rounded-xl px-3 py-2 text-xs text-on-surface shadow-sm 
                                ${isSeller ? 'bg-primary-container/20 border border-primary/20 text-right rounded-tr-none' : 'bg-surface-container-high border border-outline-variant text-left rounded-tl-none'}">
                                ${note.note_text}
                                <span class="text-[8px] text-on-surface-variant/80 block mt-1">${timestamp}</span>
                            </div>
                        </div>`;
                });
            } else {
                notesThread = `<p class="text-xs text-on-surface-variant italic text-center py-4 mb-0">No notes yet.</p>`;
            }

            const deliveryFormatted = new Date(order.delivery_date + 'T' + order.delivery_time).toLocaleString([], {
                month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            // Card assembly
            const card = document.createElement('div');
            card.id = `order-card-${order.id}`;
            card.className = "bg-surface-container-low border border-outline-variant rounded-2xl p-6 shadow-xl space-y-6";
            card.innerHTML = `
                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-outline-variant pb-4">
                    <div>
                        <div class="flex items-center gap-3 flex-wrap">
                            ${statusBadge}
                            <span class="text-xs text-on-surface-variant">Request ID: #${order.id}</span>
                        </div>
                        <h3 class="font-bold text-xl text-on-surface mt-2 mb-0">${order.dish_name}</h3>
                    </div>
                    <div class="text-right shrink-0">
                        ${budgetPriceDisplay}
                    </div>
                </div>

                <!-- Specs -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <!-- Left Column: Specs -->
                    <div class="space-y-4">
                        <div>
                            <span class="font-bold text-on-surface block mb-1">Special Ingredients &amp; Recipe:</span>
                            <p class="text-on-surface-variant text-xs mb-0">${order.ingredients}</p>
                        </div>
                        
                        <!-- Attachments layout -->
                        <div class="space-y-3">
                            <div>
                                <span class="font-bold text-on-surface text-xs block mb-1.5">Attached Image:</span>
                                ${imageMarkup}
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <span class="font-bold text-on-surface text-xs block mb-1.5">Attached Document:</span>
                                    ${docMarkup}
                                </div>
                                <div>
                                    <span class="font-bold text-on-surface text-xs block mb-1.5">Cooking Video:</span>
                                    ${videoMarkup}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Logistics & Notes Chat -->
                    <div class="space-y-4 flex flex-col justify-between">
                        <div class="bg-surface-container border border-outline-variant p-3.5 rounded-xl text-xs space-y-1.5">
                            <div class="flex justify-between">
                                <span class="text-on-surface-variant">Customer Delivery Target:</span>
                                <span class="font-bold text-on-surface">${deliveryFormatted}</span>
                            </div>
                        </div>

                        <!-- Chat thread -->
                        <div class="border border-outline-variant rounded-xl flex flex-col h-[260px] bg-surface-container-lowest">
                            <div class="p-3 border-b border-outline-variant bg-surface-container/40 flex items-center justify-between">
                                <span class="text-xs font-bold text-on-surface flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm text-primary">chat_bubble</span> Notes &amp; Customer Thread
                                </span>
                                <span class="text-[9px] text-on-surface-variant font-mono">Sync Mode: Live DB</span>
                            </div>
                            
                            <div class="flex-grow overflow-y-auto p-3 custom-scrollbar" id="notes-thread-${order.id}" style="height: 150px;">
                                ${notesThread}
                            </div>
                            
                            <!-- Notes input Form (POST) -->
                            <form method="POST" action="" class="p-2 border-t border-outline-variant bg-surface-container/20 flex gap-2 w-full">
                                <input type="hidden" name="action" value="add_note">
                                <input type="hidden" name="order_id" value="${order.id}">
                                <input type="text" name="note_text" placeholder="Type note to customer..." class="flex-grow bg-surface-container-high border border-outline-variant focus:border-primary focus:ring-0 text-on-surface px-3 py-1.5 rounded-lg text-xs outline-none" required>
                                <button type="submit" class="px-3.5 py-1.5 bg-primary text-on-primary font-bold text-xs rounded-lg hover:brightness-110 active:scale-95 border-0 cursor-pointer">
                                    Send
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Action Form area -->
                ${actionArea}
            `;
            container.appendChild(card);
            
            // Scroll to bottom of notes thread
            const chatLog = document.getElementById(`notes-thread-${order.id}`);
            if (chatLog) {
                chatLog.scrollTop = chatLog.scrollHeight;
            }
        });
    }

    // Init
    window.addEventListener('load', () => {
        renderOrders();
    });
</script>

<?php view('partials/seller_footer'); ?>
