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
    if ($action === 'extend_sub') {
        $subId = intval($_POST['sub_id'] ?? 0);
        $newExpiry = trim($_POST['new_expiry'] ?? '');
        if ($subId && $newExpiry) {
            $stmt = $db->prepare("UPDATE billing_subscriptions SET end_date = ?, status = 'Active' WHERE id = ?");
            $stmt->execute([$newExpiry, $subId]);
        }
        exit;
    } elseif ($action === 'cancel_sub') {
        $subId = intval($_POST['sub_id'] ?? 0);
        if ($subId) {
            $stmt = $db->prepare("UPDATE billing_subscriptions SET status = 'Cancelled' WHERE id = ?");
            $stmt->execute([$subId]);
        }
        exit;
    } elseif ($action === 'change_tier') {
        $storeId = intval($_POST['store_id'] ?? 0);
        $newTier = trim($_POST['tier'] ?? '');
        if ($storeId && in_array($newTier, ['Starter', 'Premium', 'Ultra Premium'])) {
            $price = 0.00;
            $commRate = 5.00;
            if ($newTier === 'Premium') { $price = 49.00; $commRate = 2.00; }
            if ($newTier === 'Ultra Premium') { $price = 149.00; $commRate = 0.00; }
            
            $db->prepare("UPDATE billing_subscriptions SET status = 'Cancelled' WHERE store_id = ? AND status = 'Active'")->execute([$storeId]);
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime('+1 month'));
            $db->prepare("INSERT INTO billing_subscriptions (store_id, tier, price_per_month, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'Active')")->execute([$storeId, $newTier, $price, $startDate, $endDate]);
            $db->prepare("UPDATE stores SET subscription_tier = ?, commission_rate = ? WHERE id = ?")->execute([$newTier, $commRate, $storeId]);
        }
        exit;
    } elseif ($action === 'gift_trial') {
        $storeId = intval($_POST['store_id'] ?? 0);
        $trialTier = trim($_POST['tier'] ?? 'Premium');
        if ($storeId && in_array($trialTier, ['Premium', 'Ultra Premium'])) {
            $db->prepare("UPDATE billing_subscriptions SET status = 'Cancelled' WHERE store_id = ? AND status = 'Active'")->execute([$storeId]);
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime('+30 days'));
            $db->prepare("INSERT INTO billing_subscriptions (store_id, tier, price_per_month, start_date, end_date, status) VALUES (?, ?, 0.00, ?, ?, 'Active')")->execute([$storeId, $trialTier, $startDate, $endDate]);
            $db->prepare("UPDATE stores SET subscription_tier = ?, commission_rate = 0.00 WHERE id = ?")->execute([$trialTier, $storeId]);
        }
        exit;
    }
}

// Stats
$mrr = $db->query("SELECT IFNULL(SUM(price_per_month), 0) FROM billing_subscriptions WHERE status = 'Active'")->fetchColumn();
$activeSubs = $db->query("SELECT COUNT(*) FROM billing_subscriptions WHERE status = 'Active'")->fetchColumn();
$avgTierRate = $db->query("SELECT IFNULL(AVG(price_per_month), 0) FROM billing_subscriptions WHERE status = 'Active'")->fetchColumn();

