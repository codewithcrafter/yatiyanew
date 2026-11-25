<?php
/**
 * YATIYA COUTURE - Store Configuration & Operational Settings
 */

$adminPageTitle = 'Store Configuration';
$activeMenu = 'settings';

require_once __DIR__ . '/includes/admin-header.php';

// Handle Settings Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/settings.php');
    }

    $submitted = $_POST['settings'] ?? [];
    $adminId = (int)$currentAdmin['id'];

    $res = admin_update_store_settings($submitted, $adminId);
    if ($res['success']) {
        set_flash('success', 'Store configuration updated successfully.');
    } else {
        set_flash('error', $res['message']);
    }

    redirect(ADMIN_URL . '/settings.php');
}

$groupedSettings = admin_get_all_settings();
?>

<div style="max-width: 900px; margin: 0 auto;">
    <div style="margin-bottom: 24px;">
        <h2 style="font-family: var(--font-heading); font-size: 20px; font-weight: 600;">
            Boutique & Operational Configuration
        </h2>
        <div style="font-size: 12px; color: var(--text-muted);">
            Manage luxury brand parameters, white-glove shipping thresholds, tax rates, and client contact details.
        </div>
    </div>

    <form action="<?= ADMIN_URL ?>/settings.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <!-- General Brand Info Card -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">1. Maison Identity & Concierge Details</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div>
                        <label class="form-label" for="storeName">Brand / Boutique Name</label>
                        <input type="text" id="storeName" name="settings[store_name]" class="form-control" value="<?= e($groupedSettings['general']['store_name'] ?? SITE_NAME) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="storeTagline">Brand Tagline</label>
                        <input type="text" id="storeTagline" name="settings[store_tagline]" class="form-control" value="<?= e($groupedSettings['general']['store_tagline'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="storeEmail">Concierge Email</label>
                        <input type="email" id="storeEmail" name="settings[store_email]" class="form-control" value="<?= e($groupedSettings['general']['store_email'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="storePhone">Client Telephone</label>
                        <input type="text" id="storePhone" name="settings[store_phone]" class="form-control" value="<?= e($groupedSettings['general']['store_phone'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px; margin-bottom: 0;">
                    <label class="form-label" for="storeAddress">Atelier Flagship Address</label>
                    <input type="text" id="storeAddress" name="settings[store_address]" class="form-control" value="<?= e($groupedSettings['general']['store_address'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- Commerce & Shipping Card -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">2. White-Glove Shipping & Taxation Controls</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div>
                        <label class="form-label" for="freeShip">Complimentary Shipping Threshold (₹)</label>
                        <input type="number" id="freeShip" name="settings[free_shipping_threshold]" step="0.01" min="0" class="form-control" value="<?= e($groupedSettings['shipping']['free_shipping_threshold'] ?? '2999.00') ?>" required>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Orders equal to or above this value qualify for free delivery</div>
                    </div>
                    <div>
                        <label class="form-label" for="defaultShip">Standard Shipping Fee (₹)</label>
                        <input type="number" id="defaultShip" name="settings[default_shipping_charge]" step="0.01" min="0" class="form-control" value="<?= e($groupedSettings['shipping']['default_shipping_charge'] ?? '199.00') ?>" required>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Applied when order total is below complimentary threshold</div>
                    </div>
                    <div>
                        <label class="form-label" for="taxRate">GST / Tax Rate (%)</label>
                        <input type="number" id="taxRate" name="settings[tax_rate_percent]" step="0.01" min="0" class="form-control" value="<?= e($groupedSettings['finance']['tax_rate_percent'] ?? '12.00') ?>" required>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="currCode">Currency Code</label>
                        <input type="text" id="currCode" name="settings[currency_code]" class="form-control" value="<?= e($groupedSettings['localization']['currency_code'] ?? 'INR') ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="currSym">Currency Symbol</label>
                        <input type="text" id="currSym" name="settings[currency_symbol]" class="form-control" value="<?= e($groupedSettings['localization']['currency_symbol'] ?? '₹') ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Marketing & Announcements Card -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">3. Announcement Bar & Social Media Links</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="annBarText">Top Announcement Bar Copy</label>
                    <input type="text" id="annBarText" name="settings[announcement_bar_text]" class="form-control" value="<?= e($groupedSettings['marketing']['announcement_bar_text'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="annBarLink">Announcement Bar Target Link</label>
                    <input type="text" id="annBarLink" name="settings[announcement_bar_link]" class="form-control" value="<?= e($groupedSettings['marketing']['announcement_bar_link'] ?? 'shop.php?category=new-arrivals') ?>">
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="socInsta">Instagram URL</label>
                        <input type="url" id="socInsta" name="settings[social_instagram]" class="form-control" value="<?= e($groupedSettings['social']['social_instagram'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label" for="socPin">Pinterest URL</label>
                        <input type="url" id="socPin" name="settings[social_pinterest]" class="form-control" value="<?= e($groupedSettings['social']['social_pinterest'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label" for="socFb">Facebook URL</label>
                        <input type="url" id="socFb" name="settings[social_facebook]" class="form-control" value="<?= e($groupedSettings['social']['social_facebook'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- SEO & Metadata Configuration Card -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">4. Search Engine Optimization (SEO) & Social Graph</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div>
                        <label class="form-label" for="metaTitle">Default Homepage SEO Title</label>
                        <input type="text" id="metaTitle" name="settings[meta_title]" class="form-control" value="<?= e($groupedSettings['seo']['meta_title'] ?? 'YATIYA COUTURE | Luxury Haute Couture & Ready-to-Wear Fashion') ?>">
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Recommended: 50–60 characters</div>
                    </div>
                    <div>
                        <label class="form-label" for="canonicalUrl">Canonical URL Base</label>
                        <input type="url" id="canonicalUrl" name="settings[canonical_base_url]" class="form-control" value="<?= e($groupedSettings['seo']['canonical_base_url'] ?? BASE_URL) ?>">
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Atelier production domain URL</div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label" for="metaDesc">Homepage Meta Description</label>
                    <textarea id="metaDesc" name="settings[meta_description]" class="form-control" rows="2"><?= e($groupedSettings['seo']['meta_description'] ?? 'Discover handcrafted luxury couture, pure Mulberry silk drapes, bespoke bandhgalas and heirloom accessories crafted by master karigars.') ?></textarea>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Recommended: 150–160 characters</div>
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="metaKeywords">Global Meta Keywords</label>
                        <input type="text" id="metaKeywords" name="settings[meta_keywords]" class="form-control" value="<?= e($groupedSettings['seo']['meta_keywords'] ?? 'luxury fashion, haute couture, indian silk saris, bespoke bandhgala, designer clothing, yatiya couture') ?>">
                    </div>
                    <div>
                        <label class="form-label" for="ogImageUrl">Open Graph / Social Share Image URL</label>
                        <input type="url" id="ogImageUrl" name="settings[og_image_url]" class="form-control" value="<?= e($groupedSettings['seo']['og_image_url'] ?? 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1200&q=80') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Security & Payment Notice Card (Key Protection) -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">5. Payment Infrastructure & API Security</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; align-items: flex-start; gap: 12px; background: rgba(198, 161, 91, 0.08); padding: 14px; border-radius: 4px; border: 1px solid rgba(198, 161, 91, 0.25);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C6A15B" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <div style="font-size: 13px; line-height: 1.5; color: var(--text-primary);">
                        <strong>Enterprise Key Security Active:</strong>
                        Private gateway secret keys (e.g. Razorpay Key Secret, Webhook Signing Secrets) are securely bound to protected environment variables and are never echoed or transmitted via the web admin panel to prevent unauthorized exfiltration or CSRF interception.
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 48px;">
            <button type="submit" class="btn btn-gold" style="padding: 12px 32px; font-size: 14px;">
                Save Store Configuration
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

