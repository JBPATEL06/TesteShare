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
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $storeId = intval($_POST['store_id'] ?? 0);

    if ($storeId) {
        if ($action === 'approve_store') {
            $stmt = $db->prepare("UPDATE stores SET onboarding_status = 'Approved' WHERE id = ?");
            $stmt->execute([$storeId]);
            echo json_encode(['status' => 'success', 'message' => 'Store approved successfully']);
            exit;
        } elseif ($action === 'reject_store') {
            $stmt = $db->prepare("UPDATE stores SET onboarding_status = 'Rejected' WHERE id = ?");
            $stmt->execute([$storeId]);
            echo json_encode(['status' => 'success', 'message' => 'Store rejected successfully']);
            exit;
        } elseif ($action === 'toggle_store_status') {
            $status = trim($_POST['status'] ?? 'Active');
            $stmt = $db->prepare("UPDATE stores SET store_status = ? WHERE id = ?");
            $stmt->execute([$status, $storeId]);
            echo json_encode(['status' => 'success', 'message' => 'Store status updated', 'store_status' => $status]);
            exit;
        } elseif ($action === 'save_commission') {
            $comm = floatval($_POST['commission_rate'] ?? 5.00);
            $stmt = $db->prepare("UPDATE stores SET commission_rate = ? WHERE id = ?");
            $stmt->execute([$comm, $storeId]);
            echo json_encode(['status' => 'success', 'message' => 'Commission rate updated', 'commission_rate' => $comm]);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

// Fetch pending store requests
$pendingStores = $db->query("
    SELECT s.*, u.fullname as owner_name 
    FROM stores s
    JOIN users u ON s.owner_id = u.id
    WHERE s.onboarding_status = 'Under Review'
    ORDER BY s.created_at DESC
")->fetchAll();

// Fetch approved stores
$activeStores = $db->query("
    SELECT s.*, u.fullname as owner_name, u.email as owner_email
    FROM stores s
    JOIN users u ON s.owner_id = u.id
    WHERE s.onboarding_status = 'Approved'
    ORDER BY s.created_at DESC
")->fetchAll();

$pageTitle = 'Merchant Accounts & Approvals';
$activeNav = 'sellers';
view('partials/admin_header', get_defined_vars());
view('partials/admin_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <!-- Content Canvas -->
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Section Header -->
        <div class="border-b border-outline pb-6">
            <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Merchant Panel Management</h2>
            <p class="text-body-md text-on-surface-variant mb-0">Approve onboarding applications, toggle store statuses, and edit commission rates.</p>
        </div>

        <!-- 1. PENDING ONBOARDING APPROVALS QUEUE -->
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary">pending_actions</span>
                <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-0">Pending Merchant Applications</h3>
                <span id="approvalCountBadge" class="text-xs font-mono px-2 py-0.5 bg-primary/10 text-primary border border-primary/20 rounded-full font-bold <?php echo empty($pendingStores) ? 'hidden' : ''; ?>"><?php echo count($pendingStores); ?> Pending</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="approvalsQueueContainer">
                <?php foreach ($pendingStores as $pStore): ?>
                    <?php 
                    $maskedAccount = '••••••••' . substr($pStore['bank_account_number'], -4);
                    ?>
                    <div class="bg-surface-container border border-outline rounded-2xl p-6 shadow-xl space-y-4 relative flex flex-col justify-between" id="app-card-<?php echo $pStore['id']; ?>">
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-bold text-on-surface text-base mb-0"><?php echo htmlspecialchars($pStore['store_name']); ?></h4>
                                    <span class="text-xs text-on-surface-variant">Category: <?php echo htmlspecialchars($pStore['category']); ?></span>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 bg-yellow-500/10 text-yellow-400 border border-yellow-500/20 rounded-full uppercase tracking-wider font-mono">Under Review</span>
                            </div>

                            <div class="pt-4 border-t border-outline/30 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                                <div>
                                    <span class="text-on-surface-variant block font-bold">Holder Name</span>
                                    <span class="text-on-surface font-semibold"><?php echo htmlspecialchars($pStore['bank_holder_name']); ?></span>
                                </div>
                                <div>
                                    <span class="text-on-surface-variant block font-bold">Bank Account</span>
                                    <span class="text-on-surface font-mono"><?php echo $maskedAccount; ?></span>
                                </div>
                                <div>
                                    <span class="text-on-surface-variant block font-bold">IFSC Code</span>
                                    <span class="text-on-surface font-mono"><?php echo htmlspecialchars($pStore['bank_ifsc']); ?></span>
                                </div>
                                <div>
                                    <span class="text-on-surface-variant block font-bold">GSTIN ID</span>
                                    <span class="text-on-surface font-mono"><?php echo htmlspecialchars($pStore['gstin'] ?: 'N/A'); ?></span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-on-surface-variant block font-bold">FSSAI License</span>
                                    <span class="text-on-surface font-mono"><?php echo htmlspecialchars($pStore['fssai'] ?: 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4 border-t border-outline/30 mt-4">
                            <button onclick="approveMerchant('<?php echo $pStore['id']; ?>', '<?php echo addslashes($pStore['store_name']); ?>')" class="flex-grow py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                                Approve Profile
                            </button>
                            <button onclick="openViewStoreModal('app-card-<?php echo $pStore['id']; ?>', '<?php echo addslashes($pStore['store_name']); ?>', '<?php echo addslashes($pStore['category']); ?>', '<?php echo addslashes($pStore['bank_holder_name']); ?>', '<?php echo addslashes($pStore['bank_name']); ?>', '<?php echo $maskedAccount; ?>', '<?php echo addslashes($pStore['bank_ifsc']); ?>', '<?php echo addslashes($pStore['gstin'] ?: 'N/A'); ?>', '<?php echo addslashes($pStore['pan'] ?: 'N/A'); ?>', '<?php echo addslashes($pStore['fssai'] ?: 'N/A'); ?>', '<?php echo addslashes($pStore['contact_email']); ?>', '<?php echo addslashes($pStore['contact_phone']); ?>', '<?php echo addslashes($pStore['address'] . ', ' . $pStore['city']); ?>')" class="px-4 py-2 border border-outline hover:border-primary text-on-surface font-bold text-xs uppercase tracking-wider rounded-lg cursor-pointer transition-all">
                                View
                            </button>
                            <button onclick="rejectMerchant('<?php echo $pStore['id']; ?>', '<?php echo addslashes($pStore['store_name']); ?>')" class="px-4 py-2 border border-outline hover:border-primary text-on-surface font-bold text-xs uppercase tracking-wider rounded-lg cursor-pointer transition-all">
                                Reject
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Empty state check -->
            <div id="noPendingApprovals" class="<?php echo empty($pendingStores) ? '' : 'hidden'; ?> p-8 bg-surface-container border border-outline border-dashed rounded-2xl text-center">
                <span class="material-symbols-outlined text-on-surface-variant text-4xl mb-2">task_alt</span>
                <p class="text-sm text-on-surface-variant mb-0">All pending merchant onboarding applications have been processed!</p>
            </div>
        </div>

        <!-- 2. ACTIVE MERCHANTS ROSTER & COMMISSION CONFIG -->
        <div class="space-y-4 pt-4">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary">store</span>
                    <h3 class="font-headline-md text-headline-md text-on-surface font-bold mb-0">Active Store Rosters</h3>
                </div>
                <div class="flex gap-3 w-full md:w-auto">
                    <input id="merchantSearchInput" oninput="filterSellersTable()" type="text" class="bg-surface border border-outline rounded-xl px-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/40 w-full md:w-64" placeholder="Search stores by name...">
                </div>
            </div>

            <!-- Roster Table -->
            <div class="bg-surface-container rounded-2xl border border-outline shadow-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-outline bg-surface-container-low font-bold text-xs uppercase tracking-wider text-on-surface-variant">
                                <th class="py-4 px-6">Store Details</th>
                                <th class="py-4 px-6">Category</th>
                                <th class="py-4 px-6">Commission Rate</th>
                                <th class="py-4 px-6">Razorpay Settlement Linked Bank</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sellersTableBody">
                            <?php foreach ($activeStores as $aStore): ?>
                                <?php 
                                $words = explode(' ', $aStore['store_name']);
                                $initials = strtoupper(substr($words[0] ?? '', 0, 1) . substr($words[1] ?? '', 0, 1));
                                if (empty($initials)) $initials = 'ST';
                                $maskedAccount = '••••••••' . substr($aStore['bank_account_number'], -4);
                                ?>
                                <tr class="border-b border-outline/30 hover:bg-surface-container-high transition-colors align-middle seller-row" id="seller-row-<?php echo $aStore['id']; ?>" data-status="<?php echo $aStore['store_status']; ?>">
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center overflow-hidden font-bold text-primary font-mono"><?php echo $initials; ?></div>
                                            <div>
                                                <div class="text-sm font-bold text-on-surface font-name"><?php echo htmlspecialchars($aStore['store_name']); ?></div>
                                                <div class="text-[10px] text-on-surface-variant font-mono">STORE-ID-<?php echo $aStore['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="text-xs text-on-surface font-semibold"><?php echo htmlspecialchars($aStore['category']); ?></span>
                                    </td>
                                    <td class="py-4 px-6 align-middle">
                                        <div class="flex items-center gap-2">
                                            <input type="number" class="w-16 bg-surface border border-outline rounded-lg px-2.5 py-1 font-mono text-xs text-on-surface font-bold text-center" value="<?php echo number_format($aStore['commission_rate'], 0); ?>" min="0" max="30">
                                            <span class="text-xs text-on-surface-variant font-bold">%</span>
                                            <button onclick="saveCommission('<?php echo $aStore['id']; ?>', '<?php echo addslashes($aStore['store_name']); ?>', this)" class="p-1 border border-outline bg-surface hover:border-primary text-on-surface rounded-lg cursor-pointer">
                                                <span class="material-symbols-outlined text-sm">save</span>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="text-xs text-on-surface font-semibold font-bank"><?php echo htmlspecialchars($aStore['bank_name']); ?></div>
                                        <div class="text-[10px] text-on-surface-variant font-mono font-acct"><?php echo $maskedAccount; ?></div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="status-badge text-[10px] font-bold px-2.5 py-1 <?php echo $aStore['store_status'] === 'Active' ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'; ?> rounded-full uppercase tracking-wider"><?php echo $aStore['store_status']; ?></span>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <div class="flex justify-end items-center gap-3">
                                            <button onclick="openViewStoreModal('seller-row-<?php echo $aStore['id']; ?>', '<?php echo addslashes($aStore['store_name']); ?>', '<?php echo addslashes($aStore['category']); ?>', '<?php echo addslashes($aStore['bank_holder_name']); ?>', '<?php echo addslashes($aStore['bank_name']); ?>', '<?php echo $maskedAccount; ?>', '<?php echo addslashes($aStore['bank_ifsc']); ?>', '<?php echo addslashes($aStore['gstin'] ?: 'N/A'); ?>', '<?php echo addslashes($aStore['pan'] ?: 'N/A'); ?>', '<?php echo addslashes($aStore['fssai'] ?: 'N/A'); ?>', '<?php echo addslashes($aStore['contact_email']); ?>', '<?php echo addslashes($aStore['contact_phone']); ?>', '<?php echo addslashes($aStore['address'] . ', ' . $aStore['city']); ?>')" class="px-3 py-1.5 bg-surface-container-highest border border-outline hover:border-primary text-on-surface rounded-lg text-xs font-bold cursor-pointer transition-all">
                                                View
                                            </button>
                                            <label class="relative inline-flex items-center cursor-pointer select-none">
                                                <input type="checkbox" <?php echo $aStore['store_status'] === 'Active' ? 'checked' : ''; ?> onchange="toggleSellerStatus('<?php echo $aStore['id']; ?>', '<?php echo addslashes($aStore['store_name']); ?>', this)" class="sr-only peer">
                                                <div class="w-11 h-6 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-on-surface after:border-outline-variant after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary peer-checked:after:bg-on-primary"></div>
                                            </label>
                                        </div>
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

<script>
    // Approve onboarding profile
    function approveMerchant(storeId, name) {
        if (confirm(`Approve onboarding application and activate public storefront for merchant: ${name}?`)) {
            const formData = new FormData();
            formData.append('action', 'approve_store');
            formData.append('store_id', storeId);
            fetch('', { method: 'POST', body: formData })
                .then(() => window.location.reload());
        }
    }

    // Reject onboarding application
    function rejectMerchant(storeId, name) {
        if (confirm(`Are you sure you want to reject the merchant application for ${name}?`)) {
            const formData = new FormData();
            formData.append('action', 'reject_store');
            formData.append('store_id', storeId);
            fetch('', { method: 'POST', body: formData })
                .then(() => window.location.reload());
        }
    }

    // Toggle store active / suspended
    function toggleSellerStatus(storeId, name, toggleCheckbox) {
        const newStatus = toggleCheckbox.checked ? 'Active' : 'Deactivated';
        const formData = new FormData();
        formData.append('action', 'toggle_store_status');
        formData.append('store_id', storeId);
        formData.append('status', newStatus);
        fetch('', { method: 'POST', body: formData })
            .then(() => window.location.reload());
    }

    // Save commission changes
    function saveCommission(storeId, name, btn) {
        const input = btn.parentElement.querySelector('input');
        if (input) {
            const val = parseFloat(input.value);
            if (isNaN(val) || val < 0 || val > 30) {
                alert('Commission rate must be a valid percentage between 0% and 30%.');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'save_commission');
            formData.append('store_id', storeId);
            formData.append('commission_rate', val);
            fetch('', { method: 'POST', body: formData })
                .then(() => window.location.reload());
        }
    }

    // Filter merchants roster list
    function filterSellersTable() {
        const query = document.getElementById('merchantSearchInput').value.toLowerCase();
        const rows = document.querySelectorAll('.seller-row');

        rows.forEach(row => {
            const name = row.querySelector('.font-name').textContent.toLowerCase();
            if (name.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Modal triggers
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
            modal.querySelector('.max-w-lg')?.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }

    function openViewStoreModal(id, name, category, holder, bank, account, ifsc, gstin, pan, fssai, email, phone, address) {
        document.getElementById('viewStoreTitle').textContent = name;
        document.getElementById('viewStoreCategory').textContent = 'Category: ' + category;
        document.getElementById('viewStoreEmail').textContent = email;
        document.getElementById('viewStorePhone').textContent = phone;
        document.getElementById('viewStoreAddress').textContent = address;
        document.getElementById('viewStoreGstin').textContent = gstin;
        document.getElementById('viewStorePan').textContent = pan;
        document.getElementById('viewStoreFssai').textContent = fssai;
        document.getElementById('viewStoreHolder').textContent = holder;
        document.getElementById('viewStoreBank').textContent = bank;
        document.getElementById('viewStoreAccount').textContent = account;
        document.getElementById('viewStoreIfsc').textContent = ifsc;
        openModal('viewStoreModal');
    }
</script>

<!-- View Store Registration Details Modal -->
<div id="viewStoreModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface-container border border-outline rounded-2xl w-full max-w-lg p-6 shadow-2xl scale-95 transition-transform duration-300 overflow-y-auto max-h-[90vh]">
        <div class="flex justify-between items-center border-b border-outline pb-3 mb-4">
            <div>
                <h3 class="font-bold text-on-surface text-base mb-0" id="viewStoreTitle">Store Details</h3>
                <span class="text-xs text-on-surface-variant font-semibold" id="viewStoreCategory"></span>
            </div>
            <button onclick="closeModal('viewStoreModal')" class="bg-transparent border-0 text-on-surface-variant hover:text-on-surface cursor-pointer p-0">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div class="space-y-6 text-left">
            <!-- 1. Store Credentials -->
            <div>
                <h4 class="text-xs uppercase tracking-widest text-primary font-bold mb-2">1. Onboarding Store Credentials</h4>
                <div class="grid grid-cols-2 gap-4 text-xs bg-surface p-3 rounded-xl border border-outline/30">
                    <div>
                        <span class="text-on-surface-variant block font-bold">Contact Email</span>
                        <span class="text-on-surface font-semibold font-mono text-xs" id="viewStoreEmail"></span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant block font-bold">Contact Phone</span>
                        <span class="text-on-surface font-semibold font-mono text-xs" id="viewStorePhone"></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-on-surface-variant block font-bold">Physical Address</span>
                        <span class="text-on-surface font-semibold text-xs" id="viewStoreAddress"></span>
                    </div>
                </div>
            </div>

            <!-- 2. Business Verification Details -->
            <div>
                <h4 class="text-xs uppercase tracking-widest text-primary font-bold mb-2">2. Business Verification Docs</h4>
                <div class="grid grid-cols-2 gap-4 text-xs bg-surface p-3 rounded-xl border border-outline/30">
                    <div>
                        <span class="text-on-surface-variant block font-bold">GSTIN / Tax ID</span>
                        <span class="text-on-surface font-semibold font-mono text-xs" id="viewStoreGstin"></span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant block font-bold">PAN Number</span>
                        <span class="text-on-surface font-semibold font-mono text-xs" id="viewStorePan"></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-on-surface-variant block font-bold">FSSAI License Number</span>
                        <span class="text-on-surface font-semibold font-mono text-xs" id="viewStoreFssai"></span>
                    </div>
                </div>
            </div>

            <!-- 3. Settlement Bank Details -->
            <div>
                <h4 class="text-xs uppercase tracking-widest text-primary font-bold mb-2">3. Razorpay Route Settlement Bank</h4>
                <div class="grid grid-cols-2 gap-4 text-xs bg-surface p-3 rounded-xl border border-outline/30">
                    <div>
                        <span class="text-on-surface-variant block font-bold">Account Holder Name</span>
                        <span class="text-on-surface font-semibold text-xs" id="viewStoreHolder"></span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant block font-bold">Bank Name</span>
                        <span class="text-on-surface font-semibold text-xs" id="viewStoreBank"></span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant block font-bold">Account Number</span>
                        <span class="text-on-surface font-semibold font-mono text-xs" id="viewStoreAccount"></span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant block font-bold">IFSC Code</span>
                        <span class="text-on-surface font-semibold font-mono text-xs" id="viewStoreIfsc"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end mt-6">
            <button onclick="closeModal('viewStoreModal')" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                Close View
            </button>
        </div>
    </div>
</div>

<?php view('partials/admin_footer'); ?>