// Roster
$subs = $db->query("
    SELECT bs.*, s.store_name, s.id as store_num
    FROM billing_subscriptions bs
    JOIN stores s ON bs.store_id = s.id
    ORDER BY bs.end_date DESC
")->fetchAll();

$pageTitle = 'Seller Subscriptions Audit';
$activeNav = 'subscriptions';
view('partials/admin_header', get_defined_vars());
view('partials/admin_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <!-- Content Canvas -->
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-outline pb-6">
            <div>
                <h2 class="font-headline-lg text-headline-lg text-on-surface font-bold mb-1">Store Subscriptions</h2>
                <p class="text-body-md text-on-surface-variant mb-0">Track active billing agreements, plan tiers, and automated billing renewal states.</p>
            </div>
            <div class="flex gap-3 w-full md:w-auto">
                <input id="subSearchInput" oninput="filterSubscriptions()" type="text" class="bg-surface border border-outline rounded-xl px-4 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/40 w-full md:w-64" placeholder="Search stores...">
            </div>
        </div>

        <!-- Metrics Overview Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-surface-container border border-outline p-5 rounded-2xl">
                <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Monthly Recurring Revenue</span>
                <div class="text-2xl font-bold font-mono text-primary mt-2">₹<?php echo number_format($mrr, 2); ?></div>
                <div class="text-xs text-on-surface-variant mt-1">Active agreement MRR aggregate</div>
            </div>
            <div class="bg-surface-container border border-outline p-5 rounded-2xl">
                <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Active Subscriptions</span>
                <div class="text-2xl font-bold font-mono text-on-surface mt-2"><?php echo number_format($activeSubs); ?> Stores</div>
                <div class="text-xs text-green-400 font-bold mt-1">All active billing stores</div>
            </div>
            <div class="bg-surface-container border border-outline p-5 rounded-2xl">
                <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Average Tier Rate</span>
                <div class="text-2xl font-bold font-mono text-on-surface mt-2">₹<?php echo number_format($avgTierRate, 2); ?> / mo</div>
                <div class="text-xs text-on-surface-variant mt-1">Average fee among active plans</div>
            </div>
        </div>

        <!-- Subscriptions Roster Table -->
        <div class="bg-surface-container rounded-2xl border border-outline shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline bg-surface-container-low font-bold text-xs uppercase tracking-wider text-on-surface-variant">
                            <th class="py-4 px-6">Merchant details</th>
                            <th class="py-4 px-6">Billing Tier</th>
                            <th class="py-4 px-6">Agreement Started</th>
                            <th class="py-4 px-6">Expiration Date</th>
                            <th class="py-4 px-6">Status</th>
                            <th class="py-4 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="subsTableBody">
                        <?php foreach ($subs as $sRow): ?>
                            <?php
                            $tier = $sRow['tier'];
                            $price = $sRow['price_per_month'];
                            $status = $sRow['status'];
                            $tierColor = 'primary';
                            if ($tier === 'Pro') $tierColor = 'teal-400';
                            elseif ($tier === 'Basic') $tierColor = 'blue-400';
                            
                            $statusColor = 'green-500/10 text-green-400 border border-green-500/20';
                            if ($status === 'Expired') $statusColor = 'red-500/10 text-red-400 border border-red-500/20';
                            elseif ($status === 'Cancelled') $statusColor = 'yellow-500/10 text-yellow-400 border border-yellow-500/20';
                            ?>
                            <tr class="border-b border-outline/30 hover:bg-surface-container-high transition-colors align-middle sub-row" id="sub-row-<?php echo $sRow['id']; ?>" data-tier="<?php echo $tier; ?>" data-status="<?php echo $status; ?>">
                                <td class="py-4 px-6 font-name">
                                    <div class="font-bold text-on-surface"><?php echo htmlspecialchars($sRow['store_name']); ?></div>
                                    <div class="text-[10px] font-mono text-on-surface-variant">STORE-ID-<?php echo $sRow['store_id']; ?></div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="text-xs font-bold text-<?php echo $tierColor; ?> px-2.5 py-1 bg-<?php echo $tierColor; ?>/10 border border-<?php echo $tierColor; ?>/20 rounded font-mono">
                                        <?php echo strtoupper($tier); ?> PLAN (₹<?php echo number_format($price, 0); ?>/mo)
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-mono text-xs text-on-surface-variant"><?php echo $sRow['start_date']; ?></td>
                                <td class="py-4 px-6 font-mono text-xs text-on-surface font-semibold font-expiry"><?php echo $sRow['end_date']; ?></td>
                                <td class="py-4 px-6">
                                    <span class="status-badge text-[10px] font-bold px-2.5 py-1 <?php echo $statusColor; ?> rounded-full uppercase tracking-wider"><?php echo $status; ?></span>
                                </td>
                                <td class="py-4 px-6 text-right space-x-2">
                                    <button onclick="openExtendSubModal('<?php echo $sRow['id']; ?>', '<?php echo addslashes($sRow['store_name']); ?>', '<?php echo $sRow['end_date']; ?>')" class="px-3 py-1.5 bg-surface-container-highest border border-outline hover:border-primary text-on-surface rounded-lg text-xs font-bold cursor-pointer transition-all">
                                        <?php echo ($status === 'Expired' || $status === 'Cancelled') ? 'Renew' : 'Extend'; ?>
                                    </button>
                                    <?php if ($status === 'Active'): ?>
                                        <button onclick="cancelSub('<?php echo $sRow['id']; ?>', '<?php echo addslashes($sRow['store_name']); ?>')" class="px-3 py-1.5 border border-red-500/30 text-red-400 bg-red-500/5 hover:bg-red-500/10 rounded-lg text-xs font-bold cursor-pointer transition-all">Cancel Plan</button>
                                    <?php else: ?>
                                        <button class="px-3 py-1.5 border border-outline text-on-surface-variant bg-transparent rounded-lg text-xs font-bold opacity-30 cursor-not-allowed select-none" disabled>Cancelled</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($subs)): ?>
                            <tr>
                                <td colspan="6" class="py-4 text-center text-on-surface-variant italic">No subscriptions registered.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Extend Subscription Modal -->
<div id="extendSubModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-surface-container border border-outline rounded-2xl w-full max-w-md p-6 shadow-2xl scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center border-b border-outline pb-3 mb-4">
            <h3 class="font-bold text-on-surface text-base mb-0">Extend Subscription Plan</h3>
            <button onclick="closeModal('extendSubModal')" class="bg-transparent border-0 text-on-surface-variant hover:text-on-surface cursor-pointer p-0">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <input type="hidden" id="extendStoreId">
        <div class="space-y-4">
            <div>
                <span class="text-xs text-on-surface-variant font-bold block mb-1">Store Name</span>
                <span id="extendStoreNameText" class="font-bold text-on-surface block text-sm"></span>
            </div>
            <div>
                <label class="text-xs text-on-surface-variant font-bold block mb-1">New Expiry Date</label>
                <input type="date" id="newExpiryDateInput" class="w-full bg-surface border border-outline rounded-xl px-3 py-2.5 text-sm text-on-surface focus:outline-none focus:border-primary">
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button onclick="closeModal('extendSubModal')" class="px-4 py-2 border border-outline text-on-surface bg-transparent font-bold text-xs uppercase tracking-wider rounded-lg hover:bg-surface-container-highest cursor-pointer">
                Cancel
            </button>
            <button onclick="submitExtendSub()" class="px-4 py-2 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-lg border-0 shadow-md cursor-pointer hover:brightness-110 active:scale-95 transition-all">
                Save Agreement
            </button>
        </div>
    </div>
</div>

<script>
    // Modal management
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
            modal.querySelector('.max-w-md')?.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }

    function openExtendSubModal(subId, name, currentExpiry) {
        document.getElementById('extendStoreId').value = subId;
        document.getElementById('extendStoreNameText').textContent = name;
        document.getElementById('newExpiryDateInput').value = currentExpiry;
        openModal('extendSubModal');
    }

    function submitExtendSub() {
        const id = document.getElementById('extendStoreId').value;
        const name = document.getElementById('extendStoreNameText').textContent;
        const newExpiry = document.getElementById('newExpiryDateInput').value;

        if (!newExpiry) {
            alert('Please select a valid expiry date.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'extend_sub');
        formData.append('sub_id', id);
        formData.append('new_expiry', newExpiry);

        fetch('', { method: 'POST', body: formData })
            .then(() => {
                closeModal('extendSubModal');
                alert(`Successfully extended billing agreement for ${name} to ${newExpiry}!`);
                window.location.reload();
            });
    }

    // Cancel plan
    function cancelSub(subId, name) {
        if (confirm(`Are you sure you want to cancel the active subscription agreement for ${name}? The seller panel will enter read-only mode after expiry.`)) {
            const formData = new FormData();
            formData.append('action', 'cancel_sub');
            formData.append('sub_id', subId);

            fetch('', { method: 'POST', body: formData })
                .then(() => {
                    alert(`Billing plan canceled for: ${name}`);
                    window.location.reload();
                });
        }
    }

    function filterSubscriptions() {
        const query = document.getElementById('subSearchInput').value.toLowerCase();
        const rows = document.querySelectorAll('.sub-row');

        rows.forEach(row => {
            const name = row.querySelector('.font-name').textContent.toLowerCase();
            const matchesQuery = name.includes(query);

            if (matchesQuery) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php view('partials/admin_footer'); ?>
