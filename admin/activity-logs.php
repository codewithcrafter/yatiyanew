<?php
/**
 * YATIYA COUTURE - Administrative Operations Audit Trail
 */

$adminPageTitle = 'Operations Audit Trail';
$activeMenu = 'activity';

require_once __DIR__ . '/includes/admin-header.php';

$logs = admin_get_activity_logs(100);
?>

<div style="margin-bottom: 24px;">
    <h2 style="font-family: var(--font-heading); font-size: 20px; font-weight: 600;">
        Atelier Security & Administrative Audit Trail
    </h2>
    <div style="font-size: 12px; color: var(--text-muted);">
        Immutable record of operational changes, inventory adjustments, status updates, and staff sessions.
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($logs)): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px; font-weight: 500;">No administrative activities logged yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Staff Member</th>
                            <th>Action Type</th>
                            <th>Entity Reference</th>
                            <th>Operational Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 12px; color: var(--text-muted);">
                                    <?= date('d M Y, h:i:s A', strtotime($log['created_at'])) ?>
                                </td>
                                <td>
                                    <strong><?= e($log['admin_name'] ?: ($log['admin_username'] ?: 'System')) ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge draft" style="font-size: 11px;">
                                        <?= e(str_replace('_', ' ', $log['action'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log['entity_type'])): ?>
                                        <span style="font-size: 12px; font-weight: 500;">
                                            <?= ucfirst(e($log['entity_type'])) ?> #<?= e($log['entity_id'] ?: 'N/A') ?>
                                        </span>
                                    <?php else: ?>
                                        &ndash;
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 360px; font-size: 12px; color: var(--text-main);">
                                    <?= e($log['details']) ?>
                                </td>
                                <td style="font-size: 11px; font-family: monospace; color: var(--text-muted);">
                                    <?= e($log['ip_address']) ?>
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

