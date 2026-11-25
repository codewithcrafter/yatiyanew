<?php
/**
 * YATIYA COUTURE - Staff Accounts & Role-Based Access Control
 */

$adminPageTitle = 'Staff Accounts & Roles';
$activeMenu = 'users';

require_once __DIR__ . '/includes/admin-header.php';

// Super Admin authorization check for user management
$isSuperAdmin = ($currentAdmin['role'] === 'super_admin');

// Handle User Create / Edit / Delete POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/users.php');
    }

    if (!$isSuperAdmin) {
        set_flash('error', 'Only Super Administrators can create or modify staff accounts.');
        redirect(ADMIN_URL . '/users.php');
    }

    $action = $_POST['action'] ?? '';
    $adminId = (int)$currentAdmin['id'];

    if ($action === 'save_user') {
        $userId = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $data = [
            'username' => clean_input($_POST['username'] ?? ''),
            'email' => clean_input($_POST['email'] ?? ''),
            'full_name' => clean_input($_POST['full_name'] ?? ''),
            'role' => clean_input($_POST['role'] ?? 'editor'),
            'status' => clean_input($_POST['status'] ?? 'active'),
            'password' => $_POST['password'] ?? ''
        ];

        $saveRes = admin_save_user($data, $userId, $adminId);
        if ($saveRes['success']) {
            set_flash('success', $saveRes['message']);
        } else {
            set_flash('error', $saveRes['message']);
        }
    } elseif ($action === 'delete_user') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $delRes = admin_delete_user($targetId, $adminId);
        if ($delRes['success']) {
            set_flash('success', $delRes['message']);
        } else {
            set_flash('error', $delRes['message']);
        }
    }

    redirect(ADMIN_URL . '/users.php');
}

$users = admin_get_users();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-family: var(--font-heading); font-size: 20px; font-weight: 600;">
            Atelier Staff Accounts &amp; Access Control (<?= count($users) ?> Administrators)
        </h2>
        <div style="font-size: 12px; color: var(--text-muted);">
            Manage team roles, privileges, editorial access, and concierge logins.
        </div>
    </div>
    <?php if ($isSuperAdmin): ?>
        <button type="button" class="btn btn-gold btn-sm" data-modal-target="userModal" id="btnCreateUser">
            + Add New Staff Member
        </button>
    <?php endif; ?>
</div>

<!-- Role Descriptions Card -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 16px; border-left: 3px solid #C6A15B;">
        <div style="font-weight: 600; font-size: 13px; color: var(--gold-primary);">Super Admin</div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Unrestricted access to all boutique operations, staff management, and system configs.</div>
    </div>
    <div class="card" style="padding: 16px; border-left: 3px solid #24211E;">
        <div style="font-weight: 600; font-size: 13px; color: var(--text-primary);">Administrator</div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Manages orders, inventory, catalog, customer requests and storefront CMS.</div>
    </div>
    <div class="card" style="padding: 16px; border-left: 3px solid #1E88E5;">
        <div style="font-weight: 600; font-size: 13px; color: #1E88E5;">Editor</div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Content curation, product descriptions, editorial stories, banners and media library.</div>
    </div>
    <div class="card" style="padding: 16px; border-left: 3px solid #757575;">
        <div style="font-weight: 600; font-size: 13px; color: #757575;">Support Concierge</div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Client assistance, order tracking review, return processing, and inquiries.</div>
    </div>
</div>

