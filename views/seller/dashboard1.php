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
require_once __DIR__ . '/../../helpers/analytics_rollup.php';

$db = getDB();
$sellerId = $_SESSION['user_id'] ?? null;

// Run automated rollup and 30-day / 1-year retention prune engine
runAnalyticsRollupAndPrune($db);

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

// Active Time Period Filter ('day', 'month', 'year')
$period = strtolower($_POST['period'] ?? $_GET['period'] ?? 'day');
if (!in_array($period, ['day', 'month', 'year'])) {
    $period = 'day';
}

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && $storeId) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_' . $period . '_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Dish Name', 'Price', 'Sell Date & Time', 'Commission Rate', 'Commission Amount', 'Net Earnings', 'Status']);
    
    $whereClause = "WHERE o.store_id = ? AND o.payment_status = 'Paid'";
    if ($period === 'day') {
        $whereClause .= " AND DATE(o.created_at) = CURDATE()";
    } elseif ($period === 'month') {
        $whereClause .= " AND MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
    } elseif ($period === 'year') {
        $whereClause .= " AND YEAR(o.created_at) = YEAR(CURDATE())";
    }
    
    $exportStmt = $db->prepare("
        SELECT m.name as dish_name, oi.price, o.created_at, s.commission_rate, o.platform_commission, o.store_net_amount, o.order_status
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN menu_items m ON oi.menu_item_id = m.id
        JOIN stores s ON o.store_id = s.id
        $whereClause
        ORDER BY o.created_at DESC
    ");
    $exportStmt->execute([$storeId]);
    $rows = $exportStmt->fetchAll();
    
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['dish_name'],
            '₹' . number_format($row['price'], 2),
            date('M d, Y | H:i', strtotime($row['created_at'])),
            number_format($row['commission_rate'], 1) . '%',
            '₹' . number_format($row['platform_commission'], 2),
            '₹' . number_format($row['store_net_amount'], 2),
            $row['order_status']
        ]);
    }
    fclose($output);
    exit;
}

// Build SQL date filter condition
$dateFilter = "AND DATE(o.created_at) = CURDATE()";
if ($period === 'month') {
    $dateFilter = "AND MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
} elseif ($period === 'year') {
    $dateFilter = "AND YEAR(o.created_at) = YEAR(CURDATE())";
}

