<?php
/**
 * YATIYA COUTURE - Address Book Management
 * Manage shipping and billing addresses with strict server-side validation and IDOR protection
 * Pure Core PHP & Vanilla JavaScript
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Authentication Guard
require_login();

$user = current_user();
$customerId = (int)$user['id'];

// Handle POST actions
if (is_post()) {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        set_flash('error', 'Security token expired. Please try again.');
        redirect(BASE_URL . '/addresses.php');
    }

    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'save_address') {
        $addressId = !empty($_POST['address_id']) ? (int)$_POST['address_id'] : null;
        $res = save_customer_address($customerId, $_POST, $addressId);
        set_flash($res['success'] ? 'success' : 'error', $res['message']);
        redirect(BASE_URL . '/addresses.php');
    } elseif ($action === 'delete_address') {
        $addressId = (int)($_POST['address_id'] ?? 0);
        $deleted = delete_customer_address($addressId, $customerId);
        set_flash($deleted ? 'success' : 'error', $deleted ? 'Address removed from your address book.' : 'Unable to remove address.');
        redirect(BASE_URL . '/addresses.php');
    } elseif ($action === 'set_default') {
        $addressId = (int)($_POST['address_id'] ?? 0);
        $type = clean_input($_POST['type'] ?? 'shipping');
        $updated = set_default_address($addressId, $customerId, $type);
        set_flash($updated ? 'success' : 'error', $updated ? 'Default ' . e($type) . ' address updated.' : 'Unable to update default address.');
        redirect(BASE_URL . '/addresses.php');
    }
}

$addresses = get_customer_addresses($customerId);
$totalAddresses = count($addresses);

$pageTitle = 'Address Book';
$pageDescription = 'Manage your saved delivery destinations and billing details at Yatiya Couture.';
$activePage = 'account';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Account Portal Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/account.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/auth.css">

<main class="account-page-wrapper">
    <div class="container">
        
        <!-- Flash Alert Messages -->
        <?= render_flash() ?>

        <!-- Breadcrumbs -->
        <nav class="account-breadcrumb" aria-label="Breadcrumb">
            <ol>
                <li><a href="<?= BASE_URL ?>/index.php">Maison Home</a></li>
                <li class="separator">/</li>
                <li><a href="<?= BASE_URL ?>/account.php">My Account</a></li>
                <li class="separator">/</li>
                <li aria-current="page">Address Book</li>
            </ol>
        </nav>

        <div class="account-layout-grid">
            
            <!-- Left Navigation Sidebar -->
            <aside class="account-sidebar-card">
                <div class="account-user-badge">
                    <div class="user-avatar-circle">
                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                    </div>
                    <h2 class="user-fullname"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                    <p class="user-email-text"><?= e($user['email']) ?></p>
                    <span class="user-tier-pill">Privileged Patron</span>
                </div>

                <ul class="account-nav-list">
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/account.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Dashboard Overview
                        </a>
                    </li>
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/orders.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <path d="M16 10a4 4 0 0 1-8 0"></path>
                            </svg>
                            My Orders
                        </a>
                    </li>
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/wishlist.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            Private Wishlist
                        </a>
                    </li>
                    <li class="account-nav-item active">
                        <a href="<?= BASE_URL ?>/addresses.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            Address Book (<?= $totalAddresses ?>)
                        </a>
                    </li>
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/profile.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Profile & Security
                        </a>
                    </li>
                    <li class="account-nav-item signout-item">
                        <form action="<?= BASE_URL ?>/logout.php" method="POST" style="margin:0;">
                            <?= csrf_field() ?>
                            <button type="submit" onclick="return confirm('Sign out of your atelier account?');">
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                                Secure Sign Out
                            </button>
                        </form>
                    </li>
                </ul>
            </aside>

            <!-- Main Content Area -->
            <div class="account-content-area">
                
                <div class="account-view-header">
                    <div>
                        <h1 class="account-view-title">Address Book</h1>
                        <p class="account-view-subtitle">Manage your verified delivery residences and billing destinations.</p>
                    </div>
                    <button type="button" class="btn btn-gold btn-sm" id="openAddAddressBtn">
                        &plus; Add New Address
                    </button>
                </div>

                <?php if (empty($addresses)): ?>
                    <div class="dashboard-card empty-history-box">
                        <div class="empty-history-icon">📍</div>
                        <h2 class="empty-history-title">No Saved Addresses</h2>
                        <p class="empty-history-text">
                            Add your primary residence or atelier delivery address for seamless one-click white-glove checkout.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="address-cards-grid">
                        <?php foreach ($addresses as $addr): ?>
                            <?php $isDef = !empty($addr['is_default_shipping']) || !empty($addr['is_default_billing']); ?>
                            <div class="address-box <?= $isDef ? 'is-default' : '' ?>" id="addressCard_<?= $addr['id'] ?>">
                                <div class="address-badges-row">
                                    <?php if (!empty($addr['is_default_shipping'])): ?>
                                        <span class="address-badge badge-default">Default Shipping</span>
                                    <?php endif; ?>
                                    <?php if (!empty($addr['is_default_billing'])): ?>
                                        <span class="address-badge badge-default">Default Billing</span>
                                    <?php endif; ?>
                                    <span class="address-badge badge-type"><?= ucfirst(e($addr['address_type'])) ?></span>
                                </div>

                                <h3 class="address-recipient-name"><?= e($addr['full_name']) ?></h3>
                                <p class="address-text">
                                    <?= e($addr['address_line1']) ?><br>
                                    <?php if (!empty($addr['address_line2'])): ?>
                                        <?= e($addr['address_line2']) ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($addr['landmark'])): ?>
                                        Landmark: <?= e($addr['landmark']) ?><br>
                                    <?php endif; ?>
                                    <?= e($addr['city']) ?>, <?= e($addr['state']) ?> — <?= e($addr['postal_code']) ?><br>
                                    <?= e($addr['country']) ?>
                                </p>
                                <span class="address-phone">📞 <?= e($addr['phone']) ?></span>

                                <div class="address-actions">
                                    <!-- Edit Action -->
                                    <button 
                                        type="button" 
                                        class="address-action-btn edit-address-btn" 
                                        data-address='<?= htmlspecialchars(json_encode($addr), ENT_QUOTES, 'UTF-8') ?>'
                                    >
                                        Edit
                                    </button>

                                    <!-- Set Default Shipping -->
                                    <?php if (empty($addr['is_default_shipping'])): ?>
                                        <form action="<?= BASE_URL ?>/addresses.php" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="set_default">
                                            <input type="hidden" name="type" value="shipping">
                                            <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                            <button type="submit" class="address-action-btn">Make Default Shipping</button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Set Default Billing -->
                                    <?php if (empty($addr['is_default_billing'])): ?>
                                        <form action="<?= BASE_URL ?>/addresses.php" method="POST" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="set_default">
                                            <input type="hidden" name="type" value="billing">
                                            <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                            <button type="submit" class="address-action-btn">Make Default Billing</button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Delete Action -->
                                    <form action="<?= BASE_URL ?>/addresses.php" method="POST" style="display:inline; margin-left:auto;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_address">
                                        <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                        <button type="submit" class="address-action-btn btn-delete" onclick="return confirm('Remove this address from your address book?');">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </div>
</main>

<!-- Address Add / Edit Modal -->
<div class="address-modal" id="addressModal" role="dialog" aria-modal="true" aria-labelledby="addressModalTitle">
    <div class="address-modal-dialog">
        <div class="address-modal-header">
            <h2 class="card-heading" id="addressModalTitle">Add New Atelier Address</h2>
            <button type="button" class="modal-close-btn" id="closeAddressModalBtn" aria-label="Close modal">&times;</button>
        </div>

        <form action="<?= BASE_URL ?>/addresses.php" method="POST" class="auth-form" id="addressForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_address">
            <input type="hidden" name="address_id" value="">

            <div class="form-row-2col">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" class="form-control" required placeholder="Recipient full name">
                </div>
                <div class="form-group">
                    <label class="form-label">Mobile Number <span class="required">*</span></label>
                    <input type="tel" name="phone" class="form-control" required placeholder="10-digit mobile number">
                </div>
            </div>

            <div class="form-row-2col">
                <div class="form-group">
                    <label class="form-label">Address Type</label>
                    <select name="address_type" class="form-control">
                        <option value="home">Home / Primary Residence</option>
                        <option value="work">Office / Work</option>
                        <option value="billing">Billing Destination</option>
                        <option value="shipping">Shipping Address</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" value="India" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Street Address / Apartment / Suite <span class="required">*</span></label>
                <input type="text" name="address_line1" class="form-control" required placeholder="Flat/House No., Building Name, Street">
            </div>

            <div class="form-group">
                <label class="form-label">Address Line 2 (Optional)</label>
                <input type="text" name="address_line2" class="form-control" placeholder="Area, Colony, Sector">
            </div>

            <div class="form-group">
                <label class="form-label">Landmark (Optional)</label>
                <input type="text" name="landmark" class="form-control" placeholder="Near notable reference">
            </div>

            <div class="form-row-2col">
                <div class="form-group">
                    <label class="form-label">City <span class="required">*</span></label>
                    <input type="text" name="city" class="form-control" required placeholder="e.g. Mumbai">
                </div>
                <div class="form-group">
                    <label class="form-label">State <span class="required">*</span></label>
                    <input type="text" name="state" class="form-control" required placeholder="e.g. Maharashtra">
                </div>
            </div>

            <div class="form-group" style="max-width: 250px;">
                <label class="form-label">Postal / PIN Code <span class="required">*</span></label>
                <input type="text" name="postal_code" class="form-control" required placeholder="e.g. 400001">
            </div>

            <div style="display:flex; flex-direction:column; gap:0.5rem; margin-top:0.5rem;">
                <label class="auth-checkbox-group">
                    <input type="checkbox" name="is_default_shipping" value="1">
                    <span>Set as default shipping address</span>
                </label>
                <label class="auth-checkbox-group">
                    <input type="checkbox" name="is_default_billing" value="1">
                    <span>Set as default billing address</span>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem; border-top:1px solid var(--border-subtle); padding-top:1.25rem;">
                <button type="button" class="btn btn-outline btn-sm" id="cancelAddressModalBtn">Cancel</button>
                <button type="submit" class="btn btn-gold btn-sm">Save Address</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= ASSETS_URL ?>/js/account.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

