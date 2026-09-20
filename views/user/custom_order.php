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
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: " . url('user/login'));
    exit;
}

$db = getDB();

// Resolve the target store — from URL param, fallback to first approved active store
$targetStoreId = intval($_GET['store_id'] ?? 0);
$targetStore = null;
if ($targetStoreId) {
    $storeStmt = $db->prepare("SELECT id, store_name FROM stores WHERE id = ? AND store_status = 'Active' AND onboarding_status = 'Approved'");
    $storeStmt->execute([$targetStoreId]);
    $targetStore = $storeStmt->fetch();
}
if (!$targetStore) {
    // Fallback to first approved active store
    $targetStore = $db->query("SELECT id, store_name FROM stores WHERE store_status = 'Active' AND onboarding_status = 'Approved' ORDER BY id ASC LIMIT 1")->fetch();
}
$resolvedStoreId = $targetStore['id'] ?? 1;

// 1. Handle Custom Order Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $dish_name = trim($_POST['dish_name'] ?? '');
    $ingredients = trim($_POST['ingredients'] ?? '');
    $offered_price = floatval($_POST['offered_price'] ?? 0);
    $delivery_date = $_POST['delivery_date'] ?? '';
    $delivery_time = $_POST['delivery_time'] ?? '';
    $video_url = trim($_POST['video_link'] ?? '');
    
    // Image handling
    $image_source_type = $_POST['image_source_type'] ?? 'upload';
    $image_url = '';
    $image_path = '';
    if ($image_source_type === 'url') {
        $image_url = trim($_POST['image_url'] ?? '');
    } else {
        $image_path = $_POST['image_base64'] ?? '';
    }
    
    // Doc handling
    $doc_source_type = $_POST['doc_source_type'] ?? 'upload';
    $doc_url = '';
    $doc_path = '';
    if ($doc_source_type === 'url') {
        $doc_url = trim($_POST['doc_url'] ?? '');
    } else {
        $doc_path = $_POST['doc_name'] ?? '';
    }

    // Attachments structure
    $attachments = [
        'image' => [
            'source_type' => $image_source_type,
            'url' => $image_url,
            'path' => $image_path
        ],
        'doc' => [
            'source_type' => $doc_source_type,
            'url' => $doc_url,
            'path' => $doc_path
        ],
        'video_url' => $video_url
    ];
    
    // Initial notes
    $initial_note = trim($_POST['initial_note'] ?? '');
    $notes = [];
    if ($initial_note) {
        $notes[] = [
            'sender_role' => 'customer',
            'note_text' => $initial_note,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    // Geofence Pincode Validation
    $custAddrStmt = $db->prepare("SELECT zip_code, address_line1, city FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1");
    $custAddrStmt->execute([$userId]);
    $custAddr = $custAddrStmt->fetch();

    $storeLocStmt = $db->prepare("SELECT id, store_name, pincode FROM stores WHERE id = ?");
    $storeLocStmt->execute([$resolvedStoreId]);
    $targetStoreData = $storeLocStmt->fetch();

    $customLocationError = '';
    if (!$custAddr) {
        $customLocationError = "Please set a delivery address in your profile/location before submitting a custom order.";
    } elseif ($targetStoreData) {
        $uZip = trim($custAddr['zip_code'] ?? '');
        $sPincode = trim($targetStoreData['pincode'] ?? '');

        if (empty($uZip)) {
            $customLocationError = "Delivery Pincode Required: Please specify a pincode in your delivery address.";
        } elseif (strcasecmp($uZip, $sPincode) !== 0) {
            $customLocationError = "Unable to Deliver: Your pincode (" . htmlspecialchars($uZip) . ") does not match " . htmlspecialchars($targetStoreData['store_name']) . "'s service pincode (" . htmlspecialchars($sPincode) . ").";
        }
    }

    if (!empty($customLocationError)) {
        $createError = $customLocationError;
    } else {
        $ins = $db->prepare("INSERT INTO custom_orders (customer_id, store_id, dish_name, ingredients, offered_price, attachments, notes, delivery_date, delivery_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $ins->execute([
            $userId,
            $resolvedStoreId,
            $dish_name,
            $ingredients,
            $offered_price,
            json_encode($attachments),
            json_encode($notes),
            $delivery_date,
            $delivery_time
        ]);
        $newOrderId = $db->lastInsertId();

        // Notify the store owner
        $ownerStmt = $db->prepare("SELECT owner_id FROM stores WHERE id = ?");
        $ownerStmt->execute([$resolvedStoreId]);
        $ownerId = $ownerStmt->fetchColumn();
        if ($ownerId) {
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
            $notifStmt->execute([
                $ownerId,
                '🍽 New Custom Order Request — #CO-' . $newOrderId,
                'Customer submitted a custom order request for "' . $dish_name . '" with a budget of $' . number_format($offered_price, 2) . '. Delivery by ' . $delivery_date . '. Review and respond.'
            ]);
        }
        
        header("Location: " . url('user/custom_order') . "&tab=tracker&success=1");
        exit;
    }
}