// 1. Calculate Analytics Metrics for chosen time period
$statsStmt = $db->prepare("
    SELECT 
        IFNULL(SUM(o.total_amount), 0.0) as total_revenue,
        IFNULL(SUM(o.platform_commission), 0.0) as total_commission,
        IFNULL(SUM(o.store_net_amount), 0.0) as net_earnings,
        COUNT(o.id) as completed_transactions
    FROM orders o
    WHERE o.store_id = ? AND o.payment_status = 'Paid' $dateFilter
");
$statsStmt->execute([$storeId]);
$stats = $statsStmt->fetch();

$totalRevenue = floatval($stats['total_revenue'] ?? 0.0);
$totalCommission = floatval($stats['total_commission'] ?? 0.0);
$netEarnings = floatval($stats['net_earnings'] ?? 0.0);
$completedTransactions = intval($stats['completed_transactions'] ?? 0);

// Fallback to rollups if granular raw table is pruned
if ($totalRevenue == 0.0 && $period !== 'day') {
    if ($period === 'month') {
        $rStmt = $db->prepare("SELECT IFNULL(SUM(total_revenue),0), IFNULL(SUM(total_commission),0), IFNULL(SUM(net_earnings),0), IFNULL(SUM(orders_count),0) FROM monthly_revenue_rollups WHERE store_id = ? AND `year_month` = ?");
        $rStmt->execute([$storeId, date('Y-m')]);
    } else {
        $rStmt = $db->prepare("SELECT IFNULL(SUM(total_revenue),0), IFNULL(SUM(total_commission),0), IFNULL(SUM(net_earnings),0), IFNULL(SUM(orders_count),0) FROM yearly_revenue_rollups WHERE store_id = ? AND `rollup_year` = ?");
        $rStmt->execute([$storeId, date('Y')]);
    }
    $rRes = $rStmt->fetch(PDO::FETCH_NUM);
    if ($rRes && $rRes[0] > 0) {
        $totalRevenue = floatval($rRes[0]);
        $totalCommission = floatval($rRes[1]);
        $netEarnings = floatval($rRes[2]);
        $completedTransactions = intval($rRes[3]);
    }
}

// Build SQL date filter for PREVIOUS period to compute dynamic trend growth
$prevDateFilter = "AND DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
if ($period === 'month') {
    $prevDateFilter = "AND MONTH(o.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(o.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
} elseif ($period === 'year') {
    $prevDateFilter = "AND YEAR(o.created_at) = (YEAR(CURDATE()) - 1)";
}

$prevStatsStmt = $db->prepare("
    SELECT 
        IFNULL(SUM(o.total_amount), 0.0) as total_revenue,
        IFNULL(SUM(o.platform_commission), 0.0) as total_commission,
        IFNULL(SUM(o.store_net_amount), 0.0) as net_earnings,
        COUNT(o.id) as completed_transactions
    FROM orders o
    WHERE o.store_id = ? AND o.payment_status = 'Paid' $prevDateFilter
");
$prevStatsStmt->execute([$storeId]);
$prevStats = $prevStatsStmt->fetch();

$prevRevenue = floatval($prevStats['total_revenue'] ?? 0.0);
$prevCommission = floatval($prevStats['total_commission'] ?? 0.0);
$prevNetEarnings = floatval($prevStats['net_earnings'] ?? 0.0);
$prevTransactions = intval($prevStats['completed_transactions'] ?? 0);

function calcTrendPercent($curr, $prev) {
    if ($prev <= 0) {
        return $curr > 0 ? 100.0 : 0.0;
    }
    return round((($curr - $prev) / $prev) * 100, 1);
}

$revGrowth = calcTrendPercent($totalRevenue, $prevRevenue);
$commGrowth = calcTrendPercent($totalCommission, $prevCommission);
$netGrowth = calcTrendPercent($netEarnings, $prevNetEarnings);
$txGrowth = calcTrendPercent($completedTransactions, $prevTransactions);

// 2. Fetch Sales Transactions for the table view
$txStmt = $db->prepare("
    SELECT oi.*, m.name as dish_name, m.image_url, o.created_at as sell_time, o.order_status, o.platform_commission, s.commission_rate
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN menu_items m ON oi.menu_item_id = m.id
    JOIN stores s ON o.store_id = s.id
    WHERE o.store_id = ? AND o.payment_status = 'Paid' $dateFilter
    ORDER BY o.created_at DESC
    LIMIT 50
");
$txStmt->execute([$storeId]);
$transactions = $txStmt->fetchAll();

// 3. Live Tracking Orders
$liveOrdersStmt = $db->prepare("
    SELECT o.*, u.fullname as customer_name
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    WHERE o.store_id = ? AND o.order_status IN ('Pending', 'Accepted', 'Preparing', 'Out For Delivery')
    ORDER BY o.created_at DESC
");
$liveOrdersStmt->execute([$storeId]);
$liveOrders = $liveOrdersStmt->fetchAll();

// Fetch items summary for each live order
$liveOrderSummaries = [];
foreach ($liveOrders as $lo) {
    $itemsStmt = $db->prepare("
        SELECT oi.*, m.name as dish_name
        FROM order_items oi
        JOIN menu_items m ON oi.menu_item_id = m.id
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$lo['id']]);
    $items = $itemsStmt->fetchAll();
    
    $summary = [];
    foreach ($items as $it) {
        $summary[] = $it['quantity'] . 'x ' . $it['dish_name'];
    }
    $liveOrderSummaries[$lo['id']] = implode(', ', $summary);
}

$pageTitle = 'Sales Overview & Dashboard';
$activeNav = 'dashboard1';
view('partials/seller_header', get_defined_vars());
view('partials/seller_sidebar', get_defined_vars());
?>

<div class="flex-grow p-8 overflow-y-auto">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <!-- Header & Download Report Bar -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-headline-xl font-bold text-on-surface mb-1">Sales Overview</h1>
                <p class="text-body-md text-on-surface-variant mb-0">Detailed performance breakdown for the current period.</p>
            </div>
            <div class="flex items-center gap-4 w-full md:w-auto">
                <!-- Time Period Segmented Filter -->
                <div class="bg-surface-container border border-outline-variant p-1 rounded-xl flex items-center gap-1">
                    <a href="<?php echo url('seller/dashboard1&period=day'); ?>" class="px-5 py-2 font-bold text-xs uppercase tracking-wider rounded-lg text-decoration-none transition-all <?php echo $period === 'day' ? 'bg-primary text-on-primary shadow-md' : 'text-on-surface-variant hover:text-on-surface'; ?>">Day</a>
                    <a href="<?php echo url('seller/dashboard1&period=month'); ?>" class="px-5 py-2 font-bold text-xs uppercase tracking-wider rounded-lg text-decoration-none transition-all <?php echo $period === 'month' ? 'bg-primary text-on-primary shadow-md' : 'text-on-surface-variant hover:text-on-surface'; ?>">Month</a>
                    <a href="<?php echo url('seller/dashboard1&period=year'); ?>" class="px-5 py-2 font-bold text-xs uppercase tracking-wider rounded-lg text-decoration-none transition-all <?php echo $period === 'year' ? 'bg-primary text-on-primary shadow-md' : 'text-on-surface-variant hover:text-on-surface'; ?>">Year</a>
                </div>
                <!-- Download Report Button -->
                <a href="<?php echo url('seller/dashboard1&action=export_csv&period=' . $period); ?>" class="px-5 py-2.5 bg-primary text-on-primary font-bold text-xs uppercase tracking-wider rounded-xl hover:brightness-110 active:scale-95 transition-all text-decoration-none flex items-center gap-2 shadow-lg shrink-0">
                    <span class="material-symbols-outlined text-sm">download</span>
                    Download Report
                </a>
            </div>
        </div>

        <!-- 4 Bento Analytics Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- Card 1: TOTAL REVENUE -->
            <div class="bg-surface-container border border-outline-variant p-6 rounded-2xl shadow-xl relative overflow-hidden group hover:border-primary/50 transition-all">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 rounded-xl bg-surface-container-high border border-outline-variant flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-xl">payments</span>
                    </div>
                    <span class="px-2.5 py-1 <?php echo $revGrowth >= 0 ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30'; ?> border text-[11px] font-bold rounded-full flex items-center gap-1 font-mono">
                        <?php echo ($revGrowth >= 0 ? '+' : '') . number_format($revGrowth, 1) . '%'; ?>
                        <span class="material-symbols-outlined text-xs"><?php echo $revGrowth >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                    </span>
                </div>
                <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Total Revenue</span>
                <div class="text-[32px] font-bold text-on-surface mt-2 mb-0 font-mono">₹<?php echo number_format($totalRevenue, 2); ?></div>
            </div>

            <!-- Card 2: TOTAL COMMISSION -->
            <div class="bg-surface-container border border-outline-variant p-6 rounded-2xl shadow-xl relative overflow-hidden group hover:border-amber-400/50 transition-all">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 rounded-xl bg-surface-container-high border border-outline-variant flex items-center justify-center text-amber-400">
                        <span class="material-symbols-outlined text-xl">percent</span>
                    </div>
                    <span class="px-2.5 py-1 <?php echo $commGrowth >= 0 ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30'; ?> border text-[11px] font-bold rounded-full flex items-center gap-1 font-mono">
                        <?php echo ($commGrowth >= 0 ? '+' : '') . number_format($commGrowth, 1) . '%'; ?>
                        <span class="material-symbols-outlined text-xs"><?php echo $commGrowth >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                    </span>
                </div>
                <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Total Commission</span>
                <div class="text-[32px] font-bold text-amber-400 mt-2 mb-0 font-mono">₹<?php echo number_format($totalCommission, 2); ?></div>
            </div>

            <!-- Card 3: NET EARNINGS -->
            <div class="bg-surface-container border border-outline-variant p-6 rounded-2xl shadow-xl relative overflow-hidden group hover:border-primary/50 transition-all">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 rounded-xl bg-surface-container-high border border-outline-variant flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-xl">account_balance_wallet</span>
                    </div>
                    <span class="px-2.5 py-1 <?php echo $netGrowth >= 0 ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30'; ?> border text-[11px] font-bold rounded-full flex items-center gap-1 font-mono">
                        <?php echo ($netGrowth >= 0 ? '+' : '') . number_format($netGrowth, 1) . '%'; ?>
                        <span class="material-symbols-outlined text-xs"><?php echo $netGrowth >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                    </span>
                </div>
                <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Net Earnings</span>
                <div class="text-[32px] font-bold text-on-surface mt-2 mb-0 font-mono">₹<?php echo number_format($netEarnings, 2); ?></div>
            </div>

            <!-- Card 4: COMPLETED TRANSACTIONS -->
            <div class="bg-surface-container border border-outline-variant p-6 rounded-2xl shadow-xl relative overflow-hidden group hover:border-primary/50 transition-all">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-10 h-10 rounded-xl bg-surface-container-high border border-outline-variant flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-xl">shopping_bag</span>
                    </div>
                    <span class="px-2.5 py-1 <?php echo $txGrowth >= 0 ? 'bg-primary/10 text-primary border-primary/30' : 'bg-red-500/10 text-red-400 border-red-500/30'; ?> border text-[11px] font-bold rounded-full flex items-center gap-1 font-mono">
                        <?php echo ($txGrowth >= 0 ? '+' : '') . number_format($txGrowth, 1) . '%'; ?>
                        <span class="material-symbols-outlined text-xs"><?php echo $txGrowth >= 0 ? 'trending_up' : 'trending_down'; ?></span>
                    </span>
                </div>
                <span class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">Completed Orders</span>
                <div class="text-[32px] font-bold text-on-surface mt-2 mb-0 font-mono"><?php echo number_format($completedTransactions); ?></div>
            </div>
        </div>

        <!-- Sales Transactions Table Section -->
        <div class="bg-surface-container border border-outline-variant rounded-2xl p-6 shadow-xl space-y-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 class="text-headline-sm font-bold text-on-surface mb-0">Sales Transactions</h2>
                
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <!-- Live Dish Search Input -->
                    <div class="relative flex-1 md:w-72">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm">search</span>
                        <input id="dish-search-input" onkeyup="filterTransactions()" type="text" placeholder="Search dish..." class="w-full bg-surface-container-low border border-outline-variant text-on-surface pl-9 pr-4 py-2 rounded-xl text-xs outline-none focus:border-primary">
                    </div>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="tx-table">
                    <thead>
                        <tr class="border-b border-outline-variant text-[11px] font-bold text-on-surface-variant uppercase tracking-wider">
                            <th class="py-3 px-4">Dish Name</th>
                            <th class="py-3 px-4">Price</th>
                            <th class="py-3 px-4">Sell Date &amp; Time</th>
                            <th class="py-3 px-4 text-center">Commission</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40 text-sm">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-on-surface-variant">No sales transactions recorded for this period.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <?php
                                $commAmount = floatval($tx['price']) * (floatval($tx['commission_rate'] ?? 5.0) / 100.0);
                                $statusClass = 'bg-green-500/10 text-green-400 border-green-500/30';
                                if ($tx['order_status'] === 'Preparing' || $tx['order_status'] === 'Pending') {
                                    $statusClass = 'bg-amber-500/10 text-amber-400 border-amber-500/30';
                                } elseif ($tx['order_status'] === 'Cancelled') {
                                    $statusClass = 'bg-red-500/10 text-red-400 border-red-500/30';
                                }
                                ?>
                                <tr class="tx-row hover:bg-surface-container-high/40 transition-colors" data-dish="<?php echo htmlspecialchars(strtolower($tx['dish_name'])); ?>">
                                    <td class="py-4 px-4 font-bold text-on-surface">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-surface-container-high border border-outline-variant overflow-hidden shrink-0 flex items-center justify-center">
                                                <?php if (!empty($tx['image_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($tx['image_url']); ?>" alt="<?php echo htmlspecialchars($tx['dish_name']); ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <span class="material-symbols-outlined text-primary text-sm">restaurant</span>
                                                <?php endif; ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($tx['dish_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 font-bold text-on-surface font-mono">
                                        $<?php echo number_format($tx['price'], 2); ?>
                                    </td>
                                    <td class="py-4 px-4 text-on-surface-variant text-xs font-mono">
                                        <?php echo date('M d, Y | H:i', strtotime($tx['sell_time'])); ?>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <span class="inline-block px-3 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/30 rounded-full font-bold text-xs font-mono">
                                            $<?php echo number_format($commAmount, 2); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="px-3 py-1 border text-xs font-bold rounded-full flex items-center gap-1.5 w-max <?php echo $statusClass; ?>">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                            <?php echo htmlspecialchars($tx['order_status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <a href="<?php echo url('seller/order_detail&id=' . $tx['order_id']); ?>" class="text-on-surface-variant hover:text-primary transition-colors text-decoration-none">
                                            <span class="material-symbols-outlined text-lg">more_vert</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    function filterTransactions() {
        const query = document.getElementById('dish-search-input').value.toLowerCase();
        document.querySelectorAll('.tx-row').forEach(row => {
            const dish = row.dataset.dish || '';
            if (dish.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php view('partials/seller_footer'); ?>
