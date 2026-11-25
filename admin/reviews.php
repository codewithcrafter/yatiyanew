<?php
/**
 * YATIYA COUTURE - Product Review Moderation Console
 */

$adminPageTitle = 'Review Moderation';
$activeMenu = 'reviews';

require_once __DIR__ . '/includes/admin-header.php';

// Handle Review Moderation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/reviews.php');
    }

    $reviewId = (int)($_POST['review_id'] ?? 0);
    $status = clean_input($_POST['status'] ?? 'approved');
    $adminId = (int)$currentAdmin['id'];

    $res = admin_update_review_status($reviewId, $status, $adminId);
    if ($res['success']) {
        set_flash('success', $res['message']);
    } else {
        set_flash('error', $res['message']);
    }

    redirect(ADMIN_URL . '/reviews.php');
}

$statusFilter = clean_input($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$reviewData = admin_get_reviews(['status' => $statusFilter], $page, 15);
?>

<div class="filter-bar">
    <form action="<?= ADMIN_URL ?>/reviews.php" method="GET" class="filter-group">
        <select name="status" class="form-control" style="max-width: 200px;">
            <option value="">All Review Statuses</option>
            <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending Moderation</option>
            <option value="approved" <?= ($statusFilter === 'approved') ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= ($statusFilter === 'rejected') ? 'selected' : '' ?>>Rejected</option>
        </select>
        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
        <?php if (!empty($statusFilter)): ?>
            <a href="<?= ADMIN_URL ?>/reviews.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Reviews Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Patron Reviews & Reflections (<?= number_format($reviewData['total']) ?> reviews)</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($reviewData['items'])): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px; font-weight: 500;">No product reviews found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Creation</th>
                            <th>Patron</th>
                            <th>Rating</th>
                            <th>Review Title & Narrative</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviewData['items'] as $rev): ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= urlencode($rev['product_slug']) ?>" target="_blank" style="font-weight: 600; color: var(--text-main); text-decoration: none;">
                                        <?= e($rev['product_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= e($rev['first_name'] . ' ' . $rev['last_name']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($rev['customer_email']) ?></div>
                                </td>
                                <td>
                                    <span style="color: var(--gold); font-size: 14px;">
                                        <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                                    </span>
                                </td>
                                <td style="max-width: 320px;">
                                    <div style="font-weight: 600; font-size: 13px;"><?= e($rev['review_title']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); line-height: 1.4; margin-top: 2px;">
                                        <?= e($rev['review_text']) ?>
                                    </div>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                    <?= date('d M Y', strtotime($rev['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= e($rev['status']) ?>">
                                        <?= ucfirst(e($rev['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <?php if ($rev['status'] !== 'approved'): ?>
                                            <form action="<?= ADMIN_URL ?>/reviews.php" method="POST" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: #2E7D32; border-color: #C8E6C9;" title="Approve Review">
                                                    Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($rev['status'] !== 'rejected'): ?>
                                            <form action="<?= ADMIN_URL ?>/reviews.php" method="POST" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: #D32F2F; border-color: #FFCDD2;" title="Reject Review">
                                                    Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

