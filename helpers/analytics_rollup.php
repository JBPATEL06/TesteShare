<?php
/**
 * Analytics Rollup and Retention Pruning Engine
 * 
 * Aggregates daily orders into daily/monthly/yearly rollup tables.
 * Purges raw daily records older than 30 days and monthly records older than 1 year (12 months).
 */

function runAnalyticsRollupAndPrune($db) {
    try {
        // 1. Rollup completed daily orders into daily_revenue_rollups
        $dailyRollupQuery = "
            INSERT INTO daily_revenue_rollups (store_id, rollup_date, total_revenue, total_commission, net_earnings, orders_count)
            SELECT 
                store_id, 
                DATE(created_at) as r_date,
                SUM(total_amount) as tot_rev,
                SUM(platform_commission) as tot_comm,
                SUM(store_net_amount) as tot_net,
                COUNT(id) as ord_cnt
            FROM orders
            WHERE payment_status = 'Paid'
            GROUP BY store_id, DATE(created_at)
            ON DUPLICATE KEY UPDATE
                total_revenue = VALUES(total_revenue),
                total_commission = VALUES(total_commission),
                net_earnings = VALUES(net_earnings),
                orders_count = VALUES(orders_count)
        ";
        $db->exec($dailyRollupQuery);

        // 2. Rollup daily records into monthly_revenue_rollups
        $monthlyRollupQuery = "
            INSERT INTO monthly_revenue_rollups (store_id, `year_month`, total_revenue, total_commission, net_earnings, orders_count)
            SELECT 
                store_id, 
                DATE_FORMAT(rollup_date, '%Y-%m') as r_month,
                SUM(total_revenue),
                SUM(total_commission),
                SUM(net_earnings),
                SUM(orders_count)
            FROM daily_revenue_rollups
            GROUP BY store_id, DATE_FORMAT(rollup_date, '%Y-%m')
            ON DUPLICATE KEY UPDATE
                total_revenue = VALUES(total_revenue),
                total_commission = VALUES(total_commission),
                net_earnings = VALUES(net_earnings),
                orders_count = VALUES(orders_count)
        ";
        $db->exec($monthlyRollupQuery);

        // 3. Rollup monthly records into yearly_revenue_rollups
        $yearlyRollupQuery = "
            INSERT INTO yearly_revenue_rollups (store_id, `rollup_year`, total_revenue, total_commission, net_earnings, orders_count)
            SELECT 
                store_id, 
                CAST(SUBSTRING(`year_month`, 1, 4) AS UNSIGNED) as r_year,
                SUM(total_revenue),
                SUM(total_commission),
                SUM(net_earnings),
                SUM(orders_count)
            FROM monthly_revenue_rollups
            GROUP BY store_id, CAST(SUBSTRING(`year_month`, 1, 4) AS UNSIGNED)
            ON DUPLICATE KEY UPDATE
                total_revenue = VALUES(total_revenue),
                total_commission = VALUES(total_commission),
                net_earnings = VALUES(net_earnings),
                orders_count = VALUES(orders_count)
        ";
        $db->exec($yearlyRollupQuery);

        // 4. RETENTION CLEANUP: Prune granular raw orders older than 30 days (older than 30 days from today)
        // Keep order records intact for recent 30 days; older data is preserved in daily_revenue_rollups & monthly_revenue_rollups
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        $pruneOrders = $db->prepare("DELETE FROM orders WHERE payment_status = 'Paid' AND order_status IN ('Completed', 'Delivered', 'Cancelled') AND created_at < ?");
        $pruneOrders->execute([$thirtyDaysAgo]);

        // 5. RETENTION CLEANUP: Prune monthly rollups older than 12 months (1 year)
        // Kept in yearly_revenue_rollups
        $oneYearAgoMonth = date('Y-m', strtotime('-12 months'));
        $pruneMonthly = $db->prepare("DELETE FROM monthly_revenue_rollups WHERE `year_month` < ?");
        $pruneMonthly->execute([$oneYearAgoMonth]);

        return true;
    } catch (Exception $e) {
        error_log("Analytics Rollup & Prune Error: " . $e->getMessage());
        return false;
    }
}
