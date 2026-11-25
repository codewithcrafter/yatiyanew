<?php
/**
 * YATIYA COUTURE - Luxury Admin Management Console Footer
 */

require_once __DIR__ . '/../../includes/config.php';
?>
        </div> <!-- End .admin-content -->

        <footer style="padding: 16px 32px; border-top: 1px solid var(--border-color); background: var(--bg-surface); font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
            <div>
                &copy; <?= date('Y') ?> <strong><?= SITE_NAME ?></strong>. All rights reserved. Haute Couture E-Commerce Platform.
            </div>
            <div>
                <span>PHP <?= PHP_VERSION ?></span> &bull; 
                <span>Server Time: <?= date('H:i:s T') ?></span>
            </div>
        </footer>
    </div> <!-- End .admin-main -->
</div> <!-- End .admin-wrapper -->

<!-- Pure Vanilla JavaScript Controller -->
<script src="<?= ADMIN_URL ?>/assets/js/admin.js"></script>
</body>
</html>

