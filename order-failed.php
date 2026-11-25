<?php
/**
 * YATIYA COUTURE - Order Payment Failure & Reconciliation
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/checkout-functions.php';
require_once __DIR__ . '/includes/payment-functions.php';

$orderNumber = clean_input($_GET['order'] ?? ($_SESSION['last_order_number'] ?? ''));
$reason = clean_input($_GET['reason'] ?? 'The payment authorization could not be completed.');

if (empty($orderNumber)) {
    redirect(BASE_URL . '/index.php');
}

$order = get_order_by_number($orderNumber);
$customerId = is_logged_in() ? (int)$_SESSION['customer_id'] : null;

if (!$order || !can_view_order($order, $customerId)) {
    redirect(BASE_URL . '/index.php');
}

// If order has already settled successfully, redirect to success
if ($order['payment_status'] === 'paid') {
    redirect(BASE_URL . '/order-success.php?order=' . urlencode($orderNumber));
}

$pageTitle = 'Payment Attention Required: ' . $order['order_number'] . ' | ' . SITE_NAME;
$extraCss = ['assets/css/checkout.css'];
$extraJs = [
    'https://checkout.razorpay.com/v1/checkout.js',
    'assets/js/checkout.js'
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="checkout-wrapper">
    <div class="checkout-container" style="max-width: 700px;">
        <div class="checkout-card" style="text-align: center; padding: 48px 32px;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #FFEBEE; color: #C62828; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px;">
                &times;
            </div>

            <h1 style="font-family: var(--font-heading); font-size: 28px; font-weight: 600; color: var(--color-dark); margin-bottom: 8px;">
                Payment Not Completed
            </h1>

            <p style="font-size: 14px; color: var(--color-muted); max-width: 500px; margin: 0 auto 20px;">
                We were unable to verify payment for Order <strong><?= e($order['order_number']) ?></strong>.
            </p>

            <div style="background: #FFF8E1; border: 1px solid #FFE082; padding: 14px 20px; border-radius: 3px; font-size: 13px; color: #795548; margin-bottom: 28px; text-align: left;">
                <strong>Reason:</strong> <?= e($reason) ?><br>
                <span style="font-size: 12px; color: #8D6E63;">No funds were transferred from your account, or any deducted funds will be automatically reversed by your banking institution within standard processing timelines.</span>
            </div>

            <div style="background: #FAF8F5; border: 1px solid #ECE7DE; padding: 20px; border-radius: 3px; margin-bottom: 28px; text-align: left;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                    <span style="color: var(--color-muted);">Order Reference:</span>
                    <strong><?= e($order['order_number']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px;">
                    <span style="color: var(--color-muted);">Acquired Creations:</span>
                    <span><?= count($order['items']) ?> items</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 700; border-top: 1px solid #ECE7DE; padding-top: 10px; margin-top: 6px;">
                    <span>Outstanding Balance:</span>
                    <span style="color: var(--color-gold); font-size: 18px;"><?= format_price($order['grand_total']) ?></span>
                </div>
            </div>

            <?php if (can_retry_payment($order)): ?>
                <div style="display: flex; flex-direction: column; gap: 12px; max-width: 380px; margin: 0 auto 24px;">
                    <button id="retryPaymentBtn" class="btn-place-order" 
                            data-order-id="<?= $order['id'] ?>" 
                            data-csrf-token="<?= csrf_token() ?>"
                            data-action-url="<?= BASE_URL ?>/actions/checkout_actions.php">
                        Retry Payment Securely &rarr;
                    </button>
                    <a href="<?= BASE_URL ?>/checkout.php" class="btn" style="padding: 12px; background: #FFFFFF; border: 1px solid var(--color-dark); color: var(--color-dark); text-decoration: none; font-size: 13px; font-weight: 600;">
                        Choose Alternate Payment Method
                    </a>
                </div>
            <?php endif; ?>

            <div style="font-size: 12px; color: var(--color-muted); border-top: 1px solid #ECE7DE; padding-top: 20px;">
                Need concierge assistance? Our team is available 24/7.<br>
                Email: <a href="mailto:<?= SITE_EMAIL ?>" style="color: var(--color-dark);"><?= SITE_EMAIL ?></a> &bull; Phone: <?= SITE_PHONE ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const retryBtn = document.getElementById('retryPaymentBtn');
    if (retryBtn) {
        retryBtn.addEventListener('click', function(e) {
            e.preventDefault();
            this.disabled = true;
            this.textContent = 'Connecting to Gateway...';

            const formData = new FormData();
            formData.append('action', 'retry_payment');
            formData.append('order_id', this.dataset.orderId);
            formData.append('csrf_token', this.dataset.csrfToken);

            fetch(this.dataset.actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.razorpay) {
                    if (typeof Razorpay !== 'undefined') {
                        const options = {
                            key: data.razorpay.key,
                            amount: data.razorpay.amount,
                            currency: data.razorpay.currency,
                            name: data.razorpay.name,
                            description: data.razorpay.description,
                            order_id: data.razorpay.order_id,
                            prefill: data.razorpay.prefill,
                            handler: function(response) {
                                const verifyData = new FormData();
                                verifyData.append('action', 'verify_payment');
                                verifyData.append('order_id', data.order_id);
                                verifyData.append('razorpay_order_id', response.razorpay_order_id || '');
                                verifyData.append('razorpay_payment_id', response.razorpay_payment_id || '');
                                verifyData.append('razorpay_signature', response.razorpay_signature || '');
                                verifyData.append('csrf_token', retryBtn.dataset.csrfToken);

                                fetch(retryBtn.dataset.actionUrl, {
                                    method: 'POST',
                                    body: verifyData
                                })
                                .then(r => r.json())
                                .then(v => {
                                    window.location.href = v.redirect || ('order-success.php?order=' + encodeURIComponent(data.order_number));
                                });
                            }
                        };
                        new Razorpay(options).open();
                    } else {
                        alert('Razorpay gateway sandbox simulation: in production, the payment window opens here.');
                        window.location.reload();
                    }
                } else {
                    alert(data.message || 'Unable to retry payment at this moment.');
                    window.location.reload();
                }
            })
            .catch(() => {
                alert('Connection failure. Please refresh and try again.');
                window.location.reload();
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

