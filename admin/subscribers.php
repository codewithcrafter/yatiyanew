<?php
/**
 * YATIYA COUTURE - Newsletter Subscribers Management & Export
 */

$adminPageTitle = 'Newsletter Subscribers';
$activeMenu = 'subscribers';

require_once __DIR__ . '/includes/admin-header.php';

// Export CSV handler
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    // Check permission
    if (!in_array($currentAdmin['role'], ['super_admin', 'admin', 'editor'], true)) {
        set_flash('error', 'You do not have permission to export subscriber data.');
        redirect(ADMIN_URL . '/subscribers.php');
    }

    $pdo = db();
    $rows = $pdo->query("SELECT id, email, status, source, subscribed_at, unsubscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC")->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=yatiya_subscribers_' . date('Ymd_His') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Email Address', 'Status', 'Acquisition Source', 'Subscribed At', 'Unsubscribed At']);

    foreach ($rows as $r) {
        fputcsv($output, [
            $r['id'],
            $r['email'],
            $r['status'],
            $r['source'] ?? 'footer',
            $r['subscribed_at'],
            $r['unsubscribed_at'] ?? 'N/A'
        ]);
    }
    fclose($output);
    exit;
}

// Handle Delete POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/subscribers.php');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'delete_subscriber') {
        $subId = (int)($_POST['subscriber_id'] ?? 0);
        $res = admin_delete_subscriber($subId, (int)$currentAdmin['id']);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('error', $res['message']);
        }
    }
    redirect(ADMIN_URL . '/subscribers.php');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$search = clean_input($_GET['search'] ?? '');
$subData = admin_get_subscribers($page, 20, $search);
$subscribers = $subData['subscribers'];
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-family: var(--font-heading); font-size: 20px; font-weight: 600;">
            Privileged Salon Subscribers (<?= (int)$subData['total'] ?> Patrons)
        </h2>
        <div style="font-size: 12px; color: var(--text-muted);">
            Manage luxury newsletter audience, private salon invitations, and export subscriber lists.
        </div>
    </div>
    <div style="display: flex; gap: 12px; align-items: center;">
        <a href="<?= ADMIN_URL ?>/subscribers.php?action=export_csv" class="btn btn-outline btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Export CSV
        </a>
    </div>
</div>

<!-- Metrics Overview -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 16px;">
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 4px;">Total Subscribers</div>
        <div style="font-size: 24px; font-family: var(--font-heading); font-weight: 600; color: var(--text-primary);"><?= (int)$subData['total'] ?></div>
    </div>
    <div class="card" style="padding: 16px;">
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 4px;">Active Subscriptions</div>
        <div style="font-size: 24px; font-family: var(--font-heading); font-weight: 600; color: #2E7D32;"><?= (int)$subData['active_count'] ?></div>
    </div>
    <div class="card" style="padding: 16px;">
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 4px;">Unsubscribed</div>
        <div style="font-size: 24px; font-family: var(--font-heading); font-weight: 600; color: var(--text-muted);"><?= (int)($subData['total'] - $subData['active_count']) ?></div>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card" style="margin-bottom: 20px; padding: 16px;">
    <form action="<?= ADMIN_URL ?>/subscribers.php" method="GET" style="display: flex; gap: 12px; align-items: center;">
        <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Search subscriber by email address..." 
            value="<?= e($search) ?>" 
            style="max-width: 400px;"
        >
        <button type="submit" class="btn btn-dark btn-sm">Search</button>
        <?php if (!empty($search)): ?>
            <a href="<?= ADMIN_URL ?>/subscribers.php" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Subscribers Table Card -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Subscriber Email</th>
                        <th>Status</th>
                        <th>Acquisition Source</th>
                        <th>Subscribed Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($subscribers)): ?>
                        <?php foreach ($subscribers as $s): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 13px;"><?= e($s['email']) ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge <?= ($s['status'] === 'subscribed') ? 'active' : 'archived' ?>">
                                        <?= e(ucfirst($s['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: var(--text-muted); text-transform: capitalize;">
                                        <?= e($s['source'] ?? 'footer') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: var(--text-muted);">
                                        <?= date('M j, Y h:i A', strtotime($s['subscribed_at'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <form action="<?= ADMIN_URL ?>/subscribers.php" method="POST" style="display: inline-block; margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_subscriber">
                                        <input type="hidden" name="subscriber_id" value="<?= (int)$s['id'] ?>">
                                        <button 
                                            type="submit" 
                                            class="btn-action danger" 
                                            title="Delete Subscriber" 
                                            data-confirm="Are you sure you want to remove <?= e($s['email']) ?> from newsletter records?"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 48px; color: var(--text-muted);">
                                No subscriber records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($subData['total_pages'] > 1): ?>
    <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
        <?php for ($p = 1; $p <= $subData['total_pages']; $p++): ?>
            <a 
                href="<?= ADMIN_URL ?>/subscribers.php?page=<?= $p ?>&search=<?= urlencode($search) ?>" 
                class="btn btn-sm <?= ($p === $subData['page']) ? 'btn-gold' : 'btn-outline' ?>"
                style="min-width: 36px; text-align: center;"
            >
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