<!-- Staff Users Table -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Administrator</th>
                        <th>Role</th>
                        <th>Email Address</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Created</th>
                        <?php if ($isSuperAdmin): ?>
                            <th style="text-align: right;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #24211E; color: #D6BC7A; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong style="font-size: 13px;"><?= e($u['full_name']) ?></strong>
                                        <div style="font-size: 11px; color: var(--text-muted); font-family: monospace;">@<?= e($u['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $roleClass = match($u['role']) {
                                    'super_admin' => 'status-badge active',
                                    'admin' => 'status-badge pending',
                                    'editor' => 'status-badge processing',
                                    default => 'status-badge archived'
                                };
                                ?>
                                <span class="<?= $roleClass ?>" style="font-size: 11px;">
                                    <?= e(ucwords(str_replace('_', ' ', $u['role']))) ?>
                                </span>
                            </td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <span class="status-badge <?= ($u['status'] === 'active') ? 'active' : 'archived' ?>">
                                    <?= e(ucfirst($u['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-muted);">
                                    <?= $u['last_login_at'] ? date('M j, Y h:i A', strtotime($u['last_login_at'])) : 'Never' ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-muted);">
                                    <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                </span>
                            </td>
                            <?php if ($isSuperAdmin): ?>
                                <td style="text-align: right;">
                                    <div class="action-group" style="justify-content: flex-end;">
                                        <button 
                                            type="button" 
                                            class="btn-action btn-edit-user" 
                                            data-user='<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>'
                                            title="Edit Staff Member"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>

                                        <?php if ((int)$u['id'] !== (int)$currentAdmin['id']): ?>
                                            <form action="<?= ADMIN_URL ?>/users.php" method="POST" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <button 
                                                    type="submit" 
                                                    class="btn-action danger" 
                                                    title="Delete Staff Member" 
                                                    data-confirm="Are you sure you want to delete administrator account '<?= e($u['username']) ?>'?"
                                                >
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit User Modal Dialog -->
<?php if ($isSuperAdmin): ?>
<div class="modal-backdrop" id="userModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="userModalTitle">Add Staff Member</h3>
            <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/users.php" method="POST" id="userForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="user_id" id="formUserId" value="">

            <div class="modal-body">
                <div class="form-row">
                    <div>
                        <label class="form-label" for="uFullName">Full Name *</label>
                        <input type="text" id="uFullName" name="full_name" class="form-control" placeholder="e.g. Eleanor Vance" required>
                    </div>
                    <div>
                        <label class="form-label" for="uUsername">Username *</label>
                        <input type="text" id="uUsername" name="username" class="form-control" placeholder="e.g. eleanor.v" required>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="uEmail">Email Address *</label>
                        <input type="email" id="uEmail" name="email" class="form-control" placeholder="e.g. atelier@yatiyacouture.com" required>
                    </div>
                    <div>
                        <label class="form-label" for="uRole">Role Assignment *</label>
                        <select id="uRole" name="role" class="form-control" required>
                            <option value="editor">Editor (Content & Catalog)</option>
                            <option value="admin">Administrator (Operations & Orders)</option>
                            <option value="support">Support Concierge (Inquiries)</option>
                            <option value="super_admin">Super Administrator (Full System)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="uPassword">Password <span id="pwdHelp" style="font-size: 11px; color: var(--text-muted);">(Required for new user)</span></label>
                        <input type="password" id="uPassword" name="password" class="form-control" placeholder="••••••••">
                    </div>
                    <div>
                        <label class="form-label" for="uStatus">Account Status</label>
                        <select id="uStatus" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold">Save Account</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('userForm');
    const title = document.getElementById('userModalTitle');
    const pwdHelp = document.getElementById('pwdHelp');
    const pwdInput = document.getElementById('uPassword');

    document.getElementById('btnCreateUser')?.addEventListener('click', function () {
        form.reset();
        document.getElementById('formUserId').value = '';
        title.textContent = 'Add New Staff Member';
        pwdHelp.textContent = '(Required for new user)';
        pwdInput.required = true;
    });

    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = JSON.parse(this.getAttribute('data-user'));
            document.getElementById('formUserId').value = d.id;
            document.getElementById('uFullName').value = d.full_name;
            document.getElementById('uUsername').value = d.username;
            document.getElementById('uEmail').value = d.email;
            document.getElementById('uRole').value = d.role;
            document.getElementById('uStatus').value = d.status;
            pwdInput.value = '';
            pwdInput.required = false;
            pwdHelp.textContent = '(Leave empty to keep current password)';

            title.textContent = 'Edit Staff Member: ' + d.full_name;
            openModal('userModal');
        });
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