// 2. Handle Adding Notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $orderId = intval($_POST['order_id']);
    $noteText = trim($_POST['note_text'] ?? '');
    
    if ($noteText) {
        $stmt = $db->prepare("SELECT notes FROM custom_orders WHERE id = ? AND customer_id = ?");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();
        if ($order) {
            $notes = json_decode($order['notes'] ?: '[]', true);
            $notes[] = [
                'sender_role' => 'customer',
                'note_text' => $noteText,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $upd = $db->prepare("UPDATE custom_orders SET notes = ? WHERE id = ?");
            $upd->execute([json_encode($notes), $orderId]);
        }
    }
    header("Location: " . url('user/custom_order') . "&tab=tracker");
    exit;
}

// 3. Handle Quote Acceptance/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'respond_quote') {
    $orderId = intval($_POST['order_id']);
    $resolution = $_POST['resolution'] ?? '';
    
    $stmt = $db->prepare("SELECT notes, demanded_price FROM custom_orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();
    
    if ($order) {
        $status = ($resolution === 'Accepted') ? 'Accepted' : 'Rejected';
        $notes = json_decode($order['notes'] ?: '[]', true);
        
        if ($resolution === 'Accepted') {
            $notes[] = [
                'sender_role' => 'customer',
                'note_text' => "Accepted custom quote of $" . number_format($order['demanded_price'], 2) . ".",
                'created_at' => date('Y-m-d H:i:s')
            ];
        } else {
            $notes[] = [
                'sender_role' => 'customer',
                'note_text' => "Declined quote and cancelled the order request.",
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        $upd = $db->prepare("UPDATE custom_orders SET status = ?, notes = ? WHERE id = ?");
        $upd->execute([$status, json_encode($notes), $orderId]);
    }
    header("Location: " . url('user/custom_order') . "&tab=tracker");
    exit;
}

// 4. Fetch Custom Orders from Database — join store name for display
$stmt = $db->prepare("
    SELECT co.*, s.store_name
    FROM custom_orders co
    LEFT JOIN stores s ON co.store_id = s.id
    WHERE co.customer_id = ?
    ORDER BY co.id DESC
");
$stmt->execute([$userId]);
$dbOrders = $stmt->fetchAll();

// Check url triggers
$activeTab = $_GET['tab'] ?? 'new-request';
$showSuccess = isset($_GET['success']);

view('partials/user_header', get_defined_vars());
?>

<main class="container-xl py-5 px-4" style="min-height: 80vh; background-color: #131313;">
    <div class="max-w-4xl mx-auto space-y-6">
        
        <!-- Navigation Tabs -->
        <div class="flex gap-4 border-b border-outline-variant pb-2 mb-6">
            <button onclick="switchTab('new-request')" id="tab-btn-new-request" class="bg-transparent border-0 text-headline-sm font-bold pb-2 border-b-2 border-primary text-primary transition-all cursor-pointer outline-none">
                Submit Custom Request
            </button>
            <button onclick="switchTab('tracker')" id="tab-btn-tracker" class="bg-transparent border-0 text-headline-sm font-bold pb-2 text-on-surface-variant hover:text-on-surface transition-all cursor-pointer flex items-center gap-2 outline-none">
                Track Requests
                <span id="tracker-badge" class="bg-primary text-on-primary text-[10px] px-2 py-0.5 rounded-full font-bold"><?php echo count($dbOrders); ?></span>
            </button>
        </div>

        <!-- NEW REQUEST TAB -->
        <div id="tab-new-request" class="space-y-6">
            <div class="bg-surface-container-low border border-outline-variant p-8 rounded-2xl shadow-2xl space-y-6">
                <!-- Title & Header -->
                <div class="border-b border-outline-variant pb-4">
                    <h2 class="font-headline-md text-headline-md text-primary font-bold mb-2">Request Custom Chef Order</h2>
                    <p class="text-on-surface-variant text-sm mb-0">Describe your culinary request and <strong class="text-primary"><?php echo htmlspecialchars($targetStore['store_name'] ?? 'the restaurant'); ?></strong> chef team will review it and reply with a custom offer.</p>
                </div>

                <form id="custom-order-form" method="POST" action="" onsubmit="handleSubmitRequest(event)" class="space-y-6">
                    <input type="hidden" name="submit_request" value="1">
                    <input type="hidden" name="image_source_type" id="image_source_type" value="upload">
                    <input type="hidden" name="image_base64" id="image_base64" value="">
                    <input type="hidden" name="doc_source_type" id="doc_source_type" value="upload">
                    <input type="hidden" name="doc_name" id="doc_name" value="">
                    
                    <!-- Custom Dish Name -->
                    <div class="space-y-2">
                        <label for="dish_name" class="block font-label-md text-on-surface font-bold">Dish Name / Custom Title</label>
                        <input type="text" id="dish_name" name="dish_name" required placeholder="e.g., Ultra-premium Truffle Infused Tomahawk Steak" class="bg-surface-container border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-sm w-full outline-none">
                    </div>

                    <!-- Detailed Ingredients / Special Request -->
                    <div class="space-y-2">
                        <label for="ingredients" class="block font-label-md text-on-surface font-bold">Ingredient Details &amp; Specifications</label>
                        <textarea id="ingredients" name="ingredients" rows="4" required placeholder="Specify rare ingredients, meat cuts, marinades, allergy requirements, cooking styles, or garnishes..." class="bg-surface-container border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-sm w-full outline-none custom-scrollbar"></textarea>
                    </div>

                    <!-- Optional initial note / instructions -->
                    <div class="space-y-2">
                        <label for="initial_note" class="block font-label-md text-on-surface font-bold">Additional Instructions / Notes (Optional)</label>
                        <input type="text" id="initial_note" name="initial_note" placeholder="e.g. Please leave details about spices, packing, or delivery instructions..." class="bg-surface-container border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-sm w-full outline-none">
                    </div>

                    <!-- Payment Offer -->
                    <div class="space-y-2">
                        <label for="offered_price" class="block font-label-md text-on-surface font-bold">Offered Payment Price (₹)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant font-bold text-sm">₹</span>
                            <input type="number" step="0.01" id="offered_price" name="offered_price" required placeholder="150.00" class="bg-surface-container border border-outline-variant focus:border-primary focus:ring-0 text-on-surface pl-8 pr-4 py-3 rounded-lg text-sm w-full outline-none">
                        </div>
                        <span class="text-xs text-on-surface-variant">Indicate how much you are willing to pay for this custom order.</span>
                    </div>


                    <!-- Media Uploads & Links -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Reference Image Option -->
                        <div class="space-y-2 bg-surface-container p-4 rounded-xl border border-outline-variant flex flex-col justify-between">
                            <div>
                                <label class="block font-label-sm text-on-surface font-bold mb-1">Reference Image</label>
                                <div class="flex gap-2 mb-3 bg-surface-container-low p-1 rounded-lg border border-outline-variant">
                                    <button type="button" id="toggle-img-upload" onclick="switchImageSource('upload')" class="flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-primary text-on-primary">
                                        Upload File
                                    </button>
                                    <button type="button" id="toggle-img-url" onclick="switchImageSource('url')" class="flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant">
                                        Enter URL
                                    </button>
                                </div>
                                
                                <div id="img-upload-group" class="transition-all">
                                    <label for="image_file" class="flex flex-col items-center justify-center border-2 border-dashed border-outline-variant hover:border-primary rounded-xl p-3 cursor-pointer hover:bg-surface-container-high transition-colors text-center min-h-[90px]">
                                        <span class="material-symbols-outlined text-2xl text-on-surface-variant mb-1">upload_file</span>
                                        <span class="text-[10px] font-bold text-on-surface block truncate max-w-full" id="img-file-label">Choose Image File</span>
                                        <input type="file" id="image_file" accept="image/*" class="hidden" onchange="handleImageUpload(this)">
                                    </label>
                                </div>
                                <div id="img-url-group" class="hidden transition-all">
                                    <input type="url" id="image_url" placeholder="https://unsplash.com/...jpg" class="bg-surface-container-low border border-outline-variant focus:border-primary text-on-surface p-3 rounded-lg text-xs w-full outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Reference Document Option -->
                        <div class="space-y-2 bg-surface-container p-4 rounded-xl border border-outline-variant flex flex-col justify-between">
                            <div>
                                <label class="block font-label-sm text-on-surface font-bold mb-1">Reference Document</label>
                                <div class="flex gap-2 mb-3 bg-surface-container-low p-1 rounded-lg border border-outline-variant">
                                    <button type="button" id="toggle-doc-upload" onclick="switchDocSource('upload')" class="flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-primary text-on-primary">
                                        Upload PDF
                                    </button>
                                    <button type="button" id="toggle-doc-url" onclick="switchDocSource('url')" class="flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant">
                                        Enter URL
                                    </button>
                                </div>
                                
                                <div id="doc-upload-group" class="transition-all">
                                    <label for="doc_file" class="flex flex-col items-center justify-center border-2 border-dashed border-outline-variant hover:border-primary rounded-xl p-3 cursor-pointer hover:bg-surface-container-high transition-colors text-center min-h-[90px]">
                                        <span class="material-symbols-outlined text-2xl text-on-surface-variant mb-1">picture_as_pdf</span>
                                        <span class="text-[10px] font-bold text-on-surface block truncate max-w-full" id="doc-file-label">Choose PDF/Doc File</span>
                                        <input type="file" id="doc_file" accept=".pdf,.doc,.docx" class="hidden" onchange="handleDocUpload(this)">
                                    </label>
                                </div>
                                <div id="doc-url-group" class="hidden transition-all">
                                    <input type="url" id="doc_url" placeholder="https://docs.google.com/..." class="bg-surface-container-low border border-outline-variant focus:border-primary text-on-surface p-3 rounded-lg text-xs w-full outline-none">
                                </div>
                            </div>
                        </div>

                        <!-- Reference Video Link -->
                        <div class="space-y-2 bg-surface-container p-4 rounded-xl border border-outline-variant flex flex-col justify-between">
                            <div class="space-y-2">
                                <label for="video_link" class="block font-label-sm text-on-surface font-bold mb-1">Reference Video Link</label>
                                <input type="url" id="video_link" placeholder="https://youtube.com/watch?..." class="bg-surface-container-low border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-xs w-full outline-none">
                            </div>
                            <div class="text-[9px] text-on-surface-variant p-2 bg-surface-container-low rounded border border-outline-variant/50">
                                <span class="font-bold text-primary">Tip:</span> Uploading visual guidelines helps the chef refine recipes perfectly.
                            </div>
                        </div>
                    </div>

                    <!-- Date and Time Picker -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label for="delivery_date" class="block font-label-md text-on-surface font-bold">Preferred Delivery Date</label>
                            <input type="date" id="delivery_date" required class="bg-surface-container border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-sm w-full outline-none">
                        </div>
                        <div class="space-y-2">
                            <label for="delivery_time" class="block font-label-md text-on-surface font-bold">Preferred Delivery Time</label>
                            <input type="time" id="delivery_time" required class="bg-surface-container border border-outline-variant focus:border-primary focus:ring-0 text-on-surface p-3 rounded-lg text-sm w-full outline-none">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit" class="w-full py-3.5 bg-primary-container text-on-primary-container font-bold rounded-xl hover:brightness-110 active:scale-95 transition-all border-0 shadow-lg cursor-pointer">
                            Submit Custom Order Request
                        </button>
                    </div>
                </form>

                <!-- Success Feedback Card -->
                <div id="success-card" style="display: none;" class="space-y-6 text-center py-8">
                    <div class="w-20 h-20 bg-green-950/40 border-2 border-green-500 rounded-full flex items-center justify-center mx-auto text-green-400">
                        <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                    </div>
                    <div>
                        <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-2">Request Submitted Successfully!</h3>
                        <p class="text-on-surface-variant text-sm max-w-md mx-auto">Your custom order has been routed to the restaurant. The chef team will review the specifications and respond with notes or price updates.</p>
                    </div>
                    <div class="pt-4 flex gap-4 justify-center">
                        <button onclick="switchTab('tracker')" class="px-6 py-3 bg-primary text-on-primary font-bold rounded-xl hover:brightness-115 transition-all border-0 cursor-pointer">
                            Track Your Custom Orders
                        </button>
                        <button onclick="resetOrderForm()" class="px-6 py-3 bg-surface-container border border-outline-variant text-on-surface font-bold rounded-xl hover:bg-surface-container-high transition-all border-0 cursor-pointer">
                            Submit Another Request
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TRACKER TAB -->
        <div id="tab-tracker" class="hidden space-y-6">
            <div id="tracker-empty-state" class="bg-surface-container-low border border-outline-variant p-12 rounded-2xl text-center shadow-xl space-y-4">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">description_alert</span>
                <h3 class="text-xl font-bold text-on-surface">No Custom Requests Yet</h3>
                <p class="text-on-surface-variant text-sm max-w-sm mx-auto">You have not submitted any custom culinary requests. Go back to the request form to design your first custom dish.</p>
                <button onclick="switchTab('new-request')" class="px-6 py-2.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 hover:brightness-110 active:scale-95 cursor-pointer">
                    Create Request
                </button>
            </div>
            
            <div id="tracker-orders-list" class="space-y-6">
                <!-- Custom order cards will be rendered dynamically here -->
            </div>
        </div>

    </div>
</main>

<script>
    // State management loading from Database
    const dbOrders = <?php echo json_encode($dbOrders); ?>;

    let currentImageSource = 'upload';
    let currentDocSource = 'upload';
    let uploadedImageBase64 = null;
    let uploadedDocName = null;
    let uploadedDocSize = null;

    function getOrders() {
        return dbOrders.map(order => {
            let attachments = {};
            let notes = [];
            try {
                attachments = typeof order.attachments === 'string' ? JSON.parse(order.attachments) : (order.attachments || {});
            } catch(e) { attachments = {}; }
            try {
                notes = typeof order.notes === 'string' ? JSON.parse(order.notes) : (order.notes || []);
            } catch(e) { notes = []; }
            
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
                delivery_time: order.delivery_time.substring(0, 5),
                status: order.status,
                created_at: order.created_at,
                store_name: order.store_name || 'Assigned Restaurant',
                notes: notes
            };
        });
    }

    // Tab Switcher
    function switchTab(tabId) {
        const newReqTab = document.getElementById('tab-new-request');
        const trackerTab = document.getElementById('tab-tracker');
        const newReqBtn = document.getElementById('tab-btn-new-request');
        const trackerBtn = document.getElementById('tab-btn-tracker');

        if (tabId === 'new-request') {
            newReqTab.classList.remove('hidden');
            trackerTab.classList.add('hidden');
            
            newReqBtn.className = "bg-transparent border-0 text-headline-sm font-bold pb-2 border-b-2 border-primary text-primary transition-all cursor-pointer outline-none";
            trackerBtn.className = "bg-transparent border-0 text-headline-sm font-bold pb-2 text-on-surface-variant hover:text-on-surface transition-all cursor-pointer flex items-center gap-2 outline-none";
        } else {
            newReqTab.classList.add('hidden');
            trackerTab.classList.remove('hidden');
            
            newReqBtn.className = "bg-transparent border-0 text-headline-sm font-bold pb-2 text-on-surface-variant hover:text-on-surface transition-all cursor-pointer outline-none";
            trackerBtn.className = "bg-transparent border-0 text-headline-sm font-bold pb-2 border-b-2 border-primary text-primary transition-all cursor-pointer flex items-center gap-2 outline-none";
            renderTrackerList();
        }
    }

    // Toggle source fields
    function switchImageSource(source) {
        currentImageSource = source;
        const uploadBtn = document.getElementById('toggle-img-upload');
        const urlBtn = document.getElementById('toggle-img-url');
        const uploadGroup = document.getElementById('img-upload-group');
        const urlGroup = document.getElementById('img-url-group');

        if (source === 'upload') {
            uploadBtn.className = "flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-primary text-on-primary";
            urlBtn.className = "flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant";
            uploadGroup.classList.remove('hidden');
            urlGroup.classList.add('hidden');
        } else {
            uploadBtn.className = "flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant";
            urlBtn.className = "flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-primary text-on-primary";
            uploadGroup.classList.add('hidden');
            urlGroup.classList.remove('hidden');
        }
    }

    function switchDocSource(source) {
        currentDocSource = source;
        const uploadBtn = document.getElementById('toggle-doc-upload');
        const urlBtn = document.getElementById('toggle-doc-url');
        const uploadGroup = document.getElementById('doc-upload-group');
        const urlGroup = document.getElementById('doc-url-group');

        if (source === 'upload') {
            uploadBtn.className = "flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-primary text-on-primary";
            urlBtn.className = "flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant";
            uploadGroup.classList.remove('hidden');
            urlGroup.classList.add('hidden');
        } else {
            uploadBtn.className = "flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-transparent text-on-surface-variant";
            urlBtn.className = "flex-1 py-1.5 text-[10px] rounded-md font-bold transition-all border-0 cursor-pointer bg-primary text-on-primary";
            uploadGroup.classList.add('hidden');
            urlGroup.classList.remove('hidden');
        }
    }

    // Handle Upload Conversions
    function handleImageUpload(input) {
        const file = input.files[0];
        if (!file) return;

        document.getElementById('img-file-label').textContent = file.name;

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                // Resize image using Canvas to fit within localStorage quota (max 300px)
                const canvas = document.createElement('canvas');
                const max_size = 300;
                let width = img.width;
                let height = img.height;
                
                if (width > height) {
                    if (width > max_size) {
                        height *= max_size / width;
                        width = max_size;
                    }
                } else {
                    if (height > max_size) {
                        width *= max_size / height;
                        height = max_size;
                    }
                }
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                uploadedImageBase64 = canvas.toDataURL('image/jpeg', 0.7);
                document.getElementById('image_base64').value = uploadedImageBase64;
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function handleDocUpload(input) {
        const file = input.files[0];
        if (!file) return;
        document.getElementById('doc-file-label').textContent = file.name;
        uploadedDocName = file.name;
        document.getElementById('doc_name').value = file.name;
    }

    // Reset Submission Form
    function resetOrderForm() {
        document.getElementById('custom-order-form').reset();
        document.getElementById('custom-order-form').style.display = 'block';
        document.getElementById('success-card').style.display = 'none';
        document.getElementById('img-file-label').textContent = "Choose Image File";
        document.getElementById('doc-file-label').textContent = "Choose PDF/Doc File";
        uploadedImageBase64 = null;
        uploadedDocName = null;
        switchImageSource('upload');
        switchDocSource('upload');
    }

    // Set hidden inputs before submitting request
    function handleSubmitRequest(event) {
        document.getElementById('image_source_type').value = currentImageSource;
        document.getElementById('doc_source_type').value = currentDocSource;
        return true;
    }

    // Render Tracker dynamic list from database
    function renderTrackerList() {
        const orders = getOrders();
        const emptyState = document.getElementById('tracker-empty-state');
        const ordersList = document.getElementById('tracker-orders-list');

        if (orders.length === 0) {
            emptyState.classList.remove('hidden');
            ordersList.innerHTML = '';
            return;
        }

        emptyState.classList.add('hidden');
        ordersList.innerHTML = '';

        orders.forEach(order => {
            let statusBadge = '';
            let budgetPriceDisplay = '';
            let actionButtons = '';

            // Status style rules
            if (order.status === 'Pending') {
                statusBadge = `<span class="bg-amber-600/20 text-amber-400 border border-amber-500/30 text-[10px] font-bold px-2.5 py-1 rounded uppercase tracking-wider">Pending Review</span>`;
                budgetPriceDisplay = `
                    <span class="text-xs text-on-surface-variant block">Offered Budget</span>
                    <span class="text-lg font-bold text-primary">₹${parseFloat(order.offered_price).toFixed(2)}</span>`;
            } else if (order.status === 'Quote Sent') {
                statusBadge = `<span class="bg-primary/20 text-primary border border-primary/30 text-[10px] font-bold px-2.5 py-1 rounded uppercase tracking-wider">Chef Quote Received</span>`;
                budgetPriceDisplay = `
                    <span class="text-xs text-on-surface-variant block">Demanded Price</span>
                    <span class="text-lg font-bold text-primary-container">₹${parseFloat(order.demanded_price).toFixed(2)}</span>
                    <span class="text-[10px] text-on-surface-variant line-through block">Budget: ₹${parseFloat(order.offered_price).toFixed(2)}</span>`;
                
                actionButtons = `
                    <div class="flex gap-2 justify-end pt-3 border-t border-outline-variant mt-3">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="respond_quote">
                            <input type="hidden" name="order_id" value="${order.id}">
                            <input type="hidden" name="resolution" value="Accepted">
                            <button type="submit" class="px-4 py-2 bg-green-700 hover:bg-green-600 text-white font-bold text-xs uppercase tracking-wider rounded-lg active:scale-95 transition-all border-0 shadow-md cursor-pointer">
                                Accept Custom Quote
                            </button>
                        </form>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="respond_quote">
                            <input type="hidden" name="order_id" value="${order.id}">
                            <input type="hidden" name="resolution" value="Rejected">
                            <button type="submit" class="px-4 py-2 border border-error-container text-error hover:bg-error-container/20 font-bold text-xs uppercase tracking-wider rounded-lg active:scale-95 transition-all bg-transparent cursor-pointer">
                                Decline &amp; Cancel
                            </button>
                        </form>
                    </div>`;
            } else if (order.status === 'Accepted') {
                statusBadge = `<span class="bg-green-600/20 text-green-400 border border-green-500/30 text-[10px] font-bold px-2.5 py-1 rounded uppercase tracking-wider">Accepted</span>`;
                const finalPrice = order.demanded_price ? order.demanded_price : order.offered_price;
                budgetPriceDisplay = `
                    <span class="text-xs text-on-surface-variant block">Agreed Price</span>
                    <span class="text-lg font-bold text-green-400">₹${parseFloat(finalPrice).toFixed(2)}</span>`;
            } else if (order.status === 'Rejected') {
                statusBadge = `<span class="bg-error-container/20 text-error border border-error-container/30 text-[10px] font-bold px-2.5 py-1 rounded uppercase tracking-wider">Declined</span>`;
                budgetPriceDisplay = `
                    <span class="text-xs text-on-surface-variant block">Offered Budget</span>
                    <span class="text-lg font-bold text-on-surface-variant line-through">₹${parseFloat(order.offered_price).toFixed(2)}</span>`;
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

            // Notes list rendering
            let notesThread = '';
            if (order.notes && order.notes.length > 0) {
                order.notes.forEach(note => {
                    const isCustomer = note.sender_role === 'customer';
                    const timestamp = new Date(note.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    notesThread += `
                        <div class="flex flex-col ${isCustomer ? 'items-end' : 'items-start'} mb-3">
                            <span class="text-[9px] text-on-surface-variant font-bold mb-1 uppercase tracking-wider">
                                ${isCustomer ? 'You' : 'Chef/Merchant'}
                            </span>
                            <div class="max-w-[85%] rounded-xl px-3 py-2 text-xs text-on-surface shadow-sm 
                                ${isCustomer ? 'bg-primary-container/20 border border-primary/20 text-right rounded-tr-none' : 'bg-surface-container-high border border-outline-variant text-left rounded-tl-none'}">
                                ${note.note_text}
                                <span class="text-[8px] text-on-surface-variant/80 block mt-1">${timestamp}</span>
                            </div>
                        </div>`;
                });
            } else {
                notesThread = `<p class="text-xs text-on-surface-variant italic text-center py-4 mb-0">No notes or instructions found.</p>`;
            }

            // Order delivery info
            const deliveryFormatted = new Date(order.delivery_date + 'T' + order.delivery_time).toLocaleString([], {
                month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            // Card assembly
            const card = document.createElement('div');
            card.className = "bg-surface-container-low border border-outline-variant rounded-2xl p-6 shadow-xl space-y-6";
            card.innerHTML = `
                <!-- Card Header -->
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

                <!-- Specs Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <!-- Left: Details -->
                    <div class="space-y-4">
                        <div>
                            <span class="font-bold text-on-surface block mb-1">Recipe Ingredients &amp; Directives:</span>
                            <p class="text-on-surface-variant text-xs mb-0">${order.ingredients}</p>
                        </div>
                        
                        <!-- Uploaded / Attached Items -->
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

                    <!-- Right: Logistics & Live Note Tag / Chat -->
                    <div class="space-y-4 flex flex-col justify-between">
                        <div class="bg-surface-container border border-outline-variant p-3.5 rounded-xl text-xs space-y-1.5">
                            <div class="flex justify-between">
                                <span class="text-on-surface-variant">Fulfillment Target:</span>
                                <span class="font-bold text-on-surface">${deliveryFormatted}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-on-surface-variant">Store Assigned:</span>
                                <span class="font-bold text-primary">${order.store_name}</span>
                            </div>
                        </div>

                        <!-- Interactive Notes (Chat System) -->
                        <div class="border border-outline-variant rounded-xl flex flex-col h-[260px] bg-surface-container-lowest">
                            <div class="p-3 border-b border-outline-variant bg-surface-container/40 flex items-center justify-between">
                                <span class="text-xs font-bold text-on-surface flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm text-primary">chat_bubble</span> Notes &amp; Chef Thread
                                </span>
                                <span class="text-[9px] text-on-surface-variant font-mono">Sync Mode: Live DB</span>
                            </div>
                            <!-- Messages Log scrollbar -->
                            <div class="flex-grow overflow-y-auto p-3 custom-scrollbar" id="notes-thread-${order.id}" style="height: 150px;">
                                ${notesThread}
                            </div>
                            <!-- Send Input Form (Backend Linked) -->
                            <form method="POST" action="" class="p-2 border-t border-outline-variant bg-surface-container/20 flex gap-2">
                                <input type="hidden" name="action" value="add_note">
                                <input type="hidden" name="order_id" value="${order.id}">
                                <input type="text" name="note_text" placeholder="Type note to the chef..." class="flex-grow bg-surface-container-high border border-outline-variant focus:border-primary focus:ring-0 text-on-surface px-3 py-1.5 rounded-lg text-xs outline-none" required>
                                <button type="submit" class="px-3.5 py-1.5 bg-primary text-on-primary font-bold text-xs rounded-lg hover:brightness-110 active:scale-95 border-0 cursor-pointer">
                                    Send
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Acceptance Actions -->
                ${actionButtons}
            `;
            ordersList.appendChild(card);
            
            // Scroll to bottom of message thread
            const chatLog = document.getElementById(`notes-thread-${order.id}`);
            if (chatLog) {
                chatLog.scrollTop = chatLog.scrollHeight;
            }
        });
    }

    // Window load actions
    window.addEventListener('load', () => {
        switchTab('<?php echo $activeTab; ?>');
        
        // Show success alert card if successfully submitted
        <?php if ($showSuccess): ?>
        document.getElementById('custom-order-form').style.display = 'none';
        document.getElementById('success-card').style.display = 'block';
        <?php endif; ?>
        
        // Setup initial default image and doc tabs
        switchImageSource('upload');
        switchDocSource('upload');
    });
</script>

<?php view('partials/user_footer', get_defined_vars()); ?>
