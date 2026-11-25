<?php
/**
 * YATIYA COUTURE - Executive Administrator Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

// Enforce admin authorization guard
require_admin();

$adminPageTitle = 'Executive Dashboard';
$adminPageSubtitle = 'Comprehensive real-time overview of boutique sales, inventory health, orders, and customer operations.';
$activeMenu = 'dashboard';

// Header Actions
$headerActions = '
    <a href="' . ADMIN_URL . '/homepage-sections.php" class="btn btn-outline btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;">
            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
            <path d="M3 9h18"></path>
            <path d="M9 21V9"></path>
        </svg>
        Manage Homepage CMS
    </a>
    <a href="' . ADMIN_URL . '/product-form.php" class="btn btn-gold btn-sm">
        + Create New Piece
    </a>
';

require_once __DIR__ . '/includes/admin-header.php';

// Fetch live metrics from database
$metrics = get_admin_dashboard_metrics();
$statusDist = $metrics['status_distribution'] ?? [];
$totalOrdersCount = max(1, (int)$metrics['total_orders']);
?>

<!-- 8 Core Metric Cards Grid -->
<div class="metrics-grid">
    <!-- 1. Total Revenue -->
    <div class="metric-card">
        <div>
            <div class="metric-title">Gross Paid Revenue</div>
            <div class="metric-value"><?= format_price($metrics['total_revenue']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Delivered / Settled payments</div>
        </div>
        <div class="metric-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
        </div>
    </div>

    <!-- 2. Total Orders -->
    <div class="metric-card">
        <div>
            <div class="metric-title">Total Orders</div>
            <div class="metric-value"><?= number_format($metrics['total_orders']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Lifetime client orders</div>
        </div>
        <div class="metric-icon-wrap info">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
        </div>
    </div>

    <!-- 3. Pending Orders -->
    <div class="metric-card">
        <div>
            <div class="metric-title">Pending Orders</div>
            <div class="metric-value"><?= number_format($metrics['pending_orders']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Awaiting fulfillment</div>
        </div>
        <div class="metric-icon-wrap" style="background-color: #FFF3E0; color: #ED6C02;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
    </div>

    <!-- 4. Completed Orders -->
    <div class="metric-card">
        <div>
            <div class="metric-title">Completed Orders</div>
            <div class="metric-value"><?= number_format($metrics['completed_orders']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Delivered to patrons</div>
        </div>
        <div class="metric-icon-wrap success">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
        </div>
    </div>

    <!-- 5. Registered Customers -->
    <div class="metric-card" onclick="window.location.href='<?= ADMIN_URL ?>/customers.php'" style="cursor: pointer;">
        <div>
            <div class="metric-title" style="display: flex; align-items: center; justify-content: space-between;">
                <span>Total Patrons</span>
                <span style="font-size: 10px; color: var(--gold); font-weight: 700;">VIEW &rarr;</span>
            </div>
            <div class="metric-value"><?= number_format($metrics['total_customers']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                <?= number_format($metrics['new_registrations'] ?? 0) ?> new in 30 days &bull; Registered clients
            </div>
        </div>
        <div class="metric-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
            </svg>
        </div>
    </div>

    <!-- 6. Total Products -->
    <div class="metric-card">
        <div>
            <div class="metric-title">Active Creations</div>
            <div class="metric-value"><?= number_format($metrics['total_products']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Catalogue products</div>
        </div>
        <div class="metric-icon-wrap info">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
            </svg>
        </div>
    </div>

    <!-- 7. Low-Stock Products -->
    <div class="metric-card">
        <div>
            <div class="metric-title">Low Stock Alert</div>
            <div class="metric-value" style="color: #ED6C02;"><?= number_format($metrics['low_stock_products']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">At or below threshold</div>
        </div>
        <div class="metric-icon-wrap" style="background-color: #FFF3E0; color: #ED6C02;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
    </div>

    <!-- 8. Out-of-Stock Products -->
    <div class="metric-card">
        <div>
            <div class="metric-title">Out of Stock</div>
            <div class="metric-value" style="color: #D32F2F;"><?= number_format($metrics['out_of_stock_products']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Zero stock remaining</div>
        </div>
        <div class="metric-icon-wrap danger">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
        </div>
    </div>
</div>

<!-- Main Dashboard Grid: Sales Overview & Order Status Distribution -->
<div class="dashboard-grid">
    <!-- Left Column: Pure Vanilla SVG Sales Overview Chart -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Weekly Revenue & Order Trajectory</h2>
            <span style="font-size: 12px; color: var(--text-muted);">Last 7 Days (Paid Orders)</span>
        </div>
        <div class="card-body">
            <div id="salesChartContainer" class="chart-container" data-chart='<?= json_encode($metrics['sales_chart']) ?>'>
                <!-- SVG dynamically populated by admin.js -->
            </div>
        </div>
    </div>

    <!-- Right Column: Order Status Distribution -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Order Status Distribution</h2>
            <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-outline btn-sm">View Orders</a>
        </div>
        <div class="card-body" style="padding: 16px 20px;">
            <?php
            $statuses = [
                'delivered' => ['label' => 'Delivered to Patron', 'color' => '#2E7D32'],
                'shipped' => ['label' => 'Shipped / In Transit', 'color' => '#0288D1'],
                'processing' => ['label' => 'Atelier Processing', 'color' => '#ED6C02'],
                'confirmed' => ['label' => 'Confirmed Orders', 'color' => '#C6A15B'],
                'placed' => ['label' => 'Newly Placed', 'color' => '#7B1FA2'],
                'cancelled' => ['label' => 'Cancelled / Returned', 'color' => '#D32F2F'],
            ];
            ?>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($statuses as $stKey => $stMeta): 
                    $count = $statusDist[$stKey] ?? 0;
                    $pct = round(($count / $totalOrdersCount) * 100);
                ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                            <span style="font-weight: 500; color: var(--text-main);"><?= $stMeta['label'] ?></span>
                            <span style="color: var(--text-muted); font-weight: 600;"><?= $count ?> orders (<?= $pct ?>%)</span>
                        </div>
                        <div style="height: 6px; background: #ECE8E0; border-radius: 3px; overflow: hidden;">
                            <div style="width: <?= $pct ?>%; height: 100%; background: <?= $stMeta['color'] ?>; border-radius: 3px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Dashboard Grid: Inventory Alerts & Top Products -->
<div class="dashboard-grid">
    <!-- Left Column: Low-Stock Inventory Alerts -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Inventory Health & Stock Alerts</h2>
            <a href="<?= ADMIN_URL ?>/inventory.php" class="btn btn-outline btn-sm">Manage Stock</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($metrics['low_stock_alerts'])): ?>
                <div style="padding: 32px 24px; text-align: center; color: var(--text-muted);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 8px; color: #2E7D32;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <p style="font-size: 13px; font-weight: 500;">All inventory levels are healthy.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Creation / SKU</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metrics['low_stock_alerts'] as $item): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; font-size: 12px;"><?= e($item['name']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($item['sku']) ?></div>
                                </td>
                                <td>
                                    <span class="status-badge <?= ($item['stock_quantity'] == 0) ? 'archived' : 'pending' ?>">
                                        <?= $item['stock_quantity'] ?> left (Min <?= $item['low_stock_threshold'] ?>)
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= ADMIN_URL ?>/inventory.php?search=<?= urlencode($item['sku']) ?>" class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size: 11px;">Restock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<!-- Secondary Dashboard Grid: Recent Orders & Top Creations / Patrons -->
<div class="dashboard-grid">
    <!-- Left: Recent Orders Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Patron Orders</h2>
            <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-outline btn-sm">All Orders</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($metrics['recent_orders'])): ?>
                <div style="padding: 48px 24px; text-align: center; color: var(--text-muted);">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 8px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    <p style="font-size: 14px; font-weight: 500;">No customer orders placed yet.</p>
                    <p style="font-size: 12px; margin-top: 4px;">Orders placed on the live boutique will populate this table automatically.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metrics['recent_orders'] as $ord): ?>
                                <tr>
                                    <td>
                                        <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $ord['id'] ?>" style="font-weight: 600; color: var(--text-main); text-decoration: none;">
                                            <?= e($ord['order_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?= e(!empty($ord['shipping_full_name']) ? $ord['shipping_full_name'] : trim(($ord['first_name'] ?? '') . ' ' . ($ord['last_name'] ?? ''))) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?= e(!empty($ord['customer_email']) ? $ord['customer_email'] : 'Guest Patron') ?></div>
                                    </td>
                                    <td style="white-space: nowrap; color: var(--text-muted); font-size: 12px;">
                                        <?= date('d M Y, h:i A', strtotime($ord['placed_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= e($ord['payment_status']) ?>">
                                            <?= ucfirst(e($ord['payment_status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= e($ord['order_status']) ?>">
                                            <?= ucfirst(str_replace('_', ' ', e($ord['order_status']))) ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 700;">
                                        <?= format_price($ord['grand_total']) ?>
                                    </td>
                                    <td>
                                        <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $ord['id'] ?>" class="btn-action" title="View Order Dossier">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Top Performing Creations & Recent Patrons -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Best Sellers -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Top Performing Creations</h2>
                <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-outline btn-sm">View Catalogue</a>
            </div>
            <div class="card-body" style="padding: 16px;">
                <?php if (empty($metrics['best_sellers'])): ?>
                    <div style="padding: 24px 16px; text-align: center; color: var(--text-muted); font-size: 13px;">
                        No sales data recorded yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($metrics['best_sellers'] as $prod): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-color);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="<?= e($prod['image_url'] ?: 'assets/images/placeholder.jpg') ?>" class="product-thumb" alt="" style="width: 36px; height: 46px; border-radius: 3px; object-fit: cover;">
                                <div>
                                    <div style="font-weight: 600; font-size: 12px;"><?= e($prod['name']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= (int)$prod['total_sold'] ?> pieces acquired</div>
                                </div>
                            </div>
                            <div style="font-weight: 600; font-size: 13px; color: var(--gold);">
                                <?= format_price($prod['total_revenue']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Customer Registrations -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Patron Registrations</h2>
                <a href="<?= ADMIN_URL ?>/customers.php" class="btn btn-outline btn-sm">All Patrons</a>
            </div>
            <div class="card-body" style="padding: 16px;">
                <?php if (empty($metrics['recent_customers'])): ?>
                    <div style="padding: 24px 16px; text-align: center; color: var(--text-muted); font-size: 13px;">
                        No patrons registered yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($metrics['recent_customers'] as $c): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-color);">
                            <div>
                                <div style="font-weight: 600; font-size: 12px;"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= e($c['email']) ?></div>
                            </div>
                            <span class="status-badge <?= e($c['status']) ?>"><?= ucfirst(e($c['status'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

